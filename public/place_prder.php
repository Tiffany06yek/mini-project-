<?php
session_start();

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Please use POST to place an order.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order data格式不正确。'
    ]);
    exit;
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '购物车是空的，不能下单。'
    ]);
    exit;
}

$dropOff = trim((string)($data['dropOff'] ?? ''));
if ($dropOff === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请填写送达地点。'
    ]);
    exit;
}

$subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : 0.0;
$deliveryFee = isset($data['deliveryFee']) ? (float)$data['deliveryFee'] : 0.0;
$total = isset($data['total']) ? (float)$data['total'] : $subtotal + $deliveryFee;

if ($subtotal <= 0 || $total <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '金额有问题，请确认后再试。'
    ]);
    exit;
}

$orderId = uniqid('ORD');
$order = [
    'id' => $orderId,
    'userId' => $data['userId'] ?? 0,
    'customerName' => trim((string)($data['customerName'] ?? '')),
    'customerNumber' => trim((string)($data['customerNumber'] ?? '')),
    'items' => $items,
    'subtotal' => $subtotal,
    'deliveryFee' => $deliveryFee,
    'total' => $total,
    'paymentMethod' => $data['paymentMethod'] ?? 'wallet',
    'paymentStatus' => $data['paymentStatus'] ?? 'paid',
    'orderStatus' => $data['orderStatus'] ?? 'preparing',
    'merchantId' => $data['merchantId'] ?? null,
    'dropOff' => $dropOff,
    'timestamp' => $data['timestamp'] ?? date('c'),
    'createdAt' => date('c'),
];

if (!isset($_SESSION['placed_orders']) || !is_array($_SESSION['placed_orders'])) {
    $_SESSION['placed_orders'] = [];
}

$_SESSION['placed_orders'][] = $order;

echo json_encode([
    'success' => true,
    'orderId' => $orderId,
]);