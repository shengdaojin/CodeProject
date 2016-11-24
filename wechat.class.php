<?php 
class WeChat{
	private $_appid;
	private $_appsecret;
	private $_token;
	
	public function __construct($_appid, $_appsecret, $_token){
		$this->_appid = $_appid;
		$this->_appsecret = $_appsecret;
		$this->_token = $_token;
	}
	
	public function valid()
    {
        $echoStr = $_GET["echostr"];
        //valid signature , option
        if($this->checkSignature()){
        	echo $echoStr;
        	exit;
        }
    }

    public function responseMsg()
    {
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		
		//解析XML到对象
		$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		switch($postObj->MsgType){
			case 'event':
				$this->_doEvent($postObj);
				exit;
			case 'text':
				$this->_doText($postObj);
				exit;
			case 'image':
				$this->_doImage($postObj);
				exit;
			case 'voice':
				$this->_doVoice($postObj);
				break;
			default:
			;
		}
	}
	private function _doText($postObj){
       $fromUsername = $postObj->FromUserName;
       $toUsername = $postObj->ToUserName;        
	   $time = time();
	   $textTpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[%s]]></MsgType>
			<Content><![CDATA[%s]]></Content>
			<FuncFlag>0</FuncFlag>
		</xml>";
		$msgType = "text";
		$content = str_replace(' ','',$postObj->Content);
		switch($content){
			case '盛道金':
				$contentStr='主人，你好帅！';
				break;
			default:
				$pos  = strpos($content,'道金');
				if($pos === false)
				{		
					$curl = 'http://api.qingyunke.com/api.php?key=free&appid=0&msg='.$content;
					$content = $this->_request($curl, false, 'GET', null);
					$content = json_decode($content);
					$contentStr = htmlspecialchars($content->content);
				}else
				{
					$contentStr='主人，你好帅！';
				}
		}
        $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
        echo $resultStr;
    }
	
	private function _doEvent($postObj){ //用于将来处理事件
		;
	}
	private function _doImage($postObj){ //用于将来处理用户发送的图像
		;
	}
	
	private function _doVoice($postObj){ //处理用户的声音信息
		;
	}
	private function checkSignature()
	{
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
		$token = $this->_token;
		$tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
	
	public function _request($curl, $https = true, $method = 'GET', $data = null){
		$ch = curl_init(); // 初始化curl
		curl_setopt($ch, CURLOPT_URL, $curl); //设置访问的 URL
		curl_setopt($ch, CURLOPT_HEADER, false); //放弃 URL 的头信息
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而不直接输出
		if($https){ //判断是否是使用 https 协议
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不做服务器的验证
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);  //做服务器的证书验证
		}
		if($method == 'POST'){ //是否是 POST 请求
			curl_setopt($ch, CURLOPT_POST, true); //设置为 POST 请求
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置POST的请求数据
		}
		$content = curl_exec($ch); //开始访问指定URL
		curl_close($ch);//关闭 cURL 释放资源
		return $content;
	}
	
	public function _getAccessToken(){ //获取Access Token
		$file = './accesstoken';	//设置Access Token的存放位置
		if(file_exists($file)){
			$content = file_get_contents($file); //读取文档
			$content = json_decode($content); //解析json数据
			if(time() - filemtime($file) < $content->expires_in) //判断access token是否过期
				return $content->access_token;
		}
		$curl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->_appid.'&secret='.$this->_appsecret; //通过该 URL 获取Access Token
		$content = $this->_request($curl);  //发送请求
		file_put_contents($file, $content);//保存Access Token 到文件
		$content = json_decode($content); //解析json
		return $content->access_token; 
	}

	public function _getTicket($sceneid, $type='temp', $expire_seconds=604800){ //获取Ticket，用于换取二维码
		if($type=='temp'){ //临时二维码
			$data = '{"expire_seconds": %s, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($data, $expire_seconds, $sceneid);
		} else {//永久二维码
			$data = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($data, $sceneid);
		}
		$curl = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$this->_getAccessToken();  //通过该URL 获取 Ticket 
		$content = $this->_request($curl, true, 'POST', $data); 
		$content = json_decode($content);
		return $content->ticket;
	}
	 public function _getQRCode($sceneid, $type = 'temp', $expire_seconds = 604800){//获取二维码
		 $ticket = $this->_getTicket($sceneid, $type, $expire_seconds); //获取 Ticket
		 $content = $this->_request('https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket));
		 return $content;
	 }
}
?>
