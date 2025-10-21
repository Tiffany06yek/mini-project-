<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

if ($action === 'add') {
    echo json_encode([
        'success' => true,
        'message' => 'Item added to server cart successfully!',
        'received' => $data
    ]);
    exit;
}

if ($action === 'remove') {
    echo json_encode([
        'success' => true,
        'message' => 'Item removed successfully!'
    ]);
    exit;
}

if ($action === 'clear') {
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully!'
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown action']);