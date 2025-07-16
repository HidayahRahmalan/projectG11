<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['auth_token'])) {
    header("Location: ../auth/login.html");
    exit;
}
?>
