<?php

namespace Kuga\Api\Acc;

use Kuga\Module\Acc\Model\RoleMenuModel;
use Kuga\Module\Acc\Model\RoleResModel;
use Kuga\Module\Acc\Model\RoleUserModel;
use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Service\Acl;
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

        $userSearcher = UserModel::query();
        //$userSearcher->where(UserModel::class.'.uid not in (select '.RoleUserModel::class.'.uid from '.RoleUserModel::class.' where rid=:rid:)');
        //$userSearcher->bind($bind);
        $userSearcher->columns(
            [UserModel::class . '.uid', 'username']
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
        $roleMenuModel = new RoleResModel();
        $roleMenuModel->setResourceConfigFile($this->_di->getShared('config')->acc);
        $list = $roleMenuModel->getResourceGroup();
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
        $roleMenuModel->setResourceConfigFile($this->_di->getShared('config')->acc);
        $resource = $roleMenuModel->getResource($data['res']);

        $assignOps = $roleMenuModel->getAssignedOperators($data['rid'], $data['res']);
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
}
