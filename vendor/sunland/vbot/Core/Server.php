<?php
/**
 * Created by PhpStorm.
 * User: Zhaoliang
 * Date: 2016/10/19
 * Time: 10:25.
 */

namespace Sunland\Vbot\Core;

use Carbon\Carbon;
use Sunland\Vbot\Foundation\Vbot;

class Server
{
    /**
     * @var Vbot
     */
    protected $vbot;

    public function __construct(Vbot $vbot)
    {
        $this->vbot = $vbot;
    }

    public function serve()
    {
        $this->login();

        $this->init();

        if ($this->vbot->config['swoole.status']) {
            $this->vbot->swoole->run();
        } else {
            $this->vbot->messageHandler->listen();
        }
    }

    public function getVUuid()
    {
        $content = $this->vbot->http->get('https://login.weixin.qq.com/jslogin', ['query' => [
            'appid' => 'wx782c26e4c19acffb',
            'fun'   => 'new',
            'lang'  => 'zh_CN',
            '_'     => time(),
        ]]);

        preg_match('/window.QRLogin.code = (\d+); window.QRLogin.uuid = \"(\S+?)\"/', $content, $matches);

        if (!$matches) {
            throw new FetchUuidException('fetch uuid failed.');
        }

        return $matches[2];
    }

    function webServe($uuid="")
    {
        if(!$this->vbot->config['server.uuid']) {
            $this->vbot->config['server.uuid'] = $uuid;
        }
        $this->waitForLogin();
        $this->getLogin();
        $this->init();

        /*if ($this->vbot->config['swoole.status']) {
            $this->vbot->swoole->run();
        } else {
            $this->vbot->messageHandler->listen();
        }*/
    }


    private function cleanCookies()
    {
        //$this->vbot->console->log('cleaning useless cookies.');
        if (is_file($this->vbot->config['cookie_file'])) {
            unlink($this->vbot->config['cookie_file']);
        }
    }

    /**
     * login.
     */
    public function login()
    {
        $this->getUuid();
        $this->showQrCode();
        $this->waitForLogin();
        $this->getLogin();
    }

    /**
     * get uuid.
     *
     * @throws \Exception
     */
    protected function getUuid()
    {
        $content = $this->vbot->http->get('https://login.weixin.qq.com/jslogin', ['query' => [
            'appid' => 'wx782c26e4c19acffb',
            'fun'   => 'new',
            'lang'  => 'zh_CN',
            '_'     => time(),
        ]]);

        preg_match('/window.QRLogin.code = (\d+); window.QRLogin.uuid = \"(\S+?)\"/', $content, $matches);

        if (!$matches) {
            throw new FetchUuidException('fetch uuid failed.');
        }
        $this->vbot->console->log($matches[2]);

        $this->vbot->config['server.uuid'] = $matches[2];
    }

    /**
     * show a login qrCode.
     */
    public function showQrCode()
    {
        $url = 'https://login.weixin.qq.com/l/'.$this->vbot->config['server.uuid'];

        $this->vbot->qrCodeObserver->trigger($url);
        $this->vbot->console->log($url);

        $this->vbot->qrCode->show($url);
    }

