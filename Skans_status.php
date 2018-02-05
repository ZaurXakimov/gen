<script type="text/javascript" src="/gen/js/main.js"></script>;
<?php
include($_SERVER['DOCUMENT_ROOT'].'/gen/config.php');
include "../gen/connect.php";

/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$id_packet = $_GET['id_packet'];
$id_state  = $_GET['packet_state'];

$sql_request = "
	SELECT 
		th.send_org as so
	FROM Virtual_packets AS p
	JOIN Virtual_themes AS th ON th.id_theme = p.id_th
	WHERE p.id_packet='$id_packet'";
$sql_prep = odbc_prepare($connect, $sql_request);
odbc_execute($sql_prep);
$send_org = odbc_result($sql_prep, 'so');

if($send_org == 1){
	$state='';
	switch ($id_state) {
        case 3:
            $state = 'paused';
            $array=[
                "PackageId"=> $id_packet,
                "State"	   => $state
            ];
            break;
        case 10:
            $state = 'paused';
            $array=[
                "PackageId"=> $id_packet,
                "State"	   => $state
            ];
            break;
        case 4:
            $state = 'claim_completed';
            $array=[
                "PackageId"=> $id_packet,
                "State"	   => $state
            ];
            break;
		case 12:
			$state = 'delivered';
            $array=[
                "PackageId"=> $id_packet,
                "State"	   => $state
            ];
			break;
        case 16:
            $SQL = "SELECT status FROM dbo.Virtual_packets WHERE id_packet='$id_packet'";
            $SQL_prepare = odbc_prepare($connect,$SQL);
            odbc_execute($SQL_prepare);
            $stat = odbc_result($SQL_prepare, 'status');

            if ($stat != 16){
                exit;
            }else{
                $state = 'canceled';
                $SQL = "SELECT * FROM dbo.Virtual_docs_result WHERE id_packet = '$id_packet' and last = 1";
                $SQL_prepare = odbc_prepare($connect, $SQL);
                odbc_execute($SQL_prepare);

                if(!odbc_num_rows($SQL_prepare)){
                    $Document = "";
                }else{
                    $filename = iconv("CP1251","UTF-8",odbc_result($SQL_prepare, 'filename'));
                    $full_path = iconv("CP1251","UTF-8",odbc_result($SQL_prepare, 'full_path'));
                    $SQL = "select top(1) base_puth_scan_for_visible, base_puth_scan_for_read from Virtual_gen_inf;";
                    $SQL_prepare = odbc_prepare($connect, $SQL);
                    odbc_execute($SQL_prepare);
                    $puth_in_server = odbc_result($SQL_prepare, "base_puth_scan_for_read").'\..\result_docs';

                    $ftp_server   = RABBIT_FTP_SERVER;
                    $ftp_username = RABBIT_FTP_USER;
                    $ftp_userpass = RABBIT_FTP_PASSWORD;

                    $ftp_conn     = ftp_connect($ftp_server) or die ("<script type='text/javascript'>addSkanState('$id_packet',0, '$full_path')</script>");
                    $login        = ftp_login($ftp_conn, $ftp_username, $ftp_userpass);

                    if($ftp_conn != FALSE){
                        ftp_pasv($ftp_conn, true);
                        $folder_exists = is_dir('ftp://'.RABBIT_FTP_USER.':'.RABBIT_FTP_PASSWORD.'@'.RABBIT_FTP_SERVER.'/'.$id_packet);
                        if(!$folder_exists){
                            ftp_mkdir($ftp_conn, $id_packet);
                        }
                        ftp_put($ftp_conn,$id_packet.'/'.$filename,iconv("UTF-8","cp1251", $puth_in_server."\\".$id_packet."\\".$filename),FTP_BINARY);
                    }
                    $doc_extension = pathinfo($puth_in_server."\\".$id_packet."\\".$filename);
                    $Document[] = [
                        'Type'              => 'Основание для отказа',
                        'Extension'         => '.'.$doc_extension['extension'],
                        'Data'              => '',
                        'FilePath'          => $id_packet."\\".$filename,
                        'DocumentType'      => 'original'
                    ];
                }
                $array=[
                    "PackageId"=> $id_packet,
                    "State"	   => $state,
                    "Documents"=> $Document
                ];

            }
            break;
        case 99:
            $state = 'removed';
            $array=[
                "PackageId"=> $id_packet,
                "State"	   => $state
            ];
            break;
	}

	if($state){
		$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
		$channel = $connection->channel();
		$channel->queue_declare('MfcOutcomingPackages', false, true, false, false);
		$msg = new AMQPMessage(json_encode($array, JSON_UNESCAPED_UNICODE),
		                        array('delivery_mode' => 2,
		                              'type'          => 'ChangePackageState'
		                              ) # make message persistent
		                      );
		$channel->basic_publish($msg, '', 'MfcOutcomingPackages');
	}
}
?>
