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
			case '道金':
				$contentStr='主人，你好帅！';
				break;
			default:
				$pos  = strpos($content,'贵荣');
				if($pos === false)
				{	
					//$data['key'] = '71c81724c6054dbfa7dc6ab569ed9106';
					//$data['info'] = $content;
					$curl = 'http://api.qingyunke.com/api.php?key=free&appid=0&msg='.$content;
					//$contentStr = json_encode($data);
					$content = $this->_request($curl, false, 'GET', null);
					$content = json_decode($content);
					$contentStr = htmlspecialchars($content->content);
				}else
				{
					$contentStr = $this->_getWords();
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
	
	public function _getWords()
	{
		$words=array(
			"世上有两种女孩最可爱，一种是漂亮；一种是聪慧，而你是聪明的漂亮女孩。",
			"我认为世界上最漂亮的女人是维纳斯，接着就是你！",
			"我好幸运呀，贾宝玉身边有花香袭人的美人，我有眼如水杏般的可爱少女，不比他差，哈哈。",
			"智慧女人是金子，气质女人是钻石，聪明女人是宝藏，可爱女人是名画，据我考证，你是世界上最大的宝藏，里面装满了金子钻石名画。",
			"求你不要再打扮了，给其他的女人留点自信吧，",
			"你的眼神如此撩人，让我忍不住地去吻她，别动，你会让我越陷越深的",
			"你的头发真美，尤其那种香味让我心神恍惚，哪是你自己的味道。 ",
			"看你不再看美女请你不要经常出现在街上好吗？不然交通事故会增加的！",
			"今天肯定没月亮了，因为月亮的光辉都给你遮盖了。 ",
			"春花秋月，是诗人们歌颂的情景，可是我对于它，却感到十分平凡。只有你嵌着梨涡的笑容，才是我眼中最美的偶象。",
			"从你的言谈话语中看见你的高贵，从你的举止面貌中看见你的清秀。",
			"你是那样地美，美得象一首抒情诗。你全身充溢着少女的纯情和青春的风采。留给我印象最深的是你那双湖水般清澈的眸子，以及长长的、一闪一闪的睫毛。像是探询，像是关切，像是问候.",
			"你像一片轻柔的云在我眼前飘来飘去，你清丽秀雅的脸上荡漾着春天般美丽的笑容。在你那双又大又亮的眼睛里，我总能捕捉到你的宁静，你的热烈，你的聪颖，你的敏感。 ",
			"你笑起来的样子最为动人，两片薄薄的嘴唇在笑，长长的眼睛在笑，腮上两个陷得很举动的酒窝也在笑。"
		); 
		$pos = (rand(0,13);
		return $words[$pos];
		
	}
}
?>
