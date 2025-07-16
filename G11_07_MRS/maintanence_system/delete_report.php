<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

// Check if user is logged in and report ID is provided
if (!isset($_SESSION["id"]) || !isset($_POST["report_id"])) {
    header("Location: view_reports.php");
    exit();
}

$report_id = $_POST["report_id"];

// Begin transaction
$conn->begin_transaction();

try {
    // Delete related records first
    // Delete comments
    $sql = "DELETE FROM COMMENTS WHERE Report_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $report_id);
    $stmt->execute();

    // Delete status logs
    $sql = "DELETE FROM STATUS_LOG WHERE Report_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $report_id);
    $stmt->execute();

    // Delete media files
    $sql = "SELECT file_path FROM MEDIA WHERE Report_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($media = $result->fetch_assoc()) {
        if (file_exists($media['file_path'])) {
            unlink($media['file_path']);
        }
    }

    // Delete media records
    $sql = "DELETE FROM MEDIA WHERE Report_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $report_id);
    $stmt->execute();

    // Delete the report
    $sql = "DELETE FROM REPORT WHERE Report_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $report_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    $_SESSION["success_msg"] = "Report deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION["error_msg"] = "Error deleting report: " . $e->getMessage();
}

header("Location: view_reports.php");
exit();
?> 