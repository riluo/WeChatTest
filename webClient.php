<?php
//发起client请求
$client = new swoole_client(SWOOLE_SOCK_UDP, SWOOLE_SOCK_SYNC);
$client->connect('127.0.0.1', 9501);
$client->send($uuid.",".$userId);
echo "send...", PHP_EOL;
?>