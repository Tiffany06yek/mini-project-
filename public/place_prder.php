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
        'message' => 'Incorrect Order Data。'
    ]);
    exit;
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot checkout with empty cart。'
    ]);
    exit;
}

$dropOff = trim((string)($data['dropOff'] ?? ''));
if ($dropOff === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in the dropOff location.'
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
        'message' => 'Insufficient Balance.'
    ]);
    exit;
}

$orderId = uniqid('ORD');
$order = [
    'id' => $orderId,
    'userId' => $data['userId'] ?? 0,
    'items' => $items,
    'subtotal' => $subtotal,
    'deliveryFee' => $deliveryFee,
    'total' => $total,
    'paymentMethod' => $data['paymentMethod'] ?? 'wallet',
    'paymentStatus' => $data['paymentStatus'] ?? 'paid',
    'dropOff' => $dropOff,
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