<?php
header('Content-Type: application/json');

// 接收 action 参数
$action = $_GET['action'] ?? '';

// 获取 POST 数据（JSON）
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

// 简单模拟数据库操作
if ($action === 'add') {
    // 这里只是显示收到的内容（测试用）
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

// 其他情况
echo json_encode(['success' => false, 'error' => 'unknown action']);