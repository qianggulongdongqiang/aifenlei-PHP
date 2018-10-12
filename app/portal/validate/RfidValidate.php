<?php
namespace app\portal\validate;

use think\Validate;

class RfidValidate extends Validate
{
    protected $rule = [
        'code' => 'require',
    ];
    protected $message = [
        'code.require' => '请填写Code！',
    ];


}