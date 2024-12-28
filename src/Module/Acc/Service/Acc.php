<?php

namespace Kuga\Module\Acc\Service;

use Kuga\Core\Base\AbstractService;
use Kuga\Core\Base\ServiceException;
use Kuga\Core\Service\ApiAccessLogService;
use Kuga\Core\Sms\SendmsgLogsModel;
use Kuga\Module\Acc\Config;
use Kuga\Module\Acc\Exception;
use Kuga\Module\Acc\Model\AppModel;
use Kuga\Module\Acc\Model\ConfigurationModel;
use Kuga\Module\Acc\Model\MenuModel;
use Kuga\Module\Acc\Model\UserBindAppModel;
use Kuga\Module\Acc\Model\UserModel;
use Phalcon\Mvc\Model\Resultset\Simple;
use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Model\RoleUserModel;
use Kuga\Module\Acc\RoleObject;
use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Module\Acc\Model\RoleResModel;
use Kuga\Module\Acc\Service\Acl as AclService;

/**
 * Access Controll Center 权限控制中心系统类
 * 负责角色管理及相关权限分配
 *
 * @author dony
 *
 */
class Acc extends AbstractService
{

    /**
     * 超级管理员角色
     *
     * @var integer
     */
    const TYPE_ADMIN = 0;

    /**
     * 普通角色
     *
     * @var integer
     */
    const TYPE_BASE = 1;

    /**
     * 不自动分配
     *
     * @var unknown_type
     */
    const ASSIGN_NO = 0;

    /**
     * 自动分配给已登录用户
     *
     * @var integer
     */
    const ASSIGN_LOGINED = 1;

    /**
     * 自动分配给未登录用户
     *
     * @var integer
     */
    const ASSIGN_NOTLOGIN = 2;
    private $accXmlContent = '';

    /**
     * ACC APP KEY
     * @return int
     */
    public static function getAccAppKey(){
        return Config::ACC_APPKEY;
    }

    /**
     * 根据角色类型ID取类型名称
     *
     * @param integer $typeId
     *
     * @return string
     */
    public function getTypeName($typeId)
    {
        $typeId = intval($typeId);
        $types  = self::getTypes();
        if (array_key_exists($typeId, $types)) {
            return $types[$typeId];
        } else {
            return $types[self::TYPE_BASE];
        }
    }

    /**
     * 取得角色类型
     *
     * @return array(值1=>名称1,值2=>名称2....)
     */
    public static function getTypes()
    {
        return [self::TYPE_ADMIN => '超级角色', self::TYPE_BASE => '一般角色'];
    }

    /**
     * 取得所有分配策略
     * return string
     */
    public static function getAssignPolicies()
    {
        return [self::ASSIGN_LOGINED => '自动分配给已登录用户', self::ASSIGN_NOTLOGIN => '自动分配给未登录用户',
                self::ASSIGN_NO      => '不自动分配'];
    }

    /**
     * 取得全部角色列表
     *
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function getRolesList()
    {
        $model           = new RoleModel();
        $roleTable       = $model->getSource();
        $ruModel         = new RoleUserModel();
        $ruTable         = $ruModel->getSource();
        $sql             = 'select r.*,(select count(uid) from '.$ruTable.' where rid=r.id) as usernum from '.$roleTable
            .' r order by priority asc';
        $cols            = $model->columnMap();
        $cols['usernum'] = 'usernum';
        $rows            = new Simple($cols, $model, $model->getReadConnection()->query($sql));

        return $rows;
    }

    /**
     * 按id取得角色
     *
     * @param integer $id
     *
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function getRoleById($id)
    {
        $id = intval($id);

        return RoleModel::findFirst(['conditions' => 'id=:id:', 'bind' => ['id' => $id]]);
    }

    /**
     * 修改或添加角色
     *
     * @param RoleObject $roleObj
     */
    public function saveRole(RoleObject $roleObj)
    {
        $roleObj->id = intval($roleObj->id);
        if ($roleObj->id) {
            $existRole = RoleModel::findFirst(['conditions' => 'id=?1', 'bind' => [1 => $roleObj->id]]);
            if ( ! $existRole) {
                throw new ServiceException($this->translator->_('要修改的角色不存在'));
            }
            $roleModel = $existRole;
        } else {
            $roleModel = new RoleModel();
        }
        $roleModel->defaultAllow = $roleObj->defaultAllow;
        $roleModel->assignPolicy = $roleObj->assignPolicy;
        $roleModel->priority     = $roleObj->priority;
        $roleModel->roleType     = $roleObj->roleType;
        $roleModel->name         = $roleObj->name;
        $result                  = $roleModel->save();
        if ( ! $result) {
            $s = $roleModel->getMessages();
            throw new ServiceException($roleModel->getMessages());
        }
        $aclService = new AclService($this->_di);
        $aclService->removeCache();

        return true;
    }

