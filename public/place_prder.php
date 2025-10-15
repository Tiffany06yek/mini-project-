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
        'message' => 'Invalid Orderdata。'
    ]);
    exit;
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot checkout with empty cart.'
    ]);
    exit;
}

$dropOff = trim((string)($data['dropOff'] ?? ''));
if ($dropOff === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in the dropoff location.'
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

require_once __DIR__ . '/../backend/database.php';

function xiapee_parse_int($value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int)$value;
    }
    if (is_string($value)) {
        if (ctype_digit($value)) {
            return (int)$value;
        }
        if (preg_match('/(-?\d+)/', $value, $matches)) {
            return (int)$matches[1];
        }
    }

    return null;

if (!isset($_SESSION['placed_orders']) || !is_array($_SESSION['placed_orders'])) {
    $_SESSION['placed_orders'] = [];
}

function xiapee_resolve_product_id(mysqli $conn, array $item): ?int
{
    $candidates = [
        $item['productId'] ?? null,
        $item['id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $parsed = xiapee_parse_int($candidate);
        if ($parsed !== null) {
            return $parsed;
        }
    }

    $name = isset($item['name']) ? trim((string)$item['name']) : '';
    if ($name === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT product_id FROM products WHERE name = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if (!$row || !isset($row['product_id'])) {
        return null;
    }

    return xiapee_parse_int($row['product_id']);
}

function xiapee_resolve_merchant_id(mysqli $conn, $provided, array $items): ?int
{
    $candidate = xiapee_parse_int($provided);
    if ($candidate !== null) {
        return $candidate;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $merchantCandidate = $item['vendorId'] ?? $item['merchantId'] ?? null;
        $parsed = xiapee_parse_int($merchantCandidate);
        if ($parsed !== null) {
            return $parsed;
        }
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $productId = xiapee_resolve_product_id($conn, $item);
        if ($productId === null) {
            continue;
        }
        $stmt = $conn->prepare('SELECT merchant_id FROM products WHERE product_id = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        if ($row && isset($row['merchant_id'])) {
            $parsed = xiapee_parse_int($row['merchant_id']);
            if ($parsed !== null) {
                return $parsed;
            }
        }
    }

    return null;
}

function xiapee_fetch_courier(mysqli $conn, ?int $merchantId): ?array
{
    if (!$merchantId) {
        return null;
    }

    try {
        $stmt = $conn->prepare('SELECT courier_id, merchant_id, name, phone, hire_date FROM couriers WHERE merchant_id = ? ORDER BY hire_date DESC, courier_id ASC LIMIT 1');
    } catch (mysqli_sql_exception $e) {
        return null;
    }

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $courier = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if (!$courier) {
        return null;
    }

    return [
        'courier_id' => $courier['courier_id'] ?? null,
        'merchant_id' => $courier['merchant_id'] ?? $merchantId,
        'name' => $courier['name'] ?? 'Assigned Courier',
        'phone' => $courier['phone'] ?? '',
        'hire_date' => $courier['hire_date'] ?? null,
    ];
}


try {
    $conn->begin_transaction();

    $buyerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $merchantId = xiapee_resolve_merchant_id($conn, $data['merchantId'] ?? null, $items);

    $notes = trim((string)($data['notes'] ?? ''));
    $paymentMethod = in_array(($data['paymentMethod'] ?? 'wallet'), ['cash', 'card', 'wallet', 'online_banking'], true)
        ? $data['paymentMethod']
        : 'wallet';
    $paymentStatus = in_array(($data['paymentStatus'] ?? 'paid'), ['pending', 'paid', 'failed', 'refunded'], true)
        ? $data['paymentStatus']
        : 'paid';

    $orderStmt = $conn->prepare(
        'INSERT INTO Orders (buyer_id, merchant_id, address, notes, delivery_fee, subtotal, total, payment_method, payment_status) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$orderStmt) {
        throw new RuntimeException('Cannot create order. Please try again.');
    }

    $orderStmt->bind_param(
        'iissdddss',
        $buyerId,
        $merchantId,
        $dropOff,
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

    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)');
    if (!$itemStmt) {
        throw new RuntimeException('Save failed. Please try again later.');
    }
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $productId = xiapee_resolve_product_id($conn, $item);
        if ($productId === null) {
            throw new RuntimeException('Invalid Product.');
        }
        $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
        $unitPrice = (float)($item['price'] ?? $item['unitPrice'] ?? 0.0);
        $itemStmt->bind_param('iiid', $orderId, $productId, $qty, $unitPrice);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $courier = xiapee_fetch_courier($conn, $merchantId);
    if (!$courier) {
        $courier = [
            'courier_id' => null,
            'merchant_id' => $merchantId,
            'name' => 'Assigned Courier',
            'phone' => '',
            'hire_date' => null,
        ];
    }

    $conn->commit();

    $orderRecord = [
        'id' => $orderId,
        'userId' => $buyerId,
        'customerName' => trim((string)($data['customerName'] ?? '')),
        'customerNumber' => trim((string)($data['customerNumber'] ?? '')),
        'items' => $items,
        'subtotal' => $subtotal,
        'deliveryFee' => $deliveryFee,
        'total' => $total,
        'paymentMethod' => $paymentMethod,
        'paymentStatus' => $paymentStatus,
        'orderStatus' => $data['orderStatus'] ?? 'preparing',
        'merchantId' => $merchantId,
        'dropOff' => $dropOff,
        'timestamp' => $data['timestamp'] ?? date('c'),
        'createdAt' => date('c'),
        'courier' => $courier,
        'staff' => $courier ? [
            'name' => $courier['name'] ?? 'Assigned Courier',
            'phone' => $courier['phone'] ?? '',
        ] : null,
        'status' => 'Order Confirmed',
        'statusText' => 'Order Confirmed',
    ];

    if (!isset($_SESSION['placed_orders']) || !is_array($_SESSION['placed_orders'])) {
        $_SESSION['placed_orders'] = [];
    }

    $_SESSION['placed_orders'][] = $orderRecord;

    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'courier' => $courier,
        'message' => 'Order Successful.',
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
}