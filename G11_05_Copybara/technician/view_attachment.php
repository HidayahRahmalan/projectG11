<?php
require '../conn.php';
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit("Invalid ID");
}

$media_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT file_name, file_type, media_data FROM attachment WHERE media_id = ?");
$stmt->bind_param("i", $media_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("File not found");
}

$row = $result->fetch_assoc();
$filename = $row['file_name'];
$filetype = $row['file_type'];
$filedata = $row['media_data'];

while (ob_get_level()) {
    ob_end_clean();
}

header("Content-Type: $filetype");
header("Content-Disposition: inline; filename=\"" . basename($filename) . "\"");
header("Content-Length: " . strlen($filedata));
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

echo $filedata;
exit;
?>
