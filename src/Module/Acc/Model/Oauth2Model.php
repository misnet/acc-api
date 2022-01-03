<?php
/**
 * System Oauth2Model Model
 * @author Roy
 */

namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\Uniqueness;

class Oauth2Model extends AbstractModel{

    /**
     * oauthId
     * @var string
     */
    public $oauthId;
    /**
     * 应用
     * @var string
     */
    public $oauthApp;

    /**
     * email
     * @var string
     */
    public $email;
    public $mobile;

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
    public $id;
    public $userId;
    public $avatarUrl;
    public function initialize()
    {
        parent::initialize();
        $this->setSource('t_oauth');
    }
    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return array(
            'id' => 'id',
            'oauth_id' => 'oauthId',
            'oauth_app'=>'oauthApp',
            'user_id'=>'userId',
            'email' => 'email',
            'name' => 'name',
            'last_login_time' => 'lastLoginTime',
            'mobile'=> 'mobile',
            'avatar_url'=>'avatarUrl'
        );
    }


    /**
     * Validations and business logic
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add(
            ['oauthApp','oauthId'], new Uniqueness(
                ['model' => $this, 'message' => $this->translator->_('应用已绑定授权')]
            )
        );
        return $this->validate($validator);
    }
}
