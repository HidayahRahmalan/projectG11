<?php
session_start();
if (!isset($_SESSION['id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'technician'])) {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

header('Content-Type: application/json');

if (!isset($_GET['report_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Report ID not provided']);
    exit();
}

$report_id = $_GET['report_id'];

// Get comments with user information
$sql = "SELECT c.*, u.name as user_name 
        FROM COMMENTS c 
        JOIN USERS u ON c.User_ID = u.User_ID 
        WHERE c.Report_ID = ? 
        ORDER BY c.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $report_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $comments = [];
        
        while ($comment = $result->fetch_assoc()) {
            $comments[] = [
                'id' => $comment['Comment_ID'],
                'user_name' => htmlspecialchars($comment['user_name']),
                'comment' => htmlspecialchars($comment['comment']),
                'created_at' => date('Y-m-d H:i', strtotime($comment['created_at'])),
                'is_own_comment' => $comment['User_ID'] == $_SESSION['id']
            ];
        }
        
        echo json_encode($comments);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error fetching comments']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error preparing query']);
}

$conn->close();
?> 