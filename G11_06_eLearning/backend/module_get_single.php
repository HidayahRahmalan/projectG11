<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require 'connection.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$moduleId = $_GET['id'] ?? null;

if (empty($moduleId) || !is_numeric($moduleId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing Module ID.']);
    exit;
}

try {
    // Fetch the module and its topic name
    $stmt = $conn->prepare("
        SELECT m.*, t.TopicName, t.TopicDescription 
        FROM module m 
        LEFT JOIN topic t ON m.TopicID = t.TopicID 
        WHERE m.ModuleID = ?
    ");
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($module) {
        echo json_encode($module);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Module not found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>