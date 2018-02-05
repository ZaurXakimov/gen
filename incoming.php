<?php
require_once $_SERVER['DOCUMENT_ROOT']."/gen/connect.php";
require_once $_SERVER['DOCUMENT_ROOT']."/gen/public_function/utils.php";
require_once $_SERVER['DOCUMENT_ROOT']."/gen/js/main.js";

$otdel        = '';
$org          = '';
$theme        = '';
$startdate    = '';
$stopdate     = '';
$id_packet    = '';
$reestr       = '';
$id_dist_org  = '';
$id_exec_dist = '';

if (isset($_POST['otdel']))         $otdel        = $_POST['otdel'];
if (isset($_POST['org']))           $org          = $_POST['org'];
if (isset($_POST['theme']))         $theme        = $_POST['theme'];
if (isset($_POST['startdate']))     $startdate    = $_POST['startdate'];
if (isset($_POST['stopdate']))      $stopdate     = $_POST['stopdate'];
if (isset($_POST['id_packet']))     $id_packet    = $_POST['id_packet'];
if (isset($_POST['reestr']))        $reestr       = $_POST['reestr'];
if (isset($_POST['district']))      $id_exec_dist = $_POST['district'];
if (isset($_POST['district_org']))  $id_dist_org  = $_POST['district_org'];

$whereAndIdOrg     = '';
$whereAndIdTheme   = '';
$whereAndIdPacket  = '';
$whereAndIdOtdelIn = '';
$whereAndIdReestr  = '';
$whereAndIdDist    = '';
$whereAndIdDistOrg = '';

if (isset($org) && !empty($org) && $org != 0){
    if ($org == 0) {
        $whereAndIdOrg = "";
    } else {
        $whereAndIdOrg = " AND t.id_org = $org ";

        if (isset($theme) && !empty($theme) && $theme > 0){
            $whereAndIdTheme = " AND p.id_th = $theme ";
        } else {
            $whereAndIdTheme = "";
        }
    }
} else {
    $whereAndIdOrg = "";
}

if (isset($startdate) && !empty($startdate)){
    $startdate = strToDate($startdate);
    $startdate = date('Ymd',$startdate);
}
if (isset($stopdate) && !empty($stopdate)) {
    $stopdate = strToDate($stopdate);
    $stopdate = date('Ymd', $stopdate + 86400);
}

if (!empty($id_packet)) $whereAndIdPacket  = " AND p.id_packet LIKE '%$id_packet%' ";
if ($otdel != 'all')    $whereAndIdOtdelIn = " AND p.id_otdel_in = $otdel ";
if ($reestr == 'false') $whereAndIdReestr  = " AND r.numb_reestr IS NULL ";
if ($id_dist_org > 0)   $whereAndIdDist    = " AND p.id_district_organization = $id_dist_org ";
if ($id_exec_dist > 0)  $whereAndIdDistOrg = " AND p.id_exec_district = $id_exec_dist ";

$sql_not = "
            SELECT distinct p.id_packet
              ,t.name_theme
              ,p.customers
              ,op.Family + ' ' + op.Name + ' ' + op.Farth AS operator
              ,p.date_beg
              ,s.name_status
              ,t.send_org
              ,o.id_org
              ,o.name_org
              ,isnull(o.org_address,'') AS org_address
              ,isnull(ds.name_org_district,'') AS name_org_district
              ,isnull(ds.org_district_address,'') AS org_district_address
              ,isnull(d.name_district,'') AS name_district
              ,isnull(d.district_address,'') AS district_address
              ,ds.id_district_organization
		      ,d.id_district
            FROM dbo.Virtual__packets p
              JOIN dbo.themes t                             ON t.id_theme = p.id_th
              LEFT JOIN Virtual_statuses s                  ON s.id_status = p.status
              LEFT JOIN Virtual_operators op                ON p.id_operator_b = op.id_operator
			  LEFT JOIN Virtual_dbo.organization o          ON o.id_org = t.id_org
			  LEFT JOIN Virtual_districts_organizations ds  ON ds.id_district_organization = p.id_district_organization
		      LEFT JOIN Virtual_districts d                 ON d.id_district = p.id_exec_district
            WHERE t.send_org IN (1)
              AND p.status IN (1, 2, 25)
              AND p.date_beg BETWEEN '$startdate' AND '$stopdate'
			  AND p.date_end IS NOT NULL
			  $whereAndIdOtdelIn
              $whereAndIdOrg
              $whereAndIdTheme
              $whereAndIdPacket
              $whereAndIdDist
              $whereAndIdDistOrg
			  ORDER BY  o.id_org, ds.id_district_organization, d.id_district, send_org, date_beg DESC
            ";

