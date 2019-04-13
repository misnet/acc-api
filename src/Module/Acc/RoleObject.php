<?php 
namespace Kuga\Module\Acc;
use Kuga\Core\Base\AbstractObject;

class RoleObject extends AbstractObject {
	/**
	 * 角色名称
	 * @var string
	 */
	public $name;
	/**
	 * 优先级
	 * @var integer
	 */
	public $priority;
	/**
	 * 默认是否允许
	 * @var integer
	 */
	public $defaultAllow;
	/**
	 * 角色id
	 * @var integer
	 */
	public $id;
	/**
	 * 角色类型
	 * @var integer
	 */
	public $roleType;
	/**
	 * 权限分配策略
	 * @var integer
	 */
	public $assignPolicy;
}