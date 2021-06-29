<?php
/**
 * System Oauth2Model Model
 * @author Roy
 */

namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Mvc\Model\Relation;

class Oauth2Model extends AbstractModel
{
    /**
     * User id
     * @var integer
     */
    public $uid;

    /**
     * oauthId
     * @var string
     */
    public $oauthId;

    /**
     * email
     * @var string
     */
    public $email;

    /**
     *
     * @var string
     */
    public $name;


    /**
     *
     * @var integer
     */
    public $lastLoginTime;

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return array(
            'id' => 'id',
            'oauth_id' => 'oauthId',
            'email' => 'email',
            'name' => 'name',
            'last_login_time' => 'lastLoginTime'

        );
    }

    /**
     * 所属门店名称
     * @var string
     */
    public $storeName;

    /**
     * Validations and business logic
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add('oauthId', new UniquenessValidator([
            'model' => $this,
            'message' => $this->translator->_('oauthId已存在')
        ]));

        if ($this->email) {
            $validator->add('email', new EmailValidator([
                'model' => $this,
                'message' => $this->translator->_('Email格式错误')
            ]));

            $validator->add('email', new UniquenessValidator([
                'model' => $this,
                'message' => $this->translator->_('Email已存在')
            ]));
        }
        return $this->validate($validator);
    }

    /**
     * 创建或者更新用户
     * @param $data
     * Created on:2021/6/29 17:18
     * Create by:Roy
     */
    public function creatOrUpdateUser($data)
    {
        //判断是否存在该用户，存在则更新登录时间，反之则创建用户
        $rows = $this->find(array('conditions'=>'oauthId=?oauthId','bind'=>array('oauthId'=>$data['oauthId'])));

        if($this->isExistUser($data['oauthId'])){
            if($rows){
                $rows->lastLoginTime = time();
                return $rows->update();
            }
        }else{
            $rows->oauthId = $data['oauthId'];
            $rows->name = $data['name'];
            $rows->email = $data['email'];
            $rows->lastLoginTime = time();
            return $rows->create();
        }

    }

    public function isExistUser($oauthId)
    {
        $rows = $this->find(array('conditions'=>'oauthId=?oauthId','bind'=>array('oauthId'=>$oauthId)));
        return empty($rows) ? false : $rows;
    }

}
