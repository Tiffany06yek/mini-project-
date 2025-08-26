<?php

$restaurants = [
  ['id' => 1, 'name' => 'Thai Taro', 'tags' => ['Halal', 'Asian'], 'area' => 'D6 Ground Floor', 'eta' => '10-15 mins', 'rating' => '4.3'],
  ['id' => 2, 'name' => "Let's Kopitiam", 'tags' => ['Halal', 'Asian'], 'area' => 'D6 Ground Floor', 'eta' => '15-25 mins', 'rating' => '4.5'],
  ['id' => 3, 'name' => 'Chinese Ramen', 'tags' => ['Halal', 'Chinese'], 'area' => 'D6 First Floor', 'eta' => '10-15 mins', 'rating' => '4.0'],
  ['id' => 4, 'name' => 'Thumbs Up', 'tags' => ['Halal', 'Western'], 'area' => 'D6 Second Floor', 'eta' => '20-25 mins', 'rating' => '3.5'],
  ['id' => 5, 'name' => 'MiniMart', 'tags' => ['Grocery'], 'area' => 'B1 LG Floor', 'eta' => '10-15 mins', 'rating' => '3.0'],
];

// ===== 模拟数据库：示例订单 =====
$ORDERS = [
  [
    'id' => 1001,
    'type' => 'delivery', // delivery | pickup
    'restaurant' => 'Thai Taro',
    'eta' => '2025-08-24 18:30:00',
    'status' => 'on_the_way', // preparing | on_the_way | ready_for_pickup | delivered | picked_up
    'courier' => 'Alex',
    'destination' => 'XMUM D4 C104',
    'items' => [
      ['name' => 'Chicken Rice',   'qty' => 1, 'price' => 8.50],
      ['name' => 'Iced Lemon Tea', 'qty' => 2, 'price' => 3.20],
    ],
  ],
  [
    'id' => 1002,
    'type' => 'pickup',
    'restaurant' => 'Let Kopitiam',
    'eta' => '2025-08-24 18:10:00',
    'status' => 'ready_for_pickup',
    'courier' => null, // 自取没有配送员
    'destination' => 'D6 First Floor',
    'items' => [
      ['name' => 'Nasi Lemak', 'qty' => 1, 'price' => 7.90],
      ['name' => 'Milo Ice',   'qty' => 1, 'price' => 3.00],
    ],
  ],
  [
    'id' => 1003,
    'type' => 'delivery',
    'restaurant' => 'Thums Up',
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
    'restaurant' => 'Small Tai Wind',
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

function safe($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function order_total(array $order) {
    $sum = 0;
    foreach ($order['items'] as $item){
        $sum += $item ['qty'] * $item['price'];
        return (float)$sum;
    }
}

function find_order(int $id) {
  global $ORDERS;
  foreach ($ORDERS as $o){
    if ((int)$o['id'] === $id){
      return $o;
    }
  }
  return null;
}

function status_label(string $status){
  $map = [
    'preparing' => "Preparing",
    'on_the_way' => "On The Way",
    'ready_for_pickup' => "Ready for Pickup",
    'delivered' => "Delivered",
    'picked_up' => "Picked Up",
  ];
  return $map[$status] ?? $status;
}