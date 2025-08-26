<?php
$pagename = "Order Status";
include("header.php");

require __DIR__ . "/order_data.php";

$id = isset($_GET['id'])? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? null;
$order = find_order($id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order<?=safe($order['id'])?></title>
    <link rel = "stylesheet" href = "decoration.css">
</head>
<body>
    <div class = 'wrap'>
        <a class = "back" href = "order_status.php">Back</a>

        <div class = "card">
            <h2 class = "info">Order #<?=safe($order['id'])?></h2>
            <h3 class = "info">Estimate Time Arrived: <?=safe($order['eta'])?></h3>

            <span class = "container">
                <ul>
                    <li class = "step" style = "--i:0"></li><span class = "label">Placed Order</span></li>
                    <li class = "step" style = "--i:1"></li><span class = "label">Preparing</span></li>
                    <?php if ($type === 'delivery'):?>
                        <li class = "step" style = "--i:2"></li><span class = "label">Picked Up By Courier</span></li>
                        <li class = "step" style = "--i:3"></li><span class = "label">On The Way</span></li>
                        <li class = "step" style = "--i:4"></li><span class = "label">Arrived</span></li>
                    <?php else :?>
                        <li class = "step" style = "--i:2"></li><span class = "label">Ready for Pickup</span></li>
                        <li class = "step" style = "--i:3"></li><span class = "label">Picked Up By Customer</span></li>
                    <?php endif;?>
                </ul>
            </span>
        </div>

        <div class = "bills">
            <div class = "delivery_details">
                <?php if ($type === "delivery"):?>
                    <div class = "courier_contact"></div>
                        <p class = "details">Courier: <?= safe($order['courier'])?></p>
                        <button class = "contact">Call</button>
                        <button class = "contact">Message</button>
                    </div>
                <p class = "details">Drop-off: <?= safe($order['destination'])?></p>
                <?php else: ?>
                <p class = "details">Courier: -</p>
                <p class = "details">Pickup at: <?= safe($order['destination'])?></p>
                <?php endif;?>
            </div>

            <div class = "order_bills">
                <h3>Order Summary</h3>
                <thead>
                    <?php foreach($order as $item):?>
                        <tr><th><?= safe($order['restaurant'])?></th></tr>
                    <?php endforeach;?>
                </thead>
            </div>
        </div>
    </div>
</body>
</html>