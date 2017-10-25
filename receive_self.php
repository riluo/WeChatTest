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

$channel->queue_declare('self', false, false, false, false);

echo ' [*] Waiting for self. To exit press CTRL+C', "\n";

$pdo = new PDO("mysql:host=localhost;dbname=sd_chat","root","Sunland16");
$callback = function($msg) use($pdo) {
    echo " [x] Received self", $msg->body, "\n";

    $arr = explode("$%",$msg->body);
    $userId = $arr[0];
    $uin = $arr[1];
    $sid = $arr[2];
    $skey = $arr[3];
    $passTicket = $arr[4];
    $deviceId = $arr[5];
    $username = $arr[6];
    $nickname = $arr[7];
    $avatar = $arr[8];


    $stmt=$pdo->prepare("SELECT * from self where uin = '".$uin."'");
    $stmt->execute();

    if($stmt->rowCount()>0) {
        $pdo->exec("UPDATE self set sid='".$sid."',skey='".$skey."',passTicket='".$passTicket."',deviceId='".$deviceId."',username='".$username."',nickname='".$nickname."',updateTime='".date("Y-m-d H:i:s",time())."' where uin = '".$uin."'");
    } else {
        $pdo->exec("insert into self(userId,uin,sid,skey,deviceId,passTicket,username,headImgUrl,nickname, createTime,updateTime) values('".$userId."','".$uin."','".$sid."','".$skey."','".$deviceId."','".$passTicket."','".$username."','".$avatar."','".$nickname."','".date("Y-m-d H:i:s",time())."','".date("Y-m-d H:i:s",time())."')");
    }
};

$channel->basic_consume('self', '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}