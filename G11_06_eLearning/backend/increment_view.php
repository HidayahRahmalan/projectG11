<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require 'connection.php';

$moduleId = $_POST['module_id'] ?? null;

if (!$moduleId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing module_id']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE module SET TotalViews = TotalViews + 1 WHERE ModuleID = :moduleId");
    $stmt->bindParam(':moduleId', $moduleId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'View count updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update views: ' . $e->getMessage()]);
}
