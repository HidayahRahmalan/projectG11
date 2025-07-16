<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require 'connection.php';

$moduleId = $_GET['module_id'] ?? null;

if (!$moduleId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing module_id parameter']);
    exit;
}

try {
    // First get the Title of the requested module
    $stmt = $conn->prepare("SELECT Title FROM module WHERE ModuleID = :moduleId");
    $stmt->execute([':moduleId' => $moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        http_response_code(404);
        echo json_encode(['error' => 'Module not found']);
        exit;
    }

    $title = $module['Title'];

    // Now fetch all topics for modules with the same Title
    $sql = "
        SELECT 
            m.ModuleID,
            t.TopicID, t.TopicName,
            v.FilePath AS VideoFilePath,
            s.FilePath AS SlideFilePath,
            n.FilePath AS NoteFilePath
        FROM module m
        JOIN topic t ON m.TopicID = t.TopicID
        LEFT JOIN video v ON m.ModuleID = v.ModuleID
        LEFT JOIN slide s ON m.ModuleID = s.ModuleID
        LEFT JOIN notes n ON m.ModuleID = n.ModuleID
        WHERE m.Title = :title
        ORDER BY t.TopicID
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':title' => $title]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $topics = [];

    foreach ($rows as $row) {
        $topicId = $row['TopicID'];

        if (!isset($topics[$topicId])) {
            $topics[$topicId] = [
                'TopicID' => $topicId,
                'TopicName' => $row['TopicName'],
                'videos' => [],
                'slides' => [],
                'notes' => []
            ];
        }

        if (!empty($row['VideoFilePath'])) {
            $path = 'uploads/videos/' . basename($row['VideoFilePath']);
            if (!in_array(['FilePath' => $path], $topics[$topicId]['videos'])) {
                $topics[$topicId]['videos'][] = ['FilePath' => $path];
            }
        }

        if (!empty($row['SlideFilePath'])) {
            $path = 'uploads/slides/' . basename($row['SlideFilePath']);
            if (!in_array(['FilePath' => $path], $topics[$topicId]['slides'])) {
                $topics[$topicId]['slides'][] = ['FilePath' => $path];
            }
        }

        if (!empty($row['NoteFilePath'])) {
            $path = 'uploads/notes/' . basename($row['NoteFilePath']);
            if (!in_array(['FilePath' => $path], $topics[$topicId]['notes'])) {
                $topics[$topicId]['notes'][] = ['FilePath' => $path];
            }
        }
    }

    echo json_encode(array_values($topics));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching topics: ' . $e->getMessage()]);
}
