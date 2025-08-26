<?php
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
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
<header id = "sidebar">
        <a href = "#">Home</a>
        <a href = "#">Notification</a>
        <a href = "create_order.php">Order Status</a>
        <a href = "#">Order History</a>
        <a href = "#">Customer Support</a>
        <a href = "#">Setting</a>
    </header>

    <section id = "topbar">
        <ul class = "top_left">
            <li>About Us</li>
            <li>Privacy</li>
            <li>Contact Us</li>
        </ul>

        <div class = "search_bar">
            <input type = "text" name = "search" placeholder = "Search your food here">
        </div>
        
        <form action = "homepage.php" method = "post">
            <input type = "submit" name = "logout" value = "Log Out">
        </form>
    </section>
</body>
</html>