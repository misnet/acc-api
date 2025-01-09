<?php
/**
 * 后台系统用户类目API
 */

namespace Kuga\Api\Acc;

use Kuga\Core\GlobalVar;
use Kuga\Core\Service\JWTService;
use Kuga\Module\Acc\Model\AppModel;
use Kuga\Module\Acc\Model\Oauth2Model;
use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Model\RoleUserModel;
use Kuga\Module\Acc\Model\UserBindAppModel;
use Kuga\Module\Acc\Model\UserModel;
use Kuga\Module\Acc\Service\Acc as AccService;
use Kuga\Module\Acc\Service\Acl as AclService;
use Kuga\Api\Acc\Exception as ApiException;
use Kuga\Module\Acc\Service\Menu as MenuService;
class User extends BaseApi
{

    /**
     * 删除用户
     */
    public function delete()
    {
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_REMOVE');
        if(!$isAllow){
            throw new ApiException($this->translator->_('没有删除权限'),ApiException::$EXCODE_FORBIDDEN);
        }
        $data   = $this->_toParamObject($this->getParams());
        $row    = UserModel::findFirstByUid($data['uid']);
        $result = true;
        if ($row) {
            if ($this->_userMemberId == $row->uid) {
                throw new ApiException($this->translator->_('这个用户是当前用户，不可删除'));
            }
            $result = $row->delete();
            if ( ! $result) {
                throw new ApiException($row->getMessages()[0]->getMessage());
            }
        }else{
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }

        return $result;
    }

