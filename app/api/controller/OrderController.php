<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\OrderModel;
use app\api\model\WxModel;
use think\Validate;
use think\Db;

class OrderController extends RestBaseController{
	
	/**
	 *	新建订单
	 */
	public function addOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			$custom_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
			$mobile = isset($data['mobile']) ? trim($data['mobile']) : '';

			$goods = $data['goods'];
			$goods_list = [];
			$type = isset($data['type']) ? intval($data['type']) : '2';
			$sn = isset($data['sn']) ? trim($data['sn']) : '';
			$code = isset($data['code']) ? trim($data['code']) : '';
			
			$userModel         	= new UserModel();
			$orderModel         = new OrderModel();
			$goodsCateModel     = new GoodsCateModel();
			
			if(!$user && $type == 2){
				$this->error(['code' => 2, 'msg' => '请先登录!']);
			}
			
			$custom_condition['user_type'] = 2;
			if($custom_id){
				$custom_condition['id'] = $custom_id;	
			}elseif($mobile){
				$custom_condition['mobile'] = $mobile;	
			}else{
				if($type != 4){
					$this->error(['code' => 0, 'msg' => '请填写住户信息!']);
				}else{
					$custom_condition['id'] = 0;
				}
			}
			$custom = $userModel->getInfo($custom_condition);
			if(!$custom){
				if($type != 4){
					$this->error(['code' => 0, 'msg' => '住户不存在!']);
				}
			}
			
			if(!$goods){
				$this->error(['code' => 0, 'msg' => '品类信息错误!']);	
			}else{
				foreach($goods as $v){
					$v['id'] = intval($v['id']);
					
					if(!$v['id']){
						$this->error(['code' => 0, 'msg' => '品类信息错误!']);
					}
					
					if(isset($goods_list[$v['id']])){
						$goods_list[$v['id']] += $v['num'];
					}else{
						$goods_list[$v['id']] = $v['num'];
					}
				}	
			}
			
			$points_number = 0;
			
			foreach($goods_list as $k=>$v){
				$goods =	$goodsCateModel->getInfo(['id'=>$k]);
				$_goods_data[] = ['id'=>$goods['id'], 'name'=>$goods['name'], 'num'=>round($v , 2), 'p'=> $goods['purchasing_point'], 'point'=> round($goods['purchasing_point'] * $v), 'unit'=>$goods['unit'], 'unit_name'=>$goods['unit_name']];
				$points_number += round($goods['purchasing_point'] * $v);
			}
			
			$addition = empty($sn) ? 0 : Db::name('pre_order')->where(['order_sn'=>$sn])->value('addition');
			
			//生成数据
			$order = [
						  'order_sn'=> empty($sn) ? createSn() : $sn,
						  'add_time'=>time(),
						  'finished_time'=>time(),
						  'points_number'=>$points_number,
						  'collecter_name'=>isset($user['user_nickname']) ? $user['user_nickname'] : '',
						  'collecter_id'=>isset($user['id']) ? $user['id'] : '',
						  'goods'=>json_encode($_goods_data),
						  'buyer_id' => isset($custom['id']) ? $custom['id'] : 0,
						  'buyer_name' => isset($custom['user_nickname']) ? $custom['user_nickname'] : '',
						  'buyer_addr' => isset($custom['user_addr']) ? $custom['user_addr'] : '',
						  'buyer_phone' => isset($custom['mobile']) ? $custom['mobile'] : '',
						  'order_from' => $type,
						  'addition' => $addition,
						  'code' => $code,
						  'mid' => isset($user['id']) ? $user['id'] : 0,
					  ];
					  
			//订单排重
			if(Db::name('order')->where(['order_sn'=>$order['order_sn']])->count() > 0){
				$this->error(['code' => 0, 'msg' => '创建订单失败，订单已存在!']);
			}
			
			//查询预约单状态
			if($sn){
				if(Db::name('pre_order')->where(['order_sn'=>$sn, 'order_state'=>10])->count() == 0){
					$this->error(['code' => 0, 'msg' => '预约单不存在！']);
				}	
			}
					  
			$insertID = Db::name('order')->insertGetId($order);
			
