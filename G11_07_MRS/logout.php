<?php
// Initialize the session
session_start();

// Include database connection
require_once "config/database.php";

// Remove remember me token if it exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    // Delete token from database
    $delete_token = "DELETE FROM remember_tokens WHERE token = ?";
    if ($stmt = $conn->prepare($delete_token)) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }
    
    // Delete the cookie
    setcookie("remember_token", "", time() - 3600, "/");
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), "", time() - 3600, "/");
}

// Destroy the session
session_destroy();

// Clear any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Ensure proper redirection
if (headers_sent()) {
    echo '<script>window.location.href="index.php";</script>';
} else {
    header("Location: index.php");
    exit();
}
?> 