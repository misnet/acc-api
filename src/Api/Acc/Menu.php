<?php
/**
 * 菜单接口
 * @author  Donny
 */
namespace Kuga\Api\Acc;
use Kuga\Module\Acc\Model\MenuModel;
use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Api\Acc\Exception as ApiException;
class Menu extends BaseApi{
    private function getChildMenuList($parentId,$allowedIds=null){
        $childList = MenuModel::find([
            'order'=>'sortByWeight desc',
            'parentId=?1',
            'bind'=>[1=>$parentId]
        ]);
        $childrenItems = $childList->toArray();
        if($childrenItems){
            foreach($childrenItems as &$childItem){
                $childItem['display'] = strtolower($childItem['display']);
                if(is_array($allowedIds)){
                    if (in_array($childItem['id'], $allowedIds)) {
                        $childItem['allow'] = 'y';
                    } else {
                        $childItem['allow'] = 'n';
                    }
                }
                $subList = $this->getChildMenuList($childItem['id'],$allowedIds);
                if($subList){
                    $childItem['children'] = $subList;
                }
            }
        }
        return $childrenItems;
    }
    /**
     * 所有菜单列表
     */
    public function listMenu(){
        $data = $this->_toParamObject($this->getParams());
        $data['pid'] = intval($data['pid']);
        $data['rid'] = intval($data['rid']);
        $appId = $data['appId'];
        if (!$appId) {
            $appId = $this->_appKey;
        }

        $result = MenuModel::find([
            'order'=>'sortByWeight desc',
            'parentId=:pid: and appId=:aid:',
            'bind'=>['pid'=>$data['pid'],'aid'=>$appId]
        ]);
        $list = $result->toArray();
        $selectedMenuIds = [];
        if($data['rid']){
            $roleMenuListResult = RoleMenuModel::find([
                'rid=?1',
                'bind'=>[1=>$data['rid']],
                'columns'=>['mid']
            ]);
            if($roleMenuListResult){
                foreach($roleMenuListResult as $row){
                    $selectedMenuIds[] = $row->mid;
                }
            }
        }
        $list || $list = [];

        foreach($list as &$item){
            $item['display'] = strtolower($item['display']);
            $childList  = $this->getChildMenuList($item['id'],$selectedMenuIds);
            if($childList){
                $item['children'] = $childList;
            }
//            $childList = MenuModel::find([
//                'order'=>'sortByWeight desc',
//                'parentId=?1',
//                'bind'=>[1=>$item['id']]
//            ]);
//            $item['children']= $childList->toArray();
//            if($item['children']){
//                foreach($item['children'] as &$childItem){
//                    $childItem['display'] = strtolower($childItem['display']);
//                }
//            }
//            if(!$item['children']){
//                unset($item['children']);
//            }else{
//                if($data['rid']) {
//                    foreach ($item['children'] as &$childItem) {
//                        if (in_array($childItem['id'], $selectedMenuIds)) {
//                            $childItem['allow'] = 'y';
//                        } else {
//                            $childItem['allow'] = 'n';
//                        }
//                    }
//                }
//            }
            if($data['rid']) {
                if (in_array($item['id'], $selectedMenuIds)) {
                    $item['allow'] = 'y';
                } else {
                    $item['allow'] = 'n';
                }
            }
        }
        return $list;
    }

    /**
     * 创建菜单
     */
    public function createMenu(){
        $data = $this->_toParamObject($this->getParams());
        $menu = new MenuModel();
        $menu->initData($data->toArray(),['id','createTime']);
        $result = $menu->create();
        if(!$result){
            throw new ApiException($menu->getMessages()[0]->getMessage());
        }
        return $result;
    }

    /**
     * 更新菜单
     */
    public function updateMenu(){
        $data = $this->_toParamObject($this->getParams());
        $menu = MenuModel::findFirstById($data['id']);
        if($menu){
            $menu->initData($data->toArray(),['createTime','appId']);
            $result = $menu->update();
            if(!$result){
                throw new ApiException($menu->getMessages()[0]->getMessage());
            }
        }else{
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
        return $result;
    }

    /**
     * 删除菜单
     */
    public function deleteMenu(){
        $data = $this->_toParamObject($this->getParams());
        $menu = MenuModel::findFirstById($data['id']);
        if($menu){
            $result = $menu->delete();
        }else{
            $result = true;
        }
        if(!$result){
            throw new ApiException($menu->getMessages()[0]->getMessage());
        }
        return $result;
    }

}