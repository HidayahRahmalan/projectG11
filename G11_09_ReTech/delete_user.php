<?php
session_start();
require 'db.php';

// Make sure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Make sure ID is provided and numeric
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['success'] = "Invalid user ID.";
    header('Location: manage_users.php');
    exit;
}

$userId = (int)$_GET['id'];

// Prevent admin from deleting themselves
if ($userId === (int)$_SESSION['user_id']) {
    $_SESSION['success'] = "You cannot delete your own account.";
    header('Location: manage_users.php');
    exit;
}

// Check if user exists
$check = $conn->prepare("SELECT * FROM users WHERE id = ?");
$check->bind_param("i", $userId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $_SESSION['success'] = "User not found.";
    header('Location: manage_users.php');
    exit;
}

// Proceed to delete
$delete = $conn->prepare("DELETE FROM users WHERE id = ?");
$delete->bind_param("i", $userId);

if ($delete->execute()) {
    $_SESSION['success'] = "User deleted successfully.";
} else {
    $_SESSION['success'] = "Error deleting user: " . $conn->error;
}

header('Location: manage_users.php');
exit;
