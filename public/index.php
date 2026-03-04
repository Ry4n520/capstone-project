<?php
session_start();

// If user is logged in, go to homepage
if(isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

// If not logged in, go to login
header("Location: auth/login.php");
exit();
?>