<?php
// 设置返回内容类型为 JSON
header('Content-Type: application/json');

// 获取 POST 请求的原始数据
$inputData = json_decode(file_get_contents("php://input"), true);

// 确保接收到数据
if (!$inputData) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received.']);
    exit;
}

// 获取订单数据
$orderId = $inputData['id'] ?? '';
$userId = $inputData['userId'] ?? '';
$items = json_encode($inputData['items']);  // 订单项数据
$total = $inputData['total'] ?? 0.00;
$dropOff = $inputData['dropOff'] ?? '';  // 送货地址
$timestamp = $inputData['timestamp'] ?? date('Y-m-d H:i:s');

// 1. 插入订单数据到 orders 表
$sql = "INSERT INTO Orders (id, buyer_id, items, total, drop_off, timestamp) 
        VALUES ('$orderId', '$userId', '$items', '$total', '$dropOff', '$timestamp')";

if ($conn->query($sql) === TRUE) {
    // 2. 更新用户余额 (假设你有一个 balance 字段)
    $userSql = "SELECT balance FROM users WHERE id = '$userId'";
    $result = $conn->query($userSql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $newBalance = $row['balance'] - $total;  // 扣除订单总额

        if ($newBalance < 0) {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance.']);
            exit;
        }

        // 更新用户余额
        $updateUserSql = "UPDATE users SET balance = '$newBalance' WHERE id = '$userId'";
        if ($conn->query($updateUserSql) === TRUE) {
            // 成功更新余额
            echo json_encode(['success' => true, 'message' => 'Order placed successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user balance.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
} else {
    // 插入订单失败
    echo json_encode(['success' => false, 'message' => 'Error placing order: ' . $conn->error]);
}

// 关闭数据库连接
$conn->close();
?>

