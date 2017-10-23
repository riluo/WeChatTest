<?php
require_once __DIR__.'/vendor/autoload.php';
class WebWeiXinBefore{
    public function __construct($userId){
        $this->DEBUG = false;
        $this->uuid = "";
        $this->base_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin';
        $this->redirect_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage';//
        $this->synckey = '';
        $this->SyncKey = [];
        $this->User = [];
        $this->autoReplyMode = false;
        $this->syncHost = '';
        $this->user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36';
        $this->interactive = false;
        $this->autoOpen = false;
        $this->saveFolder =   getcwd()."/saved/";
        $this->saveSubFolders = ['webwxgeticon'=> 'icons', 'webwxgetheadimg'=> 'headimgs',
            'webwxgetmsgimg'=> 'msgimgs','webwxgetvideo'=> 'videos', 'webwxgetvoice'=> 'voices'];
        $this->appid = 'wx782c26e4c19acffb';
        $this->lang = 'zh_CN';
        $this->lastCheckTs = time();
        $this->TimeOut = 20;  # 同步最短时间间隔（单位：秒）
        $this->media_count = -1;

        umask(0);
        $this->cookieFolder = getcwd()."/cookie/".$userId."/";
        if(!is_dir($this->cookieFolder)){
            mkdir($this->cookieFolder,0777,true);
            chmod($this->cookieFolder, 0777);
        }
        $this->cookie = $this->cookieFolder."cookie.cookie";
        $this->key = $this->cookieFolder."key.key";
        file_put_contents($this->cookie, '');
        chmod($this->cookie, 0777);
        file_put_contents($this->key, '');
        chmod($this->key, 0777);
    }
    public function loadConfig($config){
        if (isset($config['DEBUG'])){
            $this->DEBUG = $config['DEBUG'];
        }
        if (isset($config['autoReplyMode'])){
            $this->autoReplyMode = $config['autoReplyMode'];
        }
        if (isset($config['user_agent'])){
            $this->user_agent = $config['user_agent'];
        }
        if (isset($config['interactive'])){
            $this->interactive = $config['interactive'];
        }
        if (isset($config['autoOpen'])){
            $this->autoOpen = $config['autoOpen'];
        }
    }

    /**
     * 获取
     * @return bool
     */
    public function getUUID(){
        /**
         * https://login.weixin.qq.com/jslogin
         * https://login.wx.qq.com/jslogin
         * https://login.wx1.qq.com/jslogin
         * https://login.wx2.qq.com/jslogin
         */
        $url = 'https://login.wx.qq.com/jslogin';
        $params = [
            'appid'=>$this->appid,
            //'redirect_uri'=> $this->redirect_uri,
            'fun'=>'new',
            'lang'=> $this->lang,
            '_'=>time(),
        ];
        $data = $this->_get($url, $params);
        $regx = '/window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)"/';
        if (preg_match($regx, $data,$pm)){
            $code = $pm[1];
            $this->uuid = $pm[2];
            //return $code == '200';
            return $this->uuid;
        }
        return false;
    }

    public function _run($msg,$func){
        echo($msg);
        if($this->$func()){
            $this->_echo('成功');
            return true;
        }else{
            if($func == 'webwxinit'){
                $this->_echo("失败\n");
                return false;
            }
            $this->_echo("失败\n[*] 退出程序");
            exit();
        }
    }

    public function _echo($data){
        if(is_string($data)){
            echo $data."\n";
        }elseif(is_array($data)){
            print_r($data);
        }elseif(is_object($data)){
            var_dump($data);
        }else{
            echo $data;
        }
    }

    public function _transcoding($data){
        if  (!$data){
            return $data;
        }
        $result = null;
        if (gettype($data) == 'unicode'){
            $result = $data;
        }elseif(gettype($data) == 'string'){
            $result = $data;
        }
        return $result;
    }

    public static function json_encode($json){
        return json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET 请求
     * @param string $url
     */
    private function _get($url,$params=[],$api = false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        $header = [
            'User-Agent: '.$this->user_agent,
            'Referer: https://wx.qq.com/'
        ];
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        if(!empty($params)){
            if(strpos($url,'?')!==false){
                $url .="&".http_build_query($params);
            }else{
                $url .="?".http_build_query($params);
            }
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_TIMEOUT, 36);
        curl_setopt($oCurl, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($oCurl, CURLOPT_COOKIEJAR, $this->cookie);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $param
     * @param boolean $post_file 是否文件上传
     * @return string content
     */
    private function _post($url,$param,$jsonfmt=true,$post_file=false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
            $is_curlFile = true;
        } else {
            $is_curlFile = false;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, false);
            }
        }
        $header = [
            'User-Agent: '.$this->user_agent
        ];
        if($jsonfmt){
            $param = self::json_encode($param);
            $header[] = 'Content-Type: application/json; charset=UTF-8';
            //var_dump($param);
        }
        if (is_string($param)) {
            $strPOST = $param;
        }elseif($post_file) {
            if($is_curlFile) {
                foreach ($param as $key => $val) {
                    if (substr($val, 0, 1) == '@') {
                        $param[$key] = new \CURLFile(realpath(substr($val,1)));
                    }
                }
            }
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  implode("&", $aPOST);
        }

        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        curl_setopt($oCurl, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($oCurl, CURLOPT_COOKIEJAR, $this->cookie);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            if($jsonfmt)
                return json_decode($sContent,true);
            return $sContent;
        }else{
            return false;
        }
    }

}


$userId = rand(10001,19999);
$weixin = new WebWeiXinBefore($userId);
$weixin->loadConfig([
    'interactive'=>true,
    //'autoReplyMode'=>true,
    //'DEBUG'=>true
]);
$uuid = $weixin->getUUID();
$url = 'https://login.weixin.qq.com/l/'.$uuid;
$imgName = time();
use PHPQRCode\QRcode;
$code = new QRcode();
$code::png($url, "./img/".$imgName.".png", 'H', 4, 2);
?>
<span id="uuid" style="display: none;"><?php echo $uuid;?></span>
<p align="center"><img src="./img/<?php echo $imgName;?>.png" style="margin-top:10px;" /></p>
<p align="center">扫描后点击以下按钮跳转</p>
<p align="center"><input type="button" value="跳转" onClick="window.location.href='./frontend/index.html'"></p>
<?php
//发起client请求
$client = new swoole_client(SWOOLE_SOCK_TCP);
if (!$client->connect('127.0.0.1', 9501, -1))
{
    exit("connect failed. Error: {$client->errCode}\n");
}$client->send($uuid.",".$userId);
sleep(1);
//$client->close();
//echo "send...", PHP_EOL;
?>
