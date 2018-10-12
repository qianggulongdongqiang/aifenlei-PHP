<?php
namespace app\api\model;
use think\Model;
use tree\Tree;

class AreaModel extends Model{

	public function getInfoByPid($pid = 0, $field = 'id,name', $order='list_order'){
		$condition['parent_id'] = $pid;
		$condition['delete_time'] = 0;
		$condition['visit'] = 1;
		$data = $this->where($condition)->field($field)->order($order)->select()->toArray();
		if($data){
			foreach($data as $k=>$g){
				$data[$k]['items'] = $this->getInfoByPid($g['id']);
				if(!$data[$k]['items']) unset($data[$k]['items']);
			}
		}
		return $data;
	}
	
	public function getAllList($field = 'id,name', $order='list_order'){
		$condition['parent_id'] = 0;
		$condition['delete_time'] = 0;
		$condition['visit'] = 1;
		$data = $this->where($condition)->field($field)->order($order)->select()->toArray();
		if($data){
			foreach($data as $k=>$g){
				$data[$k]['items'] = $this->getInfoByPid($g['id']);
			}
		}
		return $data;
	}
}