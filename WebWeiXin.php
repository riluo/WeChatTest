#!/usr/bin/env php
<?php
function raw_input($str){
   fwrite(STDOUT,$str);
   return trim(fgets(STDIN));
}
class WebWeiXin{
    public function __toString(){
        $description =
            "=========================\n" .
            "[#] Web Weixin\n" .
            "[#] Debug Mode: {$this->DEBUG}\n" .
            "[#] Uuid: {$this->uuid}\n" .
            "[#] Uin: {$this->uin}\n" .
            "[#] Sid: {$this->sid}\n" .
            "[#] Skey: {$this->skey}\n" .
            "[#] PassTicket: {$this->pass_ticket}\n" .
            "[#] DeviceId: {$this->deviceId}\n" .
            "[#] synckey: {$this->synckey}\n" .
            "[#] SyncKey: ".self::json_encode($this->SyncKey)."\n" .
            "[#] syncHost: {$this->syncHost}\n" .
            "=========================\n";
        return $description;
    }
    public function __construct($uuid, $userId){
        $this->DEBUG = false;
        $this->uuid = $uuid;
        $this->userId = $userId;
        $this->base_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin';
        $this->redirect_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage';//
        $this->uin = '';
        $this->sid = '';
        $this->skey = '';
        $this->pass_ticket = '';
        $this->deviceId = 'e' .substr(md5(uniqid()),2,15);
        $this->BaseRequest = [];
        $this->synckey = '';
        $this->SyncKey = [];
        $this->User = [];
        $this->MemberList = [];
        $this->ContactList = [];  # 好友
        $this->GroupList = [];  # 群
        $this->GroupMemeberList = [];  # 群友
        $this->PublicUsersList = [];  # 公众号／服务号
        $this->SpecialUsersList = [];  # 特殊账号
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
        $this->memberCount = 0;
        $this->SpecialUsers = ['newsapp', 'fmessage', 'filehelper', 'weibo', 'qqmail',
            'fmessage', 'tmessage', 'qmessage', 'qqsync', 'floatbottle', 'lbsapp', 'shakeapp',
            'medianote', 'qqfriend', 'readerapp', 'blogapp', 'facebookapp', 'masssendapp',
            'meishiapp', 'feedsapp','voip', 'blogappweixin', 'weixin', 'brandsessionholder',
            'weixinreminder', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'officialaccounts',
            'notification_messages', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'wxitil',
            'userexperience_alarm', 'notification_messages'
        ];
        $this->TimeOut = 20;  # 同步最短时间间隔（单位：秒）
        $this->media_count = -1;
        $this->cookieFolder = getcwd()."/cookie/".$userId."/";
        $this->cookie = $this->cookieFolder."cookie.cookie";
        $this->key = $this->cookieFolder."key.key";
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

    public function waitForLogin($tip=1){
        sleep($tip);
        $url = sprintf('https://login.wx.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->uuid, time());
        $data = $this->_get($url);
        preg_match('/window.code=(\d+);/', $data,$pm);
        $code = $pm[1];
        $this->_echo($this->uuid);
        $this->_echo($code);

        if($code == '201')
            return true;
        elseif ($code == '200'){
            preg_match('/window.redirect_uri="(\S+?)";/', $data,$pm);
            $r_uri = $pm[1] . '&fun=new';
            $this->redirect_uri = $r_uri;
            $this->base_uri = substr($r_uri,0,strrpos($r_uri, '/'));
            //var_dump($this->base_uri);
            return true;
        } elseif ($code == '408'){
            $this->_echo("[登陆超时]");
        } else {
            $this->_echo("[登陆异常]");
        }
        return false;
    }

    public function login(){
        $data = $this->_get($this->redirect_uri);
        $array = (array)simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
        //var_dump($array);
        if (!isset($array['skey'])||!isset($array['wxsid'])||!isset($array['wxuin'])||!isset($array['pass_ticket']))
            return False;
        $this->skey = $array['skey'];
        $this->sid = $array['wxsid'];
        $this->uin = $array['wxuin'];
        $this->pass_ticket = $array['pass_ticket'];

        $this->BaseRequest = [
            'Uin'=> intval($this->uin),
            'Sid'=> $this->sid,
            'Skey'=> $this->skey,
            'DeviceID'=> $this->deviceId
        ];
        $this->initSave();
        return true;
    }

    public function webwxinit($first=true){
        $url = sprintf($this->base_uri.'/webwxinit?pass_ticket=%s&skey=%s&r=%s',$this->pass_ticket, $this->skey, time());
        $params = [
            'BaseRequest'=> $this->BaseRequest
        ];
        $dic = $this->_post($url, $params);
        $this->SyncKey = $dic['SyncKey'];
        $this->User = $dic['User'];
        # synckey for synccheck
        $tempArr = [];
        if(is_array($this->SyncKey['List'])){
            foreach ($this->SyncKey['List'] as $val) {
                # code...
                $tempArr[] = "{$val['Key']}_{$val['Val']}";
            }
        }elseif($first){
            return $this->webwxinit(false);
        }
        //$this->skey = $dic['SKey'];
        $this->synckey = implode('|', $tempArr);
        //$this->initSave();
        //var_dump($this->synckey);
        return $dic['BaseResponse']['Ret'] == 0;
    }

    public function webwxstatusnotify(){
        $url = sprintf($this->base_uri .'/webwxstatusnotify?lang=zh_CN&pass_ticket=%s',$this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            "Code"=> 3,
            "FromUserName"=> $this->User['UserName'],
            "ToUserName"=> $this->User['UserName'],
            "ClientMsgId"=> time()
        ];
        $dic = $this->_post($url, $params);
        return $dic['BaseResponse']['Ret'] == 0;
    }
    public function webwxgetcontact(){
        $SpecialUsers = $this->SpecialUsers;
        //print $this->base_uri;
        $url = sprintf($this->base_uri . '/webwxgetcontact?pass_ticket=%s&skey=%s&r=%s',
            $this->pass_ticket, $this->skey, time());
        $dic = $this->_post($url, []);

        $this->MemberCount = $dic['MemberCount']-1;//把自己减去
        $this->MemberList = $dic['MemberList'];
        $ContactList = $this->MemberList;
        //var_dump($ContactList);
        if(is_array($ContactList)){
            foreach($ContactList as $key => $Contact){
                //$this->_echo(sprintf("%s--------%d-------%d----%s",$Contact['UserName'] ,$Contact['VerifyFlag'],$Contact['VerifyFlag']&8,$Contact['VerifyFlag'] & 8 != 0));
                if (in_array($Contact['UserName'] , $SpecialUsers)){  # 特殊账号
                    unset($ContactList[$key]);
                    //$this->SpecialUsersList[] = $Contact;
                }elseif (($Contact['VerifyFlag'] & 8) != 0){  # 公众号/服务号
                    unset($ContactList[$key]);
                    //$this->PublicUsersList[] = $Contact;
                }elseif (strpos($Contact['UserName'],'@@') !== false){  # 群聊
                    unset($ContactList[$key]);
                    //$this->GroupList[] = $Contact;
                }elseif ($Contact['UserName'] == $this->User['UserName']){  # 自己
                    unset($ContactList[$key]);
                }
            }
        }else{
            return false;
        }
        $this->ContactList = $ContactList;
        return true;
    }

    public function getNameById($id){
        $url = sprintf($this->base_uri .
            '/webwxbatchgetcontact?type=ex&r=%s&pass_ticket=%s' ,
            time(), $this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            "Count"=> 1,
            "List"=> [["UserName"=> $id, "EncryChatRoomId"=> ""]]
        ];
        $dic = $this->_post($url, $params);

        # blabla ...
        return $dic['ContactList'];
    }

    public function testsynccheck(){
        //TODO:
        $SyncHost = [
            'webpush.weixin.qq.com',
            'webpush2.weixin.qq.com',
            'webpush.wechat.com',
            'webpush1.wechat.com',
            'webpush2.wechat.com',
            'webpush1.wechatapp.com',
            'webpush.wechatapp.com'
        ];
        $SyncHost = ['webpush.wx.qq.com'];
        foreach($SyncHost as $host){
            $this->syncHost = $host;
            list($retcode, $selector) = $this->synccheck();
            if ($retcode == '0')
                return true;
        }
        return false;
    }
    public function synccheck(){
        $params = [
            'r'=> time(),
            'sid'=> $this->sid,
            'uin'=> $this->uin,
            'skey'=> $this->skey,
            'deviceid'=> $this->deviceId,
            'synckey'=> $this->synckey,
            '_'=> time(),
        ];
        $url = 'https://' . $this->syncHost .'/cgi-bin/mmwebwx-bin/synccheck?'.http_build_query($params);
        $data = $this->_get($url);
        if(preg_match('/window.synccheck={retcode:"(\d+)",selector:"(\d+)"}/', $data,$pm)){
            $retcode = $pm[1];
            $selector = $pm[2];
        }else{
            //var_dump($data);
            $retcode = -1;
            $selector = -1;
        }
        return [$retcode, $selector];
    }
    public function webwxsync(){
        $url = sprintf($this->base_uri .
            '/webwxsync?sid=%s&skey=%s&pass_ticket=%s' ,
            $this->sid, $this->skey, $this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            'SyncKey'=> $this->SyncKey,
            'rr'=> time()
        ];
        $dic = $this->_post($url, $params);

        if ($dic['BaseResponse']['Ret'] == 0){
            $this->SyncKey = $dic['SyncKey'];
            $synckey = [];
            foreach($this->SyncKey['List'] as $keyVal)
                $synckey[] = "{$keyVal['Key']}_{$keyVal['Val']}";
            $this->synckey = implode('|', $synckey);
        }
        return $dic;
    }

    /**
     * 添加好友，或者通过好友验证消息
     * @Opcode 2 添加 3 通过
     */
    public function webwxverifyuser($user,$Opcode){
        $url = sprintf($this->base_uri.'/webwxverifyuser?lang=zh_CN&r=%s&pass_ticket=%s' ,time()*1000, $this->pass_ticket);

        $data = [
            "BaseRequest"=> $this->BaseRequest,
            "Opcode"=>3,
            "VerifyUserListSize"=>1,
            "VerifyUserList"=>[$user],
            "VerifyContent"=>"",
            "SceneListCount"=>1,
            "SceneList"=>[33],
            "skey"=>$this->skey
        ];
        $dic = $this->_post($url, $data);
        if ($this->DEBUG)
            var_dump($dic);
        return $dic['BaseResponse']['Ret'] == 0;
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

    public function webwxgeticon($id){
        $url = sprintf($this->base_uri .
            '/webwxgeticon?username=%s&skey=%s' , $id, $this->skey);
        $data = $this->_get($url);
        $fn = 'img_' . $id . '.jpg';
        return $this->_saveFile($fn, $data, 'webwxgeticon');
    }

    public function webwxgetheadimg(){
        $url = sprintf($this->base_uri .
            '/webwxgetheadimg?username=%s&skey=%s' , $id, $this->skey);
        $data = $this->_get($url);
        $fn = 'img_' . $id . '.jpg';
        return $this->_saveFile($fn, $data, 'webwxgetheadimg');
    }

    public function getUserRemarkName($id){
        $name = substr($id, 0,2) == '@@'?'未知群':'陌生人';
        if ($id == $this->User['UserName']){
            return $this->User['NickName'];  # 自己
        }

        # 直接联系人
        foreach($this->ContactList as $member){
            if ($member['UserName'] == $id){
                $name =  $member['RemarkName']?$member['RemarkName']:$member['NickName'];
            }
        }

        if ($name == '未知群' || $name == '陌生人'){
            var_dump($id);
        }
        return $name;
    }
    public function getUSerID($name){
        foreach($this->MemberList as $member){
            if ($name == $member['RemarkName'] || $name == $member['NickName']){
                return $member['UserName'];
            }
        }
        return null;
    }
    public function _showMsg($message){
        $srcName = null;
        $dstName = null;
        $groupName = null;
        $content = null;

        $msg = $message;
        //$this->_echo($msg);

        if ($msg['raw_msg']){
            $srcName = $this->getUserRemarkName($msg['raw_msg']['FromUserName']);
            $dstName = $this->getUserRemarkName($msg['raw_msg']['ToUserName']);
            $content = $msg['raw_msg']['Content'];//str_replace(['&lt;','&gt;'], ['<','>'], $msg['raw_msg']['Content']);
            $message_id = $msg['raw_msg']['MsgId'];

            if ($msg['raw_msg']['ToUserName'] == 'filehelper'){
                # 文件传输助手
                $dstName = '文件传输助手';
            }

            # 指定了消息内容
            if (isset($msg['message'])){
                $content = $msg['message'];
            }
        }
        if( !empty($groupName)){
            $this->_echo(sprintf('%s |%s| %s -> %s: %s' , $message_id, trim($groupName), trim($srcName), trim($dstName), str_replace("<br/>", "\n", $content)));
            return true;
        }else{
            $this->_echo(sprintf('%s %s -> %s: %s' , $message_id, trim($srcName), trim($dstName), str_replace("<br/>", "\n", $content)));
            return true;
        }
    }
    public static function br2nl ( $string ){
        return preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $string);
    }
    public function handleMsg($r){
        foreach($r['AddMsgList'] as $msg){
            $this->_echo('[*] 你有新的消息，请注意查收');

            $msgType = $msg['MsgType'];
            $name = $this->getUserRemarkName($msg['FromUserName']);
            $content = $msg['Content']= self::br2nl(html_entity_decode($msg['Content']));//str_replace(['&lt;','&gt;'], ['<','>'], $msg['Content']);
            $msgid = $msg['MsgId'];
            if ($this->DEBUG||true){
                if(!is_dir('msg')){
                    umask(0);
                    mkdir('msg',0777,true);
                }
                $fn = 'msg/msg' .$msgid. '.json';
                $f = fopen($fn, 'w');
                fwrite($f,self::json_encode($msg));
                $this->_echo( '[*] 该消息已储存到文件: ' . $fn);
                fclose($f);
            }

            if ($msgType == 1){
                $raw_msg = ['raw_msg'=> $msg];
                $isReply = $this->_showMsg($raw_msg);
            }elseif($msgType == 37){
                //是否自动通过加好友验证
                if(true){
                    $data = [
                        "Value" => $msg['RecommendInfo']['UserName'],
                        "VerifyUserTicket" => $msg['RecommendInfo']['Ticket']
                    ];
                    if($this->webwxverifyuser($data,3)){
                        $raw_msg = ['raw_msg'=> $msg,
                            'message'=>sprintf('添加 %s 好友成功' , $msg['RecommendInfo']['NickName'])];
                    }else{
                        $raw_msg = ['raw_msg'=> $msg,
                            'message'=>sprintf('添加 %s 好友失败' , $msg['RecommendInfo']['NickName'])];
                    }

                    $this->_showMsg($raw_msg);
                }
            }elseif ($msgType == 47){
                $url = $this->_searchContent('cdnurl', $content);
                $raw_msg = ['raw_msg'=> $msg,
                    'message'=>sprintf('%s 发了一个动画表情，点击下面链接查看: %s' , $name, $url)];
                $this->_showMsg($raw_msg);
                $this->_safe_open($url);
            }elseif ($msgType == 49){
                $appMsgType = [5=> '链接', 3=> '音乐', 7=> '微博',17=>'位置共享'];
                $this->_echo(sprintf('%s 分享了一个%s:' , $name, $appMsgType[$msg['AppMsgType']]));
                $this->_echo('=========================');
                $this->_echo(sprintf('= 标题: %s' , $msg['FileName']));
                $this->_echo(sprintf('= 描述: %s' , $this->_searchContent('des', $content, 'xml')));
                $this->_echo(sprintf('= 链接: %s' , $msg['Url']));
                $this->_echo(sprintf('= 来自: %s' , $this->_searchContent('appname', $content, 'xml')));
                $this->_echo('=========================');
                $card = [
                    'title'=> $msg['FileName'],
                    'description'=> $this->_searchContent('des', $content, 'xml'),
                    'url'=> $msg['Url'],
                    'appname'=> $this->_searchContent('appname', $content, 'xml')
                ];
                $raw_msg = ['raw_msg'=> $msg, 'message'=>sprintf( '%s 分享了一个%s: %s' ,
                    $name, $appMsgType[$msg['AppMsgType']], self::json_encode($card))];
                $this->_showMsg($raw_msg);
            }elseif ($msgType == 51){
                $raw_msg = ['raw_msg'=> $msg, 'message'=> '[*] 成功获取联系人信息'];
                $this->_showMsg($raw_msg);
            }elseif ($msgType == 10002){//撤销消息
                $raw_msg = ['raw_msg'=> $msg, 'message'=> sprintf('%s 撤回了一条消息' , $name)];
                $this->_showMsg($raw_msg);
            }else{
                $raw_msg = [
                    'raw_msg'=> $msg,
                    'message'=> sprintf('[*] 该消息类型为: %d，可能是表情，图片, 链接或红包' , $msg['MsgType'])
                ];
                var_dump($msg);
                $this->_showMsg($raw_msg);
            }
        }
    }

    public function listenMsgMode(){
        $this->_echo('[*] 进入消息监听模式 ... 成功');

        $this->_run('[*] 进行同步线路测试 ... ', 'testsynccheck');

        $playWeChat = 0;
        $redEnvelope = 0;

        while (true){
            $this->lastCheckTs = time();
            list($retcode, $selector) = $this->synccheck();
            if ($this->DEBUG){
                $this->_echo(sprintf('retcode: %s, selector: %s',$retcode, $selector));
            }
            //TODO:debug
            $this->_echo(sprintf('retcode: %s, selector: %s',$retcode, $selector));

            if ($retcode == '1100'){
                $this->_echo('[*] 你在手机上登出了微信，债见');
                break;
            }
            if ($retcode == '1101'){
                $this->_echo('[*] 你在其他地方登录了 WEB 版微信，债见');
                break;
            }elseif($retcode == '0'){
                if ($selector == '2'){
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif($selector == '3'){
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                    /*}elseif ($selector == '7'){
                        $playWeChat += 1;
                        $this->_echo(sprintf('[*] 你在手机上玩微信被我发现了 %d 次' , $playWeChat));
                        $r = $this->webwxsync();
                        if ($r){
                            $this->handleMsg($r);
                        }*/
                }elseif ($selector == '6'){//有消息返回结果
                    # TODO
                    //$redEnvelope += 1;
                    //$this->_echo(sprintf('[*] 收到疑似红包消息 %d 次' , $redEnvelope));
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif ($selector == '0'){
                    sleep(1);
                }
            }
            if ((time() - $this->lastCheckTs) <= 20){
                sleep(time() - $this->lastCheckTs);
            }
        }
    }

    //开始登录
    public function start(){
        $this->_echo('[*] 微信网页版 ... 开动');
        //if(!$this->init()) {
        while (true) {
            if (!$this->waitForLogin()) {
                continue;
                $this->_echo('[*] 请在手机上点击确认以登录 ... ');
            }
            if (!$this->waitForLogin(0)) {
                continue;
            }
            break;
        }
        $this->_run('[*] 正在登录 ... ', 'login');
        //}

        $this->_run('[*] 微信初始化 ... ', 'webwxinit');

        $this->_run('[*] 开启状态通知 ... ', 'webwxstatusnotify');
        $this->_run('[*] 获取联系人 ... ', 'webwxgetcontact');
        $this->_echo(sprintf('[*] 应有 %s 个联系人，读取到联系人 %d 个' ,
            $this->MemberCount, count($this->MemberList)));
        $this->_echo(sprintf('[*] %d 个直接联系人 ', count($this->ContactList)));
        $this->_echo('[*] 微信网页版 ... 开动');
        if ($this->DEBUG)
            echo($this);

        if(extension_loaded("pcntl")){
            $pf = pcntl_fork();
            if ($pf){ //父进程负责监听消息
                $this->listenMsgMode();
                exit();
            }
        }elseif(extension_loaded("pthreads")){
            return true;
        }else{
            $this->_echo('[*] 缺少扩展，暂时只能获取监听消息，不能发送消息');
            $this->_echo('[*] 如果要发消息，请安装pcntl或者pthreads扩展');
            $this->listenMsgMode();
        }

        sleep(2);
        exit();
        return false;
    }

    public function init(){
        if(file_exists($this->key)){
            $array = json_decode(file_get_contents($this->key),true);
            if($array){
                $this->skey = $array['skey'];
                $this->sid = $array['sid'];
                $this->uin = $array['uin'];
                $this->pass_ticket = $array['pass_ticket'];
                $this->deviceId = $array['deviceId'];

                $this->BaseRequest = [
                    'Uin'=> intval($this->uin),
                    'Sid'=> $this->sid,
                    'Skey'=> $this->skey,
                    'DeviceID'=> $this->deviceId
                ];
                return true;
            }
        }
        return false;
    }
    public function initSave(){
        file_put_contents($this->key,self::json_encode([
            'skey'=>$this->skey,
            'sid'=>$this->sid,
            'uin'=>$this->uin,
            'pass_ticket'=>$this->pass_ticket,
            'deviceId'=>$this->deviceId
        ]));
    }
    public function _safe_open($path){
        if ($this->autoOpen){
            if(PHP_OS == "Linux"){
                system(sprintf("xdg-open %s &" , $path));
            }elseif(PHP_OS == "Darwin"){
                system(sprintf('open %s &' , $path));
            }else{
                system($path);
            }
        }
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

    public function _searchContent($key, $content, $fmat='attr'){
        if($fmat == 'attr'){
            if (preg_match('/'.$key . '\s?=\s?"([^"<]+)"/', $content,$pm)){
                return $pm[1];
            }
        }elseif($fmat == 'xml'){
            if(!preg_match("/<{$key}>([^<]+)<\/{$key}>/",$content,$pm)){
                preg_match("/<{$key}><\!\[CDATA\[(.*?)\]\]><\/{$key}>/",$content,$pm);
            }
            if (isset($pm[1])){
                return $pm[1];
            }
        }
        return '未知';
    }

}
if(!extension_loaded('pthreads')){
    class Thread {
        public function start(){

        }
    }
}
class ListenMsg extends Thread {
    private $weixin;
    public function __construct(WebWeiXin $weixin){
        # code...
        $this->weixin = $weixin;
    }
    public function run(){
        if($this->weixin){
            $this->weixin->_echo("[*] 进入消息监听模式 ......ListenMsg...run");
            $this->weixin->listenMsgMode();
        }
    }
}
$uuid = $argv[1];
$userId = $argv[2];
$weixin = new WebWeiXin($uuid, $userId);
$weixin->loadConfig([
    'interactive'=>true,
    //'autoReplyMode'=>true,
    'DEBUG'=>true
]);
if($weixin->start()){
    $msg  = new ListenMsg($weixin);
    $msg->start();
}