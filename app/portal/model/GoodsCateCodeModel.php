<?php
namespace app\portal\model;
use think\Model;

class GoodsCateCodeModel extends Model{
	public function cate(){
		return $this->hasOne('GoodsCateModel', 'id', 'cid');
	}
    
}