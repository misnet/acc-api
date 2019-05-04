<?php

namespace Kuga\Module\Acc;


class Exception extends \Kuga\Core\Api\Exception
{

    /**
     * 无有效数据（数据库数据不全等。。。）
     * @var int
     */
    const INVALID_PASSWORD = 89003;

    public static function getExceptionList()
    {
        $di = \Phalcon\DI::getDefault();
        $t = $di->getShared('translator');
        return [
            self::INVALID_PASSWORD => $t->_('账户密码错误')
        ];
    }
}