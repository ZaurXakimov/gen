<?php // Очередь: MfcOutcomingPackages
include($_SERVER['DOCUMENT_ROOT'].'/gen/config.php');
require_once $_SERVER['DOCUMENT_ROOT']."/gen/applicant/applicant_parser.php";

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$sqlSirStatus = "SELECT p.id_packet FROM dbo.Virtual_packets p
                    LEFT JOIN dbo.themes t ON t.id_theme = p.id_th
                 WHERE p.id_packet = '$idPacket' AND t.send_org = 1";
$resultSirStatus = odbc_exec($connect, $sqlSirStatus);
if (odbc_num_rows($resultSirStatus) < 1) exit;

class editPackageAddDocuments extends Export{
    public function getAddedDocument(){
        global $idPacket;
        global $uidDoc;
        $this->id_packet = $idPacket;
        $this->uidDoc    = $uidDoc;
        $document = $this->getAddedDocument_func($this->uidDoc);
        return $document;
    }

}

$addedDoc = new editPackageAddDocuments($idPacket,$uidDoc);
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
    $document[0]['FilePath'] = $idPacket."\\".iconv('cp1251','utf-8',$docInfo['basename']);
    $document[0]['Extension'] = '.'.$document[0]['Extension'];

    $addedDocumentArray=[
        'PackageId'        => $idPacket,
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

echo true;
$channel->close();
$connection->close();
