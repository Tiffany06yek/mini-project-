<?php
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$userId = (int)($payload['userId'] ?? 0);
$items  = $payload['items'] ?? [];
if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid userId.']);
    exit;
}
if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Order items are required.']);
    exit;
}

require_once __DIR__ . '/../backend/config.php'; 
if (file_exists(__DIR__ . '/../backend/config.local.php')) {
    require_once __DIR__ . '/../backend/config.local.php';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$address        = trim((string)($payload['dropOff'] ?? ''));
$notes          = trim((string)($payload['notes'] ?? ''));
$customerName   = trim((string)($payload['customerName'] ?? ''));
$customerNumber = trim((string)($payload['customerNumber'] ?? ''));

if ($customerName !== '' || $customerNumber !== '') {
    $notes = trim($notes . "\nCustomer: " . $customerName . ($customerNumber !== '' ? " ({$customerNumber})" : ''));
}

$subtotal      = (float)($payload['subtotal'] ?? 0.0);
$deliveryFee   = (float)($payload['deliveryFee'] ?? 0.0);
$total         = (float)($payload['total'] ?? ($subtotal + $deliveryFee));
$paymentMethod = 'wallet';
$paymentStatus = 'paid';
$orderStatus   = (string)($payload['orderStatus'] ?? 'placed');

//can only choose one merchants
$merchantIds = [];
foreach ($items as $it) {
    if (isset($it['vendorId']) && (int)$it['vendorId'] > 0) {
        $merchantIds[] = (int)$it['vendorId'];
    }
}
$merchantIds = array_values(array_unique($merchantIds));
if (count($merchantIds) !== 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'All items in an order must belong to the same merchant.']);
    $conn->close();
    exit;
}
$merchantId = $merchantIds[0];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
    $stmt->bind_param('did', $total, $userId, $total);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('Insufficient wallet balance.');
    }
    $stmt->close();

    //create order in database
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
    $orderId = (int)$conn->insert_id;
    $orderStmt->close();

 //order_addons
    $itemStmt  = $conn->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)');
    $addonStmt = $conn->prepare('INSERT INTO order_item_addons (order_item_id, addon_id, price) VALUES (?, ?, ?)');

    foreach ($items as $item) {
        $productId    = (int)($item['productId'] ?? 0);
        $qty          = (int)($item['qty'] ?? 0);
        $pricePerUnit = (float)($item['price'] ?? 0.0);

        if ($productId <= 0 || $qty <= 0) {
            throw new RuntimeException('Invalid order item payload.');
        }

        //calculate Subtotal
        $addonTotal = 0.0;
        if (!empty($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $ad) {
                $addonTotal += (float)($ad['price'] ?? 0.0);
            }
        }
        $baseUnitPrice = $pricePerUnit - $addonTotal;
        if ($baseUnitPrice < 0) {
            $baseUnitPrice = $pricePerUnit;
        }

        $itemStmt->bind_param('iiid', $orderId, $productId, $qty, $baseUnitPrice);
        $itemStmt->execute();
        $orderItemId = (int)$conn->insert_id;

        if (!empty($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $ad) {
                $addonId = (int)($ad['id'] ?? 0);
                $aprice  = (float)($ad['price'] ?? 0.0);
                if ($addonId > 0 && $aprice >= 0) {
                    $addonStmt->bind_param('iid', $orderItemId, $addonId, $aprice);
                    $addonStmt->execute();
                }
            }
        }
    }
    $itemStmt->close();
    $addonStmt->close();

    $paymentId = null;
    $res = $conn->query('SELECT COALESCE(MAX(payment_id), 0) + 1 AS next_id FROM payments FOR UPDATE');
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        $paymentId = (int)($row['next_id'] ?? 1);
        $res->free();
    } else {
        $paymentId = 1;
    }
    $paymentStmt = $conn->prepare('INSERT INTO payments (order_id, payment_method, status) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE payment_method = VALUES(payment_method), status = VALUES(status)');
    $paymentStmt->bind_param('iss', $orderId, $paymentMethod, $paymentStatus);
    $paymentStmt->execute();
    $paymentId = (int)$conn->insert_id;
    $paymentStmt->close();

    if ($paymentId === 0) {
        $lookupStmt = $conn->prepare('SELECT payment_id FROM payments WHERE order_id = ? LIMIT 1');
        if ($lookupStmt) {
            $lookupStmt->bind_param('i', $orderId);
            $lookupStmt->execute();
            $lookupResult = $lookupStmt->get_result();
            $lookupRow = $lookupResult ? $lookupResult->fetch_assoc() : null;
            if ($lookupResult) {
                $lookupResult->free();
            }
            if ($lookupRow && isset($lookupRow['payment_id'])) {
                $paymentId = (int)$lookupRow['payment_id'];
            }
            $lookupStmt->close();
        }
    }

    //status of order history
    $statusHistoryId = null; $statusInserted = false;
    foreach (['order_status_history', 'Order_Status_History'] as $statusTable) {
        try {
            $rs = $conn->query(sprintf('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM `%s` FOR UPDATE', $statusTable));
            if ($rs instanceof mysqli_result) {
                $r = $rs->fetch_assoc();
                $statusHistoryId = (int)($r['next_id'] ?? 1);
                $rs->free();
            } else {
                $statusHistoryId = 1;
            }
            $st = $conn->prepare(sprintf('INSERT INTO `%s` (id, order_id, status) VALUES (?, ?, ?)', $statusTable));
            if ($st) {
                $st->bind_param('iis', $statusHistoryId, $orderId, $orderStatus);
                $st->execute();
                $st->close();
                $statusInserted = true;
                break;
            }
        } catch (Throwable $ignore) {}
    }

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
}
