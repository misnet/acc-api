<?php
namespace Kuga\Api\Acc;
use Kuga\Core\Api\AbstractApi;
abstract class BaseApi extends AbstractApi{
    protected $_accessTokenUserIdKey = 'console.uid';
    public function __construct($di = null)
    {
        parent::__construct($di);
        $di->getShared('translator')->addDirectory('acc',dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'Langs/acc');
    }
}