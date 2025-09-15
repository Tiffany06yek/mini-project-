<?php
session_start();
require __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

// 只允许 POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'POST required']); 
  exit;
}

// 兼容两种字段名：email 或 signInEmail
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'Email and password are required']); 
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'Invalid email']); 
  exit;
}

$stmt = mysqli_prepare(
  $conn,
  'SELECT reg_id, first_name, last_name, email, phone_number, password_hash
    FROM registered_user
    WHERE email = ?'
);

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $first_name, $last_name, $db_email, $phone_num, $password_hash);

// 命中一行且密码匹配
if (mysqli_stmt_fetch($stmt) && password_verify($password, $password_hash)) {
  session_regenerate_id(true);
  $_SESSION['user_id'] = $id;
  $_SESSION['email']   = $db_email;                 // 用数据库中的值
  $_SESSION['name']    = trim("$first_name $last_name");

  echo json_encode(['ok'=>true]);  
} else {
  echo json_encode(['ok'=>false, 'msg'=>'Invalid email or password.']);
  mysqli_stmt_close($stmt);
  exit;
}
?>