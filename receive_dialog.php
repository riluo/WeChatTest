<?php
/**
 * Created by PhpStorm.
 * User: zhaoliang
 * Date: 17/10/24
 * Time: 下午1:38
 */
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('dialog', false, false, false, false);

echo ' [*] Waiting for dialog. To exit press CTRL+C', "\n";

$pdo = new PDO("mysql:host=localhost;dbname=sd_chat","root","Sunland16");
$callback = function($msg) use($pdo) {
    echo " [x] Received dialog", $msg->body, "\n";

    $arr = explode("$%",$msg->body);
    $userId = $arr[0];
    $uin = $arr[1];
    $fromUserName = $arr[2];
    $fromNickName = $arr[3];
    $toUserName = $arr[4];
    $toNickName = $arr[5];
    $content = htmlentities($arr[6]);
    $createTime = date('Y-m-d H:i:s',$arr[7]);

    echo "insert into dialog(msgType,fromUserName,fromNickName,toUsername,,toNickName,content, createTime) values('1','".$fromUserName."','".$fromNickName."','".$toUserName."','".$toNickName."','".$content."','".$createTime."')";

    $pdo->exec("insert into dialog(msgType,fromUserName,fromNickName,toUsername,,toNickName,content, createTime) values('1','".$fromUserName."','".$fromNickName."','".$toUserName."','".$toNickName."','".$content."','".$createTime."')");

};

$channel->basic_consume('dialog', '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}
$pdo = null;