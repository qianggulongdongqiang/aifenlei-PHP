<?php
namespace app\portal\model;

use app\admin\model\RouteModel;
use think\Model;
use tree\Tree;

class OrderModel extends Model{
	public function createSn(){
		$sn = '';
		for ($i = 1; $i <= 4; $i++) {
			$sn .= chr(rand(65, 90));
		}
		$sn .= time();
		
		return $sn;
	} 
}