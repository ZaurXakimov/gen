<?php // Слушает очередь AisMfcSkaIncomingStates
include('../config.php');
include "../connect.php";
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);

$channel = $connection->channel();

$channel->queue_declare('AisMfcSkatIncomingStates', false, true, false, false);

$callback = function($msg){
//Проверка наличия пакета в базе.
    if(!function_exists('findIdPacket')){
        function findIdPacket($id_packet){
            global $connect;

            if ($id_packet)  {
                $sql = "SELECT * FROM dbo.Virtual_packets WHERE id_packet = '$id_packet'";
                $pre = odbc_prepare($connect, $sql);
                odbc_execute($pre, array($id_packet));
                if (odbc_num_rows($pre)){
                    $Success = true;
                    $Errors = null;
                } else {
                    $Success = false;
                    $Errors = array('ApiError' => array( 'DebugInfo' => '',
                        'Message' => "Пакет $id_packet не найден." ));
                }
            } else {
                $Success = false;
                $Errors = array('ApiError' => array( 'DebugInfo' => '',
                    'Message' => "Пакет не указан." ));
            }

            return array('Success' => $Success, 'Errors' => $Errors);
        }
    }

    if(!function_exists('recordDoc')){
        function recordDoc($id_packet, $id_status, $data, $name, &$Success = null, &$Errors = null){
            global $connect;

            $sqlDocImgPath = "SELECT TOP(1) base_puth_scan_for_visible, base_puth_scan_for_read FROM Virtual_gen_inf";
            $resultDocImgPath = odbc_prepare($connect,$sqlDocImgPath);
            odbc_execute($resultDocImgPath);

            //Создание и наполнение пришедшего файла данными документа .
            $parentPath =  iconv("cp1251", "utf-8", odbc_result($resultDocImgPath, 'base_puth_scan_for_read'));
            $name_f = iconv("UTF-8", "CP1251",$name);
            $src     = $parentPath.'\\'.$id_packet.'\\'.$name_f;

            $f = fopen($src, "wb");
            $f_w = fwrite($f, base64_decode($data));
            fclose($f);

            if($f_w){
                $sql_d = "INSERT INTO dbo.Virtual_status_in_SIR_docs (id_packet, name, id_status) VALUES (?, ?, ?)";
                $pre_d = odbc_prepare($connect, $sql_d);
                $name_i = iconv("UTF-8", "CP1251",$name);
                if (odbc_execute($pre_d, array($id_packet, $name_i, $id_status))) {
                    $Success = true;
                    $Errors = null;
                } else {
                    $Success = false;
                    $Errors = array('ApiError' => array( 'DebugInfo' => odbc_error($connect).' '.odbc_errormsg($connect),
                        'Message' => "Ошибка при записи документа '$name' в базу данных." ));
                }
            } else {
                $Success = false;
                $Errors = array('ApiError' => array( 'DebugInfo' => print_r(error_get_last(), true),
                    'Message' => "Ошибка при записи файла документа '$name'." ));
            }
            if ($Success == false) {
                $sql_del ="DELETE FROM dbo.Virtual_status_in_SIR WHERE id_status = $id_status";
                $pre_del = odbc_prepare($connect, $sql_del);
                odbc_execute($pre_del);
            }
        }
    }

    if(!function_exists('StateMethod')){
        function StateMethod($model, $method){
            $model = json_decode($model);
            $id_packet = $model->PackageId;
            $msg = findIdPacket($id_packet);
            global $connect;

            $status = null;
            if ($method == 'PackageAssigned')  $status = "Назначен исполнитеть {$model->Name}, E-mail: {$model->Email}, тел: {$model->Phone}";
            if ($method == 'PackagePaused')    $status = "Пакет приостановлен. {$model->Reason}";
            if ($method == 'PackageCompleted') $status = "Пакет завершен.";
            if ($method == 'PackageDenied')    $status = "Отказ. {$model->Reason}";
            if ($method == 'PackageCanceled')  $status = "Пакет прерван заявителем.";


            if ($msg['Success'] && !empty($status)){
                $status = iconv("UTF-8", "CP1251",$status);
                $sql = "INSERT INTO dbo.Virtual_status_in_SIR (id_packet, status, [date]) OUTPUT INSERTED.id_status VALUES (?, ?, GETDATE())";
                $pre = odbc_prepare($connect, $sql);
                odbc_execute($pre, array($id_packet, $status));
                $res = odbc_fetch_array($pre);
                $id_status = $res['id_status'];

                if($id_status){
                    if(isset($model->Documents)){
                        if(is_array($model->Documents))
                        {
                            foreach ($model->Documents as $key => $doc) {
                                $name = $id_status.'_'.$key.'_'. $doc->Type . $doc->Extension ;
                                recordDoc($id_packet, $id_status, $doc->Data, $name, $Success, $Errors);
                            }
                        }
                    } else {
                        $Success = true;
                        $Errors = null;
                    }
                } else {
                    $Success = false;
                    $Errors = array('ApiError' => array( 'DebugInfo' => odbc_error($connect).' '.odbc_errormsg($connect),
                        'Message' => "Ошибка при записи статуса в базу данных." ));
                }
                if ($method == 'PackageCompleted' || $method == 'PackageDenied'){
                    //$sql_status = "UPDATE dbo.packets SET date_in =GETDATE() WHERE id_packet='$id_packet' AND date_in IS NULL ";
                    $sql_status = "INSERT INTO dbo.Virtual_status_in_SIR_history(id_packet, date_send, id_operator, url, info) VALUES('$id_packet',GETDATE(),0,'','$method')";
                    $sql_prep = odbc_prepare($connect,$sql_status);
                    odbc_execute($sql_prep);
                }
            } else {
                $Success = $msg['Success'];
                $Errors  = $msg['Errors'];
            }
            return Array($method."Result" => array("Success" => $Success, "Errors" => $Errors));
        }
    }

    if(!function_exists('addDocumentResponse')) {
        function addDocumentResponse($model)
        {
            $model       = json_decode($model);
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
                                    INSERT INTO dbo.Virtual_status_in_SIR_history(id_packet, date_send, id_operator, url, info) VALUES('$id_packet',GETDATE(),0,'','$info');
                                    INSERT INTO dbo.Virtual_skans_To_SIR_State(id_packet, id_doc, id_state, state, date) VALUES ('$id_packet', $doc_id, 2, '');
                                    ";//Документ доставлен
                    odbc_exec($connect,$sql_insert_doc_status);
                }
            }else{
                error_log('Ошибка :'.print_r($model, 1));
            }
        }
    }

    if(!function_exists('addCommentResponse')) {
        function addCommentResponse($model)
        {
            error_log($model);
            $model       = json_decode($model);
            $id_packet   = $model -> PackageId;

            $comments[]  = $model -> Comments;
            global $connect;

            foreach ($comments[0] as $key => $value){
                error_log(print_r($comments[0][$key],1));
                $author         = $comments[0][$key] -> Author;
                $author         = iconv("UTF-8", "CP1251", $author);
                $comment_id     = $comments[0][$key] -> CommentId;
                $comment_text   = $comments[0][$key] -> Text;
                $comment_text   = iconv("UTF-8", "CP1251", $comment_text);
                $sql_insert_doc_status = "
            SET IDENTITY_INSERT dbo.Virtual_packet_comments_SIR ON
                   INSERT INTO dbo.Virtual_packet_comments_SIR
                    ( id_comment,
                      author ,
                      id_author ,
                      id_packet ,
                      comment ,
                      date ,
                      readed
                    )
            VALUES  ( $comment_id,
                      '$author' , -- author - varchar(200)
                      '' , -- id_author - int
                      '$id_packet' , -- id_packet - varchar(50)
                      '$comment_text' , -- comment - varchar(max)
                      GETDATE() , -- date - datetime
                      1  -- readed - int
                    )
		SET IDENTITY_INSERT dbo.Virtual_packet_comments_SIR OFF
                    ";
                odbc_exec($connect,$sql_insert_doc_status);
            }
        }
    }

    $model = $msg->body;
    $incoming_status = $msg->get('type');
    if($incoming_status == 'AddDocumentsToPackageResponse'){
            addDocumentResponse($model);
    }else if($incoming_status == 'AddComments'){
            addCommentResponse($model);
    }
    else{
        if(!function_exists('PackageRun')){
            function PackageRun($model, $incoming_status){
                return StateMethod($model, $incoming_status);
            }
        }
        //По завершению обработки ответа, отправка в СИР уведомления о получении сообщения.
        $connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
        $channel = $connection->channel();
        $channel->queue_declare('AisMfcSkatIncomingStatesResponse', false, true, false, false);
        $message = json_encode(PackageRun($model, $incoming_status));
        $message_to_SIR = new AMQPMessage($message,
            array('delivery_mode' => 2,
                'type'          => $incoming_status
            )
        );

        $channel->basic_publish($message_to_SIR, '', 'AisMfcSkatIncomingStatesResponse');
        $channel->close();
        $connection->close();
    }

};

$channel->basic_consume('AisMfcSkatIncomingStates', '', false, true, false, false, $callback);//no-ack 4-ый параметр, забирает сообщение из очереди.

while(count($channel->callbacks)) {

    $channel->wait();
}

$channel->close();
$connection->close();
?>

