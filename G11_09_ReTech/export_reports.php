<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=maintenance_reports.csv');

$output = fopen('php://output', 'w');

// CSV Header row
fputcsv($output, ['Report ID', 'Title', 'Location', 'Urgency Level', 'Status', 'Report Date', 'Submitted By']);

// Fetch data
$sql = "SELECT r.*, u.username 
        FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY r.report_date DESC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['report_id'],
        $row['title'],
        $row['location'],
        $row['urgency_level'],
        $row['status'],
        $row['report_date'],
        $row['username'] ?? 'N/A'
    ]);
}

fclose($output);
exit;
?>
