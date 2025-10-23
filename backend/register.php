<?php
require __DIR__ . '/database.php';

$firstName = $_POST['firstName'] ?? '';
$lastName  = $_POST['lastName'] ?? '';
$name = trim($firstName . ' ' . $lastName);
$email = trim($_POST['signUpEmail'] ?? '');
$phone_number = trim($_POST['phoneNumber'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_pswd = $_POST['confirm_pswd'] ?? '';

function back($params = []) {
    $qs = http_build_query($params);
    header('Location: ../public/login.html' . ($qs ? ('?'.$qs) : ''));
    exit;
}

if ($firstName === '' || $lastName === '' ||$email === '' || $phone_number === '' || $password === ''){
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
                            "INSERT INTO users (name, school_email, phone, password_hash)
                             VALUES (?,?,?,?)"
                            );
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone_number, $hash); //把 3 个参数按顺序绑定到 3 个 ?。
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

