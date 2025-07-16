<?php
// FILE: backend/module_delete.php

// --- THIS SCRIPT NOW PERFORMS A "SOFT DELETE" ---

// Secure the script and get the user's ID
require_once 'auth_instructor.php';
$loggedInUserId = $_SESSION['user_id'];

// We are not sending JSON back anymore, so remove the header
// header('Content-Type: application/json'); // <-- REMOVED
require 'connection.php';

// Check for a valid request
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
    // First, verify this instructor owns the module they are trying to delete.
    $checkStmt = $conn->prepare("SELECT UserID FROM module WHERE ModuleID = ?");
    $checkStmt->execute([$moduleId]);
    $moduleOwner = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // If the module exists and the owner is the logged-in user...
    if ($moduleOwner && $moduleOwner['UserID'] == $loggedInUserId) {
        
        // --- CHANGE: Instead of DELETE, we now UPDATE the 'deleted_at' column ---
        $softDeleteStmt = $conn->prepare(
            "UPDATE module SET deleted_at = NOW() WHERE ModuleID = ?"
        );
        $softDeleteStmt->execute([$moduleId]);

        // Redirect back to the manage page with a success message.
        header('Location: ../instructor/instructormanage.php?status=deleted');
        exit;

    } else {
        // The user does not own this module, or it doesn't exist.
        header('Location: ../instructor/instructormanage.php?error=unauthorized');
        exit;
    }

} catch (Exception $e) {
    // If something goes wrong, redirect with an error.
    header('Location: ../instructor/instructormanage.php?error=dberror');
    exit;
}
?>