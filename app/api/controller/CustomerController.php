<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\OrderModel;
use app\api\model\PreOrderModel;
use app\api\model\WxModel;
use think\Validate;
use think\Db;
use app\api\model\UserMsgModel;

class CustomerController extends RestBaseController{
	
	
	public function getToken(){
		$wxModel 	= new WxModel();
		dump($wxModel->returnToken());
	}
	
	
	/**
	 *	获取用户信息
	 */
	public function get_user_info(){
		$wxModel 	= new WxModel();
		$userModel  = new UserModel();
		$data = $this->request->get();
		if(isset($data['code'])){
			$info = $wxModel->getWxUserInfo($data['code']);
			
			//通过openid获取用户信息
			if(isset($info['openid'])){
				$user = $userModel->get_user_info(['openid'=>$info['openid']]);
				
				//用户不存在，注册
				if(!$user){
					$user = $userModel->addCustomerByOpenID($info['openid']);
				}
				
				$token = cmf_generate_user_token($user['id'], 'mobile');
				
				$return = [
							'username' => $user['user_nickname'],
							'sex' => $user['sex'],
							'birthday' => $user['birthday'],
							'score' => $user['score'],
							'mobile' => $user['mobile'],
							'more' => $user['more'],
							'token'	=>$token,
						];
				header('Location:' . cmf_get_domain() . '/index.html?token=' . $token);
				exit();
				$this->success('' ,$return);
			}else{
				$this->error(['code' => 0, 'msg' => '获取用户信息失败!']);
			}
		}else{
			$wxModel->getCode();
		}
	}
	
	public function isSub(){
		$wxModel = new WxModel();
		dump($wxModel->isSubscribe('o3yOR5xAAo64ccJMHYrHLXXuylMw'));
	}
	
	/**
	 *	获取用户信息
	 */
	public function get_user_info_by_token(){
		if ($this->request->isPost()) {
		
			$user = $this->user;
			$wxModel = new WxModel();
			
			if($user){
			
				$more = json_decode($user['more'], true);
				
				if(!isset($more['area_id'])){
					$more = [];
				}
				
				if($user['openid']){
					$subscribe = $wxModel->isSubscribe($user['openid']);
				}else{
					$subscribe = 0;
				}
			
				$return = [
								'username' => $user['user_nickname'],
								'sex' => $user['sex'],
								'birthday' => $user['birthday'],
								'score' => $user['score'],
								'mobile' => $user['mobile'],
								'more' => $more,
								'member_type'=>$user['member_type'],
								'subscribe'=>$subscribe
							];
	
				$this->success('' ,$return);
			}else{
				$this->error(['code' => 2, 'msg' => '无效token！']);
			}
		}
	}
	
	/**
	 *	登录
	 */
	public function login(){
		if ($this->request->isPost()) {
            $validate = new Validate([
                'username' => 'require',
                'password' => 'require|min:6|max:32',
				'mid' => 'require',
            ]);
            $validate->message([
                'username.require' => '用户名不能为空',
                'password.require' => '密码不能为空',
				'mid.require' => '机器码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
            ]);

            $data = $this->request->put();
			
			
            if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			$machine = Db::name('user')->where(['id'=>intval($data['mid']), 'user_type'=>4, 'code'=>['neq', '']])->find();
			if(!$machine){
				$this->error(['code' => 0, 'msg' => '回收机信息错误!']);
			}

            $userModel         	= new UserModel();
            $user['user_pass'] 	= $data['password'];
			$user['user_type'] 	= 2;
			$user['user_login'] = $data['username'];
			$user['device_type']= 'mobile';
			$log                = $userModel->doApi($user);
			
			if($log){
				//推送消息到回收机
				$userMsgModel = new UserMsgModel();
				$re = $userMsgModel->push($machine['code'], '扫码登录', $log, 'm');
				
				if($re['http_code'] != 200){
					$this->error(['code' => 0, 'msg' => '登录失败!']);
				}
				
				
				$this->success(['code' => 1, 'msg' => '登录成功!'] ,$log);
			}else{
				$this->error(['code' => 0, 'msg' => '登录失败!']);
			}
		}
	}
	
	/**
	 *	预约下单
	 */
	public function addPreOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$goods = isset($data['goods']) ? $data['goods'] : '';
			$d['buyer_name'] = isset($data['name']) ? trim($data['name']) : '';
			$d['buyer_phone'] = isset($data['phone']) ? trim($data['phone']) : '';
			$d['buyer_addr'] = isset($data['addr']) ? trim($data['addr']) : '';
			$d['finished_time'] = isset($data['time']) ? trim($data['time']) : '';
			$d['addition'] = isset($data['addition']) ? intval($data['addition']) : 0;
			$d['area_id'] = isset($data['area_id']) ? intval($data['area_id']) : 0;
			
			$validate = new Validate([
                'buyer_name' => 'require',
                'buyer_phone' => 'require|mobile',
				'buyer_addr' => 'require',
				'finished_time' => 'require',
            ]);
            $validate->message([
                'buyer_name.require' => '姓名不能为空',
                'buyer_phone.require' => '手机号不能为空',
                'buyer_phone.mobile'     => '手机号码格式不正确',
                'buyer_addr.require'     => '地址不能为空',
				'finished_time.require'     => '预约时间不能为空',
            ]);
			
