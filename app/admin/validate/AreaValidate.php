<?php
namespace app\admin\validate;

use app\admin\model\RouteModel;
use think\Validate;

class AreaValidate extends Validate
{
    protected $rule = [
        'name'  => 'require',
    ];
    protected $message = [
        'name.require' => '分类名称不能为空',
    ];

    protected $scene = [

    ];


}