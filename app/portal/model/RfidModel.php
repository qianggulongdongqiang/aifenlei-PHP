<?php
namespace app\portal\model;
use think\Model;

class RfidModel extends Model{
	public function user(){
		return $this->hasOne('userModel', 'id', 'user_id');
	}
    
}