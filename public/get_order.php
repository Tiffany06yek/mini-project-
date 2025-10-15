<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only GET is allowed for this endpoint.'
    ]);
    exit;
}

$orderIdRaw = trim((string)($_GET['id'] ?? ''));
if ($orderIdRaw === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing order id.'
    ]);
    exit;
}

require __DIR__ . '/database.php';

/**
 * Return the first column in $candidates that exists in the provided table.
 */
function xiapee_pick_column(mysqli $conn, string $table, array $candidates): ?string
{
    static $cache = [];
    $tableKey = strtolower($table);
    if (isset($cache[$tableKey])) {
        $columns = $cache[$tableKey];
    } else {
        $columns = [];
        try {
            $result = $conn->query(sprintf('SHOW COLUMNS FROM `%s`', $conn->real_escape_string($table)));
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($row['Field'])) {
                        $columns[] = $row['Field'];
                    }
                }
                $result->free();
            }
        } catch (mysqli_sql_exception $ignored) {
            // Table might not exist; ignore and let caller fallback.
        }
        $cache[$tableKey] = $columns;
    }

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function xiapee_fetch_session_order(string $orderId): ?array
{
    $orders = isset($_SESSION['placed_orders']) && is_array($_SESSION['placed_orders'])
        ? $_SESSION['placed_orders']
        : [];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        if ((string)($order['id'] ?? '') === $orderId) {
            return $order;
        }
    }

    return null;
}

function xiapee_normalise_session_order(array $raw): array
{
    $items = [];
    if (isset($raw['items']) && is_array($raw['items'])) {
        foreach ($raw['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = (int)($item['qty'] ?? $item['quantity'] ?? 0);
            $price = (float)($item['price'] ?? $item['unitPrice'] ?? 0);
            $items[] = [
                'id' => $item['id'] ?? $item['productId'] ?? null,
                'name' => $item['name'] ?? 'Item',
                'qty' => $qty,
                'price' => $price,
                'total' => (float)($item['total'] ?? ($price * $qty)),
                'addons' => isset($item['addons']) && is_array($item['addons']) ? $item['addons'] : [],
            ];
        }
    }

    $status = (string)($raw['orderStatus'] ?? $raw['status'] ?? 'placed');

    return [
        'id' => $raw['id'] ?? null,
        'userId' => $raw['userId'] ?? null,
        'status' => $status,
        'statusSteps' => [],
        'dropOff' => $raw['dropOff'] ?? '',
        'customerName' => $raw['customerName'] ?? '',
        'customerNumber' => $raw['customerNumber'] ?? '',
        'subtotal' => (float)($raw['subtotal'] ?? 0),
        'deliveryFee' => (float)($raw['deliveryFee'] ?? 0),
        'total' => (float)($raw['total'] ?? 0),
        'paymentMethod' => $raw['paymentMethod'] ?? 'wallet',
        'timestamp' => $raw['timestamp'] ?? $raw['createdAt'] ?? date(DATE_ATOM),
        'items' => $items,
        'courier' => null,
        'merchantId' => $raw['merchantId'] ?? null,
    ];
}

function xiapee_fetch_status_history(mysqli $conn, int $orderId): array
{
    $tables = ['order_status_history', 'Order_Status_History'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->prepare(sprintf('SELECT status, changed_at FROM `%s` WHERE order_id = ? ORDER BY changed_at ASC, id ASC', $table));
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $history = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $history[] = [
                        'status' => $row['status'] ?? '',
                        'changed_at' => $row['changed_at'] ?? null,
                    ];
                }
                $result->free();
            }
            $stmt->close();

            if ($history) {
                return $history;
            }
        } catch (mysqli_sql_exception $ignored) {
            // Table may not exist; try next name.
        }
    }

    return [];
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

$responseOrder = null;
$sessionOrder = xiapee_fetch_session_order($orderIdRaw);

