<?php
$pagename = "Order Status";
include("header.php");

require __DIR__ . '/order_data.php';

$id = isset($_GET['id'])? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? null;
$order = find_order($id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status</title>
    <link rel = "stylesheet" href = "decoration.css">
</head>
<body>
<div class = "wrap">
    <a class = "back" href = "order_status.php">Back</a>

    <div class = "card">
        <h2>Order #<?=safe($order['id'])?></h2>
        <div class = "grid">
            <div>
                <div class = "label">Delivery Method</div>
                <div class = "value"><?=safe($order['type'] === 'delivery' ? 'Delivery' : 'Pickup')?></div>
            </div>

            <div>
                <div class = "label">Estimated Time Arrived</div>
                <div class = "value"><?=safe(date('Y-m-d H:i', strtotime($order['eta'])))?>
            </div>

            <div>
                <div class = "label">Status</div>
                <div class = "value">
                    <span class = "status<?=safe($order['status'])?>">
                        <span class = "dot"></span><?=safe(status_label($order['status']))?>
                    </span>
                </div>
            </div>

            <div>
                <div class = "label"><?= $order['type'] === 'delivery' ? 'Courier Name' : "Pick Up Method"?></div>
                <div class = "value"><?= $order['type'] === 'delivery' ? safe($order['courier'] ?? '-') : "Pick up at counter"?>
            </div>

            <div>
                <div class = "label"><?= $order['type'] === 'delivery' ? 'Destination' : 'Pickup Location'?></div>
                <div class = "value"><?= safe($order['destination'])?></div>
            </div>
        </div>
    </div>

    <div class = 'card'>
        <h3>Order Bills</h3>
        <table>
            <thead>
                <tr><th>Items</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th><tr>
            </thead>
            <tbody>
                <?php foreach($order['items'] as $items):?>
                    <tr>
                        <td><?= safe ($items['name'])?></td>
                        <td><?= safe ($items['qty'])?></td>
                        <td><?= number_format ($items['price'], 2)?></td>
                        <td><?= number_format ($items['qty'] * $items['price'], 2)?></td>
                    </tr>
                    <?php endforeach;?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td><?= number_format(order_total($order), 2)?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
</body>
</html>