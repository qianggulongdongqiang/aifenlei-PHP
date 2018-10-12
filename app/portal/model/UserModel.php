<?php
namespace app\portal\model;

use think\Model;

class UserModel extends Model
{

    protected $type = [
        'more' => 'array',
    ];
	
	public function rfid(){	
		return $this->belongsTo('RfidModel', 'user_id', 'id');
	}

	/**
	 *	获取回收员
	 */
	public function get_collecter_list(){
		$data = $this->where('user_type', 3)->select()->toArray();
		return $data;
	}
	
	/**
	 *	获取住户信息
	 */
	public function get_user_info($condition, $field = '*'){
		$condition['user_type'] = 2;
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
	 *	API登录
	 */
	public function doApi($user){
		$where['user_login'] = $user['user_login'];
		$where['user_type'] = $user['user_type'];
		$where['user_pass'] = cmf_password($user['user_pass']);
		$result = $this->where($where)->field('id,user_nickname,sex,birthday,score')->find();

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
}