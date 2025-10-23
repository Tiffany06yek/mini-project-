<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../backend/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Please use POST to place an order.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order data.']);
    exit;
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot checkout with empty cart.']);
    exit;
}
$dropOff = trim((string)($data['dropOff'] ?? ''));
if ($dropOff === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in the drop-off location.']);
    exit;
}

$subtotal    = isset($data['subtotal'])    ? (float)$data['subtotal']    : 0.0;
$deliveryFee = isset($data['deliveryFee']) ? (float)$data['deliveryFee'] : 0.0;
$total       = isset($data['total'])       ? (float)$data['total']       : ($subtotal + $deliveryFee);
if ($subtotal <= 0 || $total <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Amount.']);
    exit;
}

function xiapee_prepare_optional(mysqli $conn, string $sql): ?mysqli_stmt {
    try {
        return $conn->prepare($sql);
    } catch (mysqli_sql_exception $ignored) {
        return null;
    }
}

function xiapee_normalise_addons(array $item): array {
    $addons = [];
    if (!isset($item['addons']) || !is_array($item['addons'])) {
        return $addons;
    }
    foreach ($item['addons'] as $addon) {
        if (!is_array($addon)) {
            continue;
        }
        $id = xiapee_parse_int($addon['id'] ?? $addon['addon_ID'] ?? $addon['addonId'] ?? null);
        $price = isset($addon['price']) ? (float)$addon['price'] : 0.0;
        $addons[] = [
            'id'    => $id ?? 0,
            'price' => $price,
            'name'  => isset($addon['name']) ? trim((string)$addon['name']) : '',
        ];
    }
    return $addons;
}

function xiapee_parse_int($value): ?int {//convert all the types into integer safely
    if (is_int($value)) 
    return $value;
    if (is_float($value)) 
    return (int)$value;
    if (is_string($value)) {
        if (ctype_digit($value)) return (int)$value;
        if (preg_match('/-?\d+/', $value, $m)) return (int)$m[0];
    }
    return null;
}

function xiapee_orders_supports_custom_order_id(mysqli $conn): bool {
    static $supports = null;
    if ($supports !== null) return $supports;
    try {
        $result = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'order_id'");
        $supports = ($result instanceof mysqli_result) ? ($result->num_rows > 0) : false;
        if ($result) $result->free();
    } catch (mysqli_sql_exception $ignored) { $supports = false; }
    return (bool)$supports;
}

function xiapee_resolve_merchant_id(mysqli $conn, $merchantIdCandidate, array $items): int {
    $m = xiapee_parse_int($merchantIdCandidate);
    if ($m !== null && $m > 0) return $m;

    $set = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $vid = xiapee_parse_int($it['vendorId'] ?? null);
        if ($vid !== null && $vid > 0) $set[$vid] = true;
    }
    $ids = array_keys($set);
    if (count($ids) !== 1) {
        throw new RuntimeException('All items must belong to ONE merchant.');
    }
    return (int)$ids[0];
}


