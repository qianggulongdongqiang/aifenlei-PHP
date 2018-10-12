<?php
namespace app\portal\model;
use think\Model;

class InOrderDetailModel extends Model{
	public function InOrder(){	
		return $this->belongsTo('InOrderModel', 'id', 'order_id');
	}
    
}