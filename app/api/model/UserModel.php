<?php
namespace app\api\model;

use think\Model;
use app\api\model\WxModel;

class UserModel extends Model{
	
	public function preorder(){	
		return $this->belongsTo('PreOrderModel', 'user_id', 'id');
	}

	/**
	 *	获取回收员
	 */
	public function get_collecter_list(){
		$data = $this->where('user_type', 3)->select()->toArray();
		return $data;
	}
	
	/**
	 *	获取住户
	 */
	public function get_customer_list($condition, $field = 'id,user_nickname,sex,birthday,score,mobile,more'){
		$condition['user_type'] = 2;
		$data = $this->where($condition)->field($field)->select();
		if($data){
			foreach($data as $k=>$v){
				if(isset($v['more'])){
					$data[$k]['more'] = json_decode($v['more']);
				}
			}	
		}
		return $data;
	}
	
	/**
	 *	获取住户信息
	 */
	public function get_user_info($condition, $field = '*'){
		$condition['user_type'] = 2;
		$data = $this->where($condition)->field($field)->find();
		if($data){
			$data['more'] = json_decode($data['more']);	
		}
		return $data;
	}
	
	/**
	 *	获取回收机信息
	 */
	public function get_machine_info($condition, $field = '*'){
		$condition['user_type'] = 4;
		$data = $this->where($condition)->field($field)->find();
		return $data;
	}
	
	
	/**
	 *	获取用户信息
	 */
	public function getInfo($condition = array(), $field = '*'){
		$userInfo = $this->where($condition)->field($field)->find();
		
		
		if($userInfo){
			return $userInfo->toArray();	
		}
		
		return false;
	}
	
	/**
	 *	通过openid注册用户
	 */
	public function addCustomerByOpenID($openid){
		$wxModel 	= new WxModel();
		$info		= $wxModel->getUserInfoByOpenid($openid);
		$info		= json_decode($info, true);
		
		$data   = [
                'last_login_ip'   	=> get_client_ip(0, true),
                'create_time'     	=> time(),
                'last_login_time' 	=> time(),
                'user_status'     	=> 1,
                'user_type'       	=> 2,
				'openid'			=> $openid,
				'avatar'			=> (isset($info['headimgurl']) ? $info['headimgurl'] : ''),
				'user_nickname'		=> (isset($info['nickname']) ? $info['nickname'] : ''),
            ];
		$userId = $this->insertGetId($data);
		$data   = $this->where('id', $userId)->find();
		cmf_update_current_user($data);
		return $data;
	}
	
	/**
	 *	录入住户
	 */
	public function addCustomer($data){
		$data['create_time'] = time();
		$data['user_status'] = 1;
		$data['user_type'] = 2;
		
		$userId = $this->insertGetId($data);
		$data   = $this->where('id', $userId)->find();
		
		return $data;
	}
	
	/**
	 *	API登录
	 */
	public function doApi($user, $filed = 'id,user_nickname,sex,birthday,score'){
		$where['user_login'] = $user['user_login'];
		$where['user_type'] = $user['user_type'];
		$where['user_pass'] = cmf_password($user['user_pass']);
		$result = $this->where($where)->field($filed)->find();

        if (!empty($result)) {
           
			$data = [
				'last_login_time' => time(),
				'last_login_ip'   => get_client_ip(0, true),
			];
            $this->where('id', $result["id"])->update($data);
            $result['token'] = cmf_generate_user_token($result["id"], $user['device_type']);
                
			return $result->toArray();
        }
   		return false;
	}
	
	/**
	 *	验证手机号是否存在
	 */
	public function checkMobile($user = [], $mobile){
		$where['mobile'] = $mobile;
		if($user){
			$where['id'] = array('neq', $user['id']);
		}
		$result = $this->where($where)->count();

		if($result > 0){
			return false;
		}else{
			return true;
		}
	}
}