<?php
include 'connection.php';
session_start();

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Custom log file
define('LOG_FILE', __DIR__ . '/error_log.txt');

function logErrorAndRedirect($message) {
    error_log("[" . date("Y-m-d H:i:s") . "] " . $message . "\n", 3, LOG_FILE);
    $_SESSION['error_message'] = $message;
    header("Location: ../error.php"); 
    exit;
}

if (!isset($_SESSION['user_id'])) {
    logErrorAndRedirect("User not logged in.");
}

$userId   = $_SESSION['user_id'];
$moduleId = $_POST['module_id'] ?? null;
$rating   = $_POST['rating'] ?? null;
$comment  = $_POST['comment'] ?? '';

if (!$moduleId || !$rating || !is_numeric($rating)) {
    logErrorAndRedirect("Invalid input. module_id: " . json_encode($moduleId) . ", rating: " . json_encode($rating));
}

try {
    $stmt = $conn->prepare("INSERT INTO modulerating (ModuleID, UserID, RatingValue, Comment) 
                            VALUES (:moduleId, :userId, :rating, :comment)");
    $stmt->bindParam(':moduleId', $moduleId, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
    $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);

    if ($stmt->execute()) {
        echo "Rating submitted successfully!";
    } else {
        $errorInfo = $stmt->errorInfo();
        logErrorAndRedirect("SQL execute failed: " . print_r($errorInfo, true));
    }

} catch (PDOException $e) {
    logErrorAndRedirect("PDOException: " . $e->getMessage());
}
?>
