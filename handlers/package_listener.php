<?php // Слушает очередь MfcOutcomingPackages
include('../config.php');
include "../connect.php";

/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);

$channel = $connection->channel();

$channel->queue_declare('MfcOutcomingPackages', false, true, false, false);

$callback = function($msg){
	$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
	$channel = $connection->channel();
	$channel->queue_declare('MfcOutcomingPackagesResponse', false, true, false, false);
	$msg = new AMQPMessage($msg->body,
	                        array('delivery_mode' => 2)
	                      );
	$channel->basic_publish($msg, '', 'MfcOutcomingPackagesResponse');
};

$channel->basic_consume('MfcOutcomingPackages', '', false, false, false, false, $callback);

while(count($channel->callbacks)) {

    $channel->wait();
}

$channel->close();
$connection->close();
?>

