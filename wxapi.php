<?php
/**
  * wechat php test
  */

$wechatObj = new wechatCallbackapiTest();
/*用于微信发红包*/

// $wechatObj->valid();
$wechatObj->responseMsg();//回复

class wechatCallbackapiTest
{
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

		//extract post data
		if (!empty($postStr)){
				
				$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
				$fromUsername = $postObj->FromUserName;
				$toUsername = $postObj->ToUserName;
				$keyword = trim($postObj->Content);
				$time = time();

				/*文字回复*/
				$textTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[%s]]></MsgType>
							<Content><![CDATA[%s]]></Content>
							<FuncFlag>0</FuncFlag>
							</xml>";    

				/*图文回复*/
				$itemTpl = "<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[%s]]></MsgType>
						<ArticleCount>1</ArticleCount>
						<Articles>
						<item>
						<Title><![CDATA[%s]]></Title> 
						<Description><![CDATA[%s]]></Description>
						<PicUrl><![CDATA[%s]]></PicUrl>
						<Url><![CDATA[%s]]></Url>
						</item>
						</Articles>
						</xml>";



				if(!empty( $keyword )){
					$keyworde = $this -> FindKeyword($keyword,$fromUsername);
					switch ($keyworde) {
						case '红包':
							// $msgType = "news";
							// $contentStr = "恭喜你获得一个红包！";
							// $contentStr2 = "点击图片领取你的红包！";
							// // $PicUrl = "http://imgsrc.baidu.com/forum/w%3D580/sign=6926f2cad488d43ff0a991fa4d1fd2aa/3e8ad5dcd100baa1583412044510b912c9fc2ea8.jpg";
							// $url = "http://i.shuilaile.com/index.php?s=/Home/Get/pay/openid/".$fromUsername."/code/".$keyword.".html";
							// $resultStr = sprintf($itemTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr, $contentStr2, $PicUrl, $url);
							// echo $resultStr;
							
							//执行发红包
							$this -> pay($fromUsername,$keyword);
							break;
						default:
							$msgType = "text";
							$contentStr = $keyworde;
							// $contentStr = "Welcome to wechat world!";
							$resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
							echo $resultStr;
							break;
					}
				}else{
					echo "Input something...";
				}

		}else {
			echo "";
			exit;
		}
	}


	public function FindKeyword($key="",$FromUserName){
		
		// 获取查询结果
		$row=$this -> find($key);

		if (!empty($row)) {
			$time = time();
			/*兑换码认证*/
			if ($row['isplay'] == '1') {
				$ret = '该兑换码已使用！';
				return $ret;
			}
			if ($row['isactivate'] != '1') {
				$ret = '该兑换码未激活！';
				return $ret;
			}
			if ($time > $row['valid']) {
				$ret = '抱歉,该兑换码已过期！';
				return $ret;
			}


			/*确认兑换码操作*/
			// $this -> playon($key,$FromUserName);

			$ret = '红包';
		}else{
			$ret = '无效的兑换码';
		}

		return $ret;
	}
		
	private function checkSignature()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];

		$wxinfo = $this -> Wxapi();
				
		$token = $wxinfo['token'];
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * [find 兑换码查询]
	 * @param  string $key [兑换码]
	 * @return [type]      [description]
	 */
	public function find($key=''){
		$uid = $_GET["id"];
		$conn=mysql_connect("localhost", "root", "shuilaile@123w");            
 		$result=mysql_db_query("ishuilaile", "SELECT * FROM `sllhongbao` where redeem = '".$key."' AND caresnameid = '".$uid."'", $conn);
 		mysql_close($conn);
 		return mysql_fetch_array($result);
	}


	/**
	 * [playon 记录用户操作]
	 * @param  [type] $key          [兑换码]
	 * @param  [type] $FromUserName [用户openid]
	 * @return [type]               [description]
	 */
	public function playon($key,$FromUserName){
		$uid = $_GET["id"];
		$conn=mysql_connect("localhost", "root", "shuilaile@123w");      
		mysql_select_db("ishuilaile", $conn);      
		mysql_query("UPDATE sllhongbao SET playtime='".time()."', playname= '".$FromUserName."' , isplay='1' WHERE redeem='".$key."' AND caresnameid = '".$uid."'", $conn);
		mysql_close($conn);
		return true;
	}





	/**
	 * [Wxapi description] 支付参数
	 */
	public function Wxapi(){
		$uid = $_GET["id"];
		$conn=mysql_connect("localhost", "root", "shuilaile@123w");            
 		$result=mysql_db_query("ishuilaile", "SELECT * FROM `sllwechat` where uid = '".$uid."'", $conn);
 		mysql_close($conn);
 		$wxinfo = mysql_fetch_array($result);

		return $wxinfo;
	}
	/**
	 * 微信支付
	 * 
	 * @param string $openid 用户openid
	 */
	public function pay($openid,$redeem){
		//记录兑换码使用情况
		$this -> playon($redeem,$openid);
		
		$wxinfo = $this -> Wxapi();

		$money = $this -> find($redeem);


		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";

		if (empty($openid)) {
			echo "信息错误,请重试！";exit();
		}
		   
		$mch_billno = $wxinfo['mchid'] . date ( "YmdHis", time () ) . rand ( 1000, 9999 );      //商户订单号
		$mch_id = $wxinfo['mchid'];                         //微信支付分配的商户号
		$wxappid = $wxinfo['appid'];        //公众账号appid
		$send_name = $wxinfo['title'];                          //商户名称
		$re_openid = $openid;         //用户openid
		$total_amount = $money['money']*100;                              // 付款金额，单位分
		$total_num = 1;                                          //红包发放总人数
		$wishing = "感谢您购买我们的产品";                             //红包祝福语
		$client_ip = $this -> get_client_ip();                //Ip地址
		$act_name = "购买有礼";                         //活动名称
		$remark = "测试";                                      //备注
		$apikey = $wxinfo['apikey'];   // key 商户后台设置的  微信商户平台(pay.weixin.qq.com)-->账户设置-->API安全-->密钥设置
		$nonce_str = $this -> great_rand();                   //随机字符串，不长于32位
		$m_arr = array (
			'mch_billno' => $mch_billno,
			'mch_id' => $mch_id,
			'wxappid' => $wxappid,
			'send_name' => $send_name,
			're_openid' => $re_openid,
			'total_amount' => $total_amount,
			'total_num' => $total_num,
			'wishing' => $wishing,
			'client_ip' => $client_ip,
			'act_name' => $act_name,
			'remark' => $remark,
			'nonce_str'=> $nonce_str
		);

		// var_dump($m_arr);exit;

		array_filter ( $m_arr ); // 清空参数为空的数组元素
		ksort ( $m_arr ); // 按照参数名ASCII码从小到大排序

		
		
		$stringA = "";
		foreach ( $m_arr as $key => $row ) {
		$stringA .= "&" . $key . '=' . $row;
		}
		$stringA = substr ( $stringA, 1 );
		// 拼接API密钥：
		$stringSignTemp = $stringA."&key=" . $apikey;
		$sign = strtoupper ( md5 ( $stringSignTemp ) );         //签名

		$textTpl     = 	'<xml>
				<sign><![CDATA[%s]]></sign>
				<mch_billno><![CDATA[%s]]></mch_billno>
				<mch_id><![CDATA[%s]]></mch_id>
				<wxappid><![CDATA[%s]]></wxappid>
				<send_name><![CDATA[%s]]></send_name>
				<re_openid><![CDATA[%s]]></re_openid>
				<total_amount><![CDATA[%s]]></total_amount>
				<total_num><![CDATA[%s]]></total_num>
				<wishing><![CDATA[%s]]></wishing>
				<client_ip><![CDATA[%s]]></client_ip>
				<act_name><![CDATA[%s]]></act_name>
				<remark><![CDATA[%s]]></remark>
				<nonce_str><![CDATA[%s]]></nonce_str>
				</xml>';

		$resultStr = sprintf($textTpl, $sign, $mch_billno, $mch_id, $wxappid, $send_name,$re_openid,$total_amount,$total_num,$wishing,$client_ip,$act_name,$remark,$nonce_str);

		$paymoney = $this -> curl_post_ssl($url, $resultStr);
		return $paymoney;
	}

	function curl_post_ssl($url, $vars, $second=30,$aHeader=array()){
		$ch = curl_init();
		//超时时间
		curl_setopt($ch,CURLOPT_TIMEOUT,$second);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
		//这里设置代理，如果有的话
		//curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
		//curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
	   
		//以下两种方式需选择一种
	   
		//第一种方法，cert 与 key 分别属于两个.pem文件
		//默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
		curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/ThinkPHP/Cert/apiclient_cert.pem');
		// 默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
		curl_setopt($ch,CURLOPT_SSLKEY,getcwd().'/ThinkPHP/Cert/apiclient_key.pem');
		// 默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
		curl_setopt($ch,CURLOPT_CAINFO,getcwd().'/ThinkPHP/Cert/rootca.pem');
	   	
	   	// echo getcwd().'/ThinkPHP/Cert/apiclient_key.pem';exit;
		//第二种方式，两个文件合成一个.pem文件
		//curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');
	 
		if( count($aHeader) >= 1 ){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
		}
	 
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
		$data = curl_exec($ch);
		if($data){
			curl_close($ch);
			return $data;
		}
		else {
			$error = curl_errno($ch);
			echo "call faild, errorCode:$error\n";
			curl_close($ch);
			return false;
		}
	}


	/**
	 * 生成随机数
	 * 
	 */     
	public function great_rand(){
		$str = '1234567890abcdefghijklmnopqrstuvwxyz';
		$t1 = "";
		for($i=0;$i<30;$i++){
			$j=rand(0,35);
			$t1 .= $str[$j];
		}
		return $t1;    
	}

	 /**
	 * 获取当前服务器的IP
	 * @return Ambigous <string, unknown>
	 */
	function get_client_ip()
	{
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$cip = $_SERVER['REMOTE_ADDR'];
		} elseif (getenv("REMOTE_ADDR")) {
			$cip = getenv("REMOTE_ADDR");
		} elseif (getenv("HTTP_CLIENT_IP")) {
			$cip = getenv("HTTP_CLIENT_IP");
		} else {
			$cip = "127.0.0.1";
		}
		return $cip;
	}
}

?>