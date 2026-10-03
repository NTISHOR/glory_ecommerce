<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['role'] !== 'vendor') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'];
?>