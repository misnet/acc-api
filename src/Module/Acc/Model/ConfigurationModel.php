<?php

namespace Kuga\Module\Acc\Model;
use Kuga\Core\Base\AbstractModel;
use Phalcon\Filter\Validation;
use Phalcon\Messages\Message;

class ConfigurationModel extends AbstractModel
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
    public $configName;

    /**
     *
     * @var string
     */
    public $configKey;

    /**
     *
     * @var string
     */
    public $configValue;

    /**
     *
     * @var string
     */
    public $module;

    /**
     *
     * @var string
     */
    public $remark;

    /**
     *
     * @var integer
     */
    public $createdTime;

    /**
     *
     * @var integer
     */
    public $updatedTime;
    public $appId;
    public $isEnabled = 'y';
    /**
     * @var string 是否只读
     */
    public $readonly = 'n';
    /**
     * @var string 配置项类型
     */
    public $itemType = 'text';


    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        parent::initialize();
        $this->setSource("t_configuration");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ConfigurationModel[]|ConfigurationModel|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ConfigurationModel|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }
    public function validation()
    {
        $validator = new Validation();

        $validator->add('configName',new Validation\Validator\PresenceOf([
            'message'=>$this->translator->_('参数名称不能为空')
        ]));
        $validator->add('configValue',new Validation\Validator\PresenceOf([
            'message'=>$this->translator->_('参数值不能为空')
        ]));
        $validator->add('configKey',new Validation\Validator\PresenceOf([
            'message'=>$this->translator->_('参数Key不能为空')
        ]));
        $validator->add(['configKey','module'],new Validation\Validator\Uniqueness([
            'message'=>$this->translator->_('参数Key不能重复')
        ]));
        if($this->itemType==='booleanstring' && !in_array(strtolower($this->configValue),['y','n'])){
            $this->appendMessage(new Message($this->translator->_('参数值必须是Y或N')));
            return false;
        }
        if(!$this->id && !$this->appId){
            $this->appendMessage(new Message($this->translator->_('未指定应用，appId不能为空')));
            return false;
        }
        return $this->validate($validator);
    }
    /**
     * Independent Column Mapping.
     * Keys are the real names in the table and the values their names in the application
     *
     * @return array
     */
    public function columnMap()
    {
        return [
            'id' => 'id',
            'config_name' => 'configName',
            'config_key' => 'configKey',
            'config_value' => 'configValue',
            'module' => 'module',
            'remark' => 'remark',
            'readonly'=>'readonly',
            'item_type'=>'itemType',
            'app_id'=>'appId',
            'is_enabled'=>'isEnabled', // 'is_enabled' => 'isEnabled
            'created_time' => 'createdTime',
            'updated_time' => 'updatedTime'
        ];
    }
//    public function beforeDelete(){
//        throw new \Exception($this->translator->_('系统配置不允许删除'));
//        return false;
//    }
    public function beforeCreate(){
        $this->createdTime = time();
        $this->updatedTime = $this->createdTime;
        return true;
    }
    public function beforeUpdate(){
        $this->updatedTime = time();
    }
}
