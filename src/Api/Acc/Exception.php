<?php
namespace  Kuga\Api\Acc;
class Exception extends \Kuga\Core\Api\Exception{
    const E1 = 60001;
    const E2 = 60002;
    public static function getExceptionList(){
        return [
            self::E1=>'无聊',
            self::E2=>'你好'
        ];
    }
}