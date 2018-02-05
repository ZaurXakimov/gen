<?php
header("Content-Type: text/html; charset=UTF-8");
include "connect.php";

$id_packet = $_POST['id_packet'];
$id_doc    = $_POST['uidDoc'];
$fileName  = $_POST['fileName'];

$fileName  = iconv("UTF-8","CP1251", $fileName);
$text       = "Документ не записан в директорию. Ошибка соединения.";
$error_text = iconv('UTF-8', 'CP1251', $text);
$SQL = "INSERT INTO dbo.Virtual_skans_To_SIR_State
        ( id_packet ,
          id_doc ,
          img_doc,
          id_state ,
          state ,
          date
        )
VALUES  ( '$id_packet',
          $id_doc,
          '$fileName',
          0,
          '',
          GETDATE()
        )";
$SQL_prep = odbc_prepare($connect, $SQL);
odbc_execute($SQL_prep);