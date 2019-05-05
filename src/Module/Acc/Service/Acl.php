<?php
/**
 * 权限判断类
 *
 * @author Donny
 *
 */

namespace Kuga\Module\Acc\Service;

use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Module\Acc\Model\RoleResModel;
use Kuga\Core\Base\AbstractService;

/**
 * 管理台用户权限控制中心类
 * 注意以下规则：
 * 1)权限操作必须定义成常量，且以OP_开头
 * 2)权限资源必须定义成常量，且以RES_开头
 * 遵循上面规则时，系统会自动根据定义的从数据库中加载权限规则，否则不会自动加载
 *
 * 本方法不再使用session，调用本类进行权限判断时，要先注入一下用户ID和用户的角色
 * $acl->setUserId(..);
 * $acl->setRoles(..);
 *
 *
 *
 * @author    Dony
 * @copyright 2014
 */
class Acl extends AbstractService
{

    protected $_roles;

    /**
     * 是否有超级角色
     *
     * @var boolean
     */
    protected $_hasSuperRole;

    protected $_resources;

    protected $_operates;

    protected $_uid;
    protected $_appId;

    protected $_accService;

    private $_cachePrefix = 'ACC_';

    private $_rules = [];

    /**
     * 当前用户的角色
     *
     * @var array
     */
    protected $_currentRole = [];

    /**
     * 设置用户ID
     * --设置了用户ID可以让系统知道当前用户登陆了没，这样方便角色分配
     * （角色的分配策略中有自动分配给已登陆或未登陆的用户两种策略）
     *  所以setRoles之前要先调用setUserId
     *
     * @param $id
     */
    public function setUserId($id)
    {
        $this->_uid = $id;
    }
    public function getUserId(){
        return $this->_uid;
    }
    public function setAppId($id)
    {
        $this->_appId = $id;
    }
    /**
     * 取得/设置 是否有超级角色
     * 在setRoles()之后才有效
     *
     * @param null $bol 当不传参时，可取回是否是超级角色，当有传参时，设置是否是超级角色
     *
     * @return bool
     */
    public function hasSuperRole()
    {
        return $this->_hasSuperRole;
    }

    /**
     * 设置角色，运行前请先运行setUserId
     *
     * @param $list
     */
    public function setRoles($list)
    {
        $this->_roles = $list;
        $this->getRoles();
    }

    public function __construct($di = null)
    {
        parent::__construct($di);
        //$this->_accService = new Acc($di);
        //$session = $this->_di->getShared('session');
        //$this->setUserId($session->get('console.uid'));
    }

