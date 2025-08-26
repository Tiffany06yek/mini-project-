<?php

$msg = "";
if (isset($_POST["login"])){
    $email = strtolower($_POST["email"]);
    $password = $_POST["password"];

    if (!filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL)){
        $msg = "Please enter a valid email.";
    } else if (!preg_match("/@xmu\.edu\.my$/i", $email)){
        $msg = "Please using your school email.";
    } else if (strlen($password) < 6){
        $msg = "Password must be at least 6 characters. ";
    } else {
        header ("Location: homepage.php");
        exit;
    }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel = "stylesheet" href = "xiapee.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
</head>
<body>
    <div id = "xiapee">
        <h2>XIApee</h2>
        <p>Food Ordering & Delivering Services</p>
    </div>
    <section id = "login_page">
        <form action = "Login.php" method = "post">
            <label>Email<br></label>
            <input type = "email" name = "email" placeholder = "e.g name@xmu.edu.my" class = "signin">
            <br><br>
            <label>Password<br></label>
            <input type = "password" name = "password" placeholder = "e.g xiamen123" class = "signin">
            <br><br>
            <input type = "submit" name = "login" value = "Confirm" class = "sign_button">

            <?php if ($msg): ?>
            <p class = "error">
                <?php echo $msg;?>
            </p>
            <?php endif;?>
        </form>
    </section>
</body>
</html>
