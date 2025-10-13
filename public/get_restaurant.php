<?php
header('Content-Type: application/json');
require __DIR__ . '/../backend/database.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 用 $conn 而不是 $pdo
    $stmt = $conn->prepare("SELECT * FROM merchants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $restaurant = $result->fetch_assoc();

    if ($restaurant) {
        // 再查菜单
        $stmt2 = $conn->prepare("SELECT * FROM products WHERE merchant_id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $products = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "restaurant" => $restaurant,
            "products" => $products
        ]);
    } else {
        echo json_encode(["error" => "Restaurant not found"]);
    }
} else {
    echo json_encode(["error" => "No ID provided"]);
}
