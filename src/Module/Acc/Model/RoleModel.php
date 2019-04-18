<?php

namespace Kuga\Module\Acc\Model;

use Kuga\Module\Acc\Service\Acc as AccService;
use Kuga\Module\Acc\Service\Acl as AclService;
use Kuga\Core\Base\AbstractModel;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\InclusionIn;
use Kuga\Core\Base\ModelException;

/**
 * 角色Model
 *
 * @author dony
 *
 */
class RoleModel extends AbstractModel
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var string
     */
    public $roleType;

    /**
     *
     * @var string
     */
    public $assignPolicy;

    /**
     *
     * @var integer
     */
    public $priority;

    /**
     *
     * @var integer
     */
    public $defaultAllow;
    /**
     * 应用ID
     * @var integer
     */
    public $appId;

    public function getSource()
    {
        return 't_role';
    }

    public function initialize()
    {
        parent::initialize();
        $this->hasMany(
            "id", "RoleResModel", "rid",
            ['foreignKey' => ['action' => Relation::ACTION_CASCADE], 'namespace' => 'Kuga\\Module\\Acc\\Model']
        );
        $this->hasMany(
            "id", "RoleMenuModel", "rid",
            ['foreignKey' => ['action' => Relation::ACTION_CASCADE], 'namespace' => 'Kuga\\Module\\Acc\\Model']
        );
        $this->hasMany(
            "id", "RoleUserModel", "rid",
            ['foreignKey' => ['action' => Relation::ACTION_CASCADE], 'namespace' => 'Kuga\\Module\\Acc\\Model']
        );
    }

    /**
     * Validations and business logic
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add('appId',new PresenceOf([
            'model'=>$this,
            'message'=>$this->translator->_('未指定应用')
        ]));
//        $where = 'appId=:appId: and name=:name:';
//        $bind = ['appId'=>$this->appId,'name'=>$this->name];
//        if($this->id){
//            $where.=' and id!=:id:';
//            $bind['id'] = $this->id;
//        }
//        $sameNameNum = self::count([
//            $where,
//            $bind
//        ]);
//        if($sameNameNum>0){
//            $validator->appendMessage(
//                new Validation\Message($this->translator->_('角色已存在'))
//            );
//        }

        $validator->add(
            ['name','appId'], new Uniqueness(
            ['model' => new RoleModel(), 'message' => $this->translator->_('角色已存在')]
        )
        );
        $validator->add(
            'priority', new Regex(
            ['model' => $this, "pattern" => '/^(\d+)$/', 'message' => $this->translator->_('优先级必须是大于0的数字')]
        )
        );
        $validator->add(
            ['priority','appId'], new Uniqueness(
            ['model' => $this, 'message' => $this->translator->_('优先级必须唯一')]
        )
        );
        $validator->add(
            'roleType', new InclusionIn(
            ['model'   => $this, 'domain' => array_keys(AccService::getTypes()),
             'message' => $this->translator->_('角色类型只能是超级角色或一般角色'),]
        )
        );


        $validator->add(
            'assignPolicy', new InclusionIn(
            ['model'  => $this, "message" => $this->translator->_('分配策略值有误'),
             'domain' => array_keys(AccService::getAssignPolicies())]
        )
        );

        return $this->validate($validator);
    }

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return ['id'       => 'id',
            'name' => 'name',
            'role_type' => 'roleType',
            'assign_policy' => 'assignPolicy',
            'priority' => 'priority',
            'default_allow' => 'defaultAllow',
            'app_id'=>'appId'];
    }

    public function beforeSave()
    {
        $acc     = new AclService();
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        $isAllow = true;
        if ( ! $isAllow) {
            throw new ModelException($this->translator->_('对不起，您无权限进行此操作'));
        }

        return true;
    }

    public function beforeDelete()
    {
        $acc     = new AclService();
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        $isAllow = true;
        if ( ! $isAllow) {
            throw new ModelException($this->translator->_('对不起，您无权限进行此操作'));
        }

        return true;

    }
}
