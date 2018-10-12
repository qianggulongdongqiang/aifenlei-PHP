<?php
namespace app\portal\model;
use think\Model;

class InOrderModel extends Model{
	public function InOrderDetail(){
		return $this->hasMany('InOrderDetailModel', 'order_id');
	}
    
}