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

class MachineController extends RestBaseController{
	
	/**
	 *	登录
	 */
	public function login(){
		if ($this->request->isPost()) {
            $validate = new Validate([
                'username' => 'require',
                'password' => 'require|min:6|max:32',
            ]);
            $validate->message([
                'username.require' => '机器码不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
            ]);

            $data = $this->request->put();
			
			
            if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }

            $userModel         	= new UserModel();
            $user['user_pass'] 	= $data['password'];
			$user['user_type'] 	= 4;
			$user['user_login'] = $data['username'];
			$user['device_type']= 'mobile';
			$log                = $userModel->doApi($user, 'id,user_nickname,signature,more');
			
			
			if($log){
				$log['more'] = json_decode($log['more'], true);
				if($log['more']['machine_type_1']){
					$log['machine_type_1'] = Db::name('goods_cate')
											->where(['id'=>$log['more']['machine_type_1']])
											->field('id,name,unit_name,unit,is_qrcode,purchasing_point')
											->find();
				}else{
					$log['machine_type_1'] = [];
				}
				if($log['more']['machine_type_2']){
					$log['machine_type_2'] = Db::name('goods_cate')
											->where(['id'=>$log['more']['machine_type_2']])
											->field('id,name,unit_name,unit,is_qrcode,purchasing_point')
											->find();
				}else{
					$log['machine_type_2'] = [];
				}
				unset($log['more']);
				
				$this->success(['code' => 1, 'msg' => '登录成功!'] ,$log);
			}else{
				$this->error(['code' => 0, 'msg' => '登录失败!']);
			}
		}
	}
	
	/**
	 *	用户登录
	 */
	public function userLogin(){
		if ($this->request->isPost()) {
			$data = $this->request->post();
			
			$user_login = isset($data['username']) ? trim($data['username']) : '';
			$type = isset($data['type']) ? intval($data['type']) : 1;
			
			if(!in_array($type, [1,2])){
				$this->error('登录类型错误!');
			}
			
			if(!$user_login && $type == 1){
				$this->error('手机号不能为空!');
			}
			
			if($type == 1 && !isMobile($user_login)){
				$this->error('手机号格式错误！');
			}
			
			if(!$user_login && $type == 2){
				$this->error('rfid不能为空!');
			}
			
            if($type == 1){
				$where['mobile'] = $user_login;
				$where['user_type'] = 2;
				$result = Db::name('user')->where($where)->field('id,user_nickname,sex,birthday,score,mobile,openid')->find();
				
				//用户不存在，自动注册
				if(!$result){
					$userModel = new UserModel();
					$customer = $userModel->addCustomer($where);
					$result = Db::name('user')->where($where)->field('id,user_nickname,sex,birthday,score,mobile,openid')->find();
				}

				
			}elseif($type == 2){
				$user_id = Db::name('rfid')->where(['code'=>$user_login])->value('user_id');
				$where['id'] = $user_id;
				$where['user_type'] = 2;
				$result = Db::name('user')->where($where)->field('id,user_nickname,sex,birthday,score,mobile,openid')->find();
			}
			
			if (!empty($result)) {
				$data = [
					'last_login_time' => time(),
					'last_login_ip'   => get_client_ip(0, true),
				];
				Db::name('user')->where('id', $result["id"])->update($data);
				$result['token'] = cmf_generate_user_token($result["id"], 'mobile');
				
				if($result['openid']){
					$wxModel = new WxModel();
					$subscribe = $wxModel->isSubscribe($result['openid']);
				}else{
					$subscribe = 0;
				}
				$result['subscribe'] = $subscribe;
				
				unset($result['openid']);
					
				$this->success(['code' => 1, 'msg' => '登录成功!'] ,$result);
			}else{
				$this->error(['code' => 0, 'msg' => '登录失败!']);
			}

		}
	}
	
	/**
	 *	获取二维码
	 */
	public function getQRcode(){
		if ($this->request->isPost()) {
			$data = $this->request->post();
			
			$type = isset($data['type']) ? $data['type'] : 0;
			
			$user = $this->user;
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$content = [
							'expire_seconds'=> 200,
							'action_name'=>'QR_SCENE',
							'action_info'=>[
												'scene'=>[
															'scene_id'=> $user['id'] . $type
														]
											]
						]; 
			/* $content = [
							'action_name'=>'QR_LIMIT_SCENE',
							'action_info'=>[
												'scene'=>[
															'scene_id'=> $user['id'] . $type
														]
											]
						];*/
			$wxModel = new WxModel();
			$re = $wxModel->getQRcode(json_encode($content));
			
			$return = json_decode($re, true);
			
			$return['getImg'] = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $return['ticket'];
			
			$this->success('' , $return);

		}
	}
	
	/**
	 *	同步机器状态
	 */
	public function setState(){
		if ($this->request->isPost()) {
			$data = $this->request->post();
			
			$user = $this->user;
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$up['state'] = isset($data['state']) ? intval($data['state']) : 0 ;
			
			if($user['user_type'] != 4){
				$this->error(['code' => 0, 'msg' => '机器信息错误!']);
			}
			
			if(!in_array($data['state'], [10,20,30,40])){
				$this->error(['code' => 0, 'msg' => '状态码错误!']);	
			}
			
			$up['mid'] = $user['id'];
			$up['time'] = time();
			
			Db::name('user')->where(['id'=>$user['id']])->setfield('user_status', $up['state']);
			Db::name('machine_state_log')->insert($up);
			
			$this->success(['code'=>1, 'msg'=>'同步成功！']);

		}
	}
	
	/**
	 *	用户注册发送验证码
	 */
	public function sendSmsCode(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$validate = new Validate([
                'phone' => 'require|mobile',
            ]);
            $validate->message([
                'phone.require' => '手机号不能为空',
                'phone.mobile'     => '手机号码格式不正确',
            ]);
			
			if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			//查询手机号是否已经注册
			$userModel = new UserModel();
			if(!$userModel->checkMobile([], $data['phone'])){
				$this->error(['code' => 0, 'msg' => '手机号已经存在！']);
			}
			
			//发送短信
			import('sms.Sms', EXTEND_PATH);
			$sms = new \Sms();
			$captcha = rand(100000, 999999);
			$content = '【爱分类爱回收】您正将账号和手机进行绑定，验证码为：' . $captcha . '，请正确输入。';
			$return = $sms->send($content, $data['phone']);
			
			//删除数据
			Db::name('sms_log')->where([
											'phone'=> $data['phone'],
											'type'=>3
										])->setField('deleted', 1);
			
			//写入数据库
			Db::name('sms_log')->insert([
											'phone'=> $data['phone'],
											'content'=>$content,
											'code'=>$captcha,
											'add_time'=>time(),
											'type'=>3,
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
	 *	住户注册
	 */
	public function userRegister(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
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
			
			//查询验证码
			$log = Db::name('sms_log')->where([
											'phone'=> $data['phone'],
											'code'=>$data['captcha'],
											'type'=>3,
											'deleted' => 0
										])->find();

			if($log){
				$d['mobile'] = isset($data['phone']) ? trim($data['phone']) : '';
				
				//验证手机是否已经注册
				$userModel = new UserModel();
				if(!$userModel->checkMobile([], $d['mobile'])){
					$this->error(['code' => 0, 'msg' => '手机号已经存在！']);
				}

				//新增用户信息
				$customer = $userModel->addCustomer($d);
				if(!$customer){
					$this->error(['code' => 0, 'msg' => '注册失败！']);
				}
				$token = cmf_generate_user_token($customer['id'], 'mobile');	
				
				Db::name('sms_log')->where(['id'=>$log['id']])->setField('deleted', '1');
				
				//邀请
				$invite_mobile = isset($data['invite_phone']) ? trim($data['invite_phone']) : '';
				if($invite_mobile){
					$invite_where['mobile'] = $invite_mobile;
					$invite_where['id'] = ['neq', $customer['id']];
					$invite_where['user_type'] = 2;
					$invite_user = Db::name('user')->where($invite_where)->find();
					
					if($invite_user){
						//增加积分
						$point = 100;
						Db::name('user')->where(['id'=>$invite_user['id']])->setInc('score', $point);
						Db::name('user_score_log')->insert([
															'user_id'=>$invite_user['id'],
															'create_time'=>time(),
															'action'=>'invite:'.$customer['mobile'],
															'score'=>$point
														]);
						
					}
				}

				$this->success('注册成功', ['token'=>$token]);	
			}
				
			$this->error(['code' => 0, 'msg' => '验证码错误！']);

		}
	}
	
	/**
	 *	通过条码获取瓶子信息
	 */
	public function getBottleInfoByCode(){
		if ($this->request->isPost()) {
			$data = $this->request->post();
			
			$user = $this->user;
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$code = isset($data['code']) ? trim($data['code']) : '';
			
			if(!$code){
				$this->error(['code' => 0, 'msg' => '请填写条码信息!']);
			}
			
			$info = Db::name('goodsCateCode')->where(['code'=>$code])->find();
			
			$out = [
						'name' => '未知',
						'size' => '',
						'cate' => '未知',
					];
			
			if($info){
				$cate = Db::name('goodsCate')->where(['id'=>$info['cid']])->find();
				
				if($cate){
					$out['cate'] = $cate['name'];
				}
				
				$out['size'] = $info['size'];
				$out['name'] = $info['name'];
				
			}else{	//不存在，新增
				$insert['cid'] = 0;
				$insert['code'] = $code;
				Db::name('goodsCateCode')->insert($insert);
			}
			
			$this->success('', $out);

		}
	}
}
