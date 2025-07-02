<?php
session_start();
include('db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: delete_report.php");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$report_id = $_GET['id'] ?? 0;

// Validate report ID
if (!$report_id) {
    $_SESSION['error'] = "Invalid report ID.";
    header("Location: viewquery.php");
    exit();
}

// Verify report exists and permission
$sql = "SELECT user_id FROM maintenance WHERE maintenance_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Report not found.";
    header("Location: view_reports.php");
    exit();
}

$report = $result->fetch_assoc();

if (!$is_admin && $report['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "You don't have permission to delete this report.";
    header("Location: view_reports.php");
    exit();
}

// Fetch related media files
$sql = "SELECT file_path FROM media WHERE maintenance_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$media_result = $stmt->get_result();
$media_files = [];
while ($row = $media_result->fetch_assoc()) {
    $media_files[] = $row['file_path'];
}

// Delete maintenance report
$conn->begin_transaction();

try {
    // Optional: Insert to audit_log table
    $audit_sql = "INSERT INTO audit_log (user_id, actiontype) VALUES (?, 'DELETE_REPORT')";
    $stmt = $conn->prepare($audit_sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    // Delete report
    $stmt = $conn->prepare("DELETE FROM maintenance WHERE maintenance_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();

    $conn->commit();

    // Delete media files from file system
    foreach ($media_files as $file) {
        $path = "uploads/" . $file;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $_SESSION['success'] = "Report deleted successfully.";
    header("Location: view_reports.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error deleting report: " . $e->getMessage();
    header("Location: view_report.php?id=" . $report_id);
    exit();
}
?>
