<?php

namespace Kuga\Api\Acc;

use Kuga\Module\Acc\Model\AppModel;
use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Module\Acc\Model\RoleResModel;
use Kuga\Module\Acc\Model\RoleUserModel;
use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Model\UserBindAppModel;
use Kuga\Module\Acc\Service\Acl;
use Kuga\Module\Acc\Service\Acc as AccService;
use Kuga\Core\Api\Exception as ApiException;
use Kuga\Core\GlobalVar;
use Kuga\Module\Acc\Service\Menu as MenuService;
use Kuga\Module\Acc\Model\UserModel;

/**
 * Access Controll Center API
 * 访问控制中心API
 *
 * @package Kuga\Api\Console
 */
class Acc extends BaseApi
{

    /**
     * 菜单分配给指定角色
     * @param rid
     * @param menuIds
     */
    public function assignMenusToRole()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['rid'] = intval($data['rid']);
        $roleRow = RoleModel::findFirstById($data['rid']);
        if (!$roleRow) {
            throw new ApiException($this->translator->_('指定的角色不存在'));
        }
        $data['menuIds'] = trim($data['menuIds']);
        $menuIdArray = explode(',', $data['menuIds']);
        $model = new RoleMenuModel();
        $sql = 'delete from ' . RoleMenuModel::class . ' where rid=:rid:';
        $query = $model->getModelsManager()->createQuery($sql);
        $query->execute(['rid' => $data['rid']]);
        foreach ($menuIdArray as $menuId) {
            $menuId = intval($menuId);
            if ($menuId) {
                $row = new RoleMenuModel();
                $row->rid = $data['rid'];
                $row->mid = $menuId;
                $row->create();
            }
        }
        $this->clearCache();
        return true;
    }

    /**
     * 给某些用户分配指定的角色
     */
    public function assignRoleToUsers()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['rid'] = intval($data['rid']);
        $roleRow = RoleModel::findFirstById($data['rid']);
        if (!$roleRow) {
            throw new ApiException($this->translator->_('指定的角色不存在'));
        }
        $idList = explode(',', $data['uid']);
        $success = 0;
        foreach ($idList as $uid) {
            $uid = intval($uid);
            if ($uid) {
                $hasAssigned = RoleUserModel::count(['rid=?1 and uid=?2', 'bind' => [1 => $data['rid'], 2 => $uid]]);
                if (!$hasAssigned) {
                    $row = new RoleUserModel();
                    $row->rid = $data['rid'];
                    $row->uid = $uid;
                    $result = $row->create();
                    if ($result) {
                        $success++;
                    }
                }
            }
        }
        $this->clearCache();
        return true;
    }

    /**
     * 取消某些用户的指定角色
     */
    public function unassignRoleToUsers()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['rid'] = intval($data['rid']);
        $roleRow = RoleModel::findFirstById($data['rid']);
        if (!$roleRow) {
            throw new ApiException($this->translator->_('指定的角色不存在'));
        }
        $idList = explode(',', $data['uid']);
        $success = 0;
        $ruModel = new RoleUserModel();
        $sql = 'delete from ' . RoleUserModel::class . ' where rid=:rid: and uid in ({uid:array})';
        $bind = [
            'rid' => $data['rid'],
            'uid' => $idList
        ];
        $result = $ruModel->getModelsManager()->executeQuery($sql, $bind);

        $this->clearCache();
        return $result->success() === true;
    }

    /**
     * 列出角色已分配的用户列表
     *
     * @return array
     */
    public function listRoleUser()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['rid'] = intval($data['rid']);
        $bind = ['rid' => $data['rid']];
        $roleRow = RoleModel::findFirst([
            'id=?1',
            'bind' => [1 => $data['rid']],
            'columns' => ['id', 'name']
        ]);
        if (!$roleRow) {
            throw new ApiException($this->translator->_('指定的角色不存在'));
        }
        $appId = $data['appId'];
        if (!$appId) {
            $appId = $this->_appKey;
        }
        $searcher = RoleUserModel::query();
        $searcher->join(UserModel::class, RoleUserModel::class . '.uid=user.uid', 'user');
        $searcher->columns(
            ['user.uid']
        );
        $searcher->orderBy(RoleUserModel::class . '.id desc');
        $searcher->where('rid=:rid:');
        $searcher->bind($bind);
        $result = $searcher->execute();
        $assignedList = $result->toArray();


        $userSearcher = UserBindAppModel::query();
        $userSearcher->join(UserModel::class,UserBindAppModel::class.'.uid=u.uid and appId=:aid:','u');
        $userSearcher->bind(['aid'=>$appId]);

        //$userSearcher->where(UserModel::class.'.uid not in (select '.RoleUserModel::class.'.uid from '.RoleUserModel::class.' where rid=:rid:)');
        //$userSearcher->bind($bind);
        $userSearcher->columns(
            ['u.uid', 'u.username']
        );
        $result = $userSearcher->execute();
        $unassignedList = $result->toArray();

        return ['role' => $roleRow->toArray(), 'assigned' => $assignedList, 'unassigned' => $unassignedList,];
    }

    /**
     * 角色列表
     */
    public function listRole()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['page'] = intval($data['page']);
        $data['limit'] = intval($data['limit']);
        $data['limit'] || $data['limit'] = GlobalVar::DATA_DEFAULT_LIMIT;
        $data['page'] || $data['page'] = 1;
        $appId = $data['appId'];
        if (!$appId) {
            $appId = $this->_appKey;
        }

        $searcher = RoleModel::query();
        $searcher->columns('count(0) as total');
        $searcher->where('appId=:aid:');
        $searcher->bind(['aid' => $appId]);
        $result = $searcher->execute();
        $total = $result->getFirst()->total;

        $searcher->columns([
                RoleModel::class . '.id',
                RoleModel::class . '.name',
                RoleModel::class . '.defaultAllow',
                RoleModel::class . '.assignPolicy',
                RoleModel::class . '.priority',
                RoleModel::class . '.roleType',
                '(select count(0) from ' . RoleUserModel::class . ' where rid=' . RoleModel::class . '.id) as cntUser']
        );
        $searcher->orderBy('priority asc,id desc');
        $result = $searcher->execute();
        $list = $result->toArray();

        return ['total' => intval($total), 'list' => $list, 'page' => $data['page'], 'limit' => $data['limit']];
    }

    /**
     * 创建角色
     */
    public function createRole()
    {
        $data = $this->_toParamObject($this->getParams());
        $row = new RoleModel();
        $row->initData($data->toArray(), ['id']);
        $result = $row->create();
        if (!$result) {
            throw new ApiException($row->getMessages()[0]->getMessage());
        }

        return $result;
    }

    /**
     * 修改角色
     */
    public function updateRole()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['id'] = intval($data['id']);
        $row = RoleModel::findFirstById($data['id']);
        if (!$row) {
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
        $row->initData($data->toArray(),['appId']);
        $result = $row->update();
        if (!$result) {
            throw new ApiException($row->getMessages()[0]->getMessage());
        }
        $this->clearCache();
        return $result;
    }

    /**
     * 删除角色
     */
    public function deleteRole()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['id'] = intval($data['id']);
        $row = RoleModel::findFirstById($data['id']);
        if (!$row) {
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
        $result = $row->delete();

        $this->clearCache();
        return $result;
    }

    /**
     * 列出权限资源组
     */
    public function listResourcesGroup()
    {
        $data  = $this->_toParamObject($this->getParams());
        $acc   = new AccService($this->_di);
        $list = $acc->getResourceList($data['appId']);
        $list || $list = [];
        $returnList = [];
        foreach ($list as $item) {
            $returnList[] = [
                'code' => $item['code'],
                'text' => $item['text'],
                'op' => $item['op']
            ];
        }
        return $returnList;
    }

    /**
     * 列出指定角色与权限资源的操作列表
     * @param rid
     * @param res
     */
    public function listOperationList()
    {
        $data = $this->_toParamObject($this->getParams());
        $data['rid'] = intval($data['rid']);
        $row = RoleModel::findFirstById($data['rid']);
        if (!$row) {
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }

        $data['res'] = trim($data['res']);

        $roleMenuModel = new RoleResModel();
        $acc   = new AccService($this->_di);
        $resource = $acc->getResource($data['appId'],$data['res']);
        $assignOps = $roleMenuModel->getAssignedOperators($data['rid'], $data['res'],$data['appId']);

        if ($resource) {
            foreach ($resource['op'] as &$op) {
                if (in_array($op['code'], $assignOps['allow'])) {
                    $op['allow'] = 1;
                } else {
                    $op['allow'] = 0;
                }
            }
            unset($resource['model'], $resource['idField'], $resource['nameField']);
        }
        return $resource;
    }


    /**
     * 清缓存
     */
    private function clearCache()
    {
        $aclService = new Acl($this->_di);
        $aclService->removeCache();

        $menuService = new MenuService($this->_di);
        $menuService->clearMenuAccessCache();
    }

    /**
     * 解析权限资源XML内容
     * @return array
     * @throws ApiException
     */
    public function parseResourceXml(){
        $data = $this->_toParamObject($this->getParams());
        $appId     = trim($data['appId']);
        $xml  = trim($data['xml']);
        try {
            $content = new \SimpleXMLElement($xml);
        }catch(\Exception $e){
            $content = '';
        }
        if(!$content){
            throw new ApiException('XML文件格式错误');
        }
        $resources= $content->xpath('/privileges/resource');
        if(is_array($resources) && !empty($resources)){
            $resList = [];
            foreach($resources as $res){
                $tmp = [];
                if(isset($res['title']) && isset($res['code'])){
                    $tmp['title'] = strval($res['title']);
                    $tmp['code'] = strval($res['code']);
                }
                $opcodes = $res->xpath('op');
                $tmp['op'] = [];
                if($opcodes){
                    foreach ($opcodes as $op){
                        if(isset($op['title']) && isset($op['code'])){
                            $t['title'] = strval($op['title']);
                            $t['code'] = strval($op['code']);
                            $tmp['op'][] = $t;
                        }
                    }
                }
                if(!empty($tmp)){
                    $resList[] = $tmp;
                }
            }
            if($resList){
                $cache = $this->_di->getShared('cache');
                $key       = 'resourceXml:'.$appId.':'.$this->_userMemberId;
                $cache->set($key,$xml,7200);
                return [
                    'parsedKey'=>md5($key),
                    'resources'=>$resList
                ];
            }
        }
        throw new ApiException($this->translator->_('XML内容格式有误，无法正确识别到权限资源与其对应定义的操作码'));
    }

    /**
     * 保存resource xml
     */
    public function importResourceXml(){
        $data = $this->_toParamObject($this->getParams());
        $parsedKey  = trim($data['parsedKey']);
        $appId     = trim($data['appId']);
        $cache     = $this->_di->getShared('cache');
        $key       = 'resourceXml:'.$appId.':'.$this->_userMemberId;
        if($parsedKey === md5($key)){
            $accXml= $cache->get($key);
            if(!$accXml){
                throw new ApiException();
            }else{
                $app = AppModel::findFirstById($data['appId']);
                $app->accResourcesXml = $accXml;
                $result = $app->save();
                $this->clearCache();
                return $result;
            }
        }else{
            throw new ApiException();
        }
    }

    /**
     * 保存对某个角色的操作权限分配
     */
    public function assignOperationsToRole(){
        $data = $this->_toParamObject($this->getParams());
        $resource = $data['res'];
        $roleId   = $data['rid'];
        $opcodes  = $data['opcodes'];
        $appId    = $data['appId'];
        if(!$roleId){
            throw new Exception($this->translator->_('没有指定角色，无法分配权限'));
        }
        if(!$resource){
            throw new Exception($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        if(!$appId){
            throw new Exception($this->translator->_('没有指定应用，无法分配权限'));
        }
        if(!is_array($opcodes)||empty($opcodes)){
            throw new Exception($this->translator->_('没有指定权限操作，无法分配权限'));
        }
        $aclService = $this->_di->getShared('aclService');
        $aclService->removeCache();
        $tx = $this->_di->getShared('transactions');
        $transaction = $tx->get();
        $hasChanged  = false;
        foreach ($opcodes as $op){
            $opcode = $op['code'];
            $isAllow= $op['allow'];
            $opRow = RoleResModel::findFirst([
                'rid=:rid: and rescode=:rc: and opcode=:op: and appId=:aid:',
                'bind'=>['rid'=>$roleId,'rc'=>$resource,'op'=>$opcode,'aid'=>$appId]
            ]);
            if($isAllow<0){
                if($opRow){
                    $hasChanged = true;
                    $opRow->setTransaction($transaction);
                    $result = $opRow->delete();
                    if(!$result){
                        $transaction->rollback();
                    }
                }
            }else{
                if($opRow){
                    $hasChanged = true;
                    $opRow->isAllow = $isAllow;
                    $result = $opRow->update();
                }else{
                    $hasChanged = true;
                    $model = new RoleResModel();
                    $model->rescode = $resource;
                    $model->opcode  = $opcode;
                    $model->rid     = $roleId;
                    $model->isAllow = $isAllow;
                    $model->appId   = $appId;
                    $model->setTransaction($transaction);
                    $result = $model->create();
                }
                if(!$result){
                    $transaction->rollback();
                }
            }
        }
        if($hasChanged){
            $transaction->commit();
        }
        return true;
    }

    /**
     * 权限判断
     * 1.根据给定的用户ID uid 取得这个用户绑定的角色列表
     * 2.结合查询那些不需要手动分配的角色，按优先级排序
     * 3.如果角色中有超级角色，则认为有权限
     * 4.如果查到有定义的权限记录，则看定义的结果是有权还是没权
     * 5.查不到有定义的权限记录，就看第1个角色的默认权限
     *
     * @TODO:有必要传uid吗，直接用当前用户ID是否可以？
     */
    public function getPrivileges(){
        $data = $this->_toParamObject($this->getParams());
        $data['uid'] = trim($data['uid']);
        $appId = $data['appId'];
        $roleIds = [];
        $roles   = [];
        if(!empty($data['uid'])){
            $searcher = RoleUserModel::query();
            $searcher->join(RoleModel::class,RoleUserModel::class.'.rid=r.id and r.appId=:aid1:','r');
            $searcher->join(UserBindAppModel::class,RoleUserModel::class.'.uid=ub.uid and ub.appId=:aid2:','ub','left');
            $searcher->where(RoleUserModel::class.'.uid=:uid:');
            $searcher->bind(['aid1'=>$appId,'aid2'=>$appId,'uid'=>$data['uid']]);
            $searcher->columns([
                'r.id',
                'r.defaultAllow',
                'r.roleType'
            ]);
            $searcher->orderBy('r.roleType asc,r.priority asc');
            $result = $searcher->execute();
            $roles   = $result->toArray();
            if($roles){
                foreach($roles as $r){
                    $roleIds[] = $r['id'];
                }
            }
        }

        $returnData= [];
        $acc       = new AccService($this->_di);
        $resources = $acc->getResourceList($appId);
        foreach($roles as $role){
            if($role['roleType'] == AccService::TYPE_ADMIN){
                if($resources){
                    foreach($resources as $resource){
                        $returnData[$resource['code']] = array_map(function($op){
                            return $op['code'];
                        },$resource['op']);
                    }
                    return $returnData;
                }
            }
        }

        if(!empty($roleIds) && is_array($roleIds)){
            $searcher = RoleResModel::query();
            $searcher->join(RoleModel::class,RoleResModel::class.'.rid=r.id','r','left');
            $searcher->where('r.roleType!=:rt: and isAllow=1');
            $cond = [];
            $bind = ['rt'=>AccService::TYPE_ADMIN];
            if($data['uid']){
                $cond = '(r.assignPolicy = :ap: and r.appId=:aid1:)';
                $bind['ap']   = AccService::ASSIGN_LOGINED;
                $bind['aid1'] = $appId;
                if(!empty($roleIds)){
                    $cond.=' or ('.RoleResModel::class.'.rid in ({rid:array}) and '.RoleResModel::class.'.appId=:aid2:)';
                    $bind['rid']  = $roleIds;
                    $bind['aid2'] = $appId;
                }
                $searcher->andWhere($cond);
            }else{
                $searcher->andWhere('r.assignPolicy = :ap: and r.appId=:aid3:');
                $bind['ap'] = AccService::ASSIGN_NOTLOGIN;
                $bind['aid3'] = $appId;
            }
            $searcher->bind($bind);
            $searcher->columns([
                'r.priority',
                RoleResModel::class.'.isAllow',
                RoleResModel::class.'.rescode',
                RoleResModel::class.'.opcode',
            ]);
            $searcher->orderBy('r.priority asc');
            $result = $searcher->execute();
            $rows   = $result->toArray();
            if($rows){
                foreach($rows as $row){
                    $k = $row['rescode'];
                    if(!$returnData[$k]){
                        $returnData[$k] = [];
                    }
                    if(!in_array($row['opcode'],$returnData[$k])){
                        //优先级的高的先入为主
                        $returnData[$k][] = $row['opcode'];
                    }
                }
            }
            return $returnData;
        }
        return false;
    }

    private function loadRoles($userId,$appId,$roleIds)
    {
        $roleList = null;
        $returnRoles = RoleResModel::find(array(
            'rid in ({rid:array}) and appId=:aid:',
            'bind'=>array('rid'=>$roleIds,'aid'=>$appId),
            'order'=>'id desc'
        ));
        if ( ! empty($userId)) {
            $roleList = RoleModel::find([
                'conditions' => 'assignPolicy=:ap: and appId=:aid:',
                'bind'=>['ap'=>AccService::ASSIGN_LOGINED,'aid'=>$appId]
            ])->toArray();
        } else {
            $roleList = RoleModel::find([
                    'conditions' => 'assignPolicy=:ap: and appId=:aid:',
                    'bind'=>['ap'=>AccService::ASSIGN_NOTLOGIN,'aid'=>$appId]
                ]
            )->toArray();
        }

        if ( ! empty($roleList)) {
            foreach ($roleList as $role) {
                if ( ! in_array($role['id'], $this->_currentRole)) {
                    $this->_currentRole[$role['priority']] = $role;
                }
            }
        }

        if ( ! empty($this->_roles)) {
            foreach ($this->_roles as $role) {
                if ( ! in_array($role, $this->_currentRole)) {
                    $this->_currentRole[$role['priority']] = $role;
                }
                if ($role['roleType'] == AccService::TYPE_ADMIN) {
                    $this->_hasSuperRole = true;
                }
            }
        }
        //$this->_currentRole按优先级顺序排序一下
        ksort($this->_currentRole);
    }
}
