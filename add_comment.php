<?php
include($_SERVER['DOCUMENT_ROOT'].'/gen/config.php');
require_once $_SERVER['DOCUMENT_ROOT']."/gen/connect.php";

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
/*require_once('../vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection; // Закомментировал потому что это обращение к библиотеки для Amqp протокола
use PhpAmqpLib\Message\AMQPMessage;*/
$id_operator = $_COOKIE['id'];
$id_packet   = $_POST['id_packet'];
$comment     = $_POST['comment'];
$comment     = iconv("utf-8", "cp1251", $comment);

$SQL_select = "SELECT id_operator,(ISNULL(FName,'')+' '+ISNULL(SecondName, '')+' '+ISNULL(Farth,'')) AS Fio_operator FROM dbo.Virtual_operators WHERE  id_operator = $id_operator";
$SQL_select_prep = odbc_prepare($connect, $SQL_select);
odbc_execute($SQL_select_prep);

if(odbc_num_rows($SQL_select_prep)>1){
    $author = 'Два оператора под одним ip'.$id_operator;
}else{
    $author = odbc_result($SQL_select_prep, 'Fio_operator');
}
$SQL_insert = "INSERT INTO dbo.Virtual_packet_comments_SIR (author, id_author, id_packet, comment, [date], readed) VALUES ('$author', $id_operator, '$id_packet', '$comment', GETDATE(), 1 )";
$SQL_insert_prep = odbc_prepare($connect, $SQL_insert);
odbc_execute($SQL_insert_prep);

$SQL_select = "SELECT TOP 1 id_comment, [date] FROM dbo.Virtual_packet_comments_SIR WHERE id_packet = '$id_packet' ORDER BY [date] DESC ";
$SQL_select_prep = odbc_prepare($connect, $SQL_select);
odbc_execute($SQL_select_prep);

$id_comment = odbc_result($SQL_select_prep, 'id_comment');
$date       = odbc_result($SQL_select_prep, 'date');
$author     = iconv('CP1251', 'UTF-8', $author);
$comment    = iconv('CP1251', 'UTF-8', $comment);
$messageArray = [
    'PackageId' => $id_packet,
    'Comments'  => [[
        'CommentId' =>  $id_comment,
        'Date'      =>  $date,
        'Author'    =>  $author,
        'Text'      =>  $comment
        ]]
];
$jsonArr = json_encode($messageArray,JSON_UNESCAPED_UNICODE);

$printTr = '<tr style=\" height: auto !important;\">
               <td class =\'firstTd\'>'.$author.'</td>
               <tr>
                   <td class =\'secondTd\'>'.$comment.'</td>
                   <td class =\'thirdTd\'>'.$date.'</td>
               </tr>
           </tr>';
print($printTr);
$connection = new AMQPStreamConnection(SIR_RABBIT_URL, SIR_RABBIT_PORT, SIR_RABBIT_USER, SIR_RABBIT_PASSWORD);
$channel = $connection->channel();
$channel->queue_declare('MfcOutcomingPackages', false, true, false, false);
$msg = new AMQPMessage($jsonArr,
    array(  'delivery_mode' => 2,
            'type'          => 'AddComments'
    )
);

$channel->basic_publish($msg, '', 'MfcOutcomingPackages');
echo true;
$channel->close();
$connection->close();



