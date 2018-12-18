<?php
namespace app\api\controller;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\OrderModel;
use app\api\model\PreOrderModel;
use app\api\model\WxModel;
use app\api\model\UserMsgModel;
use think\Validate;
use think\Db;
use think\queue\Job;

class WxController extends RestBaseController{
	
	//用户首次配置开发环境  
    public function index(){  
        $signature = isset($_GET["signature"]) ? $_GET["signature"] : '';  
        $timestamp = isset($_GET["timestamp"]) ? $_GET["timestamp"] : '';  
        $nonce     = isset($_GET["nonce"]) ? $_GET["nonce"] : '';  
        $echostr   = isset($_GET['echostr']) ? $_GET["echostr"] : '';        
		$token     = 'arcfun';  
		$tmpArr    = array($token, $timestamp, $nonce);  
		sort($tmpArr, SORT_STRING);  
		$tmpStr    = implode( $tmpArr );  
		$tmpStr    = sha1( $tmpStr );  
		
		if( $tmpStr == $signature && $echostr){  
			echo $echostr;  
		}else{  
			$this->reposeMsg();  
		}  
    }
	
	private function reposeMsg(){
		
		$postArr = file_get_contents('php://input');  //接受xml数据  
		
		/* $fp = fopen(CMF_ROOT . 'log.txt', 'a+');
		fwrite($fp, $postArr . time() . "\r\n");
		fclose($fp); */
 
		$postObj = simplexml_load_string( $postArr );   //将xml数据转化为对象
		
		if(!$postObj) return false;
		
		if(strtolower($postObj->Event ) == 'click'){	//点击菜单
			if($postObj->EventKey == 'point_1000'){
				$user = Db::name('user')->where(['openid'=> $postObj->FromUserName])->find();
				$wxModel = new WxModel();
				if($user){
					//pjRo7prpFrnOr22rnsVjJm5Ez7Be64qEYBUGrnvH-4I 正式 uDm5Iqgm2FQoh0ddMh3kC4Hr3xcJUAQ4F-ckwlxvj-U 测试
					$msg = '{
					   "touser":"' .$user['openid']. '",
					   "template_id":"pjRo7prpFrnOr22rnsVjJm5Ez7Be64qEYBUGrnvH-4I", 
					   "url": "'. cmf_get_domain() .'",					   
					   "data":{
							   "first": {
								   "value":""
							   },
							   "keyword1":{
								   "value":"' .substr_replace($user['mobile'], '****', 3, 4) . '"
							   },
							   "keyword2": {
								   "value":"' .$user['score']. '"
							   },
							   "remark":{
								   "value":"点击详情，前往预约。感谢您为分类回收做出的贡献！",
								   "color":"#999"
							   }
					   }
				   }';
				}else{
					//Mke__bzin3A1XF4kMnvOW9VePXoozT901VunedCPlOM 正式 kVOEYpz8rrZkTkj6rXeFK9Us_En8DtavpBiB8u9yLvo 测试
					$msg = '{
					   "touser":"' . $postObj->FromUserName . '",
					   "template_id":"Mke__bzin3A1XF4kMnvOW9VePXoozT901VunedCPlOM", 
					   "url":"' . cmf_get_domain() . '/#/bindaccount",					   
					   "data":{
							   "first": {
								   "value":"账号状态通知"
							   },
							   "keyword1":{
								   "value":"未登录"
							   },
							   "keyword2": {
								   "value":"未登录"
							   },
							   "keyword3": {
								   "value": "' .date('Y年m月d日 H:i'). '"
							   },
							   "remark":{
								   "value":"点击本消息，登录手机号查询积分。",
								   "color":"#999"
							   }
					   }
				   }';	
				}
				$re = $wxModel->sendMsg($msg);
				return $re;
			}
		}elseif(strtolower($postObj->Event) == 'subscribe'){	//关注
			if($postObj->EventKey != ''){	//扫码关注
				$key = explode('_', $postObj->EventKey);
				$mid = isset($key[1]) ? intval($key[1]) : 0;
				
				if($mid > 0){
					$re = $this->_dealScan($postObj->FromUserName, $postObj->ToUserName, $mid);
					echo $re['msg'];
				}	
			}else{	//关注回复
				$right_flag = $this->unicode2utf8("\ue231");
				$money_flag = $this->unicode2utf8("\ue12f");
			
			
				$content = "积分将发送至您的账户。";
				$img_url = cmf_get_domain() . "/public/static/images/auto.png";
				$re_url = cmf_get_domain() . "/#/bindaccount";
				$textTpl = "<xml>
							   <ToUserName><![CDATA[%s]]></ToUserName>
							   <FromUserName><![CDATA[%s]]></FromUserName>
							   <CreateTime>%s</CreateTime>
							   <MsgType><![CDATA[news]]></MsgType>
							   <ArticleCount>1</ArticleCount>
							   <Articles>
									<item>
										<Title><![CDATA[点我开始投递！]]></Title>
										<Description><![CDATA[%s]]></Description>
										<PicUrl><![CDATA[%s]]></PicUrl>
										<Url><![CDATA[%s]]></Url>
									</item>
							   </Articles>
							   </xml>";
				
				$msg = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $content, $img_url, $re_url);
				echo $msg;
			}
		}elseif(strtolower($postObj->Event) == 'scan'){	//扫码
			$mid = intval($postObj->EventKey);
			$re = $this->_dealScan($postObj->FromUserName, $postObj->ToUserName, $mid);
			echo $re['msg'];
			
		}
	}
	
	public function unicode2utf8($str) { // unicode编码转化，用于显示emoji表情
		$str = '{"result_str":"' . $str . '"}'; // 组合成json格式
		$strarray = json_decode ( $str, true ); // json转换为数组，利用 JSON 对 \uXXXX 的支持来把转义符恢复为 Unicode 字符
		return $strarray ['result_str'];
	} 
	
	public function getToken(){
		$wxModel 	= new WxModel();
		dump($wxModel->returnToken());
	}
	
	/**
	 *	提现
	 */
	public function cash(){
		$data = $this->request->put();
		
		$user = $this->user;
			
		if(!$user){
			$this->error(['code' => 2, 'msg' => '请先登录!']);	
		}
		
		if(!$user['openid']){
			$this->error(['code' => 0, 'msg' => '未获取openid，请联系管理员！']);
		}
		
		if(!isset($data['name']) || !trim($data['name'])){
			$this->error(['code' => 0, 'msg' => '姓名不能为空！']);
		}
		
		$score = intval($data['score']);
		
		if($user['score'] < 2000){
			$this->error(['code' => 0, 'msg' => '用户积分不能少于2000!']);
		}
		
		//查询积分
		if($user['score'] < $score){
			$this->error(['code' => 0, 'msg' => '积分不足!']);
		}
		
		$job = "app\api\jobs\WxJob@cash";
		$data['user'] = $user;
		$job_data = $data;
		
		$re = \think\Queue::push($job,$job_data, null);
		
		$rate = 100;
		$outData['score'] = $score;
		$outData['money'] = number_format($score / $rate, 2);
		$outData['time'] = date('Y-m-d H:i:s');
		
		$this->success(['code' => 1, 'msg' => '提现申请已提交!'] ,$outData);
		
		/* Db::startTrans(); //开启事务
		$transStatus = false;
		try {
			$user = Db::name('user')->where(['id'=>$user['id']])->find();
			$score = intval($data['score']);
			$rate = 100;
			
			$param['openid'] 	= $user['openid'];
			$param['userName'] 	= $data['name'];
			$param['Sn'] 		= $this->_makeSn($user['id']);
			$param['Fee'] 		= $score / $rate * 100;
			
			if($user['score'] < 2000){
				$this->error(['code' => 0, 'msg' => '用户积分不能少于2000!']);
			}
			
			//查询积分
			if($user['score'] < $score){
				$this->error(['code' => 0, 'msg' => '积分不足!']);
			}
		
		
			//扣除积分
			Db::name('user')->where(['id'=>$user['id']])->setDec('score', $score);
			
			//增加提现记录
			Db::name('cash')->insert([
													'sn' => $param['Sn'],
													'user_id'=>$user['id'],
													'amount'=>$param['Fee'],
													'name'=>$param['userName'],
													'add_time'=>time(),
												]);
												
			//增加用户积分日志
			Db::name('user_score_log')->insert([
													'user_id'=>$user['id'],
													'create_time'=>time(),
													'action'=>'cash:'.$param['Sn'],
													'score'=>'-' .$param['Fee']
												]);

			// 
			$wxModel 	= new WxModel();
			$re = $wxModel->transfers($param);
			//dump($re);
			
			
			
			$transStatus = true;
			
			// 提交事务
			Db::commit();
		} catch (\Exception $e) {

			// 回滚事务
			Db::rollback();
		}
		
		if($transStatus){
			//微信接口成功，更新数据
			if($re['result_code'] == 'SUCCESS'){
				$condition['sn'] = $param['Sn'];
				
				Db::name('cash')->where($condition)->update([
																'payment_state'=>1,
																'payment_time'=>time(),
																'trade_no' => $re['payment_no'],
															]);
				$outData['score'] = $score;
				$outData['money'] = number_format($score / $rate, 2);
				$outData['time'] = date('Y-m-d H:i:s');
				
				//修改用户姓名
				Db::name('user')->where(['id'=>$user['id']])->setfield('user_nickname', $param['userName']);
				
				$this->success(['code' => 1, 'msg' => '提现成功!'] ,$outData);
			}else{
				//失败，还原数据
				Db::name('user')->where(['id'=>$user['id']])->setInc('score', $score);
				
				Db::name('cash')->where(['sn'=>$param['Sn']])->delete();
													
				Db::name('user_score_log')->where(['action'=>['like', '%'.$param['Sn']]])->delete();
				
				$msg = isset($re['err_code_des']) ? $re['err_code_des'] : '';
				
				$this->error(['code' => 0, 'msg' => '提现失败!' . $msg]);
			
			}
		}else{
			$this->error(['code' => 0, 'msg' => '提现失败!']);
		} */
	}
	
	/**
	 *	扫码事件处理
	 */
	private function _dealScan($openid, $appid, $mid){
		$userModel = new UserModel();
		$userMsgModel = new UserMsgModel();
		$wxModel = new WxModel();
		$user = $userModel->get_user_info(['openid'=>$openid], 'id,user_nickname,sex,birthday,score,mobile,more,avatar');
		
		//机器信息
		$id = substr($mid, 0 , strlen($mid) - 1);
		$type = substr($mid, -1 , 1);
		$machine = $userModel->get_machine_info(['id'=>$id], 'id,code');
		
				
		$content = "积分将发送至您的账户。";
		$img_url = cmf_get_domain() . "/public/static/images/auto.png";
		$re_url = cmf_get_domain() . "/#/bindaccount?mid=" . $mid;
		$textTpl = "<xml>
					   <ToUserName><![CDATA[%s]]></ToUserName>
					   <FromUserName><![CDATA[%s]]></FromUserName>
					   <CreateTime>%s</CreateTime>
					   <MsgType><![CDATA[news]]></MsgType>
					   <ArticleCount>1</ArticleCount>
					   <Articles>
							<item>
								<Title><![CDATA[点我开始投递！]]></Title>
								<Description><![CDATA[%s]]></Description>
								<PicUrl><![CDATA[%s]]></PicUrl>
								<Url><![CDATA[%s]]></Url>
							</item>
					   </Articles>
					   </xml>";
		//获取是否关注公众号			   
		if($user){
			$user = $user->toArray();
			$user['subscribe'] = $wxModel->isSubscribe($openid);
		}
		
		//用户不存在，注册
		if(!$user){
			$user = $userModel->addCustomerByOpenID($openid);
			$user = $user->toArray();
			$user['subscribe'] = $wxModel->isSubscribe($openid);
			$info = [
					'content'=>'用户未绑定手机号',
					'user'=>$user,
					'type'=>'30',
					'time'=>time(),
				];
			//$msg = sprintf($textTpl, $openid, $appid, time(), $content, $img_url, $re_url);
		}elseif(!$user['mobile']){
			$info = [
					'content'=>'用户未绑定手机号',
					'user'=>$user,
					'type'=>'30',
					'time'=>time(),
				];
			//$msg = sprintf($textTpl, $openid, $appid, time(), $content, $img_url, $re_url);
			
		}
		
			$content = "扫码成功！请在回收机上进行投递";
			$info = [
							'content'=>'扫码开门',
							'user'=>$user,
							'type'=>'10',
							'time'=>time(),
						];
						
			$textTpl = "<xml>
						   <ToUserName><![CDATA[%s]]></ToUserName>
						   <FromUserName><![CDATA[%s]]></FromUserName>
						   <CreateTime>%s</CreateTime>
						   <MsgType><![CDATA[text]]></MsgType>
						   <Content><![CDATA[%s]]></Content>
						   <FuncFlag>0</FuncFlag>
						   </xml>"; 
						   

			$msg = sprintf($textTpl, $openid, $appid, time(), $content);

		
		//推送消息
		if($machine['code'] != ''){
			if($type == 0){
				$_type = 'm';
			}else{
				$_type = 'j';
			}
			
			$res = $userMsgModel->push($machine['code'], '', $info, $_type);
		} 
						   
		$token = cmf_generate_user_token($user['id'], 'mobile');
		
		
		
		return ['token'=>$token, 'msg'=>$msg];
	}
	
	/**
	 *	生成提现单号
	 */
	private function _makeSn($user_id) {
       return mt_rand(10,99)
              . sprintf('%010d',time() - 946656000)
              . sprintf('%03d', (float) microtime() * 1000)
              . sprintf('%03d', (int) $user_id % 1000);
    }
	
}
