<?php
namespace app\portal\validate;

use think\Validate;

class GoodsValidate extends Validate
{
    protected $rule = [
        'name' => 'require',
        'point' => 'require',
    ];
    protected $message = [
        'name.require' => '请填写礼品名称！',
        'point.require' => '请填写消耗积分！',
    ];

    protected $scene = [
//        'add'  => ['user_login,user_pass,user_email'],
//        'edit' => ['user_login,user_email'],
    ];
}