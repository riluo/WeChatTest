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


$callback = function($msg) {
    echo " [x] Received self", $msg->body, "\n";
};

$channel->basic_consume('self', '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}