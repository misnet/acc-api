<?php
namespace Kuga\Module\Acc\Model;
use Kuga\Core\Base\AbstractModel;
use Kuga\Core\GlobalVar;
use Phalcon\Validation\Validator\PresenceOf;
use Qing\Lib\Utils;

/**
 * App Model
 * Class AppModel
 * @package Kuga\Module\Acc\Model
 */
class AppModel extends  AbstractModel{
    /**
     * APP ID
     * @var int
     */
    public $id;
    /**
     * APP Name
     * @var String
     */
    public $name;
    /**
     * App Secret
     * @var String
     */
    public $secret;
    /**
     * 禁用，0启用，1禁用
     * @var int
     */
    public $disabled = 0;
    /**
     * 摘要描述
     * @var String
     */
    public $shortDesc;
    public function getSource()
    {
        return 't_apps';
    }

    /**
     * 生成APPSecret
     * @return string
     */
    public function generateSecret(){
        $pattern = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ1234567890';
        $string = '';
        for($i=0;$i<50;$i++) {
            $string .= $pattern{mt_rand(0,35)};
        }
        return md5(substr($string,8,32));
    }
    public function validate(\Phalcon\ValidationInterface $validator)
    {
        $validator->add('name',new PresenceOf([
            'model'=>$this,
            'message'=>$this->translator->_('请输入应用名称')
        ]));
        return $this->validate($validator);
    }
    public function beforeSave(){
        $this->shortDesc = Utils::shortWrite($this->shortDesc,200);
        return true;
    }
    public function columnMap()
    {
        return [
            'id'=>'id',
            'name'=>'name',
            'secret'=>'secret',
            'disabled'=>'disabled',
            'short_desc'=>'shortDesc'
        ];
    }
    /**
     * 刷新缓存
     */
    public  function freshCache(){
        $cacheId = GlobalVar::APPLIST_CACHE_ID;
        $cache   = $this->getDI()->getShared('cache');
        $cache->delete($cacheId);
        //访问APP数据库
        $rows = self::find([
            'disabled=0'
        ]);
        $apiKeyList = $rows->toArray();
        $apiKeys    = [];
        foreach ($apiKeyList as $keyItem) {
            $apiKeys[$keyItem['id']]['secret'] = $keyItem['secret'];
        }
        $cache->set($cacheId,$apiKeys);
    }
    public function afterSave(){
        $this->freshCache();
    }
    public function afterDelete(){
        $this->freshCache();
    }
}