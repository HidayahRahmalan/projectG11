<?php
require 'conn.php';

if (!isset($_GET['media_id'])) {
    http_response_code(400);
    exit('Missing media_id');
}

$media_id = (int)$_GET['media_id'];

$stmt = $conn->prepare("SELECT file_name, file_type, media_data FROM attachment WHERE media_id = ?");
$stmt->bind_param("i", $media_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('File not found');
}

$row = $result->fetch_assoc();

header("Content-Type: " . $row['file_type']);
header('Content-Disposition: inline; filename="' . $row['file_name'] . '"');
echo $row['media_data'];
