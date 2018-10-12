<?php
namespace app\portal\validate;

use think\Validate;

class GoodsCateCodeValidate extends Validate
{
    protected $rule = [
        'code' => 'require',
    ];
    protected $message = [
        'code.require' => '请填写Code！',
    ];


}