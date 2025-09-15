<?php
//AJAX（前端 → 后端不刷新页面的请求）
require __DIR__ . '/database.php';
$conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name, $port);

$first_name = ($_POST['firstName'] ?? '');
$last_name = ($_POST['lastName'] ?? '');
$email = trim($_POST['signUpEmail'] ?? '');
$phone_number = trim($_POST['phoneNumber'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_pswd = $_POST['confirm_pswd'] ?? '';

function back($params = []) {
    $qs = http_build_query($params);
    header('Location: ./login.html' . ($qs ? ('?'.$qs) : ''));
    exit;
}

if ($first_name === '' || $last_name === '' || $email === '' || $phone_number === '' || $password === ''){
    back(['err' => 'empty']);
    exit;
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    back(['err' => 'invalid_email']);
    exit;
}

if (!preg_match("/@xmu\.edu\.my$/i", $email)) {
    back(['err' => 'campus_email']);
    exit;
}


if (strlen($password) < 8) {
    back(['err' => 'short_pwd']);
    exit;
}

if ($password !== $confirm_pswd){
    back(['err' => 'mismatch']);
    exit;
}


$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = mysqli_prepare($conn,
                            "INSERT INTO registered_user (first_name, last_name, email, phone_number, password_hash)
                             VALUES (?,?,?,?,?)"
                            );
    mysqli_stmt_bind_param($stmt, 'sssss', $first_name, $last_name, $email, $phone_number, $hash); //把 3 个参数按顺序绑定到 3 个 ?。
    mysqli_stmt_execute($stmt);
    back(['registered' => 1]);
} 
catch (mysqli_sql_exception $e) {
    if ($e -> getCode() == 1062) { //duplicate email
        back(['err' => 'dup_email']);
    } else {
        back(['err' => 'server']);
    }
}

?>

