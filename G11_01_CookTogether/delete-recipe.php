<?php
session_start();
require_once 'connection.php';

// Security: Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die("Access Denied. You must be logged in.");
}

// Security: Check if the form was submitted via POST and if recipe_id is set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['recipe_id'])) {
    
    $recipe_id = $_POST['recipe_id'];
    $user_id = $_SESSION['user_id'];

    // Use a transaction for safety
    $conn->begin_transaction();

    try {
        
        // 1. Get all media files to delete them from the server
        $sql_get_media = "SELECT file_path FROM media WHERE recipe_id = ?";
        $stmt_get_media = $conn->prepare($sql_get_media);
        $stmt_get_media->bind_param("i", $recipe_id);
        $stmt_get_media->execute();
        $media_results = $stmt_get_media->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_get_media->close();
        
        // 2. Delete the physical files
        foreach ($media_results as $media) {
            if (file_exists($media['file_path'])) {
                unlink($media['file_path']); // The unlink() function deletes a file
            }
        }

        // 3. The most important security check: 
        // Delete the recipe ONLY if the recipe_id matches AND the user_id matches.
        // This prevents a malicious user from deleting someone else's recipe.
        $sql_delete = "DELETE FROM recipes WHERE recipe_id = ? AND user_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $recipe_id, $user_id);
        $stmt_delete->execute();

        // Check if a row was actually deleted
        if ($stmt_delete->affected_rows > 0) {
            // Success! Commit the transaction.
            $conn->commit();
            // Redirect to homepage with a success message
            header("location: home.php?delete_success=1");
            exit();
        } else {
            // If no rows were affected, it means the user did not have permission.
            throw new Exception("You do not have permission to delete this recipe, or the recipe does not exist.");
        }
        
    } catch (Exception $e) {
        // If any error occurred, roll back all database changes
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }

} else {
    // If not a POST request, just redirect away.
    header("location: home.php");
    exit();
}
?>
