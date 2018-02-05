<?php // Слушает очередь MfcOutcomingPackagesResponse
include('../config.php');
include('../connect.php');
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);

$channel = $connection->channel();

$channel->queue_declare('MfcOutcomingPackagesResponse', false, true, false, false);

$callback = function($msg){
	$incoming_status = $msg->get('type');
	include "../gen/connect.php";
	if($incoming_status == 'AddDocumentsToPackageResponse'){
			$model       = json_decode($msg->body);
            $id_packet   = $model -> PackageId;
            $Success     = $model -> Success;
            $documents[] = $model -> DocumentsId;
            global $connect;
            if($Success){
                foreach ($documents as $key => $arrElement){
                    $doc_id = $arrElement[$key];
                    $info = 'Документ '.$doc_id.' успешно доставлен';
                    $info = iconv("UTF-8", "CP1251", $info);
                    $sql_insert_doc_status = "
                                    INSERT INTO dbo.Virtual_status_in_SIR_history(id_packet, date_send, id_operator, url, info) VALUES('$id_packet',GETDATE(),0,'','$info')
                                    ";
                    odbc_exec($connect,$sql_insert_doc_status);
					error_log($sql_insert_doc_status);
                }
            }else{
                error_log('Ошибка :'.print_r($model, 1));
            }
	}else{
	$json_obj= $msg->body;
	$model= json_decode($json_obj);
	$id_packet = $model->ExternalPackageId;
	$Success = $model->Success;
	$check_already_closed = "SELECT date_out FROM dbo.Virtual_packets where id_packet = '$id_packet' AND date_out IS NULL AND date_in IS NULL";
	$result = odbc_prepare($connect,$check_already_closed);
	odbc_execute($result);
	$count_rows = odbc_num_rows($result);
	if($Success){
		 if($count_rows >= 1){
			 $sqlStatPacket = "UPDATE dbo.Virtual_packets SET status = 24, date_out_v_oiv = GETDATE() WHERE id_packet = '$id_packet'";
			 $result_SP = odbc_prepare($connect, $sqlStatPacket);
			 odbc_execute($result_SP);
		 }

		 $sqlStat = "INSERT INTO dbo.Virtual_status_in_SIR_history (id_packet, date_send, id_operator, url, info) VALUES ('$id_packet', GETDATE(), '', '', 'PackageDelivered')";
		 $result_S = odbc_prepare($connect, $sqlStat);
		 odbc_execute($result_S);

	}else if($Success == false){
		if($count_rows >= 1){
			 $sqlStatPacket = "UPDATE dbo.Virtual_packets SET status = 25, date_out_v_oiv = GETDATE() WHERE id_packet = '$id_packet'";
			 $result_SP = odbc_prepare($connect, $sqlStatPacket);
			 odbc_execute($result_SP);
		 }
		 $sqlStat = "INSERT INTO dbo.Virtual_status_in_SIR_history (id_packet, date_send, id_operator, url, info) VALUES ('$id_packet', GETDATE(), '', '', 'PackageRejected')";
		 $result_S = odbc_prepare($connect, $sqlStat);
		 odbc_execute($result_S);
	}
	}
};

$channel->basic_consume('MfcOutcomingPackagesResponse', '', false, true, false, false, $callback); //no-ack 4-ый параметр, забирает сообщение из очереди.

while(count($channel->callbacks)) {

    $channel->wait();
}

$channel->close();
$connection->close();
?>

