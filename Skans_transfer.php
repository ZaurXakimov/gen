<?php
include "connect.php";
include "config.php";
require_once "applicant/applicant_parser.php";
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$packets_array  =  $_POST['packets_array'];
$date_1         =  isset($_POST['date_1']);
$date_2         =  isset($_POST['date_2']);

$SQL = "select top(1) base_puth_scan_for_visible, base_puth_scan_for_read from gen_inf;";
$SQL_prepare = odbc_prepare($connect, $SQL);
odbc_execute($SQL_prepare);
$puth_in_server = odbc_result($SQL_prepare, "base_puth_scan_for_read");

if (!empty($packets_array)){
    $array_to_string = implode("','", $packets_array);
    $SQL = "
            SELECT id_packet, inter_id, docs_img FROM dbo.oper_doc 
            WHERE id_packet IN ('$array_to_string') AND docs_img IS NOT NULL AND docs_img != ''
            ";
    $SQL_prepare = odbc_prepare($connect, $SQL);
    odbc_execute($SQL_prepare);
    $array_with_scan_names = [];
    $ftp_server   = RABBIT_FTP_SERVER;
    $ftp_username = RABBIT_FTP_USER;
    $ftp_userpass = RABBIT_FTP_PASSWORD;
    $ftp_conn     = ftp_connect($ftp_server) or die("Подключение к серверу не удалось $ftp_server");
    $login        = ftp_login($ftp_conn, $ftp_username, $ftp_userpass);
        class editPackageAddDocuments extends Export{
            public function getAddedDocument(){
                global $id_packet;
                global $inter_id;
                $this->id_packet = $id_packet;
                $this->uidDoc    = $inter_id;
                $document = $this->getAddedDocument_func($this->uidDoc);
                return $document;
            }
        }
        ftp_pasv($ftp_conn, true);

        $i = 0;
        while($array = odbc_fetch_array($SQL_prepare)){
            foreach ($array as $k => $v){
                $array_with_scan_names[$i][$k] = iconv("CP1251","UTF-8",$v);
            }

            $id_packet = $array_with_scan_names[$i]['id_packet'];
            $docs_img  = $array_with_scan_names[$i]['docs_img'];
            $docs_img  = iconv("UTF-8", "CP1251", $docs_img);
            $inter_id  = $array_with_scan_names[$i]['inter_id'];

            $folder_exists = is_dir('ftp://'.RABBIT_FTP_USER.':'.RABBIT_FTP_PASSWORD.'@'.RABBIT_FTP_SERVER.'/'.$id_packet);

            if(!$folder_exists){
                ftp_mkdir($ftp_conn, $id_packet);
            }

            if(ftp_put($ftp_conn,$id_packet.'/'.$docs_img, $puth_in_server."\\".$id_packet."\\".$inter_id."\\".$docs_img,FTP_BINARY)){
                $addedDoc = new editPackageAddDocuments($id_packet,$inter_id);
                $document = $addedDoc->getAddedDocument();
                if($document[0]['Extension'] != '') {
                    switch ($document[0]['DocumentType']) {
                        case 'Подлинник':
                            $document[0]['DocumentType'] = 'original';
                            break;
                        case 'Копия'    :
                            $document[0]['DocumentType'] = 'copy';
                            break;
                        case 'Копия, заверенная нотариально':
                            $document[0]['DocumentType'] = 'notarizedCopy';
                            break;
                        default:
                            $document[0]['DocumentType'];
                    }
                }

                $docInfo = pathinfo($document[0]['FilePath']);
                $document[0]['FilePath'] = $id_packet."\\".iconv('cp1251','utf-8',$docInfo['basename']);
                $document[0]['Extension'] = '.'.$document[0]['Extension'];

                $addedDocumentArray=[
                    'PackageId'        => $id_packet,
                    'SourceName'       => 'АИС МФЦ',
                    'Documents'        => $document
                ];
                $document = json_encode($addedDocumentArray, JSON_UNESCAPED_UNICODE);

                $connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
                $channel = $connection->channel();
                $channel->queue_declare('MfcOutcomingPackages', false, true, false, false);
                $msg = new AMQPMessage($document,
                    array('delivery_mode' => 2,
                        'type'          => 'AddDocumentsToPackage'
                    )
                );

                $channel->basic_publish($msg, '', 'MfcOutcomingPackages');
                //sleep(2);
                echo true;

                $channel->close();
                $connection->close();

                $SQL_update = "UPDATE dbo.Virtual_skans_To_SIR_State SET id_state = 1, date = GETDATE() WHERE id_packet = '$id_packet' and id_doc = $inter_id";
                $SQL_update_prepare = odbc_prepare($connect, $SQL_update);
                odbc_execute($SQL_update_prepare);
            }else{
                error_log('Копирование не удалось '.$id_packet.' -> '.$inter_id);
            };
            $i++;
        }

}
