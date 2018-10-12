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
				$result = Db::name('user')->where($where)->field('id,user_nickname,sex,birthday,score,mobile')->find();
			}elseif($type == 2){
				$user_id = Db::name('rfid')->where(['code'=>$user_login])->value('user_id');
				$where['id'] = $user_id;
				$where['user_type'] = 2;
				$result = Db::name('user')->where($where)->field('id,user_nickname,sex,birthday,score,mobile')->find();
			}
			
			if (!empty($result)) {
				$data = [
					'last_login_time' => time(),
					'last_login_ip'   => get_client_ip(0, true),
				];
				Db::name('user')->where('id', $result["id"])->update($data);
				$result['token'] = cmf_generate_user_token($result["id"], 'mobile');
					
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
			
			$user = $this->user;
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$content = [
							'action_name'=>'QR_LIMIT_SCENE',
							'action_info'=>[
												'scene'=>[
															'scene_id'=> $user['id']
														]
											]
						];
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
}
