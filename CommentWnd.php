<?php
require_once $_SERVER['DOCUMENT_ROOT']."/gen/connect.php";
$id_packet = $_POST['id_packet'];
$SQL = "SELECT * from dbo.Virtual_packet_comments_SIR where id_packet = '$id_packet' ORDER BY id_comment DESC";
$SQL_prep = odbc_prepare($connect, $SQL);
odbc_execute($SQL_prep);
$comment = '';

$wndContent = "
    <div class = 'commentBackGround'>
    
    <div id='commentWnd_$id_packet' class='commentMdlWnd'>
    <a title=\"Закрыть\" class=\"class_close_modal\" onclick=closeCommentWnd('$id_packet')></a>
    <h2 align=\"center\">Комментарии по пакету $id_packet </h2>
    <div class='commentMdlContent'>

   <table id='commentTable_$id_packet' class = 'commentTable'>
   <tbody>";

if (odbc_num_rows($SQL_prep)>0){

    for($i = 1; $i <= odbc_num_rows($SQL_prep); $i++){
        $wndContent.="<tr style='height: auto !important;'>";

        odbc_fetch_row($SQL_prep,$i);
        $name_operator = iconv("CP1251","UTF-8", odbc_result($SQL_prep, 'author'));
        $comment       = iconv("CP1251","UTF-8", odbc_result($SQL_prep, 'comment'));
        $date          = odbc_result($SQL_prep, 'date');
        $ndate         = new DateTime($date);
        $res           = $ndate->format('Y-m-d H:i');

        //$date          = substr(0,16,$date);
        $wndContent.= "<td class ='firstTd'>".$name_operator."</td>
                       <tr>
                       <td class ='secondTd'>".$comment."</td>
                       <td class ='thirdTd'>".$res."</td>
                       </tr>";
        $wndContent.="</tr>";
    }
}else {
    $wndContent.="<tr style='height: auto !important;'></tr>";
}
$wndContent.='</tbody></table>';
$wndContent.="
    </div>
    <div>
    	
<textarea id='commentTextArea_$id_packet' class='commentTextArea' onkeyup= getPressedKeyCommWnd(event,'$id_packet')></textarea>
<button onclick= sendPacketCommentSir('$id_packet',document.getElementById('commentTextArea_$id_packet').value)> Отправить</button>

    </div>
</div>
</div>";

print($wndContent);
