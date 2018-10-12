<?php
namespace app\api\model;
use think\Model;

class RfidModel extends Model{
	public function user(){
		return $this->hasOne('userModel', 'id', 'user_id');
	}
	
	public function getCountByUserId($uid){
		return $this->where(['user_id'=>$uid])->count();
	}
    
}