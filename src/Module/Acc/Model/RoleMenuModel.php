<?php

namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Kuga\Core\Base\ModelException;
use Kuga\Module\Acc\Service\Acl as  AclService;
/**
 * 分配菜单给角色
 *
 * @author dony
 *
 */
class RoleMenuModel extends AbstractModel
{

    /**
     *
     * @var integer
     */
    public $rid;

    /**
     *
     * @var integer
     */
    public $mid;

    public function getSource()
    {
        return 't_role_menu';
    }

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo("rid", "RoleModel", "id",['namespace'=>'Kuga\\Module\\Acc\\Model']);
        $this->belongsTo("mid", "MenuModel", "id",['namespace'=>'Kuga\\Module\\Acc\\Model']);
    }

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return ['rid' => 'rid', 'mid' => 'mid'];
    }

    public function beforeSave()
    {
        $acc     = new AclService();
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        $isAllow = true;
        if ( ! $isAllow) {
            throw new ModelException($this->_('对不起，您无权限进行此操作'));
        }

        return true;
    }

    /**
     * 根据角色ID取该角色可以访问的菜单id列表
     *
     * @param integer $roleId 角色ID
     *
     * @return array 菜单id数组
     */
    public static function getMenuIdsByRoleId($roleId)
    {
        $rows  = self::find(
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
}
