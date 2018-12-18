<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\AreaModel;
use app\api\model\OrderModel;
use app\api\model\UserMsgModel;
use think\Validate;
use think\Db;

class CommonController extends RestBaseController{
	/**
	 *	获取品类
	 */
	public function getGoods(){
		if ($this->request->isPost()) {
			
			$goodsCate = new GoodsCateModel();
			$condition['is_machine'] = 0;
			
			$res =  $goodsCate->getAllList($condition);
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	获取区域
	 */
	public function getArea(){
		if ($this->request->isPost()) {
			
			$area = new AreaModel();
			
			$res =  $area->getAllList();
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	获取二级品类
	 */
	public function getSecGoods(){
		if ($this->request->isPost()) {
			
			//处理参数
			$data = $this->request->put();
			
			$goodsCate = new GoodsCateModel();
			
			$condition['is_machine'] = 0;
			$condition['member_type'] = isset($data['member_type']) ? intval($data['member_type']) : 1;
			
			$res =  $goodsCate->getSecList($condition, 'id,name,unit_name,unit,purchasing_price,purchasing_point,img_1,img_2');
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	获取积分兑换物品
	 */
	public function getPointGoods(){
		if ($this->request->isPost()) {
			
			$condition['status'] = 1;
			$res =  Db::name('goods')->where($condition)->field('id,name,point')->select();
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	通过rfid获取用户
	 */
	public function getCustomerByRfids(){
		if ($this->request->isPost()) {
			
			$data = $this->request->put();
			
			$rfids = isset($data['rfids']) ? $data['rfids'] : '';
			
			$customer = Db::name('rfid')->where(['code'=>['in', trim($rfids, ',')]])->field('user_id')->select()->toArray();
			$ids = '';
			if($customer){
				foreach($customer as $v){
					$ids .= $v['user_id'] . ',';	
				}	
			}
			
			$userModel = new UserModel();
			$condition['id'] = ['in', trim($ids, ',')];
			$res = $userModel->get_customer_list($condition);
			
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	获取轮播图
	 */
	public function getSlideList(){
		if ($this->request->isPost()) {
			
			$data = $this->request->put();
			
			$slideId = isset($data['type']) ? intval($data['type']) : '1';
			$res  = Db::name('slideItem')->where(['slide_id' => $slideId])->field('title,image,url')->select()->toArray();
			if($res){
				foreach($res as $k=>$v){
					$res[$k]['image'] = cmf_get_image_url($v['image']);
				}	
			}
			
			$this->success('' ,$res);
			
		}
	}
	
	/**
	 *	设置推送机器码
	 */
	public function setPushCode(){
		$data = $this->request->put();
		
		$user = $this->user;
			
		if(!$user){
			$this->error(['code' => 2, 'msg' => '请先登录!']);	
		}
		
		$code = trim($data['code']) ? trim($data['code']) : '';

		Db::name('user')->where(['id'=>$user['id']])->setField('code', $code);
		$this->success('设置成功');
	}
	
	/**
	 *	测试推送
	 */
	public function pushTest(){
		$data            = $this->request->post();
		
		$userModel = new UserMsgModel();
		$id = isset($data['id']) ? $data['id'] : '';
		$title = isset($data['title']) ? $data['title'] : '';
		$info = isset($data['info']) ? $data['info'] : [];
		$role = isset($data['type']) ? $data['type'] : 'c';
		
		if(empty($id))	$this->error('ID不能为空');
		if(empty($title))	$this->error('标题不能为空');
		
		$res = $userModel->push($id, $title, $info, $role);
		$this->success('' ,$res);
		
	}
}
