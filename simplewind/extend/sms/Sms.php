<?php
// 指定允许其他域名访问    
header('Access-Control-Allow-Origin:*');    
// 响应类型    
header('Access-Control-Allow-Methods:POST');    
// 响应头设置    
header('Access-Control-Allow-Headers:x-requested-with,content-type');

class Sms {
	private $user = '';
	private $password = '';
	private $error = array(
				  '0'=>'成功',
				  '1'=>'用户鉴权错误',
				  '2'=>'IP鉴权错误',
				  '3'=>'手机号码在黑名单',
				  '4'=>'手机号码格式错误',
				  '5'=>'短信内容有误',
				  '6'=>'',
				  '7'=>'手机号数量超限',
				  '8'=>'账户已停用',
				  '9'=>'未知错误',
				  '10'=>'时间戳已过期',
				  '11'=>'同号码同模板发送频率过快',
				  '12'=>'同号码同模板发送次数超限',
				  '13'=>'包含敏感词',
				  '14'=>'扩展号不合法',
				  '15'=>'扩展信息长度过长',
				  '16'=>'手机号重复',
				  '17'=>'批量发送文件为空',
				  '18'=>'json解析错误',
				  '19'=>'用户已退订',
				  '20'=>'短信内容超过1000字符',
				  '99'=>'账户余额不足',
			  );
	public function __construct(){
		$smsSettings    = cmf_get_option('sms_settings');	
		$this->user = $smsSettings['sms_accunt'];
		$this->password = $smsSettings['sms_key'];
	}
	
	public function send($content, $mobile){
		$timestamps = time()*1000;
		$post_data = array();
		$post_data['account'] = $this->user;
		$post_data['password'] = md5($this->password. $mobile. $timestamps);
		$post_data['content'] = $content; 
		$post_data['mobile'] = $mobile;
		$post_data['timestamps'] = $timestamps; //时间戳 单位毫秒
		$url='http://sapi.appsms.cn:8088/msgHttp/json/mt';
		;
		$result = $this->post($url, $post_data);
		$result = json_decode($result, true);
		
		$return['code'] = 0;
		$return['msg'] = '发送失败';
		
		if($result['Rets'][0]['Rspcode'] == 0){
			$return['code'] = 1;
		}
		$return['msg'] = $this->error[$result['Rets'][0]['Rspcode']];
		
		return $return;
	}
	
	private function post($url, $data){
		$o='';
		foreach ($data as $k=>$v){
			$o.= $k . '=' . urlencode($v) . '&' ;
		}

		$post_data=substr($o,0,-1);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果需要将结果直接返回到变量里，那加上这句。
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
}
?>