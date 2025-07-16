<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug output
error_log("Session Debug - Logged in: " . (isset($_SESSION["loggedin"]) ? "yes" : "no"));
?> 