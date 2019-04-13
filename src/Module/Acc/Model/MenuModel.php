<?php

namespace Kuga\Module\Acc\Model;
use Kuga\Core\Base\AbstractModel;
use Kuga\Core\Base\ModelException;
use Kuga\Module\Acc\Service\Menu;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Mvc\Model\Relation;
class MenuModel extends AbstractModel {
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
	public $url;
	/**
	 *
	 * @var integer
	 */
	public $parentId;

	/**
	 * 排序值，越大越前面
	 * @var integer
	 */
	public $sortByWeight;
    /**
     * 所属应用ID
     * @var integer
     */
	public $appId;
	/**
	 * Independent Column Mapping.
	 */
	public function columnMap() {
		return array (
				'id' => 'id',
				'name' => 'name',
				'url' => 'url',
				'parent_id' => 'parentId',
				'sort_by_weight' => 'sortByWeight',
				'display' => 'display',
                'app_id'=>'appId'
		);
	}

	public function getSource() {
		return 't_menu';
	}

	public function initialize(){
		parent::initialize();
		//实现菜单删除时，权限分配菜单给角色的记录也删除
		$this->hasMany("id", "RoleMenuModel", "mid",array(
			'foreignKey'=>array(
				'action'=>Relation::ACTION_CASCADE
			),
            'namespace'=>'\\Kuga\\Module\\Acc\\Model'
		));
	}
	private static $_triggerDeleteTree = false;
	public function afterDelete(){
	    if(false===self::$_triggerDeleteTree){
	        self::$_triggerDeleteTree = true;
	        $rows = $this->find(array('conditions'=>'parentId=?1','bind'=>array(1=>$this->id)));
	        if($rows){
	            $rows->delete();
	        }
	        self::$_triggerDeleteTree = false;
	    }
	    $menuService = new Menu();
	    $menuService->clearMenuAccessCache();
	}

	/**
	 * Validations and business logic
	 */
	public function validation() {
	    $validator = new Validation();

	    $validator->add('name', new PresenceOfValidator([
	        'model'=>$this,
	        'message'=>$this->translator->_('菜单名必须填写')
	    ]));
	    if(!$this->parentId){
            $validator->add('appId',new PresenceOfValidator([
                'model'=>$this,
                'message'=>$this->translator->_('未指定应用')
            ]));
        }
	    return $this->validate($validator);
	}

    /**
     * 保存前钩子
     * @return bool
     * @throws Exception
     */
	public function beforeSave(){
	    if($this->id){
    	    $cond = [
    	        'conditions'=>'url=?1',
    	        'bind'=>[1=>$this->url]
    	    ];
    	    if($this->id){
    	        $cond['conditions'].=' and id!=?4';
    	        $cond['bind'][4] = $this->id;
    	    }
    	    $existRow = self::findFirst($cond);
    	    $childList = self::find([
    	        'parentId=?1',
                'bind'=>[1=>$this->id]
            ]);
	        if(!$this->url && !sizeof($childList)){
	            throw new ModelException('当菜单有子菜单时才可以不填url');
	        }
            if($existRow && ($existRow->url!='')){
                throw new ModelException('存在相同URL地址的菜单【'.$existRow->name.'】');
            }
            if($this->parentId==$this->id){
                throw new ModelException($this->translator->_('父级菜单不能是自己'));
            }
            //只支持了2级，多级不支持
            foreach($childList as $node){
                if($node->id==$this->parentId){
                    throw new ModelException($this->translator->_('父级菜单不能是当前菜单的子菜单'));
                }
            }

	    }
	    return true;
	}
	public function afterSave(){
	    $menuService = new Menu();
	    $menuService->clearMenuAccessCache();
	}
}
