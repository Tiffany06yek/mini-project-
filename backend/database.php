<?php
// 让 mysqli 出错时抛异常，便于 catch 到具体错误
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$db_server = "127.0.0.1";
$db_user = "root";
$db_pass = "root";
$db_name = "DB_xiapee";
$port = 8889;
$conn = "";

try {//放“可能出错”的代码。
    $conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name, $port);//尝试连接数据库。 参数分别是：数据库主机、用户名、密码、库名。
    mysqli_set_charset($conn, 'utf8mb4');
}
catch (mysqli_sql_exception $e) {
    throw $e;
}


mysqli_set_charset($conn, 'utf8mb4');
