<?php
$pagename = "Main Page";
include("header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel = "stylesheet" href = "decoration.css">
</head>
<body>
    <header id = "topbar">
        <div id = "left">
            <a class = "top_content" href = "#">About Us</a>
            <a class = "top_content" href = "#">Privacy</a>
            <a class = "top_content" href = "#">Contact Us</a>
        </div>

        <div id = "search">
            <input type = "text" name = "search" placeholder="Search your food here">
    </header>

    <section id = sidebar>
        <a class = "side_content" href = "mainpage.php">Home</a>
        <a class = "side_content" href = "order_status.php">Order Status</a>
        <a class = "side_content" href = "#">Payment</a>
        <a class = "side_content" href = "customer_support">Help</a>
        <a class = "side_content" href = "profile.php">Profile</a>

        <a id = "logout" href = "login.php">Log Out</a>
    </section> 

    <nav id = "food_category">
        <a class = "category" href = "#">Asian</a>
        <a class = "category" href = "#">Chinese</a>
        <a class = "category" href = "#">Malay</a>
        <a class = "category" href = "#">Halal</a>
        <a class = "category" href = "#">Grocery</a>
    </nav>

    
</body>
</html>