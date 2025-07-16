<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require 'connection.php';

try {
    // --- THE FIX IS HERE: Changed 'note n' to 'notes n' ---
    $sql = "
        SELECT 
            m.ModuleID, 
            m.Title, 
            m.Description, 
            t.TopicName,
            v.FilePath AS VideoFilePath,
            s.FilePath AS SlideFilePath,
            n.FilePath AS NoteFilePath
        FROM 
            module m
        LEFT JOIN 
            topic t ON m.TopicID = t.TopicID
        LEFT JOIN 
            video v ON m.ModuleID = v.ModuleID
        LEFT JOIN 
            slide s ON m.ModuleID = s.ModuleID
        LEFT JOIN 
            notes n ON m.ModuleID = n.ModuleID
        ORDER BY
            m.ModuleID
    ";

    $stmt = $conn->query($sql);
    if (!$stmt) {
        throw new Exception("SQL query failed to execute. Check syntax.");
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $modules = [];
    foreach ($results as $row) {
        $moduleId = $row['ModuleID'];

        if (!isset($modules[$moduleId])) {
            $modules[$moduleId] = [
                "id" => $moduleId,
                "Title" => $row['Title'],
                "Description" => $row['Description'],
                "TopicName" => $row['TopicName'] ?? 'Unknown',
                "videos" => [],
                "slides" => [],
                "notes" => []
            ];
        }

        if (!empty($row['VideoFilePath'])) {
            $path = '../uploads/videos/' . basename($row['VideoFilePath']);
            $modules[$moduleId]['videos'][$path] = ['FilePath' => $path];
        }

        if (!empty($row['SlideFilePath'])) {
            $path = '../uploads/slides/' . basename($row['SlideFilePath']);
            $modules[$moduleId]['slides'][$path] = ['FilePath' => $path];
        }
        
        if (!empty($row['NoteFilePath'])) {
            $path = '../uploads/notes/' . basename($row['NoteFilePath']);
            $modules[$moduleId]['notes'][$path] = ['FilePath' => $path];
        }
    }

    $finalOutput = [];
    foreach($modules as $module) {
        $module['videos'] = array_values($module['videos']);
        $module['slides'] = array_values($module['slides']);
        $module['notes'] = array_values($module['notes']);
        $finalOutput[] = $module;
    }

    echo json_encode($finalOutput);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>