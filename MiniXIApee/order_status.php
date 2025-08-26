<?php
$pagename = "Track Order";
include("header.php");
require __DIR__ . '/order_data.php';

$type = isset($_GET['type'])? $_GET['type'] : 'delivery';
if ($type !== "delivery" && $type !== "pickup"){
    $type = "delivery";
}

$orders = [];
foreach ($ORDERS as $o){
    if ($o['type'] === $type){
        $orders[] = $o;
    }
}

$activeDelivery = ($type === 'delivery')? 'active': '';
$activePickup = ($type === 'pickup')? 'active': '';
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
<div class = "tabs">
    <a class = "tab <?php echo $activeDelivery;?>" href = "?type=delivery">Delivery</a>
    <a class = "tab <?php echo $activePickup;?>" href = "?type=pickup">Pick Up</a>
</div>

<?php if (count($orders) === 0): ?>
    <p class = "empty">No <?php echo safe($type)?> Orders</p>
    <?php else: ?>
        <div class = "order_list">
            <?php foreach ($orders as $o): ?>
                <a class = "card" href = "card_details.php?id=<?php echo $o['id']?>&type=<?php echo safe($type)?>">

                <div class = "row">
                    <div>
                        <div class = "id">#<?php echo safe($o['id']);?></div>

                        <div class = "eta">Estimated Time Arrived:<?php $t = strtotime($o['eta']);
                                                                        echo safe(date('H:i', $t));?></div>
                    </div>

                    <div class = "destination">Drop Off:<?php echo safe($o['destination'])?></div>

                    <div class = "status <?php echo safe($o['status']);?>">
                        <span class = "dot"></span>
                        <?php echo safe(status_label($o['status']));?>
                    </div>
                </div>

                <div class = "total">
                    <span><?php echo count($o['items']);?> items | </span>
                    <span>RM <?php echo number_format(order_total($o), 2);?></span>
                </div>
            </a>
        <?php endforeach;?>
        </div>
        <?php endif;?>
    </div>

    <section id = "back">
        <a class = "other_page" href = "mainpage.php">Main Page</a>
        
    </section>
</body>
</html>