<?php
define('APPID','wx51068f33468988d2');
define('APPSECRET','cb39814033ab87fbce4a8a9d8c724ad8');
define('TOKEN','weixinopen');

require './wechat.class.php';
$wechat = new WeChat(APPID, APPSECRET, TOKEN);
$wechat->responseMsg();//处理从微信平台发送的信息
?>