    /**
     * 载入系统自动分配的用户角色
     * @param integer $appId
     */
    protected function _loadRoles()
    {
        $appId = $this->_appId;
        $roleList = null;
        if ( ! empty($this->_uid)) {
            $roleList = RoleModel::find([
                'conditions' => 'assignPolicy=:ap: and appId=:aid:',
                'bind'=>['ap'=>Acc::ASSIGN_LOGINED,'aid'=>$appId]
                ])->toArray();
        } else {
            $roleList = RoleModel::find([
                    'conditions' => 'assignPolicy=:ap: and appId=:aid:',
                    'bind'=>['ap'=>Acc::ASSIGN_NOTLOGIN,'aid'=>$appId]
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
                if ($role['roleType'] == Acc::TYPE_ADMIN) {
                    $this->_hasSuperRole = true;
                }
            }
        }
        //$this->_currentRole按优先级顺序排序一下
        ksort($this->_currentRole);
    }

    /**
     * 初始化，载入模块的相关权限配置信息
     */
    protected function _loadAccResource()
    {
        $ref      = new \ReflectionObject($this);
        $cache    = $this->_di->get('cache');
        $cacheKey = $this->_cachePrefix.md5(serialize($this->_currentRole));
        $appId    = $this->_appId;
        if (1 != 1 && $data = $cache->get($cacheKey)) {
            $this->_rules = $data;
        } else {
            if (is_array($this->_currentRole) && ! empty($this->_currentRole)) {
                foreach ($this->_currentRole as $role) {
                    //取这个角色分配的资源权限情况
                    $privilegeList = RoleResModel::find(
                        ['rid=?1 and appId=?2', 'bind' => [1 => $role['id'],2=>$appId]]
                    );
                    if ($privilegeList) {
                        foreach ($privilegeList as $priv) {
                            $key                = $this->_createRuleKey($role['id'], $priv->rescode, $priv->opcode);
                            $this->_rules[$key] = boolval($priv->is_allow);
                        }
                    }
                }
            }
            $cache->set($cacheKey, $this->_rules);
        }
    }

    private function _createRuleKey($roleId, $resourceCode, $opCode)
    {
        return md5(serialize(['roleId' => $roleId, 'resourceCode' => $resourceCode, 'opCode' => $opCode]));
    }

    /**
     * 取得角色列表
     * @param integer $appId
     * @return array
     */
    public function getRoles()
    {
        if (empty($this->_currentRole)) {
            $this->_loadRoles();
        }

        return $this->_currentRole;
    }

    /**
     * 判断用户是否可以访问带有dataSrc的权限资源resource
     *
     * @param string  $resource
     * @param string  $op
     * @param integer $dataId
     *
     * @return boolean
     */
    public function isAllowedWithDataSrc($resource, $op, $dataId)
    {
        $res = $resource.':'.$dataId;

        return $this->isAllowed($res, $op);
    }

    /**
     * 判断是否有权限
     * 1  用户多角色时，有一个角色有权限访问资源，则该结果就是有权限
     * 2 Zend_Acl当存在多个角色时，系统在判断是否有权限是根据最近一次addRole的角色为主要依据，这一点和本方法不大相同
     * 3  本方法不适合于判断带有dataSrc的权限资源resource，带有dataSrc的权限资源resource判断要借助isAllowedWithDataSrc方法
     *
     * @param string $resource
     * @param string $privilege
     * @param integer $appId
     *
     * @return boolean
     */
    public function isAllowed($resource, $privilege)
    {
        $this->_loadRoles();
        $this->_loadAccResource();
        //判断有没有已定义的规则，有的话按规则
        if (is_array($this->_currentRole)) {
            foreach ($this->_currentRole as $role) {
                if ($role['roleType'] == Acc::TYPE_ADMIN) {
                    return true;
                }
                $ruleKey = $this->_createRuleKey($role['id'], $resource, $privilege);
                if (array_key_exists($ruleKey, $this->_rules)) {
                    return $this->_rules[$ruleKey];
                }
            }
            //现有规则中没有，则要看默认角色设定的访问权限
            reset($this->_currentRole);
            $firstRole = current($this->_currentRole);

            return (boolean)$firstRole['defaultAllow'];
        } else {
            return false;
        }
    }

    /**
     * 清除Acc缓存
     */
    public function removeCache()
    {
        $cache = $this->_di->get('cache');
        $cache->deleteKeys($this->_cachePrefix);
    }

    /*
     * 根据uid返回对应用户有权限访问菜单的id
     */
    public function getMenuByUid()
    {
        $roles = $this->_roles;

        $role_id_arr = [];
        if (count($roles) == 0) {
            return "";
        }
        for ($i = 0; $i < count($roles); $i++) {

            if ($roles[$i]["roleType"] == 0) {
                return "all";
            }

            $role_id_arr[] = $roles[$i]["id"];
        }
        $rid_str  = implode(",", $role_id_arr);
        $query    = new \Phalcon\Mvc\Model\Query(
            "SELECT distinct(mid) as mid FROM ".$this->getModel("roleMenu")." where rid in(".$rid_str.")", $this->_di
        );
        $list     = $query->execute();
        $menu_arr = [];
        if ($list) {
            foreach ($list as $obj) {
                $menu_arr[] = $obj->mid;
            }
        } else {
            return "";
        }

        return implode(",", $menu_arr);
    }
    /*
     * 根据uid返回用户有权限操作模块的数组，格式类似如下
     * array (size=17)
     *	  'RES_ORDER:OP_ADD' => string 'y' (length=1)
     *	  'RES_ORDER:OP_UPDATE' => string 'y' (length=1)
     *	  'RES_ORDER:OP_REMOVE' => string 'y' (length=1)
     *	  'RES_ORDER:OP_REVIEW' => string 'y' (length=1)
     *	  'RES_ORDER:OP_CHANGEPRICE' => string 'y' (length=1)
     *	  'RES_ORDER:OP_REFUND' => string 'y' (length=1)
     *	  'RES_ORDER:OP_REPLACE' => string 'y' (length=1)
     *	  'RES_ORDER:OP_CANCEL' => string 'y' (length=1)
     * 2017.4.12 没发现有调用，弃用
     */

    //	public function getAccListByUid(){
    //		$resourceList = $this->_accService->getResourceList();
    //		$session = $this->_di->getShared('session');
    //		$this->setUserId($session->get('console.uid'));
    //		$roles = $session->get('console.role');
    //		$arr = array();
    //		foreach ($resourceList as $k=>$v){
    //			foreach ($v["op"] as $op){
    //				if ($this->isAllowed($v["code"], $op["code"] )){
    //					$arr[$v["code"] . ":" . $op["code"]] = "y";
    //				}else{
    //					$arr[$v["code"] . ":" . $op["code"]] = "n";
    //				}
    //			}
    //		}
    //		return $arr;
    //	}
    /**
     * 判断某些角色是否拥有某个菜单id
     *
     * @param array   $roleIds 角色id数组
     * @param integer $menuId  菜单id
     *
     * @return boolean
     */
    public function isRolesHasMenuId($roleIds, $menuId)
    {
        if (is_array($roleIds)) {
            $roleIds = join(',', $roleIds);
        }
        $model = new RoleMenuModel();
        $count = $model->count(['conditions' => 'rid in ('.$roleIds.') and mid=?1', 'bind' => [1 => $menuId]]);

        return $count ? true : false;
    }
}
