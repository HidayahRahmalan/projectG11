<?php
session_start();
ob_start(); // Start output buffering to prevent "headers already sent" issues

require_once "config/database.php";

// Protect: Only Admins
if (!isset($_SESSION['id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = $_POST['report_id'];
    $newStatus = $_POST['status'];
    $adminId = $_SESSION['id'];

    // Insert new status into status_log
    $stmt = $conn->prepare("INSERT INTO status_log (status, updated_by, updated_time, Report_ID) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("sis", $newStatus, $adminId, $reportId);
    $stmt->execute();

    // Redirect after update
    header("Location: admin_manage_reports.php");
    exit();
}

include "includes/admin_header.php"; // Only include HTML or echo after all header work
?>
