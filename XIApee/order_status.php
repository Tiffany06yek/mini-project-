<?php
    $food = $_POST["food"];
    $quantity = $_POST["quantity"];
    $price = $_POST["price"];
    $location_chosen = $_POST["location_select"];
    $method = $_POST["delivery"];
    $orderId  = $_POST["orderId"];

    $subtotal = $quantity * $price;
    $fee = ($method === 'delivery') ? 2.00 : 0.00;
    $total = $subtotal + $fee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel = "stylesheet" href = "xiapee.css">

    <title>Document</title>
</head>
<body>
    <section id = "top_orderstatus">

        <div class = "order_delivery">
            <input type = "submit" value = "delivery">Delivery
            <input type = "submit" value = "pickup">Pick Up 
        </div>
        <div class = "orderID_ETA">
            <p><?php echo htmlspecialchars($orderId);?></p>
            <p>Estimated Time Arrived: 20-25 min</p>
        </div>
    </section>


    <div id = "card1">
        <p>Status</p>
       
        <ol class = "timeline">
            <li class = "done">Placed</li>
            <li class = "done">Preparing</li>
            <?php if ($method === "delivery"):?>
                <li class = "active">Picked Up</li>
                <li>On The Way</li>
                <li>Arrived</li>
            <?php else: ?>
                <li>Ready for Pick Up</li>
                <li>Picked Up</li>
            <?php endif;?> 
        </ol>
    </div>


    <div class = "card2">
        <h3>Order Details</h3>
        <p class = "info">Staff: Tiffany -- 019-999 9999</p>
        <?php if ($method === "delivery"):?>
            <p>Drop-off: <?php echo htmlspecialchars($location_chosen);?></p>
        <?php else:?>
            <p>Pick Up at Counter -- Show Your OrderID</p>
        <?php endif;?>

    <hr>

        <h4>Order Summary</h4>
        <ul>
            <li><?php echo htmlspecialchars($food);?> x <?php echo $quantity;?> --- <?php echo number_format($price, 2);?> each</li>
        </ul>

        <p>Subtotal: RM <?php echo number_format($subtotal, 2);?></p>
        <?php if ($method === "delivery"):?>
            <p>Delivery Fee: RM2</p>
        <?php else:?>
            <p>Delivery Fee: RM0</p>
        <?php endif;?>

        <div class = "total">
            <p>Total</p>
            <p><?php echo number_format($total, 2);?></p>
        </div>
    </div>

    <div id = "back">
        <a href = "create_order.php">Order</a>
        <a href = "homepage.php">Home Page</a>
    </div>
    

</body>
</html>