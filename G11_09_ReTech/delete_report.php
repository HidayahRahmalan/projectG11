<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $report_id = (int) $_POST['report_id'];
    $user_id   = $_SESSION['user_id'];

    // ✅ Check if the report belongs to the logged-in user
    $check = $conn->prepare("SELECT report_id FROM reports WHERE report_id = ? AND user_id = ?");
    if (!$check) {
        die("Prepare failed: " . $conn->error); 
    }
    $check->bind_param("ii", $report_id, $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        // ✅ Delete related media
        $delMedia = $conn->prepare("DELETE FROM media WHERE report_id = ?");
        if (!$delMedia) {
            die("Prepare failed (media): " . $conn->error); 
        }
        $delMedia->bind_param("i", $report_id);
        $delMedia->execute();

        // ✅ Delete related logs
        $delLogs = $conn->prepare("DELETE FROM logs WHERE report_id = ?");
        if (!$delLogs) {
            die("Prepare failed (logs): " . $conn->error); 
        }
        $delLogs->bind_param("i", $report_id);
        $delLogs->execute();

        // ✅ Delete the report
        $delReport = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
        if (!$delReport) {
            die("Prepare failed (report): " . $conn->error); 
        }
        $delReport->bind_param("i", $report_id);
        $delReport->execute();

        $_SESSION['success'] = "✅ Report deleted successfully.";
    } else {
        $_SESSION['success'] = "❌ Invalid report or not authorized.";
    }
}

header("Location: detailsReport_staff.php");
exit;
