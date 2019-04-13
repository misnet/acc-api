<?php
/**
 * System Parameter Setting Model
 * @author Donny
 */
namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Kuga\Core\GlobalVar;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation\Validator\Regex;

class SysParamsModel extends AbstractModel {
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
	public $description;
	
	/**
	 *
	 * @var integer
	 */
	public $value_type;

	
	/**
	 *
	 * @var string
	 */
	public $current_value;
	/**
	 * 
	 * @var unknown
	 */
	public $keyname;
	const KEYTYPE_STRING = 1;
	const KEYTYPE_BOOLEAN= 2;
	const KEYTYPE_DATE   = 3;
	const KEYTYPE_NUMBER = 4;
	public function getValueTypeList(){
	    return array('1'=>'字符串','2'=>'布尔型','3'=>'日期','4'=>'数字');
	}
	/**
	 * Independent Column Mapping.
	 */
	public function columnMap() {
		return array (
				'id' => 'id',
				'name' => 'name',
				'description' => 'description',
				'value_type' => 'value_type',
				//'value_list' => 'value_list',
				//'default_value' => 'default_value',
				'current_value' => 'current_value',
				'keyname' =>'keyname'
		);
	}
	public function validation(){
	    $validator = new Validation();
	    $validator->add('name', new PresenceOf([
	        'model'=>$this,
	        'message'=>$this->translator->_('配置名称不能为空')
	    ]));
	    $validator->add('keyname', new PresenceOf([
	        'model'=>$this,
	        'message'=>$this->translator->_('配置KEY名称不能为空')
	    ]));
	    $validator->add('description', new PresenceOf([
	        'model'=>$this,
	        'message'=>$this->translator->_('配置描述不能为空')
	    ]));
	    $validator->add('keyname', new Uniqueness([
	        'model'=>$this,
	        'message'=>$this->translator->_('KEY已存在')
	    ]));
	    $validator->add('name', new Uniqueness([
	        'model'=>$this,
	        'message'=>$this->translator->_('配置名称已存在')
	    ]));
	    $validator->add('value_type', new Regex([
	        'model'=>$this,
	        'pattern' => '/^[1-4]$/',
			'message' =>$this->translator->_('值类型不正确')
	    ]));
	    return $this->validate($validator);
	}
	public function getSource() {
		return 't_sysparams';
	}
	public function beforeSave(){
	    $this->keyname = trim($this->keyname);
	    return true;
	}
	public function getDefaultValueByKeyName($name){
		$rows = $this->find(array(
				'conditions'=>'keyname=?1',
				'bind'=>array(1=>$name)
		));
		if($rows){
			$temp = $rows->toArray();
			return $temp[0]["default_value"];
		}else{
			return "";
		}
	}
	public function getCurrentValueByKeyName($name){
		$rows = $this->find(array(
				'conditions'=>'keyname=?1',
				'bind'=>array(1=>$name)
		));
		if($rows){
			$temp = $rows->toArray();
			return $temp[0]["current_value"];
		}else{
			return "";
		}
	}
	/**
	 * 
	 * @var \Kuga\Module\Acc\Model\SysParamsModel
	 */
	private static $instance;
	/**
	 * 单实例
	 * @return \Kuga\Module\Acc\Model\SysParamsModel
	 */
	public static function getInstance(){
	    if(self::$instance instanceof self){
	        return self::$instance;
	    }else{
	        return new self();
	    }
	}
    /**
     * 取得参数对应的值
     * @param unknown $key
     * @return boolean|number|NULL
     */
	public  function get($key){
	    $cacheKey = 'data:sys:'.$key;
	    $cacheEngine = $this->getDI()->get('cache');
        $cacheData   = $cacheEngine->get($cacheKey);
        if(!$cacheData) {
            $row = $this->findFirst(array(
                'conditions' => 'keyname=:key:',
                'bind' => array('key' => $key),
                "cache" => ["lifetime" => 600, "key" => "data:setting:" . $key]
            ));
            if ($row) {
                $row = $row->toArray();
                //$value = !is_null($row['current_value'])?$row['current_value']:$row['default_value'];
                $value = $row['current_value'];
                switch ($row['value_type']) {
                    case self::KEYTYPE_BOOLEAN:
                        $value = trim(strtolower($value)) == 'false' ? false : true;
                        break;
                    case self::KEYTYPE_NUMBER:
                        if (stripos($value, '.') === false)
                            $value = intval($value);
                        else
                            $value = doubleval($value);
                        break;
                    case self::KEYTYPE_DATE:
                    case self::KEYTYPE_STRING:
                    default:
                        break;
                }
                $cacheData =  $value;
            } else {
                $cacheData = null;
            }
            $cacheEngine->set($cacheKey,$cacheData,GlobalVar::DATA_CACHE_LIFETIME);
            return $cacheData;
        }else{
            return $cacheData;
        }
	}	
}
