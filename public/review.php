<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    require __DIR__ . '/../backend/database.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to the database.',
        'error' => $e->getMessage(),
    ]);
    exit;
}

function xiapee_reviews_supports_auto_increment(mysqli $conn): bool
{
    static $supports = null;
    if ($supports !== null) {
        return $supports;
    }
    try {
        $result = $conn->query("SHOW COLUMNS FROM `Reviews` LIKE 'id'");
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $extra = strtolower((string)($row['Extra'] ?? ''));
            $supports = strpos($extra, 'auto_increment') !== false;
            $result->free();
        } else {
            $supports = false;
        }
    } catch (mysqli_sql_exception $e) {
        $supports = false;
    }
    return (bool)$supports;
}

function xiapee_reviews_next_id(mysqli $conn): int
{
    try {
        $result = $conn->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM `Reviews`');
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $next = (int)($row['max_id'] ?? 0) + 1;
            $result->free();
            return $next > 0 ? $next : 1;
        }
    } catch (mysqli_sql_exception $e) {
        // Ignore and fall through.
    }
    return 1;
}

function xiapee_fetch_review_summary(mysqli $conn, int $merchantId, int $userId = 0): array
{
    $summary = [
        'count' => 0,
        'average' => 0.0,
    ];
    try {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total_reviews, AVG(rating) AS avg_rating FROM `Reviews` WHERE merchant_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $merchantId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                if ($row) {
                    $summary['count'] = (int)($row['total_reviews'] ?? 0);
                    $summary['average'] = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
                }
                $result->free();
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        // Ignore summary errors for resilience.
    }

    $userReview = null;
    if ($userId > 0) {
        try {
            $stmt = $conn->prepare('SELECT id, rating, text, created_at FROM `Reviews` WHERE merchant_id = ? AND user_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ii', $merchantId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $row = $result->fetch_assoc();
                    if ($row) {
                        $userReview = [
                            'id' => isset($row['id']) ? (int)$row['id'] : null,
                            'rating' => isset($row['rating']) ? (float)$row['rating'] : 0.0,
                            'text' => $row['text'] ?? '',
                            'createdAt' => $row['created_at'] ?? null,
                        ];
                    }
                    $result->free();
                }
                $stmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            // Ignore user review fetch errors.
        }
    }

    return [
        'summary' => $summary,
        'currentUserReview' => $userReview,
    ];
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $merchantId = isset($payload['merchantId']) ? (int)$payload['merchantId'] : 0;
    $rating = isset($payload['rating']) ? (float)$payload['rating'] : 0.0;
    $text = trim((string)($payload['text'] ?? ''));

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId <= 0 && isset($payload['userId'])) {
        $userId = (int)$payload['userId'];
    }

    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in before leaving a review.']);
        exit;
    }

    if ($merchantId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid merchant id is required.']);
        exit;
    }

    if ($rating <= 0 || $rating > 5) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
        exit;
    }
    $rating = round($rating * 2) / 2; // clamp to 0.5 steps

    try {
        $conn->begin_transaction();

        $existingId = null;
        try {
            $checkStmt = $conn->prepare('SELECT id FROM `Reviews` WHERE merchant_id = ? AND user_id = ? LIMIT 1');
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $merchantId, $userId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                if ($result) {
                    $row = $result->fetch_assoc();
                    if ($row && isset($row['id'])) {
                        $existingId = (int)$row['id'];
                    }
                    $result->free();
                }
                $checkStmt->close();
            }
        } catch (mysqli_sql_exception $ignored) {
            $existingId = null;
        }

        if ($existingId !== null) {
            $updateStmt = $conn->prepare('UPDATE `Reviews` SET rating = ?, text = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?');
            if (!$updateStmt) {
                throw new RuntimeException('Unable to update review at this time.');
            }
            $updateStmt->bind_param('dsi', $rating, $text, $existingId);
            $updateStmt->execute();
            $updateStmt->close();
            $reviewId = $existingId;
        } else {
            $reviewId = xiapee_reviews_supports_auto_increment($conn)
                ? null
                : xiapee_reviews_next_id($conn);

            if ($reviewId === null) {
                $insertStmt = $conn->prepare('INSERT INTO `Reviews` (merchant_id, user_id, rating, text) VALUES (?, ?, ?, ?)');
                if (!$insertStmt) {
                    throw new RuntimeException('Unable to save review.');
                }
                $insertStmt->bind_param('iids', $merchantId, $userId, $rating, $text);
            } else {
                $insertStmt = $conn->prepare('INSERT INTO `Reviews` (id, merchant_id, user_id, rating, text) VALUES (?, ?, ?, ?, ?)');
                if (!$insertStmt) {
                    throw new RuntimeException('Unable to save review.');
                }
                $insertStmt->bind_param('iiids', $reviewId, $merchantId, $userId, $rating, $text);
            }

            $insertStmt->execute();
            if ($reviewId === null) {
                $reviewId = (int)$conn->insert_id;
            }
            $insertStmt->close();
        }

        $conn->commit();

        $reviewData = xiapee_fetch_review_summary($conn, $merchantId, $userId);
        $reviewData['currentUserReview'] = array_merge(
            $reviewData['currentUserReview'] ?? [],
            ['id' => $reviewId, 'rating' => $rating, 'text' => $text]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Review saved successfully.',
            'review' => $reviewData['currentUserReview'],
            'summary' => $reviewData['summary'],
        ]);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save review.',
            'error' => $e->getMessage(),
        ]);
    } finally {
        $conn->close();
    }
    exit;
}

if ($method === 'GET') {
    $merchantId = isset($_GET['merchantId']) ? (int)$_GET['merchantId'] : 0;
    if ($merchantId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid merchant id is required.']);
        exit;
    }
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $data = xiapee_fetch_review_summary($conn, $merchantId, $userId);
    echo json_encode([
        'success' => true,
        'summary' => $data['summary'],
        'review' => $data['currentUserReview'],
    ]);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}