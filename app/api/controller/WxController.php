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
		
		$postArr = file_get_contents('php://input');
		
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
				return $wxModel->sendMsg($msg);
			}
		}elseif(strtolower($postObj->Event) == 'subscribe'){	//关注
			if($postObj->EventKey){	//扫码关注
				$key = explode('_', $postObj->EventKey);
				$mid = isset($key[1]) ? intval($key[1]) : 0;
				
				if($mid > 0){
					$re = $this->_dealScan($postObj->FromUserName, $postObj->ToUserName, $mid);
					echo $re['msg'];
				}	
			}else{	//关注回复
				$content = "恭喜，关注了我们，是您迈向垃圾分类回收的第一步，戳下方链接，了解如何投递垃圾获取积分，让我们一起把家园变得更美好~\n<a href='http://mp.weixin.qq.com/s?__biz=MzU1NjU3NDUwNQ==&mid=100000108&idx=1&sn=0d2c911eb460ddaf76f61fb0fdd64334&chksm=7bc3b32a4cb43a3c5f7ed4e93d98ecd0dfa737968dd209fb097cb29b1a7a6f67ceee005bf51d#rd'>戳这里</a>";
				$textTpl = "<xml>
							   <ToUserName><![CDATA[%s]]></ToUserName>
							   <FromUserName><![CDATA[%s]]></FromUserName>
							   <CreateTime>%s</CreateTime>
							   <MsgType><![CDATA[text]]></MsgType>
							   <Content><![CDATA[%s]]></Content>
							   <FuncFlag>0</FuncFlag>
							   </xml>";
				$msg = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $content);
				echo $msg;
			}
		}elseif(strtolower($postObj->Event) == 'scan'){	//扫码
			$mid = intval($postObj->EventKey);
			
			$re = $this->_dealScan($postObj->FromUserName, $postObj->ToUserName, $mid);
			echo $re['msg'];
			
		}
	}
	
	public function getToken(){
		$wxModel 	= new WxModel();
		dump($wxModel->returnToken());
	}
	
	/**
	 *	扫码事件处理
	 */
	private function _dealScan($openid, $appid, $mid){
		$userModel = new UserModel();
		$user = $userModel->get_user_info(['openid'=>$openid], 'id,user_nickname,sex,birthday,score,mobile,more');
		//用户不存在，注册
		if(!$user){
			$user = $userModel->addCustomerByOpenID($openid);
			$content = "最后一步！发放积分需绑定手机！点击链接，绑定手机后自动完成登录<a href='" . cmf_get_domain() . "/#/bindaccount?mid=" . $mid . "'>【链接】</a>";
		}else{
			$content = "恭喜登陆成功！请在自助机确认身份并开始投递！";	
			
			//推送消息
			$machine = $userModel->get_machine_info(['id'=>$mid], 'id,code');
			
			if($machine['code'] != ''){
				$userMsgModel = new UserMsgModel();
				
				$info = [
							'content'=>'扫码开门',
							'user'=>$user->toArray(),
							'type'=>'10'
						];
				
				$res = $userMsgModel->push($machine['code'], '', $info, 'm');
			} 
		}
		
		$textTpl = "<xml>
						   <ToUserName><![CDATA[%s]]></ToUserName>
						   <FromUserName><![CDATA[%s]]></FromUserName>
						   <CreateTime>%s</CreateTime>
						   <MsgType><![CDATA[text]]></MsgType>
						   <Content><![CDATA[%s]]></Content>
						   <FuncFlag>0</FuncFlag>
						   </xml>";
						   
		$token = cmf_generate_user_token($user['id'], 'mobile');
		
		$msg = sprintf($textTpl, $openid, $appid, time(), $content);
		
		return ['token'=>$token, 'msg'=>$msg];
	}
	
}
