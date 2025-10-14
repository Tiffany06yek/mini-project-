<?php
// 设置返回内容类型为 JSON
header('Content-Type: application/json');

// 获取 POST 请求的原始数据
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    exit;
}

$userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
$items = $payload['items'] ?? [];

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid userId.'
    ]);
    exit;
}

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Order items are required.'
    ]);
    exit;
}

require_once __DIR__ . '/../backend/config.example.php';
if (file_exists(__DIR__ . '/../backend/config.local.php')) {
    require_once __DIR__ . '/../backend/config.local.php';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.',
        'error' => $e->getMessage()
    ]);
    exit;
}

$address = trim((string)($payload['dropOff'] ?? ''));
$notes = trim((string)($payload['notes'] ?? ''));
$customerName = trim((string)($payload['customerName'] ?? ''));
$customerNumber = trim((string)($payload['customerNumber'] ?? ''));

if ($customerName !== '' || $customerNumber !== '') {
    $notes = trim($notes . "\nCustomer: " . $customerName . ($customerNumber !== '' ? " ({$customerNumber})" : ''));
}

$subtotal = isset($payload['subtotal']) ? (float)$payload['subtotal'] : 0.0;
$deliveryFee = isset($payload['deliveryFee']) ? (float)$payload['deliveryFee'] : 0.0;
$total = isset($payload['total']) ? (float)$payload['total'] : $subtotal + $deliveryFee;
$paymentMethod = $payload['paymentMethod'] ?? 'wallet';
$paymentStatus = $payload['paymentStatus'] ?? 'paid';
$orderStatus = $payload['orderStatus'] ?? 'placed';

$merchantIds = [];
foreach ($items as $item) {
    if (isset($item['vendorId']) && $item['vendorId'] !== '' && $item['vendorId'] !== null) {
        $merchantIds[] = (int)$item['vendorId'];
    }
}
$merchantIds = array_values(array_unique(array_filter($merchantIds, fn($id) => $id > 0)));


// 确保接收到数据
if (count($merchantIds) !== 1) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'All items in an order must belong to the same merchant.'
    ]);
    $conn->close();
    exit;
}

$merchantId = $merchantIds[0];

try {
    $conn->begin_transaction();

    $orderStmt = $conn->prepare(
        'INSERT INTO orders (buyer_id, merchant_id, address, notes, delivery_fee, subtotal, total, payment_method, payment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $orderStmt->bind_param(
        'iissdddss',
        $userId,
        $merchantId,
        $address,
        $notes,
        $deliveryFee,
        $subtotal,
        $total,
        $paymentMethod,
        $paymentStatus
    );
    $orderStmt->execute();
    $orderId = $conn->insert_id;
    $orderStmt->close();

    $itemStmt = $conn->prepare(
        'INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)'
    );
    $addonStmt = $conn->prepare(
        'INSERT INTO order_item_addons (order_item_id, addon_id, price) VALUES (?, ?, ?)'
    );

    foreach ($items as $item) {
        $productId = isset($item['productId']) ? (int)$item['productId'] : 0;
        $qty = isset($item['qty']) ? (int)$item['qty'] : 0;
        $pricePerUnit = isset($item['price']) ? (float)$item['price'] : 0.0;

        if ($productId <= 0 || $qty <= 0) {
            throw new RuntimeException('Invalid order item payload.');
        }

        $addonTotal = 0.0;
        if (isset($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $addon) {
                $addonTotal += isset($addon['price']) ? (float)$addon['price'] : 0.0;
            }
        }
    }

        $baseUnitPrice = $pricePerUnit - $addonTotal;
        if ($baseUnitPrice < 0) {
            $baseUnitPrice = $pricePerUnit;

        // 更新用户余额
        $itemStmt->bind_param('iiid', $orderId, $productId, $qty, $baseUnitPrice);
        $itemStmt->execute();
        $orderItemId = $conn->insert_id;

        if (isset($item['addons']) && is_array($item['addons']) && count($item['addons']) > 0) {
            foreach ($item['addons'] as $addon) {
                $addonId = isset($addon['id']) ? (int)$addon['id'] : (isset($addon['addon_ID']) ? (int)$addon['addon_ID'] : 0);
                $addonPrice = isset($addon['price']) ? (float)$addon['price'] : 0.0;
                if ($addonId <= 0) {
                    continue;
                }
                $addonStmt->bind_param('iid', $orderItemId, $addonId, $addonPrice);
                $addonStmt->execute();
            }
        }
        $itemStmt->close();
        $addonStmt->close();
    
        $paymentIdResult = $conn->query('SELECT COALESCE(MAX(payment_id), 0) AS max_id FROM payments');
        $paymentRow = $paymentIdResult->fetch_assoc();
        $paymentId = (int)($paymentRow['max_id'] ?? 0) + 1;
        $paymentIdResult->free();
    
        $paymentStmt = $conn->prepare(
            'INSERT INTO payments (payment_id, order_id, payment_method, status) VALUES (?, ?, ?, ?)'
        );
        $paymentStmt->bind_param('iiss', $paymentId, $orderId, $paymentMethod, $paymentStatus);
        $paymentStmt->execute();
        $paymentStmt->close();
    
        $statusResult = $conn->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM Order_Status_History');
        $statusRow = $statusResult->fetch_assoc();
        $statusHistoryId = (int)($statusRow['max_id'] ?? 0) + 1;
        $statusResult->free();
    
        $statusStmt = $conn->prepare(
            'INSERT INTO Order_Status_History (id, order_id, status) VALUES (?, ?, ?)'
        );
        $statusStmt->bind_param('iis', $statusHistoryId, $orderId, $orderStatus);
        $statusStmt->execute();
        $statusStmt->close();
    
        $conn->commit();
    
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully.',
            'orderId' => $orderId,
            'paymentId' => $paymentId,
            'statusHistoryId' => $statusHistoryId
        ]);
    }
} catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create order.',
            'error' => $e->getMessage()
        ]);
    } finally {
        $conn->close();
    }

?>

