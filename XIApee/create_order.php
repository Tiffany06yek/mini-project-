<?php
    $orderId = "XA-" . time() . rand(100, 999);
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

    <form action = "order_status.php" method = "post">
        <div class = "row">
            <label>Food:</label>
            <input type = "text" name = "food" required>
        </div>

        <div class = "row">
            <label>Quantity:</label>
            <input type = "text" name = "quantity" min = "1" required>
        </div>

        <div class = "row">
            <label>Price(RM):</label>
            <input type = "text" name = "price" min = "0" required>
        </div>

        <div class = "row">
            <label>Location:</label>
            <select name = "location_select">
                <option value = "Block A3">A3</option>
                <option value = "Block D3">D3</option>
                <option value = "Block D4">D4</option>
                <option value = "Block D6">D6</option>
                <option value = "Block LY5">LY5</option>
            </select>
        </div>

        <div class = "row">
            <select name = "delivery">
                <option value = "delivery">Delivery</option>
                <option value = "pick-up">Pick-Up</option>
            </select>
        </div>

        <input type = "hidden" name = "orderId" value = "<?php echo htmlspecialchars($orderId); ?>">
        <button class="btn" type="submit" name="submit_food" value="1">Confirm</button>
    </form>
</body>
</html>