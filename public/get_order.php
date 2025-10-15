<?php
session_start();

header('Content-Type: application/json');

$orderId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
if ($orderId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing order id.'
    ]);
    exit;
}

$orders = isset($_SESSION['placed_orders']) && is_array($_SESSION['placed_orders'])
    ? $_SESSION['placed_orders']
    : [];

$matched = null;
foreach ($orders as $order) {
    if (!is_array($order)) {
        continue;
    }
    if ((string)($order['id'] ?? '') === $orderId) {
        $matched = $order;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Order not found.'
    ]);
    exit;
}

// Simple courier “database”
$couriers = [
    [
        'courier_id' => 'C1001',
        'merchant_id' => '101',
        'name' => 'Alex Tan',
        'phone' => '012-3456789',
        'hire_date' => '2021-04-10'
    ],
    [
        'courier_id' => 'C1002',
        'merchant_id' => '102',
        'name' => 'Nur Aisyah',
        'phone' => '013-9876543',
        'hire_date' => '2020-12-01'
    ],
    [
        'courier_id' => 'C1003',
        'merchant_id' => '103',
        'name' => 'Lim Wei',
        'phone' => '014-2233445',
        'hire_date' => '2022-06-18'
    ],
];

$merchantId = (string)($matched['merchantId'] ?? '');
if ($merchantId === '' && !empty($matched['items'][0]['vendorId'])) {
    $merchantId = (string)$matched['items'][0]['vendorId'];
}
$courier = null;
if ($merchantId !== '') {
    foreach ($couriers as $candidate) {
        if ((string)($candidate['merchant_id'] ?? '') === $merchantId) {
            $courier = $candidate;
            break;
        }
    }
}

if (!$courier) {
    $courier = [
        'courier_id' => 'C0000',
        'merchant_id' => $merchantId,
        'name' => 'Assigned Courier',
        'phone' => 'N/A',
        'hire_date' => '2020-01-01'
    ];
}

$labels = [
    'placed' => 'Placed Order',
    'preparing' => 'Preparing',
    'picked' => 'Picked Up By Courier',
    'on-the-way' => 'On The Way',
    'arrived' => 'Arrived'
];

$statusMap = array_keys($labels);
$currentStatus = strtolower(str_replace(' ', '-', (string)($matched['orderStatus'] ?? 'placed')));
$activeIndex = array_search($currentStatus, $statusMap, true);
if ($activeIndex === false) {
    $activeIndex = 0;
}

$statusSteps = [];
$index = 0;
foreach ($labels as $key => $title) {
    $statusSteps[] = [
        'key' => $key,
        'title' => $title,
        'done' => $index <= $activeIndex
    ];
    $index++;
}

$items = [];
foreach ($matched['items'] as $item) {
    if (!is_array($item)) {
        continue;
    }
    $items[] = [
        'name' => $item['name'] ?? 'Item',
        'qty' => (int)($item['qty'] ?? 0),
        'price' => (float)($item['price'] ?? 0),
        'total' => (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0),
        'addons' => isset($item['addons']) && is_array($item['addons']) ? $item['addons'] : [],
        'vendorName' => $item['vendorName'] ?? ''
    ];
}

$response = [
    'success' => true,
    'order' => [
        'id' => $matched['id'],
        'status' => $labels[$currentStatus] ?? 'Placed Order',
        'statusSteps' => $statusSteps,
        'dropOff' => $matched['dropOff'] ?? '',
        'customerName' => $matched['customerName'] ?? '',
        'customerNumber' => $matched['customerNumber'] ?? '',
        'subtotal' => (float)($matched['subtotal'] ?? 0),
        'deliveryFee' => (float)($matched['deliveryFee'] ?? 0),
        'total' => (float)($matched['total'] ?? 0),
        'paymentMethod' => $matched['paymentMethod'] ?? 'wallet',
        'timestamp' => $matched['timestamp'] ?? $matched['createdAt'] ?? date('c'),
        'items' => $items,
        'courier' => $courier
    ]
];

echo json_encode($response);