<?php
// FILE: backend/publish_module.php

// 1. Secure the script and get the user's ID
require_once 'auth_instructor.php';
$loggedInUserId = $_SESSION['user_id'];

require_once 'connection.php';

// 2. Check for a valid request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: ../instructor/instructormanage.php?error=invalid_request');
    exit;
}

$moduleId = $_POST['id'];
if (empty($moduleId) || !is_numeric($moduleId)) {
    header('Location: ../instructor/instructormanage.php?error=invalid_id');
    exit;
}

try {
    // 3. Verify this instructor owns the module they are trying to publish.
    $checkStmt = $conn->prepare("SELECT UserID FROM module WHERE ModuleID = ?");
    $checkStmt->execute([$moduleId]);
    $moduleOwner = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // If the module exists and the owner is the logged-in user...
    if ($moduleOwner && $moduleOwner['UserID'] == $loggedInUserId) {
        
        // 4. Update the module's status to 'yes'
        $publishStmt = $conn->prepare(
            "UPDATE module SET isPublished = 'yes' WHERE ModuleID = ?"
        );
        $publishStmt->execute([$moduleId]);

        // Redirect back with a success message.
        header('Location: ../instructor/instructormanage.php?status=published');
        exit;

    } else {
        // The user does not own this module, or it doesn't exist.
        header('Location: ../instructor/instructormanage.php?error=unauthorized');
        exit;
    }

} catch (Exception $e) {
    header('Location: ../instructor/instructormanage.php?error=dberror');
    exit;
}
?>