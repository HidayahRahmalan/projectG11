<?php
require '../conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['report_id'], $_POST['new_status'])) {
        die("Missing report ID or status.");
    }

    $report_id = intval($_POST['report_id']);
    $new_status = trim($_POST['new_status']);
    $user_id = $_SESSION['user_id'];
    $note = !empty($_POST['note']) ? trim($_POST['note']) : 'Status updated by technician';

    // 1. Update status in user_report
    $stmt = $conn->prepare("UPDATE user_report SET status = ? WHERE report_id = ?");
    $stmt->bind_param("si", $new_status, $report_id);
    if (!$stmt->execute()) {
        die("Failed to update report: " . $stmt->error);
    }
    $stmt->close();

    // 2. Insert into report_hist
    $hist = $conn->prepare("INSERT INTO report_hist (report_id, status, changed_by, changed_at, notes) VALUES (?, ?, ?, NOW(), ?)");
    $hist->bind_param("isis", $report_id, $new_status, $user_id, $note);
    if (!$hist->execute()) {
        die("Failed to insert history: " . $hist->error);
    }
    $hist->close();

    header("Location: tech_ass.php?status=updated");
    exit;
} else {
    echo "Invalid request.";
}
