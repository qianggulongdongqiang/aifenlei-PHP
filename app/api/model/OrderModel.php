<?php
namespace app\api\model;

use think\Model;
use tree\Tree;
use app\api\model\GoodsCateModel;
use app\api\model\RfidModel;
class OrderModel extends Model{
	public function createSn(){
		$sn = '';
		for ($i = 1; $i <= 4; $i++) {
			$sn .= chr(rand(65, 90));
		}
		$sn .= time();
		
		return $sn;
	}
	
	public function user(){
		return $this->hasOne('userModel', 'id', 'buyer_id');
	} 
	
	public function getList($condition, $page = 0, $limit = 10, $order= "add_time desc"){
		$list = $this->where($condition)->with(['user'=>function($query){$query->field('id,user_nickname as name,mobile');}])->limit($page * $limit, $limit)->order($order)->select()->toArray();
		if($list){
			$goodsCateModel = new GoodsCateModel();
			$goods_cate = $goodsCateModel->getList();
			$rfidModel = new RfidModel();
			foreach($goods_cate as $v){
				$_list[$v['id']] = $v;
			}
			foreach($_list as $k=>$v){
				if($v['parent_id'] != 0) 
				$_parent[$v['id']] = $_list[$v['parent_id']]['name'];	
			}
			foreach($list as $k=>$v){
				$_json = json_decode($v['goods'], true);
				if($_json){
					foreach($_json as $gk=>$gv){
						$_json[$gk]['pname'] = isset($_parent[$gv['id']]) ? $_parent[$gv['id']] : '';
					}
				}
				$list[$k]['goods'] = $_json;
				
				if(!$v['user']){
					$list[$k]['user']['id'] = 0;
					$list[$k]['user']['name'] = '';
					$list[$k]['user']['mobile'] = '';
				}

				$list[$k]['user']['rfid_count'] = $rfidModel->getCountByUserId($v['user']['id']);
			}	
		}
		return $list;
	}
	
	public function getInfo($condition){
		$data = $this->where($condition)->with(['user'=>function($query){$query->field('id,user_nickname as name,mobile');}])->find();
		if($data){
			$goodsCateModel = new GoodsCateModel();
			$rfidModel = new RfidModel();
			$goods_cate = $goodsCateModel->getList();
			foreach($goods_cate as $v){
				$_list[$v['id']] = $v;
			}
			foreach($_list as $k=>$v){
				if($v['parent_id'] != 0) 
				$_parent[$v['id']] = $_list[$v['parent_id']]['name'];	
			}
			
			$_json = json_decode($data['goods'], true);
			foreach($_json as $gk=>$gv){
				$_json[$gk]['pname'] = isset($_parent[$gv['id']]) ? $_parent[$gv['id']] : '';
			}
			$data['goods'] = $_json;
			$data['user']['rfid_count'] = $rfidModel->getCountByUserId($data['user']['id']);
		}
		return $data;
	}
	
	public function getCount($condition){
		$count = $this->where($condition)->count();
		return $count;
	}
	
	public function getPoint($condition){
		$count = $this->where($condition)->sum('points_number');
		return $count;
	}
}