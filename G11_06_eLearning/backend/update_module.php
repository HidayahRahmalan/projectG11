<?php
// FILE: backend/update_module.php
require_once 'auth_instructor.php';
require_once 'connection.php';
$loggedInUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../instructor/instructormanage.php?error=invalid_request');
    exit;
}

$moduleId = $_POST['module_id'];
$topicId = $_POST['topic_id'];
$title = $_POST['title'];
$description = $_POST['description'];
$topicName = $_POST['topic'];
$topicDescription = $_POST['topicdescription'];
$isPublished = $_POST['isPublished'];

if (empty($moduleId) || empty($topicId) || empty($title) || empty($topicName)) {
    // This redirect is updated to point to the correct edit page location
    header("Location: edit_module.php?id=$moduleId&error=empty_fields");
    exit;
}

try {
    $checkStmt = $conn->prepare("SELECT UserID FROM Module WHERE ModuleID = :moduleID");
    $checkStmt->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $checkStmt->execute();
    $moduleOwner = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($moduleOwner && $moduleOwner['UserID'] == $loggedInUserId) {
        $conn->beginTransaction();
        $moduleStmt = $conn->prepare(
            "UPDATE Module SET Title = :title, Description = :description, isPublished = :isPublished WHERE ModuleID = :moduleID"
        );
        $moduleStmt->execute([':title' => $title, ':description' => $description, ':isPublished' => $isPublished, ':moduleID' => $moduleId]);
        $topicStmt = $conn->prepare(
            "UPDATE Topic SET TopicName = :topicName, TopicDescription = :topicDescription WHERE TopicID = :topicID"
        );
        $topicStmt->execute([':topicName' => $topicName, ':topicDescription' => $topicDescription, ':topicID' => $topicId]);
        $conn->commit();
        header('Location: ../instructor/instructormanage.php?status=updated');
        exit;
    } else {
        throw new Exception("Unauthorized action.");
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // This redirect is updated to point to the correct edit page location
    header("Location: edit_module.php?id=$moduleId&error=db_error");
    exit;
}
?>