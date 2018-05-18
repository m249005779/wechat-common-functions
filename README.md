# PHP微信网页开发常用功能 #
***
## 主要封装的方法 ##
- 生成授权登录的连接
- 获取换取用户信息的access_token,openid
- 获取用户信息
- 获取基本接口access_token
- 获取 JSSDK jsapi_ticket
- 生成JSSDK签名
- 发送模板消息
***
## 使用方法 ##
### 以ThinkPHP5为例：###
在TP5的extend目录下创建weixin目录，讲代码放入weixin目录。
修改如下代码:
```PHP
<?php
namespace weixin;
use think\Cache;//缓存类
use think\Log;//日志类
class Weixin
{
    public $_appid;
	...
```
```
//记录日志
public function writeLog ($actionname, $errcode = '') {
    Log::write('微信公众号错误日志方法：' . $actionname . ' === CODE：' . $errcode, 'WXERRCODE');
    return true;
}
...
 //获取基本接口access_token
public function getAccessToken () {
    $accessToken = Cache::get('access_token');//tp5的缓存方法
    if ($accessToken) {
        return $accessToken;
    } else {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->_appid . '&secret=' . $this->_appsecret;
        $data = $this->getData($url, 'access_token');
        if ($data) {
            $accessToken = $data['access_token'];
            Cache::set('access_token', $accessToken, 7000);//缓存access_token
        } else {
            $accessToken = false;
        }
        return $accessToken;
    }
}
...
// 获取 jssdk 签名
private function getJsapiTicket () {
    $tiket = Cache::get('jsapi_ticket');//同样是读取缓存
    if ($tiket) {
        return $tiket;
    } else {
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $this->getAccessToken() . '&type=jsapi';
        $data = $this->getData($url, 'jsapi_ticket');
        if (!$data) {
            $tiket = false;
        } else {
            $tiket = $data['ticket'];
            Cache::set('jsapi_ticket', $tiket, 7000);//缓存jsapi_ticket
        }
        return $tiket;
    }
}
```
在需要使用的地方例如下面代码
```
<?php
namespace app\index\controller;

use \weixin\Weixin;
use think\Controller;

class Index extends Controller
{
	protected $_weixin;
	public function _initialize () {
		$this->_weixin = new Weixin();	
	}
	//微信授权回调获取CODE方法
	public function getcode () {
		$code = $_GET['code'];
		//这样就获取到了用户信息
		$userInfo = $this->_weixin->getUserInfo($code);
	}
}
```
引入类，将代码中需要缓存的数据写入自己框架的缓存即可