function xiapee_resolve_product_id(mysqli $conn, array $item): ?int {
    foreach ([$item['productId'] ?? null, $item['id'] ?? null] as $cand) {
        $p = xiapee_parse_int($cand);
        if ($p !== null && $p > 0) return $p;
    }
    $name = isset($item['name']) ? trim((string)$item['name']) : '';
    if ($name === '') return null;

    $stmt = $conn->prepare('SELECT product_id FROM products WHERE name = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $pid = null;
    if ($res && ($row = $res->fetch_assoc())) {
        $pid = xiapee_parse_int($row['product_id'] ?? null);
    }
    if ($res) $res->free();
    $stmt->close();
    return $pid;
}

//fetch courier from merchants (one to one)
function xiapee_fetch_courier(mysqli $conn, ?int $merchantId): ?array {
    if (!$merchantId) return null;
    $stmt = $conn->prepare('SELECT courier_id, merchant_id, name, phone, hire_date FROM couriers WHERE merchant_id = ? ORDER BY hire_date DESC, courier_id ASC LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $merchantId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    return $row ?: null;
}

try {
    $conn->begin_transaction();

    $buyerId    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; 
    $merchantId = xiapee_resolve_merchant_id($conn, $data['merchantId'] ?? null, $items);

    $notes          = trim((string)($data['notes'] ?? ''));
    $paymentMethod  = 'wallet';
    $paymentStatus  = 'paid';

    if (!function_exists('payment_status')) {
        function payment_status($status): string
        {
            $status = strtolower(trim((string)$status));
            switch ($status) {
                case 'paid':
                    return 'success';
                case 'failed':
                    return 'failed';
                case 'pending':
                default:
                    return 'success';
            }
        }
    }

    //order_id
    $customOrderId  = null;
    if (!empty($data['id'])) $customOrderId = substr(trim((string)$data['id']), 0, 64);
    $supportsCustom = xiapee_orders_supports_custom_order_id($conn);
    if (!$supportsCustom) $customOrderId = null;

    if ($supportsCustom && $customOrderId !== null) {
        $orderStmt = $conn->prepare(
            'INSERT INTO `orders` (order_id, buyer_id, merchant_id, address, notes, delivery_fee, subtotal, total, payment_method, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$orderStmt) throw new RuntimeException('Cannot create order. Please try again.');
        $orderStmt->bind_param('siissdddss',
            $customOrderId, $buyerId, $merchantId, $dropOff, $notes,
            $deliveryFee, $subtotal, $total, $paymentMethod, $paymentStatus
        );
    } else {
        $orderStmt = $conn->prepare(
            'INSERT INTO `orders` (buyer_id, merchant_id, address, notes, delivery_fee, subtotal, total, payment_method, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$orderStmt) throw new RuntimeException('Cannot create order. Please try again.');
        $orderStmt->bind_param('iissdddss',
            $buyerId, $merchantId, $dropOff, $notes,
            $deliveryFee, $subtotal, $total, $paymentMethod, $paymentStatus
        );
    }
    $orderStmt->execute();
    $orderId = (int)$conn->insert_id;
    $orderStmt->close();

    //insert items
    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)');
    if (!$itemStmt) throw new RuntimeException('Save failed. Please try again later.');
    $addonStmt = xiapee_prepare_optional($conn, 'INSERT INTO order_item_addons (order_item_id, addon_id, price) VALUES (?, ?, ?)');

    $sessionItems = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $pid = xiapee_resolve_product_id($conn, $it);
        if ($pid === null || $pid <= 0) throw new RuntimeException('Invalid product.');
        $qty   = max(1, (int)($it['qty'] ?? $it['quantity'] ?? 1));
        $price = (float)($it['price'] ?? $it['unitPrice'] ?? 0.0);
        $addons = xiapee_normalise_addons($it);
        $addonTotal = array_reduce($addons, fn($sum, $addon) => $sum + (float)$addon['price'], 0.0);
        $baseUnitPrice = $price - $addonTotal;
        if ($baseUnitPrice < 0) {
            $baseUnitPrice = $price;
        }

        $itemStmt->bind_param('iiid', $orderId, $pid, $qty, $baseUnitPrice);
        $itemStmt->execute();
        $orderItemId = (int)$conn->insert_id;

        $sessionAddons = [];
        if ($addonStmt && $orderItemId > 0 && $addons) {
            foreach ($addons as $addon) {
                $addonId = $addon['id'] ?? 0;
                if (!$addonId || $addonId <= 0) {
                    continue;
                }
                $addonPrice = (float)$addon['price'];
                $addonStmt->bind_param('iid', $orderItemId, $addonId, $addonPrice);
                $addonStmt->execute();
                $sessionAddons[] = [
                    'id'    => $addonId,
                    'name'  => $addon['name'] ?? '',
                    'price' => $addonPrice,
                ];
            }
        } elseif ($addons) {
            foreach ($addons as $addon) {
                $sessionAddons[] = [
                    'id'    => $addon['id'] ?? 0,
                    'name'  => $addon['name'] ?? '',
                    'price' => (float)$addon['price'],
                ];
            }
        }

        $sessionItems[] = [
            'id'        => $orderItemId ?: ($it['id'] ?? $pid),
            'productId' => $pid,
            'name'      => $it['name'] ?? 'Item',
            'qty'       => $qty,
            'price'     => $price,
            'unitPrice' => $baseUnitPrice,
            'total'     => ($baseUnitPrice + $addonTotal) * $qty,
            'addons'    => $sessionAddons,
        ];
    }
    $itemStmt->close();

    if ($addonStmt) $addonStmt->close();

    $paymentId = null;
    $paymentStmt = $conn->prepare('INSERT INTO payments (order_id, payment_method, status) VALUES (?, ?, ?)');
    if ($paymentStmt) {
        $paymentStmt->bind_param('iss', $orderId, $paymentMethod, $paymentStatus);
        try {
            $paymentStmt->execute();
            $paymentId = (int)$conn->insert_id;
        } catch (mysqli_sql_exception $ignored) {
            $paymentId = null;
        }
        $paymentStmt->close();
        }

    $statusHistoryId = null;
    foreach (['order_status_history', 'Order_Status_History'] as $statusTable) {
        try {
            $statusResult = $conn->query(sprintf('SELECT COALESCE(MAX(id), 0) AS max_id FROM `%s`', $statusTable));
            if ($statusResult instanceof mysqli_result) {
                $row = $statusResult->fetch_assoc();
                $statusHistoryId = (int)($row['max_id'] ?? 0) + 1;
                $statusResult->free();
            } else {
                $statusHistoryId = 1;
            }
            $statusStmt = $conn->prepare(sprintf('INSERT INTO `%s` (id, order_id, status) VALUES (?, ?, ?)', $statusTable));
            if ($statusStmt) {
                $status = isset($data['orderStatus']) ? (string)$data['orderStatus'] : 'delivered';
                $statusStmt->bind_param('iis', $statusHistoryId, $orderId, $status);
                $statusStmt->execute();
                $statusStmt->close();
                break;
            }
        } catch (mysqli_sql_exception $ignored) {
            $statusHistoryId = null;
        }
    }

    $courier = xiapee_fetch_courier($conn, $merchantId) ?: [
        'courier_id'  => null,
        'merchant_id' => $merchantId,
        'name'        => 'Assigned Courier',
        'phone'       => '',
        'hire_date'   => null,
    ];

    $conn->commit();

    if (!isset($_SESSION['placed_orders']) || !is_array($_SESSION['placed_orders'])) {
        $_SESSION['placed_orders'] = [];
    }
    $_SESSION['placed_orders'][] = [
        'id'             => $customOrderId ?? $orderId,
        'orderNumber'    => $orderId,
        'externalId'     => $customOrderId,
        'userId'         => $buyerId,
        'customerName'   => trim((string)($data['customerName'] ?? '')),
        'customerNumber' => trim((string)($data['customerNumber'] ?? '')),
        'items'          => $items,
        'subtotal'       => $subtotal,
        'deliveryFee'    => $deliveryFee,
        'total'          => $total,
        'paymentMethod'  => $paymentMethod,
        'paymentStatus'  => $paymentStatus,
        'orderStatus'    => $data['orderStatus'] ?? 'preparing',
        'merchantId'     => $merchantId,
        'dropOff'        => $dropOff,
        'timestamp'      => $data['timestamp'] ?? date('c'),
        'createdAt'      => date('c'),
        'courier'        => $courier,
        'staff'          => [
            'name'  => $courier['name']  ?? 'Assigned Courier',
            'phone' => $courier['phone'] ?? '',
        ],
        'status'         => 'Order Confirmed',
        'statusText'     => 'Order Confirmed',
    ];

    echo json_encode([
        'success'      => true,
        'orderId'      => $customOrderId ?? $orderId,
        'orderNumber'  => $orderId,
        'externalId'   => $customOrderId,
        'courier'      => $courier,
        'paymentId'    => $paymentId,
        'statusHistoryId' => $statusHistoryId,
        'message'      => 'Order Successful.',
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
