<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!file_exists('connection.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'CRITICAL: Database connection file not found.']);
    exit;
}
require 'connection.php';

try {
    $moduleId = $_GET['module_id'] ?? null;

    // Build base query
    $sql = "
        SELECT 
            m.ModuleID, m.Title, m.Description, t.TopicName,
            v.FilePath AS VideoFilePath, s.FilePath AS SlideFilePath, n.FilePath AS NoteFilePath
        FROM module m
        LEFT JOIN topic t ON m.TopicID = t.TopicID
        LEFT JOIN video v ON m.ModuleID = v.ModuleID
        LEFT JOIN slide s ON m.ModuleID = s.ModuleID
        LEFT JOIN notes n ON m.ModuleID = n.ModuleID
        WHERE m.isPublished = 'yes'
    ";

    if ($moduleId) {
        $sql .= " AND m.ModuleID = :moduleId";
    }

    $sql .= " ORDER BY m.Title, t.TopicName";

    $stmt = $conn->prepare($sql);

    if ($moduleId) {
        $stmt->bindParam(':moduleId', $moduleId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedCourses = [];
    foreach ($results as $row) {
        $courseTitle = $row['Title'];
        if (empty($courseTitle)) continue;

        $topicName = $row['TopicName'] ?? 'General';

        if (!isset($groupedCourses[$courseTitle])) {
            $groupedCourses[$courseTitle] = [
                "ModuleID" => $row['ModuleID'],
                "Title" => $courseTitle,
                "UserID" => $userId,
                "Topics" => []
            ];
        }

        if (!isset($groupedCourses[$courseTitle]['Topics'][$topicName])) {
            $groupedCourses[$courseTitle]['Topics'][$topicName] = [
                "TopicName" => $topicName,
                "videos" => [],
                "slides" => [],
                "notes" => []
            ];
        }

        if (!empty($row['VideoFilePath'])) {
            $path = 'uploads/videos/' . basename($row['VideoFilePath']);
            $groupedCourses[$courseTitle]['Topics'][$topicName]['videos'][$path] = ['FilePath' => $path];
        }

        if (!empty($row['SlideFilePath'])) {
            $path = 'uploads/slides/' . basename($row['SlideFilePath']);
            $groupedCourses[$courseTitle]['Topics'][$topicName]['slides'][$path] = ['FilePath' => $path];
        }

        if (!empty($row['NoteFilePath'])) {
            $path = 'uploads/notes/' . basename($row['NoteFilePath']);
            $groupedCourses[$courseTitle]['Topics'][$topicName]['notes'][$path] = ['FilePath' => $path];
        }
    }

    $finalOutput = array_values($groupedCourses);
    foreach ($finalOutput as &$course) {
        $course['Topics'] = array_values($course['Topics']);
        foreach ($course['Topics'] as &$topic) {
            $topic['videos'] = array_values($topic['videos']);
            $topic['slides'] = array_values($topic['slides']);
            $topic['notes'] = array_values($topic['notes']);
        }
    }

    echo json_encode($finalOutput);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred in the backend script: " . $e->getMessage()]);
}
