<?php
require '../conn.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../login.php");
    exit;
}

function get_mime_by_extension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'pdf' => 'application/pdf'
    ];
    return $mime_types[$ext] ?? 'application/octet-stream';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['report_id']) || !isset($_FILES['attachments'])) {
        die("Missing report ID or attachments.");
    }

    $report_id = intval($_POST['report_id']);
    $files = $_FILES['attachments'];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            $fileType = get_mime_by_extension($fileName);
            $fileData = file_get_contents($fileTmpPath);

            $stmt = $conn->prepare("INSERT INTO attachment (report_id, file_name, file_type, media_data) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $null = NULL;
            $stmt->bind_param("issb", $report_id, $fileName, $fileType, $null);
            $stmt->send_long_data(3, $fileData);

            if (!$stmt->execute()) {
                echo "Upload failed for {$fileName}: " . $stmt->error . "<br>";
            }

            $stmt->close();
        } else {
            echo "Error uploading {$files['name'][$i]} (Code: {$files['error'][$i]})<br>";
        }
    }

    header("Location: tech_ass.php?upload=success");
    exit;
} else {
    echo "Invalid request.";
}
?>
