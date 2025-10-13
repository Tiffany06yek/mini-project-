<?php
require __DIR__ . '/../backend/database.php'; // 连接数据库

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
  echo json_encode(['success' => false, 'message' => 'No items received']);
  exit;
}

// 获取客户信息（假设有登录）
session_start();
$customer_id = $_SESSION['customer_id'] ?? 1;

// 创建主订单
$total = floatval($data['total'] ?? 0);
$shipping = floatval($data['shipping'] ?? 0);
$sql = "INSERT INTO orders (customer_id, total_price, shipping_fee, status, created_at)
        VALUES (?, ?, ?, 'Pending', NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("idd", $customer_id, $total, $shipping);
$stmt->execute();
$order_id = $stmt->insert_id;

// 插入订单项
$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, addons) VALUES (?, ?, ?, ?, ?)");
foreach ($data['items'] as $it) {
  $addonsJson = json_encode($it['addons'] ?? []);
  $itemStmt->bind_param("iiids", $order_id, $it['productId'], $it['qty'], $it['price'], $addonsJson);
  $itemStmt->execute();
}

echo json_encode(['success' => true, 'order_id' => $order_id]);
