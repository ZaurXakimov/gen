<?php
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    require_once $_SERVER['DOCUMENT_ROOT']."/gen/connect.php";
} else {
    require_once "connect.php";
}

$sql = "SELECT [key], [value] FROM dbo.Virtual_settings";
$res = odbc_exec($connect, $sql);
while ($s = odbc_fetch_array($res)){
    foreach ($s as $k => $v){
        $s[$k] = iconv ("CP1251", "UTF-8", $v);
    }
    if (!defined($s['key'])) define($s['key'], $s['value']);
}
?>