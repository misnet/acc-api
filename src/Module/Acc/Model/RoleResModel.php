<?php

namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Kuga\Core\Base\ModelException;
use Kuga\Module\Acc\Service\Acl as AclService;

/**
 * 分配资源给角色
 *
 * @author dony
 *
 */
class RoleResModel extends AbstractModel
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $rid;

    /**
     *
     * @var string
     */
    public $rescode;

    /**
     *
     * @var string
     */
    public $opcode;

    /**
     * 是否允许
     * @var integer
     */
    public $isAllow;
    /**
     * 应用ID
     * @var
     */
    public $appId;

    public function initialize()
    {
        parent::initialize();
        $this->setSource('t_role_res');
        $this->belongsTo("rid", "RoleModel", "id", ['namespace' => 'Kuga\\Core\\Acc\\Model']);
    }

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return ['id' => 'id',
            'rid' => 'rid',
            'rescode' => 'rescode',
            'opcode' => 'opcode',
            'app_id' => 'appId',
            'is_allow' => 'isAllow'];
    }

    public function beforeSave()
    {
        $acc     = $this->getDI()->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        if ( ! $isAllow) {
            throw new ModelException($this->translator->_('对不起，您无权限进行此操作'),ModelException::$EXCODE_FORBIDDEN);
        }

        return true;
    }
    public function beforeDelete(){
        $acc     = $this->getDI()->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        if ( ! $isAllow) {
            throw new ModelException($this->translator->_('对不起，您无权限进行此操作'),ModelException::$EXCODE_FORBIDDEN);
        }
        return true;
    }

    /**
     * 根据角色ID与资源代码取得分配的对应的权限操作列表（分允许与禁止）
     * Enter description here ...
     * @param integer $roleId 角色ID
     * @param string $resourceCode 资源代码
     * @return array 例：array('allow'=>array('','OP_ADD','OP_REMOVE'),'deny'=>array('OP_CHECK'));
     */
    public function getAssignedOperators($roleId,$resourceCode,$appId){
        $roleId = intval($roleId);
        if(!$roleId){
            throw new ModelException($this->translator->_('没有指定角色，无法分配权限'));
        }
        if(!$resourceCode){
            throw new ModelException($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        if(!$appId){
            throw new ModelException($this->translator->_('没有指定应用，无法分配权限'));
        }
        $rows = self::find(array(
            'conditions'=>'rid=:rid: and (rescode=:rescode:) and appId=:aid:',
            'bind'=>array('rid'=>$roleId,'rescode'=>$resourceCode,'aid'=>$appId),
            'order'=>'id desc'
        ));
        $allowOperators = array();
        $denyOperators  = array();
        if($rows){
            foreach($rows as $row){
                if($row->isAllow){
                    $allowOperators[] = $row->opcode;
                }else{
                    $denyOperators[]  = $row->opcode;
                }
            }
        }
        return array('allow'=>$allowOperators,'deny'=>$denyOperators);
    }

}