    /**
     * 个人资料修改
     */
    public function updateProfile(){
        $data = $this->_toParamObject($this->getParams());
        $row  = UserModel::findFirstByUid($this->_userMemberId);
        if(!$row){
            throw new ApiException(ApiException::$EXCODE_NOTEXIST,$this->translator->_('用户不存在'));
        }else{
            if($data['password']!=$data['repassword']){
                throw new ApiException($this->translator->_('新密码和确认密码不一致'));
            }
            if($data['fullname']){
                $row->fullname = $data['fullname'];
            }
            if(!is_null($data['gender'])){
                $row->gender = intval($data['gender']);
                if(!in_array($row->gender,[UserModel::GENDER_BOY,UserModel::GENDER_GIRL,UserModel::GENDER_SECRET])){
                    throw new ApiException($this->translator->_('错误的性别值'));
                }
            }
            return $row->update();
        }
    }
    /**
     * 更新用户
     */
    public function update()
    {
        $data = $this->_toParamObject($this->getParams());
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_UPDATE');
        if(!$isAllow){
            throw new ApiException($this->translator->_('没有修改权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $row  = UserModel::findFirstByUid($data['uid']);
        if ( ! $row) {
            throw new ApiException($this->translator->_('找不到用户，可能已被删除'));
        }
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();
        $transaction->throwRollbackException(true);

        $row->fullname = $data['fullname'];
        $row->memo = $data['memo'];
        $row->username = $data['username'];
        if ($data['password']) {
            $row->password = $data['password'];
        }
        $row->email         = $data['email'];
        $row->mobile = $data['mobile'];
        $row->setTransaction($transaction);
        $result      = $row->update();
        if ( ! $result) {
            $transaction->rollback($row->getMessages()[0]->getMessage());
        }else{
            $bindList = UserBindAppModel::find([
                'uid=:uid:',
                'bind'=>['uid'=>$data['uid']],
            ]);
            //对比差异
            $bindAppIds = [];
            if($bindList){
                $bindListArray  = $bindList->toArray();
                foreach($bindListArray as $bindRow){
                    $bindAppIds[] = $bindRow['appId'];
                }
            }
            if(!is_array($data['appIds'])){
                $data['appIds'] = [];
            }
            $toBeDeletedAppIds = array_diff($bindAppIds,$data['appIds']);
            $toBeCreateAppIds  = array_diff($data['appIds'],$bindAppIds);
            if(!empty($toBeDeletedAppIds)){
                foreach($bindList as $bRow){
                    if(in_array($bRow->appId,$toBeDeletedAppIds)) {
                        $bRow->setTransaction($transaction);
                        $result = $bRow->delete();
                        if(!$result){
                            $transaction->rollback($this->translator->_('用户解绑应用失败'));
                        }
                    }
                }
            }
            if(!empty($toBeCreateAppIds)){
                foreach($toBeCreateAppIds as $appId){
                    $appId = intval($appId);
                    if($appId){
                        $bindModel = new UserBindAppModel();
                        $bindModel->appId = $appId;
                        $bindModel->setTransaction($transaction);
                        $bindModel->uid   = $row->uid;
                        $result = $bindModel->create();
                        if(!$result){
                            $transaction->rollback($this->translator->_('用户绑定应用失败'));
                        }
                    }
                }
            }
            $transaction->commit();
        }
        return $result;
    }

    /**
     * 创建用户
     *
     * @return bool
     * @throws ApiException
     */
    public function create()
    {
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_ADD');
        if(!$isAllow){
            throw new ApiException($this->translator->_('没有创建权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $data                 = $this->_toParamObject($this->getParams());
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();
        //要设throwRollbackException为true，否则不会throw exception
        $transaction->throwRollbackException(true);
        $model                = new UserModel();
        $model->username      = $data['username'];
        $model->memo      = $data['memo'];
        $model->fullname      = $data['fullname'];
        $model->password      = $data['password'];
        $model->mobile        = $data['mobile'];
        $model->email         = $data['email'];

        $model->createTime    = time();
        $model->lastVisitIp   = \Qing\Lib\Utils::getClientIp();
        $model->lastVisitTime = $model->createTime;
        $model->setTransaction($transaction);
        $result               = $model->create();
        if ( ! $result) {
            $transaction->rollback($model->getMessages()[0]->getMessage());
            //throw new ApiException($model->getMessages()[0]->getMessage());
        }else{
            if(is_array($data['appIds'])){

                foreach($data['appIds'] as $appId){
                    $appId = intval($appId);
                    if($appId){
                        $bindModel = new UserBindAppModel();
                        $bindModel->appId = $appId;
                        $bindModel->setTransaction($transaction);
                        $bindModel->uid   = $model->uid;
                        $result = $bindModel->create();
                        if(!$result){
                            $transaction->rollback($this->translator->_('用户绑定应用失败'));
                        }
                        if(!empty($data['roleIds']) && is_array($data['roleIds'])){
                            $this->assignRoles($appId,$bindModel->uid,$data['roleIds']);
                        }
                    }
                }
            }
            $transaction->commit();
        }
        return $model->uid;
    }
    private function assignRoles($appId,$uid,$roleIds){
        $acc     = $this->_di->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        if ( ! $isAllow) {
            return false;
        }
        $roles = RoleModel::find([
            'appId=:aid:',
            'bind'=>['aid'=>$appId]
        ]);
        $allRoles = [];
        foreach($roles as $r){
            $allRoles[] = $r->id;
        }
        foreach ($roleIds as $rid) {
            $rid = intval($rid);
            if ($rid && in_array($rid, $allRoles)) {
                $row = new RoleUserModel();
                $row->uid = $uid;
                $row->rid = $rid;
                $result = $row->create();
                if(!$result){
                    throw new Exception($this->translator->_('分配角色失败'));
                }
            }
        }
    }

    /**
     * 改密码
     */
    public function changePassword(){
        $data = $this->_toParamObject($this->getParams());
        if(!$data['password']){
            throw new ApiException($this->translator->_('没设置新密码'));
        }
        $row  = UserModel::findFirstByUid($this->getUserMemberId());
        if(!$row){
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
        $row->password = $data['password'];
        return $row->update();
    }
    /**
     * 验证码是否正确
     * @param $phone
     * @param $verifyCode
     * @param $token
     * @return void
     * @throws ApiException
     */
    protected function validateVerifyCode($receive,$verifyCode,$seed,$autoThrow=true){
        //验证码是否正确
        $storage = $this->_di->getShared('simpleStorage');
        $smsPrefix = 'sms';
        $countryCode = '';
        $key = $smsPrefix . $countryCode. $receive . '_' . $seed;
        $correctVerifyCode = $storage->get($key);
        if(!$correctVerifyCode || $correctVerifyCode != $verifyCode){
            if($autoThrow){
                throw new ApiException($this->translator->_('验证码错误'));
            }else{
                return false;
            }
        }
        return true;
    }
    /**
     * 更换手机号
     * @param array $params['newPhone'] 手机号或email
     * @param array $params['oldVerifyCode'] 旧手机验证码
     * @param array $params['oldVerifyCodeToken'] 旧手机验证码种子
     * @param array $params['newVerifyCode'] 新手机验证码
     * @param array $params['newVerifyCodeToken'] 新手机验证码种子
     * @return void
     */
    public function changePhoneByVerifyCode($params=[]){
        $uid = $this->getUserMemberId();
        $user = UserModel::findFirst(['uid=:uid:','bind'=>['uid'=>$uid]]);
        if(!$user){
            throw new ApiException($this->translator->_('账号不存在'));
        }
        $data = $this->_toParamObject($params);
        //验证码是否正确
        if($user->mobile)
            $this->validateVerifyCode($user->mobile,$data['oldVerifyCode'],$data['oldVerifyCodeToken']);
        else {
            //未设手机号时，允许不验证旧手机
            //throw new ApiException($this->translator->_('原手机号不存在'));
        }
        $this->validateVerifyCode($data['newPhone'],$data['newVerifyCode'],$data['newVerifyCodeToken']);
        $user->mobile = $data['newPhone'];
        $result = $user->update();
        if(!$result) {
            throw new ApiException($this->translator->_('手机号修改失败，原因%s%', ['s' => $user->getMessages()[0]->getMessage()]));
        }else{
            return true;
        }
    }
    /**
     * 改密码
     * @param $params['receive'] 手机号或email
     * @param $params['verifyCode']
     * @param $params['verifyCodeToken']
     * @param $params['password']
     * @return void
     */
    public function changePasswordByVerifyCode($params=[]){
        $data = $this->_toParamObject($params);
        //验证码是否正确
        $user = $this->_loginByVerifyCode($data['receive'],$data['verifyCode'],$data['verifyCodeToken']);
        $user= UserModel::findFirst([
            'conditions' => 'uid = :uid:',
            'bind' => [
                'uid' => $user['uid']
            ]
        ]);
        if(!$user){
            throw new ApiException($this->translator->_('账号不存在'));
        }
        $user->password = $data['password'];
        $result = $user->update();
        if(!$result){
            throw new ApiException($this->translator->_('密码修改失败，原因%s%',['s'=>$user->getMessages()[0]->getMessage()]));
        }else{
            return true;
        }
    }
    /**
     * 按用户ID给列表
     * @return array
     * @throws Exception
     * @throws \Kuga\Core\Api\Exception
     */
    public function userListByIds(){
        $data = $this->_toParamObject($this->getParams());
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_LIST');
        if(!$isAllow){
            throw new ApiException($this->translator->_('你没有查看用户列表权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $data['pageSize'] || $data['pageSize'] = 10;
        $data['current'] || $data['current'] = 1;
        //只列出当前用户的
        $searcher  = UserBindAppModel::query();
        $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u','left');
        $appId = $data['appId'];
        if(!$appId){
            $appId = $this->_appKey;
        }
        $bind['aid'] = $appId;
        if($data['uids']){
            $searcher->where('u.uid in ({q1:array})');
            //$bind['q1'] = join(',',$data['uids']);
            $bind['q1'] = $data['uids'];
        }else{
            $searcher->where('1!=1');
        }
        $searcher->bind($bind);
        $searcher->columns('count(0) as total');
        $result = $searcher->execute();
        $total  = $result->getFirst()->total;
        $searcher->orderBy('u.uid desc');
        $searcher->columns([
            'u.uid',
            'u.mobile',
            'u.email',
            'u.fullname',
            'u.gender',
            'u.username',
            'u.memo',
            'u.createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid=u.uid) as appIds'
        ]);
        $searcher->limit($data['pageSize'], ($data['current'] - 1) * $data['pageSize']);
        $result = $searcher->execute();
        $list   = $result->toArray();
        if($list){
            foreach($list as &$user){
                $user['appIds'] = explode(',',$user['appIds']);
                $user['appIds'] = array_map(function($item){
                    return intval($item);
                },$user['appIds']);
            }
        }
        return ['list' => $list, 'total' => $total, 'page' => $data['current'], 'limit' => $data['pageSize']];
    }
    public function allUserList(){
        $data = $this->_toParamObject($this->getParams());
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_LIST');
        if(!$isAllow){
            throw new ApiException($this->translator->_('你没有查看用户列表权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $data['pageSize'] || $data['pageSize'] = 10;
        $data['current'] || $data['current'] = 1;
        //只列出当前用户的
        $searcher  = UserModel::query();
        $bind = [];
        if($data['q']){
            $searcher->where('fullname like :q1:');
            $searcher->orWhere('mobile like :q2:');
            $searcher->orWhere('email like :q3:');
            $searcher->orWhere('username like :q4:');
            $bind['q1'] = '%'.$data['q'].'%';
            $bind['q2'] = '%'.$data['q'].'%';
            $bind['q3'] = '%'.$data['q'].'%';
            $bind['q4'] = '%'.$data['q'].'%';
        }
        if(!empty($bind)){
            $searcher->bind($bind);
        }
        $searcher->columns('count(0) as total');
        $result = $searcher->execute();
        $total  = $result->getFirst()->total;

        $searcher->orderBy('uid desc');
        $searcher->columns([
            'uid',
            'mobile',
            'email',
            'fullname',
            'memo',
            'gender',
            'username',
            'createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid='.UserModel::class.'.uid) as appIds'
        ]);
        $searcher->limit($data['pageSize'], ($data['current'] - 1) * $data['pageSize']);
        $result = $searcher->execute();
        $list   = $result->toArray();
        if($list){
            foreach($list as &$user){
                $user['appIds'] = explode(',',$user['appIds']);
                $user['appIds'] = array_map(function($item){
                    return intval($item);
                },$user['appIds']);
            }
        }
        return ['list' => $list, 'total' => $total, 'page' => $data['current'], 'limit' => $data['pageSize']];
    }
    /**
     * 显示用户列表
     * @todo 权限验证
     * @return array
     */
    public function userList()
    {
        $data = $this->_toParamObject($this->getParams());
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_LIST');
        if(!$isAllow){
            throw new ApiException($this->translator->_('你没有查看用户列表权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $data['pageSize'] || $data['pageSize'] = 10;
        $data['current'] || $data['current'] = 1;
        //只列出当前用户的
        $searcher  = UserBindAppModel::query();
        $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
        $appId = $data['appId'];
        if(!$appId){
            $appId = $this->_appKey;
        }
        $bind['aid'] = $appId;
        if($data['q']){
            $searcher->where('u.fullname like :q1:');
            $searcher->orWhere('u.mobile like :q2:');
            $searcher->orWhere('u.email like :q3:');
            $searcher->orWhere('u.username like :q4:');
            $bind['q1'] = '%'.$data['q'].'%';
            $bind['q2'] = '%'.$data['q'].'%';
            $bind['q3'] = '%'.$data['q'].'%';
            $bind['q4'] = '%'.$data['q'].'%';
        }
        $searcher->bind($bind);
        $searcher->columns('count(0) as total');
        $result = $searcher->execute();
        $total  = $result->getFirst()->total;

        $searcher->orderBy('u.uid desc');
        $searcher->columns([
            'u.uid',
            'u.mobile',
            'u.email',
            'u.fullname',
            'u.memo',
            'u.gender',
            'u.username',
            'u.createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid=u.uid) as appIds'
        ]);
        $searcher->limit($data['pageSize'], ($data['current'] - 1) * $data['pageSize']);
        $result = $searcher->execute();
        $list   = $result->toArray();
        if($list){
            foreach($list as &$user){
                $user['appIds'] = explode(',',$user['appIds']);
                $user['appIds'] = array_map(function($item){
                    return intval($item);
                },$user['appIds']);
                $searcher = RoleUserModel::query();
                $searcher->join(RoleModel::class,RoleUserModel::class.'.rid=role.id','role','left');
                $searcher->where('role.appId=:aid: and uid=:uid:');
                $searcher->bind(['aid'=>$appId,'uid'=>$user['uid']]);
                $searcher->columns([
                    'role.name as roleName',
                    'role.id as roleId',
                    'role.roleType'
                ]);
                $result = $searcher->execute();
                $user['roles'] = $result->toArray();
            }
        }
        return ['list' => $list, 'total' => $total, 'current' => $data['current'], 'pageSize' => $data['pageSize']];
    }
    private function _login($mobile,$email){
        $searcher  = UserBindAppModel::query();
        $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
        //$searcher->where(UserBindAppModel::class.'.appId=:aid:');
        $searcher->where('(mobile!="" and mobile=:m:) or (email!=""  and email=:e:)');
        $searcher->columns([
            'password',
            'u.uid',
            'u.mobile',
            'u.email',
            'u.fullname',
            'u.gender',
            'u.username'
        ]);
        $bind['aid'] = $this->_appKey;
        $bind['m']   = $mobile;
        $bind['e']   = $email;
        $searcher->bind($bind);
        $result      = $searcher->execute();
        $row         = $result->getFirst();
        return $row;
    }
    private function _loginByVerifyCode($receive,$verifyCode,$seed){
        $storage = $this->_di->getShared('simpleStorage');
        $smsPrefix = 'sms';
        $countryCode = '';
        $key = $smsPrefix . $countryCode. $receive . '_' . $seed;

        $correctVerifyCode = $storage->get($key);
        if(!$correctVerifyCode){
            throw new ApiException($this->translator->_('验证码已过期'));
        }
        if($verifyCode != $correctVerifyCode){
            throw new ApiException($this->translator->_('验证码不正确'));
        }
        $email = '';
        $mobile ='';
        if(preg_match('/^(13|14|15|17|18|19)[\d+]{9}$/',$receive)){
            //手机
            $mobile = $receive;
        }else{
            //邮箱
            $email = $receive;
        }
        $user =  $this->_login($mobile,$email);
        if($user){
            return $this->generateLoginInfo($user);
        }else{
            return null;
        }
    }
    private function _createUser($data){
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();
        $transaction->throwRollbackException(true);
        $model                = new UserModel();
        $model->username      = $data['username'];
        $model->memo          = $data['memo'];
        $model->fullname      = $data['fullname'];
        $model->password      = $data['password'];
        $model->mobile        = $data['mobile'];
        $model->email         = $data['email'];

        $model->createTime    = time();
        $model->lastVisitIp   = \Qing\Lib\Utils::getClientIp();
        $model->lastVisitTime = $model->createTime;
        $model->setTransaction($transaction);
        $result               = $model->create();
        if ( ! $result) {
            $transaction->rollback($model->getMessages()[0]->getMessage());
            //throw new ApiException($model->getMessages()[0]->getMessage());
        }else{
            if(is_array($data['appIds'])){

                foreach($data['appIds'] as $appId){
                    $appId = intval($appId);
                    if($appId){
                        $bindModel = new UserBindAppModel();
                        $bindModel->appId = $appId;
                        $bindModel->setTransaction($transaction);
                        $bindModel->uid   = $model->uid;
                        $result = $bindModel->create();
                        if(!$result){
                            $transaction->rollback($this->translator->_('用户绑定应用失败'));
                        }
                    }
                }
            }
            $transaction->commit();
        }
        return $model;
    }
    /**
     * 根据手机号/邮箱，验证码
     *
     */
    public function bindLogin(){
        $data      = $this->_toParamObject($this->getParams());
        $oauth     = Oauth2Model::findFirst([
            'oauthId=:id: and oauthApp=:app:',
            'bind'=>['id'=>$data['oauthId'],'app'=>$data['app']]
        ]);
        if(!$oauth){
            throw new ApiException($this->translator->_('未在第三方平台登陆'));
        }
        if($oauth->userId){
            throw new ApiException($this->translator->_('已绑定过用户'));
        }
        $result    = $this->_loginByVerifyCode($data['receive'],$data['verifyCode'],$data['seed']);
        if(!$result){
            //自动创建？
            $app = AppModel::findFirstById($this->_appKey);
            if(!$app){
                throw new ApiException($this->translator->_('应用不存在'));
            }
            if(!$app->allowAutoCreateUser){
                throw new ApiException($this->translator->_('用户不存在，不能绑定'));
            }
            $row['username'] = uniqid('guest');
            $row['mobile']   = '';
            $row['email']    = '';
            if(preg_match('/^(13|14|15|17|18|19)[\d+]{9}$/',$data['receive'])){
                $row['mobile'] = $data['receive'];
            }else{
                $row['email'] = $data['receive'];
            }
            $row['password'] = md5(time());
            $row['fullname'] = $row['username'];
            $row['memo'] = '';
            $row['appIds'] = [$this->_appKey];
            $model = $this->_createUser($row);
            if($model->id){
                $result = $this->_loginByVerifyCode($data['receive'],$data['verifyCode'],$data['seed']);
                if($result){
                    $oauth->userId = $result['uid'];
                    $oauth->update();
                    return $result;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            //绑定
            $oauth->userId = $result['uid'];
            $oauth->update();
            return $result;
        }
    }

    public function loginByCode(){
        $data      = $this->_toParamObject($this->getParams());
        $storage = $this->_di->getShared('simpleStorage');
        $userId  = $storage->get($data['code']);
        if(!$userId){
            throw new ApiException($this->translator->_('错误的code'));
        }else{
            $userModel = new UserModel();
            $searcher  = UserBindAppModel::query();
            $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
            //$searcher->where(UserBindAppModel::class.'.appId=:aid:');
            $searcher->where('u.uid=:uid:');
            $searcher->columns([
                'password',
                'u.uid',
                'u.mobile',
                'u.email',
                'u.fullname',
                'u.gender',
                'u.username'
            ]);
            $bind['aid'] = $this->_appKey;
            $bind['uid']   = $userId;

            $searcher->bind($bind);
            $result      = $searcher->execute();
            $row         = $result->getFirst();

            if ( $row) {
                return $this->generateLoginInfo($row);
            } else {
                throw new ApiException(ApiException::$EXCODE_NOTEXIST);
            }
        }
    }
    public function loginByVerifyCode(){
        $data      = $this->_toParamObject($this->getParams());
        $result    = $this->_loginByVerifyCode($data['receive'],$data['verifyCode'],$data['seed']);
        if($result){
            return $result;
        }else{
            $app = AppModel::findFirst($this->_appKey);
            if(!$app){
                throw new ApiException($this->translator->_('应用不存在'));
            }
            if(strtolower($app->allowAutoCreateUser)==='y'){
                //自动创建用户
                $row = [];
                if(preg_match('/^(13|14|15|17|18|19)[\d+]{9}$/',$data['receive'])){
                    //手机
                    $row['mobile'] = $data['receive'];
                }else{
                    //邮箱
                    $row['email'] = $data['receive'];
                }
                $row['username'] = uniqid('guest');
                $row['password'] = md5(time());
                $row['fullname'] = $row['username'];
                $row['memo'] = '';
                $row['appIds'] = [$this->_appKey];
                $model = $this->_createUser($row);
                if($model->uid) {
                    $result = $this->generateLoginInfo($model);
                    if ($result) {
                        return $result;
                    } else {
                        return false;
                    }
                }
            }
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
    }

    /**
     * 管理人员登录
     */
    public function login()
    {
        $data      = $this->_toParamObject($this->getParams());
        $userModel = new UserModel();
        $searcher  = UserBindAppModel::query();
        $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
        //$searcher->where(UserBindAppModel::class.'.appId=:aid:');
        $searcher->where('username=:name: or (mobile!="" and mobile=:m: )  or (email!="" and email=:e:)');
        $searcher->columns([
            'password',
            'u.uid',
            'u.mobile',
            'u.email',
            'u.fullname',
            'u.gender',
            'u.username'
        ]);
        $bind['aid'] = $this->_appKey;
        $bind['name']   = $data['user'];
        $bind['m']   = $data['user'];
        $bind['e']   = $data['user'];

        $searcher->bind($bind);
        $result      = $searcher->execute();
        $row         = $result->getFirst();
        if ( ! $row) {
            $acc = new \Kuga\Module\Acc\Service\Acc($this->_di);
            $initRootUser = $acc->initSystem($data['user'],$data['password'],$this->_appKey);
            if($initRootUser){
                return $this->generateLoginInfo($initRootUser);
            }
            throw new ApiException(ApiException::INVALID_PASSWORD);
        } elseif ($userModel->passwordVerify($row->password, $data['password'])) {
            return $this->generateLoginInfo($row);
        } else {
            throw new ApiException(ApiException::INVALID_PASSWORD);
        }
    }

    /**
     * 刷新accessToken
     * @throws ApiException
     */
    public function refreshAccessToken(){
        $data = $this->_toParamObject($this->getParams());
        if($data['refreshToken']){
            $uid = $this->_di->getShared('cache')->get('refreshToken-'.$data['refreshToken']);
            if(!$uid){
                //无效的刷新token
                throw new ApiException(ApiException::$EXCODE_INVALID_REFRESHTOKEN);
            }else{
                $row = UserModel::findFirstByUid($uid);
                return $this->generateLoginInfo($row);
            }
        }else{
            throw new ApiException($this->translator->_('refreshToken参数没传值'));
        }
    }

    public function generateLoginInfo($row){
        $row->lastVisitIP   = \Qing\Lib\Utils::getClientIp();
        $row->lastVisitTime = time();
        //$row->update();
        //取得角色
        $result                               = $this->getRoles($row->uid);
        $result[$this->_accessTokenUserIdKey] = $row->uid;
        $result['fullname'] = $row->fullname;
        $result['username'] = $row->username;
        $hours = 10;
        $days  = ceil($hours / 24);
        //$expiredDate = strtotime($days+' days');
        $accessToken                          = $this->_createAccessToken($result,$hours*3600);

        //取得可以访问的菜单
        $menuService = new MenuService($this->_di);
        $aclService  = new AclService($this->_di);
        $aclService->setUserId($row->uid);
        $aclService->setAppId($this->_appKey);
        if(isset($result['console.roles.'.$this->_appKey])){
            $aclService->setRoles($result['console.roles.'.$this->_appKey]);
        }else{
            $aclService->setRoles([]);
        }
        //$aclService->setRoles($result['console.roles']);
        $menuService->setAclService($aclService);
        $returnData['menuList'] = $menuService->getAll(true, true, false,['id','name','url'],$this->_appKey);

        //权限数据放到token
        $acc = new Acc($this->_di);
        $acc->initParams(['uid'=>$row->uid,'appId'=>$this->_appKey],'getPrivileges');
        $result['acc'] = $acc->getPrivileges();
//        $result['fullname'] = $this->_userFullname;
        if($this->_accessTokenType ===  GlobalVar::TOKEN_TYPE_JWT){
            $payload = [
                'username'=>$row->username,
                'fullname'=>$row->fullname,
            ];
            $payload[$this->_accessTokenUserIdKey] = $row->uid;
            $jwt = new JWTService();
            $jwt->setSecret($this->_di->get('config')->path('app.jwtTokenSecret'));
            $token = $jwt->createToken($payload,$hours * 3600);
            $returnData['accessToken'] = $token;
        }else{
            $returnData['accessToken'] = $accessToken;
        }
        $returnData['refreshToken'] = md5($accessToken.'KUGA');
        $returnData['refreshTokenExpiredIn'] = ( $hours + 1) * 3600;
        $returnData['accessTokenExpiredIn'] = $hours * 3600;

        $returnData['uid']         = $row->uid;
        $returnData['username']    = $row->username;
        $returnData['gender']    = $row->gender;
        $returnData['mobile']    = $row->mobile;
        $returnData['fullname']    = $row->fullname;
        $this->_di->getShared('cache')->set('refreshToken-'.$returnData['refreshToken'],$row->uid,$returnData['refreshTokenExpiredIn']);
        return $returnData;
    }
    /**
     * 载入角色信息
     *
     * @param integer $userId
     */
    private function getRoles($userId)
    {
        $acc                        = new AccService($this->_di);
        //$roles                      = $acc->findRolesByUserid($userId);
        $searcher = RoleUserModel::query();
        $searcher->join(RoleModel::class,RoleUserModel::class.'.rid=b.id and uid=:uid:','b');
        $searcher->columns([
            'b.id',
            'name',
            'roleType',
            'defaultAllow',
            'priority',
            'assignPolicy',
            'appId'
        ]);
        $searcher->bind(['uid'=>$userId]);
        $searcher->orderBy('appId asc');
        $result = $searcher->execute();
        $roles  = $result->toArray();
        //TODO:console.roles要作废掉
        $data['console.roles']      = $roles;
        if($roles){
            foreach($roles as $role){
                $data['console.roles.'.$role['appId']][] = $role;
            }
        }

        return $data;
    }
}
