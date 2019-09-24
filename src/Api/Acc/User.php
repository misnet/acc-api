<?php
/**
 * 后台系统用户类目API
 */

namespace Kuga\Api\Acc;

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
            if($data['realname']){
                $row->realname = $data['realname'];
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

        $row  = UserModel::findFirstByUid($data['uid']);
        if ( ! $row) {
            throw new ApiException($this->translator->_('找不到用户，可能已被删除'));
        }
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();

        $row->username = $data['username'];
        if ($data['password']) {
            $row->password = $data['password'];
        }
        $row->email         = $data['email'];
        $row->mobileVerified= $data['mobileVerified'];
        $row->emailVerified = $data['emailVerified'];
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
        $data                 = $this->_toParamObject($this->getParams());
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();
        $model                = new UserModel();
        $model->username      = $data['username'];
        $model->password      = $data['password'];
        $model->mobile        = $data['mobile'];
        $model->email         = $data['email'];
        $model->mobileVerified= $data['mobileVerified'];
        $model->emailVerified = $data['emailVerified'];

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
        return true;
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
        $row->passwd = $data['password'];
        return $row->update();
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

        $data['limit'] || $data['limit'] = 10;
        $data['page'] || $data['page'] = 1;
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
            'u.realname',
            'u.gender',
            'u.username',
            'u.mobileVerified',
            'u.emailVerified',
            'u.createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid=u.uid) as appIds'
        ]);
        $searcher->limit($data['limit'], ($data['page'] - 1) * $data['limit']);
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
        return ['list' => $list, 'total' => $total, 'page' => $data['page'], 'limit' => $data['limit']];
    }
    public function allUserList(){
        $data = $this->_toParamObject($this->getParams());
        $acl = $this->_di->getShared('aclService');
        $isAllow = $acl->isAllowed('RES_USER','OP_LIST');
        if(!$isAllow){
            throw new ApiException($this->translator->_('你没有查看用户列表权限'),ApiException::$EXCODE_FORBIDDEN);
        }

        $data['limit'] || $data['limit'] = 10;
        $data['page'] || $data['page'] = 1;
        //只列出当前用户的
        $searcher  = UserModel::query();
        $bind = [];
        if($data['q']){
            $searcher->where('realname like :q1:');
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
            'realname',
            'gender',
            'username',
            'mobileVerified',
            'emailVerified',
            'createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid='.UserModel::class.'.uid) as appIds'
        ]);
        $searcher->limit($data['limit'], ($data['page'] - 1) * $data['limit']);
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
        return ['list' => $list, 'total' => $total, 'page' => $data['page'], 'limit' => $data['limit']];
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

        $data['limit'] || $data['limit'] = 10;
        $data['page'] || $data['page'] = 1;
        //只列出当前用户的
        $searcher  = UserBindAppModel::query();
        $searcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
        $appId = $data['appId'];
        if(!$appId){
            $appId = $this->_appKey;
        }
        $bind['aid'] = $appId;
        if($data['q']){
            $searcher->where('u.realname like :q1:');
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
            'u.realname',
            'u.gender',
            'u.username',
            'u.mobileVerified',
            'u.emailVerified',
            'u.createTime',
            '(select group_concat(appId) from '.UserBindAppModel::class.' b where b.uid=u.uid) as appIds'
        ]);
        $searcher->limit($data['limit'], ($data['page'] - 1) * $data['limit']);
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
        return ['list' => $list, 'total' => $total, 'page' => $data['page'], 'limit' => $data['limit']];
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
        $searcher->where('username=:name: or (mobile=:m: and mobileVerified=1)  or (email=:e: and emailVerified=1)');
        $searcher->columns([
            'password',
            'u.uid',
            'u.mobile',
            'u.email',
            'u.realname',
            'u.gender',
            'u.username',
            'u.mobileVerified',
            'u.emailVerified'
        ]);
        $bind['aid'] = $this->_appKey;
        $bind['name']   = $data['user'];
        $bind['m']   = $data['user'];
        $bind['e']   = $data['user'];

        $searcher->bind($bind);
        $result      = $searcher->execute();
        $row         = $result->getFirst();

        if ( ! $row) {
            throw new ApiException(ApiException::INVALID_PASSWORD);
        } elseif ($userModel->passwordVerify($row->password, $data['password'])) {
            return $this->_generateLoginInfo($row);
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
            $uid = $this->_di->getShared('cache')->get('refreshToken:'.$data['refreshToken']);
            if(!$uid){
                //无效的刷新token
                throw new ApiException(ApiException::$EXCODE_INVALID_REFRESHTOKEN);
            }else{
                $row = UserModel::findFirstByUid($uid);
                return $this->_generateLoginInfo($row);
            }
        }else{
            throw new ApiException($this->translator->_('refreshToken参数没传值'));
        }
    }

    private function _generateLoginInfo($row){
        $row->lastVisitIP   = \Qing\Lib\Utils::getClientIp();
        $row->lastVisitTime = time();
        //$row->update();
        //取得角色
        $result                               = $this->getRoles($row->uid);
        $result[$this->_accessTokenUserIdKey] = $row->uid;
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
        $returnData['accessToken'] = $accessToken;
        $returnData['accessTokenExpiredIn'] = $hours * 3600;
        $returnData['refreshToken'] = md5($accessToken.'KUGA');
        $returnData['refreshTokenExpiredIn'] = ( $hours + 1) * 3600;
        $returnData['uid']         = $row->uid;
        $returnData['username']    = $row->username;
        $returnData['gender']    = $row->gender;
        $returnData['mobile']    = $row->mobile;
        $returnData['realname']    = $row->realname;
        $this->_di->getShared('cache')->set('refreshToken:'.$returnData['refreshToken'],$row->uid,$returnData['refreshTokenExpiredIn']);
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

//        $data['console.super_role'] = false;
//        if (is_array($roles)) {
//            $superRoles = $acc->findRolesByTypeId(AccService::TYPE_ADMIN);
//
//            if (is_array($superRoles)) {
//                $ids = [];
//                foreach ($roles as $role) {
//                    $ids[] = $role['id'];
//                }
//                $data['console.role_ids'] = $ids;
//                foreach ($superRoles as $superRole) {
//                    if (in_array($superRole['id'], $ids)) {
//                        $data['console.super_role'] = true;
//                        unset ($superRoles);
//                        break;
//                    }
//                }
//            }
//        }

        return $data;
    }
}
