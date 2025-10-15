<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require __DIR__ . '/config.example.php';

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Let caller see a meaningful error (you can customize this)
    throw $e;
}

/**
 * Build a light-weight snapshot of the current user that mirrors the
 * structure the front-end expects when it calls this script directly.
 */
if (!function_exists('xiapee_build_database_snapshot')) {
    function xiapee_build_database_snapshot(mysqli $conn): array
    {
        $data = [
            'users' => [],
            'orders' => [],
            'couriers' => [],
        ];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            $couriersResult = $conn->query('SELECT courier_id, merchant_id, name, phone FROM couriers ORDER BY merchant_id ASC, courier_id ASC');
            if ($couriersResult instanceof mysqli_result) {
                while ($courier = $couriersResult->fetch_assoc()) {
                    $data['couriers'][] = [
                        'courier_id' => $courier['courier_id'] ?? null,
                        'merchant_id' => $courier['merchant_id'] ?? null,
                        'name' => $courier['name'] ?? '',
                        'phone' => $courier['phone'] ?? '',
                    ];
                }
                $couriersResult->free();
            }
        } catch (mysqli_sql_exception $ignored) {
            // Older schemas may not have a couriers table.
        }


        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($userId <= 0) {
            return $data;
        }

        // Detect whether the users table has a balance column so we can include it if present.
        $balanceColumn = false;
        try {
            $colResult = $conn->query("SHOW COLUMNS FROM users LIKE 'balance'");
            if ($colResult instanceof mysqli_result) {
                $balanceColumn = $colResult->num_rows > 0;
                $colResult->free();
            }
        } catch (mysqli_sql_exception $ignored) {
            // Older schemas simply won't report a balance field; ignore.
        }

        $select = 'SELECT id, name, school_email, phone, default_address' . ($balanceColumn ? ', balance' : '') . ' FROM users WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($select);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRow = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$userRow) {
            return $data;
        }

        $user = [
            'id' => (int)$userRow['id'],
            'name' => $userRow['name'] ?? '',
            'email' => $userRow['school_email'] ?? '',
            'customerNumber' => $userRow['phone'] ?? '',
            'address' => $userRow['default_address'] ?? '',
            'balance' => $balanceColumn ? (float)$userRow['balance'] : 0.0,
            'orderHistory' => [],
        ];

        // Fetch a light order history for the currently signed-in user.
        $ordersHasCustomId = false;
        try {
            $ordersColResult = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_id'");
            if ($ordersColResult instanceof mysqli_result) {
                $ordersHasCustomId = $ordersColResult->num_rows > 0;
                $ordersColResult->free();
            }
        } catch (mysqli_sql_exception $ignored) {
            $ordersHasCustomId = false;
        }

        $orderSelect = 'SELECT id, total, created_at, address'
            . ($ordersHasCustomId ? ', order_id' : '')
            . ' FROM orders WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 20';

        $ordersStmt = $conn->prepare($orderSelect);
        
        if ($ordersStmt) {
            $ordersStmt->bind_param('i', $user['id']);
            $ordersStmt->execute();
            $ordersResult = $ordersStmt->get_result();
            if ($ordersResult) {
                while ($orderRow = $ordersResult->fetch_assoc()) {
                    $timestamp = $orderRow['created_at'] ?? null;
                    try {
                        $dt = $timestamp ? new DateTime($timestamp) : null;
                    } catch (Throwable $e) {
                        $dt = null;
                    }

                    $externalId = $ordersHasCustomId ? ($orderRow['order_id'] ?? null) : null;
                    $user['orderHistory'][] = [
                        'id' => $externalId && $externalId !== '' ? $externalId : (int)$orderRow['id'],
                        'orderNumber' => (int)$orderRow['id'],
                        'externalId' => $externalId,
                        'total' => isset($orderRow['total']) ? (float)$orderRow['total'] : 0.0,
                        'timestamp' => $dt ? $dt->format(DateTime::ATOM) : null,
                        'dropOff' => $orderRow['address'] ?? '',
                    ];
                }
                $ordersResult->free();
            }
            $ordersStmt->close();
        }

        $data['users'][] = $user;
        $data['orders'] = $user['orderHistory'];
        $data['currentUser'] = $user;

        return $data;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');

    try {
        $payload = xiapee_build_database_snapshot($conn);
        $response = array_merge(['success' => true], $payload);
        echo json_encode($response);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch database snapshot.',
            'error' => $e->getMessage(),
        ]);
    } finally {
        $conn->close();
    }
}


