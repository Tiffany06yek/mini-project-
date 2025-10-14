<?php
require __DIR__ . '/../backend/database.php'; // 数据库连接 ($conn)

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing product ID']);
    exit;
}

$id = intval($_GET['id']);

// 1️⃣ 查询产品
$sql_product = "SELECT * FROM products WHERE product_id = ?";
$stmt = $conn->prepare($sql_product);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
$vendor = null;
if (!empty($product['merchant_id'])) {
    $sql_vendor = "SELECT id, name, type, location, tags, description FROM merchants WHERE id = ? LIMIT 1";
    $stmt_vendor = $conn->prepare($sql_vendor);
    if ($stmt_vendor) {
        $stmt_vendor->bind_param("i", $product['merchant_id']);
        $stmt_vendor->execute();
        $res_vendor = $stmt_vendor->get_result();
        if ($res_vendor && $res_vendor->num_rows > 0) {
            $vendor_row = $res_vendor->fetch_assoc();
            $vendor = [
                'id' => $vendor_row['id'],
                'vendorId' => $vendor_row['id'],
                'name' => $vendor_row['name'] ?? '',
                'vendorType' => $vendor_row['type'] ?? 'restaurant',
                'type' => $vendor_row['type'] ?? 'restaurant',
                'location' => $vendor_row['location'] ?? '',
                'cuisine' => $vendor_row['tags'] ?? '',
                'tags' => $vendor_row['tags'] ?? '',
                'description' => $vendor_row['description'] ?? ''
            ];
        }
        $stmt_vendor->close();
    }
}

// 3️⃣ 查询该产品的所有 addon
$sql_addons = "SELECT addon_ID, name, price FROM product_addons WHERE product_id = ?";
$stmt_addon = $conn->prepare($sql_addons);
$stmt_addon->bind_param("i", $id);
$stmt_addon->execute();
$res_addons = $stmt_addon->get_result();

$addons = [];
while ($row = $res_addons->fetch_assoc()) {
    $addons[] = $row;
}

// 3️⃣ 返回 JSON
echo json_encode([
    'success' => true,
    'product' => $product,
    'addons' => $addons,
    'vendor' => $vendor
]);
?>
