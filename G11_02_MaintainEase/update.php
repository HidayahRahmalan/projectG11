<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance_id = $_POST['maintenance_id'];
    $new_status = $_POST['status'];
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    // Get current status for audit
    $stmt = $conn->prepare("SELECT status FROM maintenance WHERE maintenance_id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $maintenance = $result->fetch_assoc();

    if (!$maintenance) {
        echo "Maintenance request not found.";
        exit;
    }

    $old_status = $maintenance['status'];

    // Insert into user_maintenance
    $track_stmt = $conn->prepare("INSERT INTO user_maintenance (user_id, maintenance_id, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)");
    $track_stmt->bind_param("iisss", $user_id, $maintenance_id, $old_status, $new_status, $comment);
    $track_stmt->execute();

    // Update maintenance status
    $update_stmt = $conn->prepare("UPDATE maintenance SET status = ? WHERE maintenance_id = ?");
    $update_stmt->bind_param("si", $new_status, $maintenance_id);
    $update_stmt->execute();

    // Insert audit log
    $audit_stmt = $conn->prepare("INSERT INTO audit_log (user_id, actiontype, actiontime) VALUES (?, 'Updated report status', NOW())");
    $audit_stmt->bind_param("i", $user_id);
    $audit_stmt->execute();

    // Close statements
    $track_stmt->close();
    $update_stmt->close();
    $audit_stmt->close();

    // Redirect back to viewquery.php
    header("Location: viewquery.php");
    exit;
} else {
    echo "Invalid request.";
}
?>
