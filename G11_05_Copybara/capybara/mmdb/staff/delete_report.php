<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 3) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['report_id']) || !is_numeric($_GET['report_id'])) {
    header("Location: view_report.php?error=invalid");
    exit;
}

$report_id = (int)$_GET['report_id'];
$user_id = $_SESSION['user_id'];

// Verify ownership and status
$stmt = $conn->prepare("SELECT status FROM user_report WHERE report_id = ? AND submitted_by = ?");
$stmt->bind_param("ii", $report_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: view_report.php?error=notfound");
    exit;
}

$report = $result->fetch_assoc();
if ($report['status'] !== 'Pending') {
    $stmt->close();
    header("Location: view_report.php?error=notallowed");
    exit;
}
$stmt->close();

// Begin deletion
$conn->begin_transaction();
try {
    $delAttachments = $conn->prepare("DELETE FROM attachment WHERE report_id = ?");
    $delAttachments->bind_param("i", $report_id);
    $delAttachments->execute();
    $delAttachments->close();

    $delReport = $conn->prepare("DELETE FROM user_report WHERE report_id = ?");
    $delReport->bind_param("i", $report_id);
    $delReport->execute();
    $delReport->close();

    $conn->commit();
    header("Location: view_report.php?deleted=1");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: view_report.php?error=deletion_failed");
}
?>
