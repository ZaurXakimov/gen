<?php
include($_SERVER['DOCUMENT_ROOT'].'/gen/config.php');
require_once $_SERVER['DOCUMENT_ROOT']."/gen/applicant/applicant_parser.php";
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/

$id_packet = $_POST['id_packet'];
$id_operator = $_COOKIE['id'];
$sqlSirStatus = "SELECT p.id_packet FROM dbo.Virtual_packets p
                    LEFT JOIN dbo.themes t ON t.id_theme = p.id_th
                 WHERE p.id_packet = '$id_packet' AND t.send_org = 1";
$resultSirStatus = odbc_exec($connect, $sqlSirStatus);
if (odbc_num_rows($resultSirStatus) < 1) exit;

class ExportJSONforSIR extends Export{
    public function getJSON(){
        global $id_packet;
        $this->id_packet = $id_packet;
        $packet = $this->getFieldsArray($this->id_packet);
        $arr = [];
        $notes = null;
        foreach ($packet['Applicant'] as  $idClient => $applicant){
            $fields_arr=[];
            $notes .= $applicant['AppFields']['cl_notes']['value'].' ';
            foreach ($applicant['AppFields'] as $fields) {
                if($fields['dbname'] == 'cl_addr_KLADR' && $fields['value'] != ''){
                    $fields_arr[] = [
                        'Name'          => $fields['label']. '(КЛАДР)',
                        'Value'         => $fields['value']
                    ];
                }else{
                    $fields_arr[] = [
                        'Name'          => $fields['label'],
                        'Value'         => $fields['value']
                    ];
                }
            }

            if($applicant['AddFields'] != ''){
                foreach ($applicant['AddFields'] as $fields) {
                    $fields_arr[] = [
                        'Name' => $fields['label'],
                        'Value' => $fields['value']
                    ];
                }
            }

            $applicant_type=null;

            if($applicant['AppFields']['type_client']['value']=='физлицо'){
                $applicant_type='physical';
            }
            elseif($applicant['AppFields']['type_client']['value']=='юрлицо'){
                $applicant_type='juridical';
            }
            elseif($applicant['AppFields']['type_client']['value']=='ИП'){
                $applicant_type='entrepreneur';
            }

            $appl[] = [
                'ApplicantType' => $applicant_type,
                'ApplicantId'   => $idClient,
                'Fields' => $fields_arr
            ];
        }
        $documents = $this->getDocumentsArray();
        $packets_param = $this->getPacketParamsArray();
        $packet_docs = [];
        foreach ($documents as $key => $document) {
            if($document['Extension'] != '')
            {
                switch ($document['DocumentType']) {
                    case 'Подлинник':
                        $docType = 'original';              break;
                    case 'Копия'    :
                        $docType = 'copy';                  break;
                    case 'Копия, заверенная нотариально':
                        $docType = 'notarizedCopy';         break;
                    default:
                        $docType = $document['DocumentType'];
                }
                $docInfo = pathinfo($document['FilePath']);
                $packet_docs[] =[
					'DocumentId'    => $document['DocumentId'],
                    'Data' 		    => '',
                    'FilePath'      => $id_packet."\\".iconv('cp1251','utf-8',$docInfo['basename']),
                    'Extension'     => '.'.$document['Extension'],
                    'Type'		    => $document['Type'],
                    'ProvidedType'  => $docType
                ];
            }
        }
        include($_SERVER['DOCUMENT_ROOT'].'/gen/connect.php');
        $sql = "    SELECT 
       			       p.id_operator_b,
       			       p.reestr_id_serv,
    				   t.reestr_id_serv,
    				   o.reestr_authority_ID,
    				   t.single_point_service,
					   p.id_exec_district,
					   d.district_SIR_code
				    FROM dbo.Virtual_packets AS p
			            LEFT JOIN dbo.Virtual_themes AS t         ON t.id_theme = p.id_th
            			LEFT JOIN dbo.Virtual_organization AS o   ON o.id_org = t.id_org
						LEFT JOIN dbo.Virtual_districts AS d      ON d.id_district = p.id_exec_district
            WHERE p.id_packet = '$this->id_packet'";

        $result = odbc_prepare($connect, $sql);
        odbc_execute($result);
        $a = odbc_fetch_array($result);
        $source=null;
        if ($a['single_point_service']){
            $source = 'Ecu';
        }else{
            $source = 'Mfc';
        }
        $id_operator = $_COOKIE['id'];
        $OrganizationUnitRegistryId = $a['reestr_authority_ID'];
        if($a['id_exec_district'] > 0 && $a['district_SIR_code'] != NULL){
            $OrganizationUnitRegistryId = $a['district_SIR_code'];
        }

        $id_otdel_to_out = $packets_param['id_otdel_to_out']['value'];
        $id_otdel_in     = $packets_param['id_otdel_in']['value'];
        $districtCode = '';
        if ($id_otdel_in != $id_otdel_to_out){
            $districtCode = $id_otdel_to_out;
        } else {
            $districtCode='';
        }
        $packet_info =[
            'AdditionalInfo'					   	  => $notes,
            'CreateDate' 					          => $packets_param['date_beg']['value'],
            'EndDate'                                 => $packets_param['date_end']['value'],
            'GovServiceRegistryId' 		  		      => $a['reestr_id_serv'],
            'OrganizationUnitRegistryId'			  => $OrganizationUnitRegistryId,
            'PackageId'						          => $packets_param['id_packet']['value'],
            'Source'							      => $source,
            'SourceName'						      => "АИС МФЦ СКАТ",
            'Bodies' 						          => $appl,
            'Documents'						          => $packet_docs,
            'DistrictCode'                            => $districtCode,
            'SourceDistrictCode'					  => $id_otdel_in,
            'MatrTH'                                  => $packets_param['matr_th']['value']
        ];
        return json_encode($packet_info, JSON_UNESCAPED_UNICODE);
    }
}

$JSON = new ExportJSONforSIR($id_packet);
error_log($JSON -> getJSON());
$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
$channel = $connection->channel();
$channel->queue_declare('MfcOutcomingPackages', false, true, false, false);
$msg = new AMQPMessage($JSON -> getJSON(),
    array('delivery_mode' => 2,
        'type'          => 'SendPackage'
    )
);

$channel->basic_publish($msg, '', 'MfcOutcomingPackages');

// Статутс пакета как отправленный в организацию (2)

	$check_already_closed = "SELECT date_out FROM dbo.Virtual_packets where id_packet = '$id_packet' AND date_out IS NULL AND date_in IS NULL";
	$result = odbc_prepare($connect,$check_already_closed);
	odbc_execute($result);
	$count_rows = odbc_num_rows($result);
	if($count_rows >= 1){
		$sqlStatPacket = "UPDATE dbo.Virtual_packets SET status=2, date_out_v_oiv = GETDATE() WHERE id_packet = '$id_packet' and date_out IS NULL";
		$result_SP = odbc_prepare($connect, $sqlStatPacket);
		odbc_execute($result_SP);
	}
//TODO:Zaur Отключенны статусы для того чтобы пакет появлялся в реестре.

// Информация об отправлении пакета.
$sqlStat = "INSERT INTO dbo.Virtual_status_in_SIR_history (id_packet, date_send, id_operator, url, info) VALUES ('$id_packet', GETDATE(), $id_operator, '', 'SendPackage')";
$result_S = odbc_prepare($connect, $sqlStat);
odbc_execute($result_S);

echo true;
$channel->close();
$connection->close();
?>