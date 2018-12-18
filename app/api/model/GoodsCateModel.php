<?php
namespace app\api\model;
use think\Model;
use tree\Tree;

class GoodsCateModel extends Model{

	public function getInfoByPid($pid = 0, $field = 'id,name,unit_name,unit,purchasing_price,purchasing_point,img_1,img_2', $order='list_order'){
		$condition['parent_id'] = $pid;
		$condition['delete_time'] = 0;
		if($pid != 0)	$condition['in_status'] = 1;
		$data = $this->where($condition)->field($field)->order($order)->select()->toArray();
		if($data){
			foreach($data as $k=>$g){
				if($g['img_1']){
					$data[$k]['img_1'] = 	cmf_get_image_preview_url($g['img_1']);
				}
				if($g['img_2']){
					$data[$k]['img_2'] = 	cmf_get_image_preview_url($g['img_2']);
				}	
			}
		}
		return $data;
	}
	
	public function getAllList($condition, $field = 'id,name,unit_name,unit,purchasing_price,purchasing_point,img_1,img_2,op_type', $order='list_order'){
		$condition['parent_id'] = 0;
		$condition['delete_time'] = 0;
		$condition['is_machine'] = 0;
		$data = $this->where($condition)->field($field)->order($order)->select()->toArray();
		if($data){
			foreach($data as $k=>$g){
				if($g['img_1']){
					$data[$k]['img_1'] = 	cmf_get_image_preview_url($g['img_1']);
				}
				if($g['img_2']){
					$data[$k]['img_2'] = 	cmf_get_image_preview_url($g['img_2']);
				}

				$data[$k]['items'] = $this->getInfoByPid($g['id']);
			}
		}
		return $data;
	}
	
	public function getInfo($condition = array(), $field = '*'){
		$condition['in_status'] = 1;
		$condition['delete_time'] = 0;
		return $this->where($condition)->field($field)->find();
	}
	
	public function getList($condition = array(), $field = '*'){
		$condition['delete_time'] = 0;
		return $this->where($condition)->field($field)->select();
	}
	
	public function getSecList($condition = array(), $field = 'id,name,unit,purchasing_price,purchasing_point,img_1,img_2', $order='list_order'){
		$condition['in_status'] = 1;
		$condition['parent_id'] = ['neq', 0];
		$condition['delete_time'] = 0;
		$return = [];

		$secGoods = $this->where($condition)->field($field)->order($order)->select();	
		if($secGoods){
			foreach($secGoods as $k=>$g){
				if($g['img_1']){
					$g['img_1'] = 	cmf_get_image_preview_url($g['img_1']);
				}
				if($g['img_2']){
					$g['img_2'] = 	cmf_get_image_preview_url($g['img_2']);
				}	
			}
			array_push($return, $secGoods);	
		}

		return $return;
	}
}