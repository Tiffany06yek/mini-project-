<?php
// ===== 模拟数据库：示例订单 =====
$ORDERS = [
  [
    'id' => 1001,
    'type' => 'delivery', // delivery | pickup
    'eta' => '2025-08-24 18:30:00',
    'status' => 'on_the_way', // preparing | on_the_way | ready_for_pickup | delivered | picked_up
    'courier' => 'Alex',
    'destination' => 'XMUM C2 Dorm, Room 510',
    'items' => [
      ['name' => 'Chicken Rice',   'qty' => 1, 'price' => 8.50],
      ['name' => 'Iced Lemon Tea', 'qty' => 2, 'price' => 3.20],
    ],
  ],
  [
    'id' => 1002,
    'type' => 'pickup',
    'eta' => '2025-08-24 18:10:00',
    'status' => 'ready_for_pickup',
    'courier' => null, // 自取没有配送员
    'destination' => 'Cafeteria Counter A',
    'items' => [
      ['name' => 'Nasi Lemak', 'qty' => 1, 'price' => 7.90],
      ['name' => 'Milo Ice',   'qty' => 1, 'price' => 3.00],
    ],
  ],
  [
    'id' => 1003,
    'type' => 'delivery',
    'eta' => '2025-08-24 18:50:00',
    'status' => 'preparing',
    'courier' => 'Dina',
    'destination' => 'Library Level 2',
    'items' => [
      ['name' => 'Tom Yum Noodles', 'qty' => 1, 'price' => 11.90],
    ],
  ],
  [
    'id' => 1004,
    'type' => 'pickup',
    'eta' => '2025-08-24 19:00:00',
    'status' => 'picked_up',
    'courier' => null,
    'destination' => 'Grocery Counter B',
    'items' => [
      ['name' => 'Banana (1kg)', 'qty' => 1, 'price' => 6.50],
      ['name' => 'Yogurt',       'qty' => 2, 'price' => 2.80],
    ],
  ],
];

// ===== 工具函数 =====
function safe($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function order_total(array $order): float {
  $sum = 0;
  foreach ($order['items'] as $it) $sum += $it['qty'] * $it['price'];
  return $sum;
}

function find_order(int $id): ?array {
  global $ORDERS;
  foreach ($ORDERS as $o) if ((int)$o['id'] === $id) return $o;
  return null;
}

function status_label(string $status): string {
  $map = [
    'preparing'        => '准备中',
    'on_the_way'       => '配送中',
    'ready_for_pickup' => '可取餐',
    'delivered'        => '已送达',
    'picked_up'        => '已取餐',
  ];
  return $map[$status] ?? $status;
}

