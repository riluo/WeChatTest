<?php
/**
 * Created by PhpStorm.
 * User: zhaoliang
 * Date: 17/10/20
 * Time: 下午5:45
 */
require_once __DIR__."/WeiXin.php";
class MsgServer
{
    private $serv;

    function __construct()
    {
        $this->serv = new Swoole\Server("127.0.0.1", 9501);//创建一个服务
        $this->serv->set(array('task_worker_num' => 4)); //配置task进程的数量
        $this->serv->on('receive', array($this, 'onReceive'));//有数据进来的时候执行
        $this->serv->on('task', array($this, 'onTask'));//有任务的时候执行
        $this->serv->on('finish', array($this, 'onFinish'));//任务结束时执行
        $this->serv->start();
    }


    public function onReceive($serv, $fd, $from_id, $data)
    {
        $data = explode(",", $data);
        $task_id = $serv->task($data);//这里发起了任务，于是上面的on('task', array($this, 'onTask'))就会执行

    }

    public function onTask($serv, $task_id, $from_id, $data)
    {
        $uuid = trim($data[0]);
        $userId = trim($data[1]);
        //1.7.3之前，是$serv->finish("result");
        $weixin = new WeiXin($uuid, $userId);
        $weixin->loadConfig([
            'interactive'=>true,
            //'autoReplyMode'=>true,
            'DEBUG'=>true
        ]);
        $weixin->start();

        $serv->tick(500, function ($id) use ($serv, &$weixin) {
            $returnCode = $weixin->listenMsgMode();
            if($returnCode == 'logout'){
                $serv->clearTimer($id);
            }
        });
        return "logout";
        //return "result.";//这里告诉任务结束，于是上面的on('finish', array($this, 'onFinish'))就会执行
    }

    public function onFinish($serv, $task_id, $data)
    {
        //$this->addSendLog($data); //添加短信发送记录
    }
}

$msgServ = new msgServer;