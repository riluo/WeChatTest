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

$channel->queue_declare('contact', false, false, false, false);

echo ' [*] Waiting for contact. To exit press CTRL+C', "\n";

$pdo = new PDO("mysql:host=localhost;dbname=sd_chat","root","Sunland16");
$callback = function($msg) use($pdo) {
    echo " [x] Received contact", $msg->body, "\n";
    
    $arr = explode("$%",$msg->body);
    $userId = $arr[0];
    $uin = $arr[1];
    $username = $arr[2];
    $nickName = $arr[3];
    $remarkName = $arr[4];
    $sex = $arr[5];
    $avatar = $arr[6];

    $stmt=$pdo->prepare("SELECT * from friends where nickName = '".$nickName."' and remarkName = '".$remarkName."' and who = '".$uin."'");
    $stmt->execute();

    if($stmt->rowCount()>0) {
        $pdo->exec("UPDATE friends set userName='".$username."',updateTime='".date("Y-m-d H:i:s",time())."' where nickName = '".$nickName."' and remarkName = '".$remarkName."' and who = '".$uin."'");
    } else {
        $pdo->exec("insert into friends(userName,nickName,remarkName,sex,headImgUrl,who,whoId, createTime,updateTime) values('".$username."','".$nickName."','".$remarkName."','".$sex."','".$avatar."','".$uin."','".$userId."','".date("Y-m-d H:i:s",time())."','".date("Y-m-d H:i:s",time())."')");
    }
};

$channel->basic_consume('contact', '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}