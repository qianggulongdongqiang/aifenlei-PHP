<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\ScoreModel;
use think\Validate;
use think\Db;

class ScoreController extends RestBaseController{
	
	/**
	 *	新建订单
	 */
	public function addOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			$custom_id = intval($data['user_id']);

			$goods = $data['goods'];
			$goods_list = [];
			$type = isset($data['type']) ? intval($data['type']) : '2';
			$captcha = isset($data['captcha']) ? trim($data['captcha']) : '';
			
			$userModel         	= new UserModel();
			
			if(!$user && $type == 2){
				$this->error(['code' => 2, 'msg' => '请先登录!']);
			}
			
			$custom = $userModel->getInfo(['user_type'=>2, 'id'=>$custom_id]);
			if(!$custom){
				$this->error(['code' => 0, 'msg' => '住户不存在!']);
			}
			
			//查询验证码
			$log = Db::name('sms_log')->where([
											'phone'=> $custom['mobile'],
											'code'=>$captcha,
											'type'=>2,
											'deleted' => 0
										])->find();
			if(!$log){
				$this->error(['code' => 0, 'msg' => '验证码不正确!']);
			}

			if(!$goods){
				$this->error(['code' => 0, 'msg' => '礼品错误!']);	
			}else{
				foreach($goods as $v){
					if(isset($goods_list[$v['id']])){
						$goods_list[$v['id']] += $v['num'];
					}else{
						$goods_list[$v['id']] = $v['num'];
					}
				}	
			}
			
			$points_number = 0;

			foreach($goods_list as $k=>$v){
				$goods = Db::name('goods')->where(['id'=>$k])->field('id,name,point')->find();
				$_goods_data[] = ['id'=>$goods['id'], 'name'=>$goods['name'], 'num'=>$v, 'point'=> $goods['point'] * $v];
				$points_number += $goods['point'] * $v;
			}

			//生成数据
			$order = [
						  'order_sn'=> createSn(),
						  'add_time'=>time(),
						  'points_number'=>$points_number,
						  'collecter_name'=>isset($user['user_nickname']) ? $user['user_nickname'] : '',
						  'collecter_id'=>isset($user['id']) ? $user['id'] : '',
						  'goods'=>json_encode($_goods_data),
						  'buyer_id' => $custom['id'],
						  'buyer_name' => $custom['user_nickname'],
						  'buyer_addr' => '',
						  'buyer_phone' => '',
						  'order_from' => $type,
					  ];
					  
			$insertID = Db::name('score_order')->insertID($order);
			
			if($insertID){
				$order['id'] = $insertID;
				$order['goods'] = json_decode($order['goods'], true);
				
				//给住户减少积分
				Db::name('user')->where(['id'=>$custom['id']])->setDec('score', $points_number);
				Db::name('user_score_log')->insert([
													'user_id'=>$custom['id'],
													'create_time'=>time(),
													'action'=>'score:'.$order['order_sn'],
													'score'=>'-' . $points_number
												]);
				
				$order['user_score'] = Db::name('user')->where(['id'=>$custom['id']])->value('score');
				Db::name('sms_log')->where(['id'=>$log['id']])->setField('deleted', '1');
				$this->success(['code' => 1, 'msg' => '积分兑换成功!'] , $order);
			}else{
				$this->error(['code' => 0, 'msg' => '操作失败!']);	
			}
		}
	}
	
	/**
	 *	消费积分
	 */
	public function consume(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			$custom_id = intval($data['user_id']);

			$type = isset($data['type']) ? intval($data['type']) : '2';
			$captcha = isset($data['captcha']) ? trim($data['captcha']) : '';
			$ctype = isset($data['ctype']) ? trim($data['ctype']) : '';
			$point = isset($data['point']) ? intval($data['point']) : '0';

			$userModel         	= new UserModel();
			
			if(!$user && $type == 2){
				$this->error(['code' => 2, 'msg' => '请先登录!']);
			}
			
			$custom = $userModel->getInfo(['user_type'=>2, 'id'=>$custom_id]);
			if(!$custom){
				$this->error(['code' => 0, 'msg' => '住户不存在!']);
			}
			
			//验证积分
			if($custom['score'] < $point){
				$this->error(['code' => 0, 'msg' => '可用积分不足!']);
			}
			
			//查询验证码
			$log = Db::name('sms_log')->where([
											'phone'=> $custom['mobile'],
											'code'=>$captcha,
											'type'=>2,
											'deleted' => 0
										])->find();
			
			if(!$log){
				$this->error(['code' => 0, 'msg' => '验证码不正确!']);
			}

				
			//住户减少积分
			Db::name('user')->where(['id'=>$custom['id']])->setDec('score', $point);
			Db::name('user_score_log')->insert([
												'user_id'=>$custom['id'],
												'create_time'=>time(),
												'action'=>'consume:'.$ctype,
												'score'=>'-' . $point
											]);
			$out = [
						'id' => $custom['id'],
						'name' => $custom['user_nickname'],
						'mobile' => $custom['mobile'],
						'score' => Db::name('user')->where(['id'=>$custom['id']])->value('score')
					];
			Db::name('sms_log')->where(['id'=>$log['id']])->setField('deleted', '1');
			$this->success(['code' => 1, 'msg' => '积分兑换成功!'], $out);

		}
	}
	
	/**
	 *	发送验证码
	 */
	public function sendSmsCode(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$userModel = new UserModel();
			
			$custom_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
			$mobile = isset($data['mobile']) ? trim($data['mobile']) : '';
			
			if($custom_id){
				$custom_condition['id'] = $custom_id;	
			}elseif($mobile){
				$custom_condition['mobile'] = $mobile;	
			}else{
				$this->error(['code' => 0, 'msg' => '请填写住户信息!']);
			}
			
			$custom_condition['user_type'] = 2;
			
			$custom = $userModel->getInfo($custom_condition);
			if(!$custom){
				$this->error(['code' => 0, 'msg' => '住户不存在!']);
			}
			
			if($custom['mobile'] == ''){
				$this->error(['code' => 0, 'msg' => '请先绑定手机号!']);
			}
			
			
			//发送短信
			import('sms.Sms', EXTEND_PATH);
			$sms = new \Sms();
			$captcha = rand(100000, 999999);
			$content = '【爱分类爱回收】您正在使用积分消费，请正确输入验证码。验证码为：' .$captcha. '，请确认为本人交易。';
			$return = $sms->send($content, $custom['mobile']);
			
			//删除数据
			Db::name('sms_log')->where([
											'phone'=> $custom['mobile'],
											'type'=>2
										])->setField('deleted', 1);
			
			//写入数据库
			Db::name('sms_log')->insert([
											'phone'=> $custom['mobile'],
											'content'=>$content,
											'code'=>$captcha,
											'add_time'=>time(),
											'type'=>2,
											'ref' => json_encode($return)
										]);
			
			if($return['code'] == 1){
				$data = [
							'id' => $custom['id'],
							'name' => $custom['user_nickname'],
							'mobile'=> $custom['mobile'],
							'score' => $custom['score']
						];
				
				$this->success(['code' => 1, 'msg' => $return['msg']], $data);	
			}else{
				$this->error(['code' => 0, 'msg' => $return['msg']]);		
			}

		}
	}
}