			if (!$validate->check($d)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			/* if(strtotime("+1 day", str_replace(['年','月','日'], '-', substr($d['finished_time'],0 ,strpos($d['finished_time'], '日')))) < time()){
				$this->error(['code' => 0, 'msg' => '上门时间不正确！']);
			} */
			
			if($d['area_id'] == 0){
				$this->error(['code' => 0, 'msg' => '小区ID不能为空']);
			}
			
			if($user){
				$d['buyer_id'] = $user['id'];
			}
			
			if(!$goods){
				$this->error(['code' => 0, 'msg' => '品类信息错误!']);	
			}
			
			$goodsCateModel = new GoodsCateModel();
			$goods_list = $goodsCateModel->getList(['in_status'=>1,"children"=>0], 'id,name');
			if($goods_list){
				foreach($goods_list as $v){
					$_gl[$v['id']] = $v['name'];	
				}	
			}
			
			//检测goods数据
			$goods_names = '';
			$_goods = [];
			foreach($goods as $k=>$v){
				//过滤数据
				if(!isset($v['id']) || !isset($v['name'])){
					$this->error(['code' => 0, 'msg' => '品类信息错误!']);	
				}	
				if(!isset($_gl[$v['id']])){
					$this->error(['code' => 0, 'msg' => '品类信息错误!']);	
				}
				$_goods[$k]['name'] = $_gl[$v['id']];
				$_goods[$k]['id'] = $v['id'];
				$_goods[$k]['num'] = isset($v['num'])? $v['num'] : '0.00';
				$goods_names .= $_gl[$v['id']] . '、';
			}
			
			//生成数据
			$d['order_sn'] = createSn();
			$d['add_time'] = time();
			//$d['finished_time'] = strtotime($d['finished_time']);
			$d['goods'] = json_encode($_goods);
			
			
					  
			$insertID = Db::name('pre_order')->insertGetId($d);
			
			if($insertID){
				$d['id'] = $insertID;
				$d['goods'] = json_decode($d['goods'], true);
				
				//更新住户预约地址
				if($user){
					Db::name('user')->where(['id'=>$user['id']])->setfield('more', json_encode([
																								'name'=>$d['buyer_name'],
																								'mobile'=>$d['buyer_phone'],
																								'addr'=>$d['buyer_addr'],
																								'area_id'=>$d['area_id']
																								], JSON_UNESCAPED_UNICODE));
				}
				
				//发送短信
				$smsSettings    = cmf_get_option('sms_settings');
				$mobile = $smsSettings['sms_mobile'];
				$content = '【爱分类爱回收】[预约订单] 时间：' .$d['finished_time']. '，地址：' .$d['buyer_addr']. '，姓名：' .$d['buyer_name']. '，回收类型：' .trim($goods_names, '、'). '，电话：' .$d['buyer_phone']. '，订单号：' .$d['order_sn']. '。';
				import('sms.Sms', EXTEND_PATH);
				$sms = new \Sms();
				$re = $sms->send($content, $mobile);
				
				//推送消息
				$msg = '新订单：' .$d['finished_time']. ',' . $d['buyer_addr'];
				
				$codes = Db::name('area_user')->alias('a')
													->join([['__USER__ b', 'a.user_id = b.id']])
													->where(['b.user_type'=>3, 'a.area_id'=>$d['area_id'], 'b.code'=>['neq', '']])
													->field('b.code')
													->select()->toArray();
				$userMsgModel = new UserMsgModel();

				if($codes){
					foreach($codes as $v){
						$userMsgModel->push($v['code'], $msg, ['id'=>$insertID]);
					}
				}

				
				$this->success(['code' => 1, 'msg' => '预约下单成功!'] , $d);
			}else{
				$this->error(['code' => 0, 'msg' => '操作失败!']);	
			}
		}
	}
	
	/**
	 *	订单列表
	 */
	public function getOrders(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$condition['buyer_id'] = $user['id'];
			$page = isset($data['p']) ? intval($data['p']) : 0;
			$size = isset($data['s']) ? intval($data['s']) : 10;

			$orderModel = new OrderModel();
			$data = $orderModel->getList($condition, $page, $size);
			$total = $orderModel->getCount($condition);
			
			foreach($data as $k=>$v){
				$data[$k]['add_time'] = date('Y年m月d日 H:i:s', $v['add_time']);
				$data[$k]['finished_time'] = date('Y年m月d日 H:i:s', $v['finished_time']);
			}
			
			$this->success('' , ['items'=>$data, 'total'=>$total]);

		}
	}
	
	/**
	 *	预约下单列表
	 */
	public function getPreOrders(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$condition['buyer_id'] = $user['id'];
			$condition['order_state'] = ['neq', 0];
			$page = isset($data['p']) ? intval($data['p']) : 0;
			$size = isset($data['s']) ? intval($data['s']) : 10;

			$preorderModel = new PreOrderModel();
			$data = $preorderModel->getList($condition, $page, $size);
			$total = $preorderModel->getCount($condition);
			
			
			$this->success('' , ['items'=>$data, 'total'=>$total]);

		}
	}
	
	/**
	 *	取消预约
	 */
	public function cancelPreOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$condition['buyer_id'] = $user['id'];
			$order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
			$condition['order_id'] = $order_id;

			$preorderModel = new PreOrderModel();
			$data = $preorderModel->cancel($condition);
			
			
			$this->success(['code' => 1, 'msg' => '取消成功']);

		}
	}
	
	/**
	 *	积分查询
	 */
	public function getScoreLog(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			
			$this->success('' , ['all'=>$user['score'], 'month'=>20]);

		}
	}
	
	/**
	 *	发送验证码
	 */
	public function sendSmsCode(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$validate = new Validate([
                'phone' => 'require|mobile',
				//'captcha'  => 'require',
            ]);
            $validate->message([
                'phone.require' => '手机号不能为空',
                'phone.mobile'     => '手机号码格式不正确',
				//'captcha.require'  => '验证码不能为空',
            ]);
			
			if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			/*if (!cmf_captcha_check($data['captcha'])) {
                $this->error(['code' => 0, 'msg' => '验证码错误']);
            }*/
			
			//发送短信
			import('sms.Sms', EXTEND_PATH);
			$sms = new \Sms();
			$captcha = rand(100000, 999999);
			$content = '【爱分类爱回收】您正将账号和手机进行绑定，验证码为：' . $captcha . '，请正确输入。';
			$return = $sms->send($content, $data['phone']);
			
			//删除数据
			Db::name('sms_log')->where([
											'phone'=> $data['phone'],
											'type'=>1
										])->setField('deleted', 1);
			
			//写入数据库
			Db::name('sms_log')->insert([
											'phone'=> $data['phone'],
											'content'=>$content,
											'code'=>$captcha,
											'add_time'=>time(),
											'type'=>1,
											'ref' => json_encode($return)
										]);
			
			if($return['code'] == 1){
				$this->success(['code' => 1, 'msg' => $return['msg']]);	
			}else{
				$this->error(['code' => 0, 'msg' => $return['msg']]);		
			}

		}
	}
	
	/**
	 *	绑定手机号
	 */
	public function bind(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			$userModel = new UserModel();
			$wxModel = new WxModel();
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$token = $this->request->put('token');
			
			$validate = new Validate([
                'phone' => 'require|mobile',
				'captcha'  => 'require',
            ]);
            $validate->message([
                'phone.require' => '手机号不能为空',
                'phone.mobile'     => '手机号码格式不正确',
				'captcha.require'  => '验证码不能为空',
            ]);
			
			if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			
			//查询
			$log = Db::name('sms_log')->where([
											'phone'=> $data['phone'],
											'code'=>$data['captcha'],
											'type'=>1,
											'deleted' => 0
										])->find();

			if($log){
				//验证手机是否已经绑定
				$_user = Db::name('user')->where(['mobile'=>$data['phone'], 'id' => array('neq', $user['id']), 'user_type'=>2])->find();
				if(!$_user){
					$userModel->where(['id'=>$user['id']])->setField('mobile', $data['phone']);
				}elseif($_user && $_user['openid'] == '' && $user['openid'] != ''){
					$userModel->where(['id'=>$_user['id']])->setField('openid', $user['openid']);
					$userModel->where(['id'=>$user['id']])->setField('openid', '');
					Db::name('user_token')->where(['user_id'=>$user['id']])->delete();
					$token = cmf_generate_user_token($_user['id'], 'mobile');
				}else{
					$this->error(['code' => 0, 'msg' => '手机号已经存在']);
				}
				
				Db::name('sms_log')->where(['id'=>$log['id']])->setField('deleted', '1');
				
				//是否需要推送
				if(isset($data['mid'])){
					$content = "恭喜登陆成功！请在自助机确认身份并开始投递！";	
					
					$id = substr($data['mid'], 0 , strlen($data['mid']) - 1);
					$type = substr($data['mid'], -1 , 1);
			
					$machine = $userModel->get_machine_info(['id'=>$id], 'id,code');
					$user = $userModel->get_user_info(['id'=>$user['id']], 'id,user_nickname,sex,birthday,score,mobile,more,openid,avatar');
					
					if($machine['code'] != ''){
						$userMsgModel = new UserMsgModel();
						$user = $user->toArray();
						if($user['openid']){
							$user['subscribe'] = $wxModel->isSubscribe($user['openid']);
						}else{
							$user['subscribe'] = 0;
						}
						unset($user['openid']);
						$info = [
									'content'=>'扫码开门',
									'user'=>$user,
									'type'=>'10'
								];
								
						if($type == 0){
							$_type = 'm';
						}else{
							$_type = 'j';
						}
						
						$res = $userMsgModel->push($machine['code'], '', $info, $_type);
					}
				}

				$this->success('绑定成功', ['token'=>$token]);	
			}
				
			$this->error(['code' => 0, 'msg' => '验证码错误！']);

		}
	}
}
