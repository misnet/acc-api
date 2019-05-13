<?php
/**
 * 系统菜单Service
 *
 * @author dony
 * @category Qing
 * @package Service
 * @subpackage MenuService
 */
namespace Kuga\Module\Acc\Service;
use Kuga\Module\Acc\Model\MenuModel;
use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Core\Base\AbstractService;

class Menu extends AbstractService {
	private $_menuObject;
    /**
     * @var \Kuga\Module\Acc\Service\Acl
     */
	private $_aclService;
	const PREFIX_MENULIST = 'data:menuList:';

    /**
     * 注入权限判断服务ACL
     * @param $s
     */
    public function setAclService($s){
        $this->_aclService = $s;
    }
	/**
	 * 取出所有菜单，并按层级排好顺序
	 * @param integer $visible 是否可见，1:可见,0:不可见,null:所有
	 * @param boolean $filterByAcc 是否用权限系统过滤，为true时，请先调用setAclService方法，注入ACL服务
     * @param boolean $isTree 是否树状，默认false
     * @param array $columns 要取的列
	 * @return array
	 */
	public function getAll($visible=null,$filterByAcc=false,$isTree=false,$columns=[],$appId=''){
	    $cacheEngine = $this->_di->get('cache');
	    if($filterByAcc && $this->_aclService){
    	    $keySeed = array(
    	        'isAdmin'=>$this->_aclService->hasSuperRole(),
    	        'roleIds'=>$this->_aclService->getRoles()
            );
	    }else{
	        $keySeed=array('allMenu'=>true);
	    }
	    $cacheId = self::PREFIX_MENULIST.md5(serialize($keySeed));
	    $data = $cacheEngine->get($cacheId);
	    if($data===0){
	        $this->_menuObject = $data;
	    }else{
    		$this->_menuObject= null;
    		if(!$isTree){
    		    $this->_findChildMenu(0,$visible, $columns,$appId);
            }else{
    		    $this->_menuObject = $this->findByParentId(0,$visible, $columns,$appId);
    		    if($this->_menuObject){
    		        foreach($this->_menuObject as &$menu){
                        $menu['children'] = $this->findByParentId($menu['id'],$visible, $columns,$appId);
                        $menu['children']||$menu['children'] = [];
                    }
                }
            }
    		//通知钩子
    		if($filterByAcc){
                $this->_menuObject= $this->_filterMenus($this->_menuObject,$appId);
    		}
    		$cacheEngine->set($cacheId,$this->_menuObject);
    		//$this->_menuObject = $rows;
	    }
		return $this->_menuObject;
	}
	/**
	 * 删除菜单缓存
	 */
	public function clearMenuAccessCache(){
	    $cacheEngine = $this->_di->get('cache');
	    $cacheEngine->deleteKeys(self::PREFIX_MENULIST);
	}
	/**
	 * 登陆判断过滤
	 * @param array $menuObjects
	 */
	private  function _filterMenus($menuObjects,$appId){
		//根据权限访问过滤

		$isAdmin = $this->_aclService && $this->_aclService->hasSuperRole();
		if(!$isAdmin && $this->_aclService){
			//超级角色的有全部权限
			$currentRoles = $this->_aclService->getRoles();

			if(is_array($currentRoles) && !empty($currentRoles)){
				//根据当前用户所具有的全部角色分析，只要有一个角色有访问权限就可以访问该菜单
				$accessableMenuIds = array();
				foreach($currentRoles as $role){
					$hasPrivMenuIds = RoleMenuModel::getMenuIdsByRoleId($role['id']);
					$accessableMenuIds = array_merge($hasPrivMenuIds,$accessableMenuIds);
				}
				$accessableMenuIds = array_unique($accessableMenuIds);
				$menus = array();
				if(is_array($menuObjects) && !empty($menuObjects)){
					foreach($menuObjects as $menu){
						if(in_array($menu['id'],$accessableMenuIds)){
							$menus[] = $menu;
						}
						if(isset($menu['children']) && is_array($menu['children']) && sizeof($menu['children'])>0){
						    foreach($menu['children'] as $child){
                                if(in_array($child['id'],$accessableMenuIds)){
                                    $menu['children'][] = $child;
                                }
                            }
                        }
					}
				}
				$menuObjects = $menus;
			}else{
				//无权
				$menuObjects = array();
			}
		}
		return $menuObjects;
	}
	/**
	 * 检测菜单是否可以访问，菜单不在系统库中的，默认可以访问
	 * @param string $url
	 * @return boolean
	 */
	public function isAccessable($url){

        $data   = $this->getAll(true,true);
        $filteredMenus  = $this->_formatMenuData($data);
        $data   = $this->getAll(true,false);
        $allMenus  = $this->_formatMenuData($data);

        $hasAccess = false;
        $existMenu = false;
	    if($filteredMenus){
	        foreach($filteredMenus as $menuId=>$menu){
	            if($menu['url']==$url){
	                $hasAccess = true;
	            }elseif(isset($menu['submenu'])){
	                foreach($menu['submenu'] as $submenu){
	                    if($submenu['url']==$url){
	                        $hasAccess = true;
	                        break;
	                    }
	                }
	            }
	            if($hasAccess){
	                break;
	            }
	        }
	    }
	    if($allMenus){
	       foreach($allMenus as $menuId=>$menu){
	            if($menu['url']==$url){
	                $existMenu = true;
	            }elseif(isset($menu['submenu'])){
	                foreach($menu['submenu'] as $submenu){
	                    if($submenu['url']==$url){
	                        $existMenu = true;
	                        break;
	                    }
	                }
	            }
	            if($existMenu){
	                break;
	            }
	        }
	    }
	    //不存在菜单时，可以访问
	    if(!$existMenu){
	        $hasAccess = true;
	    }
	    return $hasAccess;
	}
	/**
	 * 取得菜单树
	 * @return array
	 */
	private function _formatMenuData($data){
	    $returnData = array();
	    if($data){
	        foreach ($data as $item){
	            if(isset($returnData[$item['parentId']])){
	                $returnData[$item['parentId']]['submenu'][$item['id']] = $item;
	            }else{
	                $returnData[$item['id']] = $item;
	            }
	        }

	    }
	    return $returnData;
	}
	/**
	 * 取直系子菜单列表
	 * @param number $pid 父菜单id
	 * @return array
	 */
	public function findByParentId($pid=0,$visible=null,$columns=[],$appId){
		$model = new MenuModel();
		$cond = 'parentId=:pid:';
		$bind['pid']= $pid;
		if(is_numeric($visible)){
		    $cond.=' and display = :v:';
		    $bind['v'] = $visible?1:0;
        }
        if($appId){
            $cond.=' and appId=:aid:';
            $bind['aid'] = $appId;
        }
		$rows = $model->find(array(
				'conditions'=>$cond,
				'bind'=>$bind,
				'order'=>'sortByWeight desc',
                'columns'=>empty($columns)?'*':$columns
		));
		if($rows){
			return $rows->toArray();
		}
		return false;
	}
	/**
	 * 根据父级菜单id取出其下所有子孙级菜单
	 * @param integer $parentId  父级菜单id
	 * @param integer $visible $visible 是否可见，1:可见,0:不可见,null:所有
     * @param array $columns 指定要取的列
	 */
	private function _findChildMenu($parentId,$visible=null,$columns=[],$appId=''){
		$model = new MenuModel();
		$condition = 'parentId=:pid:';
		$bind['pid'] = $parentId;
		if(!is_null($visible)){
			$condition.=' and display=:v: ';
			$bind['v'] = $visible?1:0;
		}
        if($appId){
            $condition.=' and appId=:aid:';
            $bind['aid'] = $appId;
        }
        $rows = [];
		$result= $model->find(array('conditions'=>$condition,'bind'=>$bind,'order'=>'sortByWeight desc','columns'=>empty($columns)?'*':$columns));
		if($result){
			$rows = $result->toArray();
			foreach($rows as $row){
				$this->_menuObject[] = $row;
				$this->_findChildMenu($row['id'],$visible,$columns,$appId);
			}
		}
		//return $rows;
	}
}
