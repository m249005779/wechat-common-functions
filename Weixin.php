<?php
/**
 * Created by PhpStorm.
 * User: SuperYee
 * Date: 2018/4/23
 * Time: 16:27
 * 微信网页开发类
 */

namespace weixin;


use think\Cache;
use think\Config;
use think\Log;

class Weixin
{
    public $_appid;
    public $_appsecret;
    public $_redirect_uri;
    public $_access_token;
    public $_jsapi_ticket;

    public function __construct ($appid = '', $appsecret = '', $redirect_uri = '') {
        $this->_appid = $appid ? $appid : '自己的appid';
        $this->_appsecret = $appsecret ? $appsecret : '自己的app_secret';
        $this->_redirect_uri = $redirect_uri ? $redirect_uri : '自己的授权回调地址';
    }


    /*
     * 生成授权链接
     * @param $stat string 自定义参数
     * @param $scope string 授权方式
     * @return string
     */
    public function createAuthUrl ($state = '', $scope = 'snsapi_userinfo') {
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
        $url .= 'appid=' . $this->_appid . '&redirect_uri=' . urlencode($this->_redirect_uri);
        $url .= '&response_type=code&state=' . $state . '&scope=' . $scope . '#wechat_redirect';
        return $url;
    }


    //获取换取用户信息的access_token,openid
    public function getOpenid ($code) {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';
        $url .= 'appid=' . $this->_appid . '&secret=' . $this->_appsecret . '&code=' . $code . '&grant_type=authorization_code';
        $result = $this->getData($url, 'openid_accesstoken');
        return $result;
    }

    //获取用户信息
    public function getUserInfo ($code) {
        $data = $this->getOpenid($code);
        if ($data) {
            $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $data['access_token'] . '&openid=' . $data['openid'] . '&lang=zh_CN';
            $userInfo = $this->getData($url, 'user_info');
            return $userInfo;
        } else {
            return false;
        }
    }

    //http get 方法 默认返回数组
    private function httpGet ($url, $data_type = 'array') {
        $cl = curl_init();
        if (stripos($url, 'https://') !== FALSE) {
            curl_setopt($cl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($cl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($cl, CURLOPT_SSLVERSION, 1);
        }
        curl_setopt($cl, CURLOPT_URL, $url);
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($cl);
        $status = curl_getinfo($cl);
        curl_close($cl);
        if (isset($status['http_code']) && $status['http_code'] == 200) {
            if ($data_type == 'json') {
                $content = json_decode($content);
            }
            return json_decode($content, true);
        } else {
            return FALSE;
        }
    }

    //http post 方法 默认返回数组
    private function httpPost ($url, $fields, $data_type = 'array') {
        $cl = curl_init();
        if (stripos($url, 'https://') !== FALSE) {
            curl_setopt($cl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($cl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($cl, CURLOPT_SSLVERSION, 1);
        }
        curl_setopt($cl, CURLOPT_URL, $url);
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cl, CURLOPT_POST, true);
        curl_setopt($cl, CURLOPT_POSTFIELDS, $fields);
        $content = curl_exec($cl);
        $status = curl_getinfo($cl);
        curl_close($cl);
        if (isset($status['http_code']) && $status['http_code'] == 200) {
            if ($data_type == 'json') {
                $content = json_decode($content);
            }
            return json_decode($content, true);
        } else {
            return FALSE;
        }
    }

    //记录日志
    public function writeLog ($actionname, $errcode = '') {
        //Log::write('微信公众号错误日志方法：' . $actionname . ' === CODE：' . $errcode, 'WXERRCODE');
        //记录微信错误日志
        return true;
    }

    //获取基本接口access_token
    public function getAccessToken () {
        $accessToken = '缓存中读取';//Cache::get('access_token');
        if ($accessToken) {
            return $accessToken;
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->_appid . '&secret=' . $this->_appsecret;
            $data = $this->getData($url, 'access_token');
            if ($data) {
                $accessToken = $data['access_token'];
                //将$accessToken存入缓存并设置有效期
                //Cache::set('access_token', $accessToken, 7000);
            } else {
                $accessToken = false;
            }
            return $accessToken;
        }
    }

    // 获取 jssdk 签名
    private function getJsapiTicket () {
        $tiket = '缓存中读取';//Cache::get('jsapi_ticket');
        if ($tiket) {
            return $tiket;
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $this->getAccessToken() . '&type=jsapi';
            $data = $this->getData($url, 'jsapi_ticket');
            if (!$data) {
                $tiket = false;
            } else {
                $tiket = $data['ticket'];
                //将$tiket存入缓存，并设置有效期
                //Cache::set('jsapi_ticket', $tiket, 7000);
            }
            return $tiket;
        }
    }

    /**
     * 获取数据curl get
     * @param $url string 获取链接
     * @param $input string 获取字段
     * @return bool|mixed
     */
    private function getData ($url, $input) {
        $retry = 3;
        while ($retry--) {
            $data1 = $this->httpGet($url);
            if (!$data1) {
                continue;
            }
            break;
        }
        if (isset($data1['errcode']) && $data1['errcode']) {
            $this->writeLog($input, $data1['errcode']);
            return false;
        }
        return $data1;
    }

    protected function postData ($url, $field, $input) {
        $retry = 3;
        while ($retry--) {
            $data1 = $this->httpPost($url, $field);
            if (!$data1) {
                continue;
            }
            break;
        }
        if (isset($data1['errcode']) && $data1['errcode']) {
            $this->writeLog($input, $data1['errcode']);
            return false;
        }
        return $data1;
    }

    //生成随机字符串
    public function createNonceStr ($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    //生成签名
    public function creatSign () {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $jsapiTicket = $this->getJsapiTicket();
        if (!$jsapiTicket) {
            return false;
        }
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array(
            "appId"     => $this->_appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    // 发送模板消息
    /**
     * @param $touser string 用户openid
     * @param $msg array 要发送的消息
     * @param $template_id string 模板消息ID
     * @param string $url 模板消息url
     * @param string $color 字体颜色
     * @return bool|string
     */
    public function sendTplMsg ($touser, $msg, $template_id, $url = '', $color = '#FF683F') {
        if (!$touser || !$template_id) {
            return false;
        }
        if (!is_array($msg) || !$msg) {
            return false;
        }
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        $postUrl = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $accessToken;
        $data = array(
            'touser'      => trim($touser),
            'template_id' => trim($template_id),
            'url'         => $url,
        );
        $message = array();
        $message['first'] = array(
            'value' => $msg['first'],
            'color' => $color
        );
        $message['keyword1'] = array(
            'value' => $msg['keyword1'],
            'color' => $color
        );
        $message['keyword2'] = array(
            'value' => $msg['keyword2'],
            'color' => $color
        );
        if (array_key_exists('keyword3', $msg)) {
            $message['keyword3'] = array(
                'value' => $msg['keyword3'],
                'color' => $color
            );
        }
        if (array_key_exists('keyword4', $msg)) {
            $message['keyword4'] = array(
                'value' => $msg['keyword4'],
                'color' => $color
            );
        }
        $message['remark'] = array(
            'value' => $msg['remark'],
            'color' => $color
        );
        $data['data'] = $message;
        $data = json_encode($data);
        $result = $this->httpPost($postUrl, $data);
        if (!$result) {
            return false;
        }
        if ($result['errcode'] != 0 || $result['errmsg'] != 'ok') {
            $this->writeLog('sendTplMsg', $result['errcode']);
            return false;
        }
        return true;
    }
}