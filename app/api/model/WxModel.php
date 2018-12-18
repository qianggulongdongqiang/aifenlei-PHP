<?php
namespace app\api\model;

use think\Model;

class WxModel extends Model{
	public $APPID = '';
	public $SECRET = '';
	
	public function __construct(){
		parent::__construct();
		
		$wxSettings    = cmf_get_option('wx_settings');
		$this->APPID = $wxSettings['wx_appid'];
		$this->SECRET = $wxSettings['wx_appsecret'];
		$this->MCHID = $wxSettings['wx_mchid'];
		$this->APIKEY = $wxSettings['wx_apikey'];
		
		$this->SSLCERT_PATH = CMF_ROOT . 'data/' . 'apiclient_cert.pem';
		$this->SSLKEY_PATH = CMF_ROOT . 'data/' .  'apiclient_key.pem';
		
	}
	
	public function getCode(){
		$redirect_uri = 'http://'.$_SERVER['HTTP_HOST']. url('customer/get_user_info');
		$code_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" .$this->APPID. "&redirect_uri=".urlencode($redirect_uri)."&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
		Header("Location: $code_url");
		exit();
	}
	
	

	/**
	 *	获取微信用户个人信息
	 */
	public function getWxUserInfo($code){
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='. $this->APPID .'&secret='. $this->SECRET .
            '&code='.$code.'&grant_type=authorization_code';
		$access_token = $this->getUrlContents($url);//通过code获取access_token
        $code_info = json_decode($access_token, true);

        return $code_info;
	}
	
	/**
     * OAuth2.0授权认证
     * @param string $url
     * @return string
     */
    private function getUrlContents($url){
        if (ini_get("allow_url_fopen") == "1") {
            return file_get_contents($url);
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_URL, $url);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }
    }
	
	public function getSignPackage() {
		$jsapiTicket = $this->getJsApiTicket();

		// 注意 URL 一定要动态获取，不能 hardcode.
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		//$url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$url = "$_SERVER[HTTP_REFERER]";

		$timestamp = time();
		$nonceStr = $this->createNonceStr();

		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

		$signature = sha1($string);

		$signPackage = array(
		  "appId"     => $this->APPID,
		  "nonceStr"  => $nonceStr,
		  "timestamp" => $timestamp,
		  "url"       => $url,
		  "signature" => $signature,
		  "rawString" => $string
		);
		return $signPackage; 
	}
	
	/**
	 *	发送模板消息
	 */
	public function sendMsg($content){
		$token = $this->getAccessToken();
		$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token;

		return $this->httpPost($url, $content); 
	}
	
	/**
	 *	获取二维码
	 */
	public function getQRcode($content){
		$token = $this->getAccessToken();
		$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $token;

		return $this->httpPost($url, $content); 
	}
	
	/**
	 *	判断用户是否关注公众号
	 */
	public function isSubscribe($openid){
		$token = $this->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$token."&openid=".$openid."&lang=zh_CN";

		$re = json_decode($this->getUrlContents($url), true);
		
		$subscribe = isset($re['subscribe']) ? $re['subscribe'] : 0;
		
		return $subscribe;
	}
	
	/**
	 *	通过openid获取用户信息
	 */
	public function getUserInfoByOpenid($openid){
		$token = $this->getAccessToken();
		$url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $token . '&openid=' . $openid . '&lang=zh_CN';
		return $this->getUrlContents($url); 
	}
  
	public function returnToken(){
		$token = $this->getAccessToken();
		return $token;
	}
	
	/**
	 *	企业付款
	 */
	public function transfers($param){

        $data = array();
        $data['mch_appid'] = $this->APPID;
        $data['mchid'] = $this->MCHID;
        $data['nonce_str'] = md5(uniqid(mt_rand(), true));
		$data['check_name'] = 'FORCE_CHECK';
        $data['re_user_name'] = $param['userName'];
        $data['partner_trade_no'] = $param['Sn'];
        $data['amount'] = $param['Fee'];
        $data['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
        $data['desc'] = '积分提现';
        $data['openid'] = $param['openid'];

        $sign = $this->sign($data);
        $data['sign'] = $sign;
        $result = $this->postXml('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers', $data, true);

        return $result;
    }
	
	private function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
		  $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	private function getJsApiTicket() {
	// jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
	$data = json_decode($this->get_php_file("jsapi_ticket.php"));
	if ($data->expire_time < time()) {
		$accessToken = $this->getAccessToken();
		// 如果是企业号用以下 URL 获取 ticket
		// $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
		$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
		$res = json_decode($this->httpGet($url));
		
		$ticket = $res->ticket;
		if ($ticket) {
			$data->expire_time = time() + 7000;
			$data->jsapi_ticket = $ticket;
			$this->set_php_file("jsapi_ticket.php", json_encode($data));
		}
	} else {
		$ticket = $data->jsapi_ticket;
	}

		return $ticket;
	}

	private function getAccessToken() {
		// access_token 应该全局存储与更新，以下代码以写入到文件中做示例
		$data = json_decode($this->get_php_file("access_token.php"));
		if ($data->expire_time < time()) {
			// 如果是企业号用以下URL获取access_token
			// $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->APPID&secret=$this->SECRET";
			$res = json_decode($this->httpGet($url));
			
			$access_token = $res->access_token;
			if ($access_token) {
				$data->expire_time = time() + 7000;
				$data->access_token = $access_token;
				$this->set_php_file("access_token.php", json_encode($data));
			}
		} else {
			$access_token = $data->access_token;
		}
		return $access_token;
	}
  
	private function httpGet($url) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		// 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
		// 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_URL, $url);

		$res = curl_exec($curl);
		curl_close($curl);

		return $res;
	}

	private function get_php_file($filename) {
		return trim(substr(file_get_contents(CMF_ROOT . 'data/' . $filename), 15));
	}
	
	private function set_php_file($filename, $content) {
		$fp = fopen(CMF_ROOT . 'data/' . $filename, "w");
		fwrite($fp, "<?php exit();?>" . $content);
		fclose($fp);
	}

	private function httpPost($url, $data) {
		$curl = curl_init();
		$header = array("Accept-Charset: utf-8");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		// 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
		// 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		$res = curl_exec($curl);
		curl_close($curl);

		return $res;
	}
	
	public function sign(array $data){
        ksort($data);

        $a = array();
        foreach ($data as $k => $v) {
            if ((string) $v === '') {
                continue;
            }
            $a[] = "{$k}={$v}";
        }

        $a = implode('&', $a);
        $a .= '&key=' . $this->APIKEY;

        return strtoupper(md5($a));
    }
	
	public function postXml($url, array $data, $cert = false){
        // pack xml
        $xml = $this->arrayToXml($data);

        // curl post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
		
		if($cert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $this->SSLCERT_PATH);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $this->SSLKEY_PATH);
		}else{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		}
        
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $response = curl_exec($ch);
        if (!$response) {
            echo 'CURL Error: ' . curl_errno($ch);
			return false;
        }
        curl_close($ch);

        // unpack xml
        return $this->xmlToArray($response);
    }

    public function arrayToXml(array $data){
        $xml = "<xml>";
        foreach ($data as $k => $v) {
            if (is_numeric($v)) {
                $xml .= "<{$k}>{$v}</{$k}>";
            } else {
                $xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    public function xmlToArray($xml){
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
}