try {
    $orderRow = null;
    $orderIdNumeric = ctype_digit($orderIdRaw) ? (int)$orderIdRaw : null;

    if ($orderIdNumeric !== null) {
        $stmt = $conn->prepare(
            'SELECT o.id, o.buyer_id, o.merchant_id, o.address, o.notes, o.delivery_fee, o.subtotal, o.total, o.payment_method, o.payment_status, o.created_at, '
            . 'u.name AS buyer_name, u.phone AS buyer_phone, u.school_email AS buyer_email, '
            . 'm.name AS merchant_name '
            . 'FROM orders o '
            . 'LEFT JOIN users u ON u.id = o.buyer_id '
            . 'LEFT JOIN merchants m ON m.id = o.merchant_id '
            . 'WHERE o.id = ? LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('i', $orderIdNumeric);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $orderRow = $result->fetch_assoc();
                $result->free();
            }
            $stmt->close();
        }
    }

    if ($orderRow) {
        $productIdColumn = xiapee_pick_column($conn, 'products', ['product_id', 'id']);
        $productNameColumn = xiapee_pick_column($conn, 'products', ['name']);

        $items = [];
        if ($orderIdNumeric !== null) {
            if ($productIdColumn && $productNameColumn) {
                $itemsSql = sprintf(
                    'SELECT oi.id, oi.product_id, oi.qty, oi.unit_price, p.`%s` AS product_name '
                    . 'FROM order_items oi '
                    . 'LEFT JOIN products p ON p.`%s` = oi.product_id '
                    . 'WHERE oi.order_id = ? ORDER BY oi.id ASC',
                    $productNameColumn,
                    $productIdColumn
                );
            } else {
                $itemsSql = 'SELECT oi.id, oi.product_id, oi.qty, oi.unit_price FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id ASC';
            }

            $itemsStmt = $conn->prepare($itemsSql);
            if ($itemsStmt) {
                $itemsStmt->bind_param('i', $orderIdNumeric);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                if ($itemsResult) {
                    while ($row = $itemsResult->fetch_assoc()) {
                        $qty = (int)($row['qty'] ?? 0);
                        $price = (float)($row['unit_price'] ?? 0);
                        $items[] = [
                            'id' => $row['id'] ?? $row['product_id'] ?? null,
                            'productId' => $row['product_id'] ?? null,
                            'name' => $row['product_name'] ?? ('Item #' . ($row['product_id'] ?? '')),
                            'qty' => $qty,
                            'price' => $price,
                            'total' => $price * $qty,
                            'addons' => [],
                        ];
                    }
                    $itemsResult->free();
                }
                $itemsStmt->close();
            }
        }

        $history = $orderIdNumeric !== null ? xiapee_fetch_status_history($conn, $orderIdNumeric) : [];
        $statusMap = [
            'placed' => 'Placed Order',
            'accepted' => 'Order Accepted',
            'preparing' => 'Preparing',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        $defaultFlow = ['placed', 'accepted', 'preparing', 'on_the_way', 'delivered'];

        $currentStatusKey = 'placed';
        if ($history) {
            $last = end($history);
            $candidate = strtolower((string)($last['status'] ?? 'placed'));
            if (isset($statusMap[$candidate])) {
                $currentStatusKey = $candidate;
            }
        } elseif (!empty($orderRow['payment_status']) && $orderRow['payment_status'] === 'paid') {
            $currentStatusKey = 'preparing';
        }

        $activeIndex = array_search($currentStatusKey, $defaultFlow, true);
        if ($activeIndex === false) {
            $activeIndex = 0;
        }

        $statusSteps = [];
        foreach ($defaultFlow as $index => $key) {
            $statusSteps[] = [
                'key' => $key,
                'title' => $statusMap[$key],
                'done' => $index <= $activeIndex,
            ];
        }

        $courier = xiapee_fetch_courier($conn, isset($orderRow['merchant_id']) ? (int)$orderRow['merchant_id'] : null);
        if (!$courier) {
            $courier = [
                'courier_id' => null,
                'merchant_id' => $orderRow['merchant_id'] ?? null,
                'name' => 'Assigned Courier',
                'phone' => '',
                'hire_date' => null,
            ];
        }

        $createdAt = $orderRow['created_at'] ?? null;
        try {
            $timestamp = $createdAt ? new DateTime($createdAt) : null;
            $timestampString = $timestamp ? $timestamp->format(DATE_ATOM) : date(DATE_ATOM);
        } catch (Throwable $e) {
            $timestampString = date(DATE_ATOM);
        }

        $responseOrder = [
            'id' => (int)$orderRow['id'],
            'userId' => isset($orderRow['buyer_id']) ? (int)$orderRow['buyer_id'] : null,
            'merchantId' => isset($orderRow['merchant_id']) ? (int)$orderRow['merchant_id'] : null,
            'merchantName' => $orderRow['merchant_name'] ?? '',
            'status' => $statusMap[$currentStatusKey] ?? 'Placed Order',
            'statusSteps' => $statusSteps,
            'dropOff' => $orderRow['address'] ?? '',
            'customerName' => $orderRow['buyer_name'] ?? '',
            'customerNumber' => $orderRow['buyer_phone'] ?? '',
            'subtotal' => (float)($orderRow['subtotal'] ?? 0),
            'deliveryFee' => (float)($orderRow['delivery_fee'] ?? 0),
            'total' => (float)($orderRow['total'] ?? 0),
            'paymentMethod' => $orderRow['payment_method'] ?? 'wallet',
            'paymentStatus' => $orderRow['payment_status'] ?? '',
            'timestamp' => $timestampString,
            'items' => $items,
            'courier' => $courier,
        ];
    } elseif ($sessionOrder) {
        $responseOrder = xiapee_normalise_session_order($sessionOrder);
    }

    if (!$responseOrder) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found.'
        ]);
        exit;
    }

    if ($responseOrder['courier'] === null) {
        $merchantId = isset($responseOrder['merchantId']) ? (int)$responseOrder['merchantId'] : null;
        $courier = xiapee_fetch_courier($conn, $merchantId);
        if ($courier) {
            $responseOrder['courier'] = $courier;
        } else {
            $responseOrder['courier'] = [
                'courier_id' => null,
                'merchant_id' => $merchantId,
                'name' => 'Assigned Courier',
                'phone' => '',
                'hire_date' => null,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'order' => $responseOrder,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load order details.',
        'error' => $e->getMessage(),
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