    /**
     * waiting user to login.
     *
     * @throws \Exception
     */
    protected function waitForLogin()
    {
        $retryTime = 10;
        $tip = 1;

        $this->vbot->console->log('please scan the qrCode with wechat.');
        while ($retryTime > 0) {
            $url = sprintf('https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->vbot->config['server.uuid'], time());
            $this->vbot->console->log($url);
            $content = $this->vbot->http->get($url, ['timeout' => 35, 'connect_timeout' => 6]);

            preg_match('/window.code=(\d+);/', $content, $matches);

            $code = $matches[1];
            switch ($code) {
                case '201':
                    $this->vbot->console->log('please confirm login in wechat.');
                    $tip = 0;
                    break;
                case '200':
                    preg_match('/window.redirect_uri="(https:\/\/(\S+?)\/\S+?)";/', $content, $matches);

                    $this->vbot->config['server.uri.redirect'] = $matches[1].'&fun=new';
                    $url = 'https://%s/cgi-bin/mmwebwx-bin';
                    $this->vbot->config['server.uri.file'] = sprintf($url, 'file.'.$matches[2]);
                    $this->vbot->config['server.uri.push'] = sprintf($url, 'webpush.'.$matches[2]);
                    $this->vbot->config['server.uri.base'] = sprintf($url, $matches[2]);

                    return;
                case '408':
                    $tip = 1;
                    $retryTime -= 1;
                    sleep(1);
                    break;
                default:
                    $tip = 1;
                    $retryTime -= 1;
                    sleep(1);
                    break;
            }
        }

        $this->vbot->console->log('login time out!', Console::ERROR);
        throw new LoginTimeoutException('Login time out.');
    }

    /**
     * login wechat.
     *
     * @throws \Exception
     */
    private function getLogin()
    {
        $content = $this->vbot->http->get($this->vbot->config['server.uri.redirect']);

        $data = (array) simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        $this->vbot->config['server.skey'] = $data['skey'];
        $this->vbot->config['server.sid'] = $data['wxsid'];
        $this->vbot->config['server.uin'] = $data['wxuin'];
        $this->vbot->config['server.passTicket'] = $data['pass_ticket'];

        if (in_array('', [$data['wxsid'], $data['wxuin'], $data['pass_ticket']])) {
            throw new LoginFailedException('Login failed.');
        }

        $this->vbot->config['server.deviceId'] = 'e'.substr(mt_rand().mt_rand(), 1, 15);

        $this->vbot->config['server.baseRequest'] = [
            'Uin'      => $data['wxuin'],
            'Sid'      => $data['wxsid'],
            'Skey'     => $data['skey'],
            'DeviceID' => $this->vbot->config['server.deviceId'],
        ];

        $this->vbot->console->log('begin print needed parameter');
        $this->vbot->console->log('current Uin: '.$data['wxuin']);
        $this->vbot->console->log('current Sid: '.$data['wxsid']);
        $this->vbot->console->log('current Skey: '.$data['skey']);
        $this->vbot->console->log('current pass_ticket: '.$data['pass_ticket']);
        $this->vbot->console->log('current DeviceID: '.$this->vbot->config['server.deviceId']);
        $this->vbot->console->log('End');

        $pdo = new \PDO("mysql:host=localhost;dbname=sd_chat","root","Sunland16");

        $stmt=$pdo->prepare("SELECT * from config where Uin = '".$data['wxuin']."'");

        $this->vbot->console->log("SELECT * from config where Uin = '".$data['wxuin']."'");

        $stmt->execute();

        $this->vbot->console->log("rowCount:".$stmt->rowCount());


        if($stmt->rowCount()>0) {
            $pdo->exec("UPDATE config set Sid='".$data['wxsid']."',Skey='".$data['skey']."',DeviceID='".$this->vbot->config['server.deviceId']."',pass_ticket='".$data['pass_ticket']."',UpdateTime='".date("Y-m-d H:i:s",time())."' where Uin = '".$data['wxuin']."'");

            $this->vbot->console->log("UPDATE config set Sid='".$data['wxsid']."',Skey='".$data['skey']."',DeviceID='".$this->vbot->config['server.deviceId']."',pass_ticket='".$data['pass_ticket']."',UpdateTime='".date("Y-m-d H:i:s",time())."' where Uin = '".$data['wxuin']."'");

        } else {
            $pdo->exec("insert into config(Uin,Sid,Skey,DeviceID,pass_ticket,username,HeadImgUrl,nickname,CreateTime,UpdateTime) values('".$data['wxuin']."','".$data['wxsid']."','".$data['skey']."','".$this->vbot->config['server.deviceId']."','".$data['pass_ticket']."','','','','".date("Y-m-d H:i:s",time())."','".date("Y-m-d H:i:s",time())."')");

            $this->vbot->console->log("insert into config(Uin,Sid,Skey,DeviceID,pass_ticket,username,HeadImgUrl,nickname,CreateTime,UpdateTime) values('".$data['wxuin']."','".$data['wxsid']."','".$data['skey']."','".$this->vbot->config['server.deviceId']."','".$data['pass_ticket']."','','','','".date("Y-m-d H:i:s",time())."','".date("Y-m-d H:i:s",time())."')");
        }


        $this->saveServer();
    }

    /**
     * store config to cache.
     */
    private function saveServer()
    {
        $this->vbot->cache->forever('session.'.$this->vbot->config['session'], json_encode($this->vbot->config['server']));
    }

    /**
     * init.
     *
     * @param bool $first
     *
     * @throws InitFailException
     */
    protected function init($first = true)
    {
        $this->beforeInitSuccess();
        $url = $this->vbot->config['server.uri.base'].'/webwxinit?r='.time();

        $result = $this->vbot->http->json($url, [
            'BaseRequest' => $this->vbot->config['server.baseRequest'],
        ], true);

        $this->generateSyncKey($result, $first);

        $this->vbot->myself->init($result['User']);

        ApiExceptionHandler::handle($result, function ($result) {
            $this->vbot->cache->forget('session.'.$this->vbot->config['session']);
            $this->vbot->log->error('Init failed.'.json_encode($result));
            throw new InitFailException('Init failed.');
        });

        $this->afterInitSuccess($result);

        $this->initContactList($result['ContactList']);
        $this->initContact();
    }

    /**
     * before init success.
     */
    private function beforeInitSuccess()
    {
        $this->vbot->console->log('current session: '.$this->vbot->config['session']);
        $this->vbot->console->log('init begin.');
    }

    /**
     * after init success.
     *
     * @param $content
     */
    private function afterInitSuccess($content)
    {
        $this->vbot->log->info('response:'.json_encode($content));
        $this->vbot->console->log('init success.');
        $this->vbot->loginSuccessObserver->trigger();
        $this->vbot->console->log('init contacts begin.');
    }

    protected function initContactList($contactList)
    {
        if ($contactList) {
            $this->vbot->contactFactory->store($contactList);
        }
    }

    protected function initContact()
    {
        $this->vbot->contactFactory->fetchAll();
    }

    /**
     * open wechat status notify.
     */
    protected function statusNotify()
    {
        $url = sprintf($this->vbot->config['server.uri.base'].'/webwxstatusnotify?lang=zh_CN&pass_ticket=%s', $this->vbot->config['server.passTicket']);

        $this->vbot->http->json($url, [
            'BaseRequest'  => $this->vbot->config['server.baseRequest'],
            'Code'         => 3,
            'FromUserName' => $this->vbot->myself->username,
            'ToUserName'   => $this->vbot->myself->username,
            'ClientMsgId'  => time(),
        ]);
    }

    protected function generateSyncKey($result, $first)
    {
        $this->vbot->config['server.syncKey'] = $result['SyncKey'];

        $syncKey = [];

        if (is_array($this->vbot->config['server.syncKey.List'])) {
            foreach ($this->vbot->config['server.syncKey.List'] as $item) {
                $syncKey[] = $item['Key'].'_'.$item['Val'];
            }
        } elseif ($first) {
            $this->init(false);
        }

        $this->vbot->config['server.syncKeyStr'] = implode('|', $syncKey);
    }
}