    /**
     * 根据角色id列表数组排序角色优先级
     *
     * @param array $ids
     */
    public function sortRolesPriority($ids)
    {
        if (is_array($ids) && ! empty($ids)) {
            $i         = 0;
            $model     = new RoleModel();
            $modelName = '\\Kuga\\Module\\Acc\\Model\\RoleModel';
            foreach ($ids as $id) {
                $model->getWriteConnection()->query(
                    'update '.$model->getSource().' set priority=:pid where id=:id', ['pid' => $i, 'id' => $id]
                );
                //$command = $model->getModelsManager()->createQuery('update '.$modelName.' set priority=:pid: where id=:id:');
                //$result  = $command->execute(array('pid'=>$i,'id'=>$id));
                $i++;
            }
            //TODO:优先级的顺序会影响权限，所以要清缓存
            $aclService = new AclService($this->_di);
            $aclService->removeCache();
        }
    }

    /**
     * 根据角色ID取该角色可以访问的菜单id列表
     *
     * @param integer $roleId 角色ID
     *
     * @return array 菜单id数组
     */
    public function getMenuIdsByRoleId($roleId)
    {
        $model = new RoleMenuModel();
        $rows  = $model->find(
            ['conditions' => 'rid=?1', 'bind' => [1 => $roleId]]
        );
        $list  = $rows->toArray();
        $ids   = [];
        if ($rows) {
            foreach ($list as $row) {
                $ids[] = $row['mid'];
            }
        }

        return $ids;
    }

    /**
     * 分配菜单给角色
     *
     * @param array   $menu   菜单数组
     * @param integer $roleId 角色ID
     *
     * @return boolean
     */
    public function assignMenuToRole($menu, $roleId)
    {
        $roleId = intval($roleId);
        if ( ! $roleId) {
            throw new ServiceException($this->translator->_('没有指定角色，无法分配权限'));
        }
        $menuModel = new RoleMenuModel();
        //TODO:事务
        try {
            $roleId    = intval($roleId);
            $modelName = "\\Kuga\\Module\\Acc\\Model\\RoleMenuModel";
            $query     = $menuModel->getModelsManager()->createQuery('delete from '.$modelName.' where rid=:rid:');
            $result    = $query->execute(['rid' => $roleId]);
            if (is_array($menu)) {
                foreach ($menu as $mid) {
                    //不同记录必须new多个model
                    $model      = new RoleMenuModel();
                    $model->mid = $mid;
                    $model->rid = $roleId;
                    $model->create();
                    unset($model);
                }
            }
            $aclService = new AclService($this->_di);
            $aclService->removeCache();

            return true;
        } catch (Exception $e) {
            throw new Exception ($e->getMessage());
        }
    }

    /**
     * 取得权限资源列表
     */
    public function getResourceList($appId,$defaultXml=null)
    {
        $cache             = $this->_di->get('cache');
        $cacheKey          = 'acc_setting_'.$appId;
        $resourceList      = $cache->get($cacheKey);
        if(!$resourceList){
            $this->setAccXmlContent($appId,$defaultXml);
            $resourceList = $this->parsePrivilegeSetting();
            $cache->set($cacheKey,$resourceList,86400);
        }
        return $resourceList;
    }
    public function setAccXmlContent($appId,$defaultXml){
        $app = AppModel::findFirstById($appId);
        if($app){
            $this->accXmlContent = $app->accResourcesXml;
        }else{
            $this->accXmlContent = null;
        }
        if(!$this->accXmlContent){
            $this->accXmlContent = $defaultXml;
        }
    }
    /**
     * 分析acc.xml文件，读取权限资源操作配置
     *
     * @return array
     */
    public function parsePrivilegeSetting()
    {
        //$file = QING_ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'acc.xml';

        $resourceList = [];
        if($this->accXmlContent){
            $dom = simplexml_load_string($this->accXmlContent);
            if ($dom instanceof \SimpleXMLElement) {
                if (sizeof($dom->children()) > 0) {
                    $i = 0;
                    foreach ($dom->children() as $key => $node) {
                        $nodeName = (string)$key;
                        $nodeName = strtolower($nodeName);
                        if ($nodeName == 'resource') {
                            $code = (string)$node['code'];
                            $code = trim($code);
                            if ($code != '' && ! array_key_exists($code, $resourceList)) {
                                $resourceList[$code] = ['code'      => $code, 'text' => (string)$node['title'],
                                                        'op'        => $this->_parseOpNode($node),
                                                        'model'     => strval($node['model']),
                                                        'idField'   => strval($node['idField']),
                                                        'nameField' => strval($node['nameField'])];
                            }
                        }
                    }
                }
            }
        }
        return $resourceList;
    }

