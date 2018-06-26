<?php
require_once("utils/mysql.config.php");
require_once("utils/phpMysql.class.php");
	
class JSSDK {
  private $appId;
  private $appSecret;
  private $url;
  private $oMysql;

  public function __construct($appId, $appSecret, $url) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
    $this->url = $url;
	
    $this->oMysql = new MySQL($database, $username, $password, $hostname);
  }

  public function getSignPackage() {
    $jsapiTicket = $this->getJsApiTicket();

    // 注意 URL 一定要动态获取，不能 hardcode.
    $url = $this->url;

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
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
    $data = $this->oMysql->Select('json', array('id' => 2));

    if ($data[0]['expire_time'] < time()) {
      $accessToken = $this->getAccessToken();
      // 如果是企业号用以下 URL 获取 ticket
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      if ($ticket) {
        $expire_time = time() + 7000;     

        $this->oMysql->Update('json', array('expire_time' => $expire_time, 'code' => $ticket), array('id' => 2));
      }
    } else {
      $ticket = $data[0]['code'];
    }

    return $ticket;
  }

  private function getAccessToken() {
    $data = $this->oMysql->Select('json', array('id' => 1));
    if ($data[0]['expire_time'] < time()) {
      // 如果是企业号用以下URL获取access_token
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
      $res = json_decode($this->httpGet($url));
      $access_token = $res->access_token;
      if ($access_token) {
        $expire_time = time() + 7000;

        $this->oMysql->Update('json', array('expire_time' => $expire_time, 'code' => $access_token), array('id' => 1));
      }
    } else {
      $access_token = $data[0]['code'];
    }
    return $access_token;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }
}

