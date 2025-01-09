<?php
namespace Kuga\Api\Acc;
use Kuga\Core\Api\AbstractApi;
use Kuga\Module\Acc\Model\RoleModel;
use Kuga\Module\Acc\Model\RoleUserModel;
use Kuga\Module\Acc\Service\Acl;

abstract class BaseApi extends AbstractApi{
    protected $_accessTokenUserIdKey = 'console.uid';
    public function __construct($di = null)
    {
        parent::__construct($di);
        $di->getShared('translator')->addDirectory('acc',dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'Langs/acc');
    }
    public function beforeInvoke()
    {
        if ($this->_accessToken && $this->_accessTokenRequiredLevel > 0) {
            $di  = $this->_di;
            $uid = $this->_userMemberId;
            $list = RoleUserModel::find([
                'uid=:uid:',
                'bind'=>['uid'=>$uid]
            ]);
            if($list){
                $roleIds = [];
                foreach($list as $item){
                    $roleIds[] = $item->rid;
                }
                $roleList = RoleModel::find([
                    'id in ({roleIds:array}) and appId=:appId:',
                    'bind'=>['roleIds'=>$roleIds,'appId'=>$this->_appKey]
                ]);
                $roles = $roleList?$roleList->toArray():[];
            }else{
                $roles = [];
            }
            //$roles = $this->_getInfoFromAccessToken($this->_accessToken,'console.roles.'.$this->_appKey);
            $appKey= $this->_appKey;
            if(!$this->_di->has('aclService')){
                $this->_di->setShared('aclService',function() use($uid,$roles,$di,$appKey){
                    $acl = new Acl($di);
                    $acl->setUserId($uid);
                    $acl->setAppId($appKey);
                    $acl->setRoles($roles);
                    return $acl;
                });
            }
        }
    }
}