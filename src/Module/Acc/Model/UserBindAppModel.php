<?php
namespace Kuga\Module\Acc\Model;
use Kuga\Core\Base\AbstractModel;

/**
 * 用户与应用的绑定关系
 * Class UserBindAppModel
 * @package Kuga\Module\Acc\Model
 */
class UserBindAppModel extends AbstractModel{
    public $id;
    public $uid;
    public $appId;
    public function getSource()
    {
        return 't_user_bind_app';
    }
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('uid','UserModel','uid',[
            'namespace'=>'Kuga\Module\Acc\Model'
        ]);
    }
    public function columnMap()
    {
        return  [
            'id'=>'id',
            'uid'=>'uid',
            'app_id'=>'appId'
        ];
    }
}