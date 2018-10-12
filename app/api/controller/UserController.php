<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\OrderModel;
use app\api\model\PreOrderModel;
use think\Validate;
use think\Db;

class UserController extends RestBaseController{
	
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
                'username.require' => '用户名不能为空',
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
			$user['user_type'] 	= 3;
			$user['user_login'] = $data['username'];
			$user['device_type']= 'mobile';
			$log                = $userModel->doApi($user);
			
			$area = Db::name('area')->where(['master'=>$log['id']])->count();
			$log['isMaster'] = $area > 0 ? true : false;
			
			if($log){
				$this->success(['code' => 1, 'msg' => '登录成功!'] ,$log);
			}else{
				$this->error(['code' => 0, 'msg' => '登录失败!']);
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

			$condition = [];
			//$condition['collecter_id'] = $user['id'];
			$page = isset($data['p']) ? intval($data['p']) : 0;
			$size = isset($data['s']) ? intval($data['s']) : 10;
			$from = isset($data['from']) ? trim($data['from']) : '';
			$to = isset($data['to']) ? trim($data['to']) : '';

			if($from && $to){
				$condition['finished_time'] = ['between', [strtotime($from), strtotime('+1 day', strtotime($to))]];	
			}elseif($from){
				$condition['finished_time'] = ['gt', strtotime($from)];
			}elseif($to){
				$condition['finished_time'] = ['lt', strtotime('+1 day', strtotime($to))];
			}
			
			$condition['buyer_id'] = ['neq', 0];
			$condition['order_from'] = ['neq', 4];

			$orderModel = new OrderModel();
			$data = $orderModel->getList($condition, $page, $size);
			$total = $orderModel->getCount($condition);
			$point = $orderModel->getPoint($condition);
			
			
			$this->success('' , ['items'=>$data, 'total'=>$total, 'point'=>$point]);

		}
	}
	
	/**
	 *	扫码获取订单
	 */
	public function getOrderByCode(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$condition = [];
			
			$code = isset($data['code']) ? trim($data['code']) : '';
			if(!$code){
				$this->error(['code' => 0, 'msg' => '请扫码!']);	
			}
			
			$condition['code'] = ['like', '%' . $code . '%'];


			$orderModel = new OrderModel();
			$data = $orderModel->getInfo($condition);
			
			
			$this->success('', $data);

		}
	}
	
	/**
	 *	录入住户
	 */
	public function add(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$rfids = isset($data['rfids']) ? trim($data['rfids'], ',') : '';
			$d['user_nickname'] = isset($data['name']) ? trim($data['name']) : '';
			$d['sex'] = isset($data['sex']) ? intval($data['sex']) : '';
			$d['mobile'] = isset($data['mobile']) ? trim($data['mobile']) : '';
			$d['user_addr'] = isset($data['addr']) ? trim($data['addr']) : '';
			
			//查询rfid是否可用
			if($rfids){
				foreach(explode(',' , $rfids) as $v){
					$re = Db::name('rfid')->where(['code'=> $v])->find();
					if(!$re){
						$this->error(['code' => 0, 'msg' => $v . '不存在!']);
					}elseif($re['user_id'] != 0){
						$this->error(['code' => 0, 'msg' => $v . '已经被使用!']);
					}
				}
			}
			
			$validate = new Validate([
                'mobile' 	=> 'require|mobile',
				'user_nickname'  => 'require',
				'sex'  	=> 'require',
				'user_addr'  => 'require',
            ]);
            $validate->message([
                'mobile.require' => '手机号不能为空',
                'mobile.mobile'     => '手机号码格式不正确',
				'user_nickname.require'  => '住户姓名不能为空',
				'sex.require'  => '性别不能为空',
				'user_addr.require'  => '领奖地址不能为空',
            ]);
			
			if (!$validate->check($d)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			//新增住户
			$userModel = new UserModel();
			if(!$userModel->checkMobile([], $d['mobile'])){
				$this->error(['code' => 0, 'msg' => '手机号已经存在！']);
			}
			
			$customer = $userModel->addCustomer($d);

			
			if(!$customer){
				$this->error(['code' => 0, 'msg' => '录入失败！']);
			}
			
			//绑定rfid
			if($rfids){
				Db::name('rfid')->where(['user_id'=>0, 'code'=>['in', $rfids]])->update(['user_id'=>$customer['id'], 'bind_time'=>time()]);
			}
			
			$this->success(['code' => 1, 'msg' => '录入成功!']);

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
			
			//获取区域信息
			$area = Db::name('area_user')->where(['user_id'=>$user['id']])->field('area_id')->select();
			$in_area = [];
			if($area){
				foreach($area as $v){
					$in_area[] = $v['area_id'];	
				}	
			}
			

			$condition['order_state'] = 10;
			$condition['area_id'] = ['in', $in_area];
			
			$page = isset($data['p']) ? intval($data['p']) : 0;
			$size = isset($data['s']) ? intval($data['s']) : 10;
			$type = isset($data['type']) ? intval($data['type']) : 0;
			
			$in_master_area = [];
			if($type == 1){
				$in_master_area[] = $user['id'];
			}elseif($type == 2){
				$in_master_area[] = 0;
			}elseif($type == 3){
				$in_master_area[] = $user['id'];
				
				//判断是否是站长
				$master_area = Db::name('area_user')->alias('a')
													->join([['__AREA__ b', 'a.area_id = b.id']])
													->where(['b.master'=>$user['id']])
													->field('a.user_id')
													->select();
				if($master_area){
					foreach($master_area as $v){
						$in_master_area[] = $v['user_id'];	
					}	
				}
			}
			
			$condition['collecter_id'] = ['in', $in_master_area];

			$preorderModel = new PreOrderModel();
			$data = $preorderModel->getList($condition, $page, $size);
			$total = $preorderModel->getCount($condition);
			
			
			$this->success('' , ['items'=>$data, 'total'=>$total]);

		}
	}
	
	/**
	 *	预约下单详情
	 */
	public function getPreOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$id = isset($data['id']) ? intval($data['id']) : 0;
			if(!$id){
				$this->error(['code' => 0, 'msg' => 'ID参数错误!']);	
			}
			

			$condition['order_id'] = $id;

			$preorderModel = new PreOrderModel();
			$data = $preorderModel->getInfo($condition);

			
			
			$this->success('' , $data);

		}
	}
	
	/**
	 *	获取所有住户手机号
	 */
	public function getAllMobile(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$key = isset($data['key']) ? intval($data['key']) : 0;
			
			if($key > 0){
				$condition['mobile'] = ['like', '%' .$key. '%'];
			}else{
				$condition['mobile'] = ['neq', ''];
			}
			
			$userModel         	= new UserModel();
			$data = $userModel->get_customer_list($condition, 'mobile, user_nickname as name');		
			
			$this->success('' , ['user'=>$data]);

		}
	}
	
	/**
	 *	通过住户手机号查询积分
	 */
	public function getScoreByMobile(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}
			
			$d['mobile'] = isset($data['mobile']) ? trim($data['mobile']) : '';
			
			$userModel = new UserModel();
			
			$validate = new Validate([
                'mobile' 	=> 'require|mobile',
            ]);
            $validate->message([
                'mobile.require' => '手机号不能为空',
            ]);
			if (!$validate->check($d)) {
				$this->error(['code' => 0, 'msg' => $validate->getError()]);
            }
			
			$d['user_type'] = 2;
			
			$custom = $userModel->getInfo($d);
			
			if(!$custom){
				$this->error(['code' => 0, 'msg' => '住户不存在!']);
			}
			
			$data = [
						'id' => $custom['id'],
						'name' => $custom['user_nickname'],
						'mobile'=> $custom['mobile'],
						'score' => $custom['score']
					];
			
			$this->success(['code' => 1, 'msg' => '成功'], $data);	


		}
	}
	
	/**
	 *	认领预约单
	 */
	public function bindPreOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$id = isset($data['id']) ? intval($data['id']) : 0;
			
			$preorderModel = new PreOrderModel();

			$condition['order_id'] = $id;
			$condition['collecter_id'] = 0;
			$condition['order_state'] = 10;
			
			$data = $preorderModel->getInfo($condition);

			if(!$data){
				$this->error(['code' => 0, 'msg' => '预约单信息错误!']);	
			}
			
			$update['collecter_id'] = $user['id'];
			$update['collecter_name'] = $user['user_nickname'];
			Db::name('pre_order')->where($condition)->update($update);
			
			$this->success('领取成功');

		}
	}
	
	/**
	 *	取消认领预约单
	 */
	public function unbindPreOrder(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$id = isset($data['id']) ? intval($data['id']) : 0;
			
			$preorderModel = new PreOrderModel();

			$condition['order_id'] = $id;
			$condition['collecter_id'] = $user['id'];
			$condition['order_state'] = 10;
			
			$data = $preorderModel->getInfo($condition);

			if(!$data){
				$this->error(['code' => 0, 'msg' => '预约单信息错误!']);	
			}
			
			$update['collecter_id'] = 0;
			$update['collecter_name'] = '';
			Db::name('pre_order')->where($condition)->update($update);
			
			$this->success('取消领取成功');

		}
	}
	
	/**
	 *	是否是站长
	 */
	public function isMaster(){
		
		if ($this->request->isPost()) {
			//处理参数
			$data = $this->request->put();
			
			$user = $this->user;
			
			if(!$user){
				$this->error(['code' => 2, 'msg' => '请先登录!']);	
			}

			$area = Db::name('area')->where(['master'=>$user['id']])->count();
			
			$return = $area > 0 ? true : false;
			
			$this->success('', $return);

		}
	}
}