$result_n = odbc_prepare($connect, $sql_not);
odbc_execute($result_n);

$row_data = '';
$count_n = odbc_num_rows($result_n);

while ($a = odbc_fetch_array($result_n)){
		$name_org = $a['name_org'];
				if(!empty($a['org_address']))
					$name_org .= '('. $a['org_address'].')';
		
			
				if(!empty($a['name_org_district']))
				{
					$name_org=$a['name_org_district'];
					if(!empty($a['org_district_address']))
						$name_org .= '('. $a['org_district_address'].')';
				}				
				
				if(!empty($a['name_district']))
				{
					$name_org= $a['name_district'];
						if(!empty($a['district_address']))
						$name_org .= '('. $a['district_address'].')';
				}
				
				$current_id_org_elem = $a['id_org'] ."_".$a['id_district_organization'] ."_".$a['id_district'];
						$name_org = iconv("CP1251", "UTF-8", $name_org);
				if($current_id_org_elem !=  $current_id_org_elem_Last)
				{ 
					$row_data .= "<tr><td colspan='8'><h2>$name_org</h2></td></tr>";
						$current_id_org_elem_Last = $current_id_org_elem;
				}		
    foreach ($a as $key => $value) {
        $a[$key] = iconv("CP1251", "UTF-8", $value);
    }

    $id_packet_url = "<a href='#' onclick='clickInfo(\"$a[id_packet]\")'>$a[id_packet]</a>";

    $date = date('d-m-Y', strtotime($a['date_beg']));
    $time = date('H:i:s', strtotime($a['date_beg']));
    $date_time = "<span class='date'>$date <span class='time'>$time</span></span>";

    switch ($a['send_org']) {
        case 1: // Отправка
            $send = "<button class='button button-simple' onclick='sendSIR(\"$a[id_packet]\")'>Отправить</button>";
            break;
        default:
            $send = "";
    }
    $row_data .= "<tr>
                    <td><input value='$a[id_packet]' data-cb-item='cb_not_send' type='checkbox' onclick='cbItem($(this))'></td>
                    <td>$id_packet_url</td>
                    <td>$a[name_theme]</td>
                    <td>$a[customers]</td>
                    <td>$a[operator]</td>
                    <td>$date_time</td>
                    <td>$a[name_status]</td>
                    <td align='center' id='dynBut_$a[id_packet]'>$send</td>
                 </tr>";
}

$id_table_n = "not_send";
echo "
<div>
<h1 onclick='$( \"#$id_table_n\" ).toggle()'>Не отправленные пакеты <br>ВСЕГО НАЙДЕНО: $count_n</h1>
<a  onclick='cbSendItems(\"cb_not_send\", 1)' id = 'btn_unsent_packets' class = 'unsentPackages' >Отправить выбранные: 
    <span data-cb-checked-items='cb_not_send'>0</span>
    <span data-cb-count-items='cb_not_send'>-</span>
</a>
<a  onclick='parseTable(\"{$id_table_n}\", [0,7])' target='_blank' class='ico print'>Распечатать список</a>

<table id='$id_table_n' class='table'>
    <thead>
        <tr>
            <th><input data-cb-group='cb_not_send' type='checkbox' onclick='cbGroup($(this))'></th>
            <th>НОМЕР ПАКЕТА</th>
            <th>УСЛУГА</th>
            <th>ЗАЯВИТЕЛЬ</th>
            <th>ОПЕРАТОР</th>
            <th>ДАТА</th>
            <th>СТАТУС</th>
            <th>ОТПРАВИТЬ</th>
        </tr>
    </thead>
    <tbody>
        $row_data
    </tbody>
</table>
</div>
";