			if($insertID){
				//插入order_item
				foreach($_goods_data as $k=>$v){
					$_goods_data[$k]['oid'] = $insertID;
				}
				Db::name('order_item')->insertAll($_goods_data);
				
				
				$order['id'] = $insertID;
				$order['goods'] = json_decode($order['goods'], true);
				$order['user_score'] = 0;
				
				if($custom){
					//给住户增加积分
					Db::name('user')->where(['id'=>$custom['id']])->setInc('score', $points_number);
					Db::name('user_score_log')->insert([
														'user_id'=>$custom['id'],
														'create_time'=>time(),
														'action'=>'order:'.$order['order_sn'],
														'score'=>$points_number
													]);
					$order['user_score'] = Db::name('user')->where(['id'=>$custom['id']])->value('score');
					
					if($custom['openid']){
						//推送公众号消息
						$wxModel = new WxModel();
						$msg_goods = '';
						foreach($_goods_data as $v){
							$msg_goods .= $v['name'] . ' ';
						}
						
						$msg = '{
							   "touser":"' .$custom['openid']. '",
							   "template_id":"0ERWsoaEF9V2gs2Lkgx0tHmPo2eX3myR07IKvTAhZ-w", 
							   "url":"' . cmf_get_domain() . '/#/points",					   
							   "data":{
									   "first": {
										   "value":""
									   },
									   "keyword1":{
										   "value":"' . date('Y年m月d日 H:i:s') . '"
									   },
									   "keyword2": {
										   "value":"' . $points_number . '"
									   },
									   "keyword3": {
										   "value":"' . $msg_goods . '"
									   }, 
									   "remark":{
										   "value":"点击详情进入积分提现服务",
										   "color":"#999"
									   }
							   }
						   }';
						   
						   $re = $wxModel->sendMsg($msg);
					}
				}
				
				if($sn){
					Db::name('pre_order')->where(['order_sn'=>$sn])->setField('order_state', 100);	
				}
				
				//来自交易柜、回收机的订单，直接入库
				if(in_array($type, [3,4])){
					$data = [];			//理论数组
					$act_data = [];		//实际数组
					$order_update = [];	//订单更新数据
					$total = 0;			//理论总数
					$act_total = 0;		//实际总数
					
					//品类列表
					$goods = [];
					foreach(DB::name('goods_cate')->select() as $gl){
						$goods[$gl['id']] = $gl;
					}
					
					//处理数据
					foreach($_goods_data as $g){
						$data[$g['id']] = [
									'goods_id' => $g['id'],
									'num' => round($g['num'] , 2),
									'type' => 1,
									'price' => $goods[$g['id']]['settlement_in_price'],
									];
						$act_data[$g['id']] = [
									'goods_id' => $g['id'],
									'num' => round($g['num'] , 2),
									'type' => 2,
									'price' => $goods[$g['id']]['settlement_in_price'],
									];
									
						$total += round($g['num'] , 2) * $data[$g['id']]['price'];
					}
					
					$order_update[] = [
										'order_id' => $insertID,
										'is_stock' => 1
									];
					
					//合并
					$data = array_merge($data, $act_data);
		
					$d['user_id'] = isset($user['id']) ? $user['id'] : 0;
					$d['start_time'] = time();
					$d['end_time'] = time();
					$d['amount'] = $total;
					$d['actual_amount'] = $total;
					
					$orderModel = new OrderModel();
					$goodsCateModel = new GoodsCateModel();
					
					if($insertID = DB::name('in_order')->insertGetId($d)){
						foreach($data as $k=>$v){
							$data[$k]['order_id'] = $insertID; 
						}
						DB::name('in_order_detail')->insertAll($data);
						
						//更新库存
						foreach($act_data as $v){
							$goodsCateModel->where(['id'=>$v['goods_id']])->setInc('stock', $v['num']);
						}
						
						//更新订单状态
						$orderModel->saveAll($order_update);	
					}
					
				}
				
				$this->success(['code' => 1, 'msg' => '订单新建成功!'] , $order);
			}else{
				$this->error(['code' => 0, 'msg' => '操作失败!']);	
			}
		}
	}
}
