<?php
namespace app\api\model;

use think\Model;
use tree\Tree;
use app\api\model\GoodsCateModel;
use app\api\model\RfidModel;
class PreOrderModel extends Model{
	public function user(){
		return $this->hasOne('userModel', 'id', 'buyer_id');
	}
	
	public function getList($condition, $page = 0, $limit = 10, $order = 'order_state asc, finished_time desc'){
		$list = $this->where($condition)->with(['user'=>function($query){$query->field('id,user_nickname as name,mobile');}])->order($order)->limit($page * $limit, $limit)->select();
		if($list){
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
			foreach($list as $k=>$v){
				$_json = json_decode($v['goods'], true);
				foreach($_json as $gk=>$gv){
					$_json[$gk]['pname'] = isset($_parent[$gv['id']]) ? $_parent[$gv['id']] : '';
				}
				$list[$k]['goods'] = $_json;
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
	
	public function cancel($order){
		return $this->where($order)->setField('order_state', 0);
	}
}