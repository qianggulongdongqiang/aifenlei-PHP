<?php
namespace app\user\controller;

use cmf\controller\RestBaseController;
use app\portal\model\UserModel;
use app\portal\model\GoodsCateModel;
use app\portal\model\OrderModel;
use think\Validate;
use think\Db;

class ApiController extends RestBaseController{
	
	/**
	 *	登录
	 */
	public function login(){
		$type = array(2, 3);
		if ($this->request->isPost()) {
            $validate = new Validate([
                'username' => 'require',
                'password' => 'require|min:6|max:32',
            ]);
            $validate->message([
                'username.require' => '用户名不能为空',
                'password.require' => '密码不能为空',
                'password.max'     => '密码不能超过32个字符',
                'password.min'     => '密码不能小于6个字符',
            ]);

            $data = $this->request->put();
			
			if(!in_array($data['type'], $type)){
				$this->error(['code' => 0, 'msg' => '账户类型错误!']);
			}
			
            if (!$validate->check($data)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }

            $userModel         	= new UserModel();
            $user['user_pass'] 	= $data['password'];
			$user['user_type'] 	= $data['type'];
			$user['user_login'] = $data['username'];
			$user['device_type']= 'mobile';
			$log                = $userModel->doApi($user);
			
			if($log){
				$this->success(['code' => 1, 'msg' => '登录成功!'] ,$log);
			}else{
				$this->error(['code' => 0, 'msg' => '登录失败!']);
			}
		}
	}
	
	/**
	 *	退出
	 */
	public function logout(){
		
	}
	
	/**
	 *	获取品类
	 */
	public function getGoods(){
		if ($this->request->isPost()) {
			//处理参数
			$pid = $this->request->put('pid', 0, 'intval');
			
			$goodsCate = new GoodsCateModel();
			
			$res =  $goodsCate->getInfoByPid($pid);
			
			$this->success('' ,$res);
			
		}
	}
	
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
			
			$userModel         	= new UserModel();
			$orderModel         = new OrderModel();
			$goodsCateModel     = new GoodsCateModel();
			
			if(!$user){
				$this->error(['code' => 0, 'msg' => '请先登录!']);
			}
			
			$custom = $userModel->getInfo(['user_type'=>2, 'id'=>$custom_id]);
			if(!$custom){
				$this->error(['code' => 0, 'msg' => '住户不存在!']);
			}
			
			if(!$goods){
				$this->error(['code' => 0, 'msg' => '品类信息错误!']);	
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
				$goods =	$goodsCateModel->getInfo(['id'=>$k]);
				$_goods_data[] = ['id'=>$goods['id'], 'name'=>$goods['name'], 'num'=>$v];
				$points_number += $goods['purchasing_point'] * $v;
			}
			
			//生成数据
			$order = [
						  'order_sn'=>$orderModel->createSn(),
						  'add_time'=>time(),
						  'points_number'=>$points_number,
						  'collecter_name'=>$user['user_nickname'],
						  'collecter_id'=>$user['id'],
						  'goods'=>json_encode($_goods_data),
						  'buyer_id' => $custom['id'],
						  'buyer_name' => $custom['user_nickname'],
						  'buyer_addr' => '',
						  'buyer_phone' => '',
					  ];
					  
			$insertID = Db::name('order')->insertID($order);
			
			if($insertID){
				$order['id'] = $insertID;
				$order['goods'] = json_decode($order['goods'], true);
				$this->success(['code' => 1, 'msg' => '订单新建成功!'] , $order);
			}else{
				$this->error(['code' => 0, 'msg' => '操作失败!']);	
			}
		}
	}
}