    /**
     * 解析acc.xml的op项
     *
     * @param SimpleXMLElement $dom
     *
     * @return array
     */
    private function _parseOpNode($dom)
    {
        $op = [];
        if ($dom instanceof \SimpleXMLElement) {
            if (sizeof($dom->children()) > 0) {
                $i = 0;
                foreach ($dom->children() as $key => $node) {
                    $nodeName = (string)$key;
                    $nodeName = strtolower($nodeName);
                    if ($nodeName == 'op') {
                        $code = (string)$node['code'];
                        if ($code != '') {
                            $op[$i]['code'] = $code;
                            $op[$i]['text'] = (string)$node['title'];
                            $i++;
                        }
                    }
                }
            }
        }

        return $op;
    }

    /**
     * 根据资源代码取得资源数组信息
     * @param string $appId
     * @param string $code
     *
     * @return array
     */
    public function getResource($appId,$code)
    {
        $resourceList = $this->getResourceList($appId);
        if (is_array($resourceList) && sizeof($resourceList) > 0) {
            if (array_key_exists($code, $resourceList)) {
                return $resourceList[$code];
            } else {
                return [];
            }
        } else {
            return [];
        }
    }

    /**
     * 根据角色ID与资源代码取得分配的对应的权限操作列表（分允许与禁止）
     * Enter description here ...
     *
     * @param integer $roleId       角色ID
     * @param string  $resourceCode 资源代码
     *
     * @throws \Kuga\Core\Base\ServiceException
     * @return array 例：array('allow'=>array('','OP_ADD','OP_REMOVE'),'deny'=>array('OP_CHECK'));
     */
    public function getAssignedOperators($roleId, $resourceCode)
    {
        $roleId = intval($roleId);
        if ( ! $roleId) {
            throw new ServiceException($this->translator->_('没有指定角色，无法分配权限'));
        }
        if ( ! $resourceCode) {
            throw new ServiceException($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        $resModel       = new RoleResModel();
        $rows           = $resModel->find(
            ['conditions' => 'rid=:rid: and (rescode=:rescode:)',
             'bind'       => ['rid' => $roleId, 'rescode' => $resourceCode], 'order' => 'id desc']
        );
        $allowOperators = [];
        $denyOperators  = [];
        if ($rows) {
            foreach ($rows as $row) {

                if ($row->is_allow) {
                    $allowOperators[] = $row->opcode;
                } else {
                    $denyOperators[] = $row->opcode;
                }
            }
        }

        return ['allow' => $allowOperators, 'deny' => $denyOperators];
    }

    /**
     * 取消某角色关于某资源的权限分配
     *
     * @param integer $roleId       角色id
     * @param string  $resourceCode 资源代码
     *
     * @throws Ume_Exception
     */
    public function unassginResourceToRole($roleId, $resourceCode)
    {
        $roleId = intval($roleId);
        if ( ! $roleId) {
            throw new ServiceException($this->translator->_('没有指定角色，无法分配权限'));
        }
        if ( ! $resourceCode) {
            throw new ServiceException($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        $model     = new RoleResModel();
        $modelName = '\\Kuga\Module\Acc\Model\RoleResModel';
        $command   = $model->getModelsManager()->createQuery(
            'delete from '.$modelName.' where rid=:rid: and rescode=:rcd:'
        );
        $command->execute(['rid' => $roleId, 'rcd' => $resourceCode]);
        //TODO:权限分配变更了，所以要清缓存
        $aclService = $this->_di->getShared('aclService');
        $aclService->removeCache();
    }

    /**
     * 给角色分配资源权限
     *
     * @param integer $roleId       角色ID
     * @param string  $resourceCode 资源代码
     * @param string  $opCode       操作代码
     * @param integer $isAllow      是否允许(1允许,0禁止)
     */
    public function assignResourceToRole($roleId, $resourceCode, $opCode, $isAllow)
    {
        $roleId = intval($roleId);
        if ( ! $roleId) {
            throw new ServiceException($this->translator->_('没有指定角色，无法分配权限'));
        }
        if ( ! $resourceCode) {
            throw new ServiceException($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        //TODO:事务
        //TODO:权限分配变更了，所以要清缓存
        $aclService = $this->_di->getShared('aclService');
        $aclService->removeCache();
        try {
            $opCode = is_null($opCode) ? '' : $opCode;
            $model  = new RoleResModel();
            $data   = ['rid' => $roleId, 'rescode' => $resourceCode, 'opcode' => $opCode, 'is_allow' => $isAllow];
            $model->create($data);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除角色
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function removeById($id)
    {
        $modelName = '\\Kuga\Module\Acc\Model\RoleModel';
        $model     = new RoleModel();
        $command   = $model->getModelsManager()->createQuery('delete from '.$modelName.' where id=:id:');
        $num       = $command->execute(['id' => $id]);
        //会影响权限，所以要清缓存
        $aclService = new AclService($this->_di);
        $aclService->removeCache();

        return $num ? true : false;
    }

    /**
     * 根据类型id(单个或数组)取角色列表
     *
     * @param integer|array $tid
     *
     * @return array
     */
    public function findRolesByTypeId($tid)
    {
        $tid = intval($tid);
        if ($tid !== self::TYPE_ADMIN && $tid != self::TYPE_BASE) {
            throw new ServiceException($this->translator->_('findRolesByTypeId()传入的参数不正确'));
        }
        if ( ! is_array($tid)) {
            $cond = ['conditions' => 'roleType=:rt:', 'bind' => ['rt' => $tid]];
        } else {
            $cond = ['conditions' => 'roleType in (:rt:)', 'bind' => ['rt' => $tid]];
        }
        $roleModel = new RoleModel();
        $rows      = $roleModel->find($cond)->toArray();

        return $rows;
    }

    /**
     * 根据用户id取得其所分属的角色(不含系统自动分配的)
     *
     * @param integer $uid
     *
     * @return array 值为roleModel的属性
     */
    public function findRolesByUserId($uid)
    {
        $userRoleModelName = '\\Kuga\\Module\\Acc\\Model\\RoleUserModel';
        $roleModelName     = '\\Kuga\\Module\\Acc\\Model\\RoleModel';
        $roleModel         = new RoleModel();
        $query             = $roleModel->getModelsManager()->createQuery(
            'select b.id,name,roleType,defaultAllow,priority,assignPolicy from '.$userRoleModelName.' a,'.$roleModelName
            .' b  where a.rid=b.id and uid=:uid:'
        );
        $result            = $query->execute(['uid' => $uid]);

        return $result->toArray();
    }

    /**
     * 取消某个用户与某个角色的绑定关系
     *
     * @param integer $roleId
     * @param integer $userId
     *
     * @return integer
     */
    public function unbindRoleUser($roleId, $userId)
    {
        if (empty($roleId) || empty($userId)) {
            return false;
        }
        $roleUserModel = new RoleUserModel();
        $row           = $roleUserModel->findFirst(
            ['conditions' => 'rid=:rid: and uid=:uid:', 'bind' => ['rid' => $roleId, 'uid' => $userId]]
        );
        if ($row) {
            $row->delete();
        }
        $aclService = new AclService($this->_di);
        $aclService->removeCache();
    }

    /**
     * 判断某个用户是否具有某个角色
     *
     * @param integer $roleId
     * @param integer $userId
     *
     * @return boolean
     */
    public function isRoleUserBinded($roleId, $userId)
    {
        if (empty($roleId) || empty($userId)) {
            return false;
        }
        $roleUserModel = new RoleUserModel();
        $row           = $roleUserModel->findFirst(
            ['conditions' => 'rid=:rid: and uid=:uid:', 'bind' => ['rid' => $roleId, 'uid' => $userId]]
        );

        return ($row) ? true : false;
    }

    /**
     * 绑定用户与角色关系
     *
     * @param integer $roleId
     * @param integer $userId
     *
     * @return boolean
     */
    public function bindRoleUser($roleId, $userId)
    {
        if (empty($roleId) || empty($userId)) {
            return false;
        }
        $roleUserModel      = new RoleUserModel();
        $roleUserModel->rid = $roleId;
        $roleUserModel->uid = $userId;
        $aclService         = new AclService($this->_di);
        $aclService->removeCache();

        return $roleUserModel->save();
    }

    /**
     * 初始化系统
     * —— 条件：系统中没有任何应用与用户，缓存中system_init不存在
     * —— 处理：删除所有表数据，重新创建一个应用，一个超级管理员角色，一个超级管理员用户，并绑定
     * @param $username
     * @param $password
     * @param $appId
     * @return false|UserModel
     * @throws \Exception
     */
    public function initSystem($username,$password,$appId){
        $cache = $this->_di->get('cache');
        $cacheKey = 'system_init';
        $init = $cache->get($cacheKey);
        if($init){
            return false;
        }

        $cnt = AppModel::count();
        if($cnt !== 0){
            return false;
        }

        $cnt = UserModel::count();
        if($cnt !== 0){
            return false;
        }
//        $tx = $this->_di->get('transactions');
//        $transaction = $tx->get();
        $appModel = new AppModel();
        $query     = $appModel->getModelsManager()->createQuery('delete from '.RoleModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.MenuModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.ConfigurationModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.UserBindAppModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.RoleUserModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.RoleResModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.RoleMenuModel::class);
        $query->execute();

        $query     = $appModel->getModelsManager()->createQuery('delete from '.SendmsgLogsModel::class);
        $query->execute();
        $appModel->getModelsManager()->getWriteConnection($appModel)->begin();

        $appModel->id = $appId;
        $appModel->name = 'ACC系统';
        $appModel->accResourcesXml = file_get_contents(QING_ROOT_PATH.'/config/acc.xml');
        $appModel->allowAutoCreateUser = 'n';
        $appModel->disabled = 'n';
        $appModel->secret = $appModel->generateSecret();
        $result = $appModel->create();
        if(!$result){
            $appModel->getModelsManager()->getWriteConnection($appModel)->rollback();
            throw new \Exception('初始化系统失败——无法创建应用');
        }

        $roleModel = new RoleModel();
        $roleModel->roleType = self::TYPE_ADMIN;
        $roleModel->name     = '超级管理员';
        $roleModel->defaultAllow = 'y';
        $roleModel->assignPolicy = self::ASSIGN_NO;
        $roleModel->priority = 0;
        $roleModel->appId = $appId;
        $result = $roleModel->create();
        if(!$result){
            $appModel->getModelsManager()->getWriteConnection($appModel)->rollback();
            throw new \Exception('初始化系统失败——无法创建角色');
        }
        $userModel = new UserModel();
        $userModel->username = $username;
        $userModel->password = $password;
        $userModel->fullname = '系统管理员';
        $userModel->mobile   = '';
        $userModel->email    = '';
        $result = $userModel->create();
        if(!$result){
            $appModel->getModelsManager()->getWriteConnection($appModel)->rollback();
            throw new \Exception('初始化系统失败——无法创建用户');
        }
        $bindModel = new UserBindAppModel();
        $bindModel->uid = $userModel->uid;
        $bindModel->appId = $appId;
        $result = $bindModel->create();
        if(!$result){
            $appModel->getModelsManager()->getWriteConnection($appModel)->rollback();
            throw new \Exception('初始化系统失败——无法绑定用户与应用');
        }
        $this->bindRoleUser($roleModel->id,$userModel->uid);
        $config = $this->_di->getShared('config');
        if($config->path('app.apiLogEnabled')){
            $logService = new ApiAccessLogService($this->_di);
            $logService->flush();
        }
        $acl = new AclService($this->_di);
        $acl->removeCache();

        $menuService = new Menu();
        $menuService->clearMenuAccessCache();

        $cache->set($cacheKey,1);
//        $transaction->commit();
        $appModel->getModelsManager()->getWriteConnection($appModel)->commit();
        return $userModel;
    }
}