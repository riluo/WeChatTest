#!/usr/bin/env php
<?php
require_once 'QRcode.class.php';

class WebWeiXinBefore{
    public function __construct($userId){
        $this->DEBUG = false;
        $this->uuid = "";
        $this->userId = $userId;
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
        chmod($this->cookie, 0777);
        file_put_contents($this->cookie, '');
        chmod($this->key, 0777);
        file_put_contents($this->key, '');
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
            return $code == '200';
        } 
        return false;
	}

	public function genQRCode(){
        if(PHP_OS !='Darwin'&&strpos(PHP_OS, 'win')!==false){
            $this->_showQRCodeImg();
        }else{
            $this->_str2qr('https://login.weixin.qq.com/l/' . $this->uuid);
        }
	}
    public function _showQRCodeImg(){
        $url = 'https://login.weixin.qq.com/qrcode/' . $this->uuid;
        $params = [
            't'=> 'webwx',
            '_'=> time()
        ];

        $data = $this->_post($url, $params, false);
        $QRCODE_PATH = $this->_saveFile('qrcode.jpg', $data, '_showQRCodeImg');
        //os.startfile(QRCODE_PATH)
        //TODO:没有完成
        system($QRCODE_PATH);
    }

    public function _saveFile($filename, $data, $api=null){
        $fn = $filename;
        if (isset($this->saveSubFolders[$api])){
            $dirName = $this->saveFolder.$this->saveSubFolders[$api];
            umask(0);
            if(!is_dir($dirName)){
                mkdir($dirName,0777,true);
                chmod($dirName, 0777);
            }
            $fn = $dirName.'/'. $filename;
            $this->_echo(sprintf('Saved file: %s' , $fn));
            //file_put_contents($fn, $data);
            $f = fopen($fn, 'wb');
            if($f){
                fwrite($f,$data);
                fclose($f);
            }else{
                $this->_echo('[*] 保存失败 - '.$fn);
            }
        }
        return $fn;
    }

	//开始登录
	public function start(){
		$this->_echo('[*] 微信网页版 ... 开动');
        $this->_run('[*] 正在获取 uuid ... ', 'getUUID');
        $this->_echo('[*] 正在获取二维码 ... 成功');
        $this->genQRCode();
        $this->_echo('[*] 请使用微信扫描二维码以登录 ... ');
        $this->_echo($this->uuid);
        $this->_echo("/usr/bin/php ".__DIR__."/WebWeiXin.php ".$this->uuid);

        $cmd = "/usr/bin/php ".__DIR__."/WebWeiXin.php ".$this->uuid." ".$this->userId;
        pclose(popen($cmd.' > /tmp/'.$this->userId.'.log &', 'r'));
        //发起client请求
        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        //注册连接成功回调
        $client->on("connect", function($cli) {
            $cli->send($this->uuid.",".$this->userId);
            echo "send...", PHP_EOL;
        });
        //注册数据接收回调
        $client->on("receive", function($cli, $data){
            echo "Received: ".$data."\n";
        });
        //注册连接失败回调
        $client->on("error", function($cli){
            echo "Connect failed\n";
        });
        //注册连接关闭回调
        $client->on("close", function($cli){
            echo "Connection close\n";
        });
        //发起连接
        $client->connect('127.0.0.1', 9501, 0.5);
        //client请求结束



        /*$weixin = new WebWeiXin($this->uuid, 123);
        $weixin->loadConfig([
            'interactive'=>true,
            //'autoReplyMode'=>true,
            'DEBUG'=>true
        ]);
        if($weixin->start()){
            $msg  = new ListenMsg($weixin);
            $msg->start();
        }*/
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
    public function _printQR($mat){
        $black = "\033[40m  \033[0m";
        $white = "\033[47m  \033[0m";
        foreach ($mat as $v) {
            # code...
            for($i=0;$i<strlen($v);$i++){
                if($v[$i]){
                    print $black;
                }else{
                    print $white;
                }
            }
            print "\n";
        }
    }
    public function _str2qr($str){
        //$errorCorrectionLevel = 'L';//容错级别
        //$matrixPointSize = 190;//生成图片大小
        //QRcode::png($str, false, $errorCorrectionLevel, $matrixPointSize, 2);
        $mat=QRcode::text($str);
        $this->_printQR($mat);
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
$weixin->start();