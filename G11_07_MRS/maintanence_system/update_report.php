<?php
session_start();
require_once "config/database.php";

// Ensure staff is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'Staff') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $report_id = $_POST['Report_ID'] ?? null;
    $status = $_POST['status'] ?? null;
    $staff_id = $_SESSION['id'];

    if (empty($report_id) || empty($status)) {
        $_SESSION['status_error'] = "Missing data.";
        header("Location: report_management.php");
        exit();
    }

    // Evidence is only required for "Completed"
    $requireEvidence = $status === "Completed";

    if ($requireEvidence && (!isset($_FILES['evidence']) || empty($_FILES['evidence']['name'][0]))) {
        $_SESSION['status_error'] = "Please upload at least one file as evidence for Completed status.";
        header("Location: report_management.php");
        exit();
    }

    // Define upload directory
    define('UPLOAD_PATH', 'assets/evidence/');
    if (!file_exists(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'audio/mpeg', 'audio/mp3'];
$uploadedFiles = $_FILES['evidence'];

if (isset($uploadedFiles) && !empty($uploadedFiles['name'][0])) {
    for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
        $fileName = basename($uploadedFiles['name'][$i]);
        $fileTmp = $uploadedFiles['tmp_name'][$i];
        $fileType = mime_content_type($fileTmp);

        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['status_error'] = "Invalid file type. Only images, videos, or audio are allowed.";
            header("Location: report_management.php");
            exit();
        }

        $targetPath = UPLOAD_PATH . uniqid() . "_" . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            $stmtMedia = $conn->prepare("INSERT INTO media (Report_ID, file_path, file_type, uploaded_by, upload_time) VALUES (?, ?, ?, ?, NOW())");
            $stmtMedia->bind_param("sssi", $report_id, $targetPath, $fileType, $staff_id);
            $stmtMedia->execute();
            $stmtMedia->close();
        }
    }
}

    // // Insert status log regardless of evidence
    // $stmt = $conn->prepare("INSERT INTO status_log (Report_ID, updated_by, status, updated_time) VALUES (?, ?, ?, NOW())");
    // $stmt->bind_param("sis", $report_id, $staff_id, $status);

    // With this block:
    $stmt = $conn->prepare("UPDATE status_log SET updated_by = ?, status = ?, updated_time = NOW() WHERE Report_ID = ?");
    $stmt->bind_param("iss", $staff_id, $status, $report_id);

    if ($stmt->execute()) {
        $_SESSION['status_success'] = "Status updated successfully" . ($requireEvidence ? " with evidence." : ".");
        header("Location: report_management.php");
    } else {
        $_SESSION['status_error'] = "Failed to update status.";
        header("Location: report_management.php");
    }

    $stmt->close();
    $conn->close();
    exit();
} else {
    header("Location: report_management.php");
    exit();
}