<?php
// Start the session to get user information
session_start();

// Include the database connection
require_once 'connection.php';

// --- 1. INITIAL SECURITY AND INPUT VALIDATION ---

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('HTTP/1.1 405 Method Not Allowed');
    die("Invalid request method.");
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die("Access Denied. You must be logged in to perform this action.");
}

// Get and validate the IDs from the form submission
$review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT); // For redirecting back

if (!$review_id || $review_id <= 0) {
    die("Error: Invalid review ID provided.");
}
if (!$recipe_id || $recipe_id <= 0) {
    die("Error: Invalid recipe ID for redirection.");
}

// Get the current user's ID
$current_user_id = $_SESSION['user_id'];

// --- 2. VERIFY OWNERSHIP AND DELETE ---

// Use a transaction for safety
$conn->begin_transaction();

try {
    // Step 2a: Fetch the review to get its user_id and any associated media files
    $sql_get_review = "SELECT r.user_id, p.file_path 
                       FROM reviews r
                       LEFT JOIN review_photos p ON r.review_id = p.review_id
                       WHERE r.review_id = ?";
    
    $stmt_get = $conn->prepare($sql_get_review);
    $stmt_get->bind_param("i", $review_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Review not found.");
    }
    
    $review_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_get->close();

    $review_owner_id = $review_data[0]['user_id'];
    
    // =================================================================
    //  THE MOST IMPORTANT SECURITY CHECK: VERIFY OWNERSHIP
    // =================================================================
    // Check if the logged-in user is the actual owner of the review
    if ($review_owner_id !== $current_user_id) {
        // If not the owner, stop everything.
        header('HTTP/1.1 403 Forbidden');
        throw new Exception("You do not have permission to delete this review.");
    }

    // Step 2b: Delete the associated media files from the server
    foreach ($review_data as $row) {
        if (!empty($row['file_path']) && file_exists($row['file_path'])) {
            unlink($row['file_path']); // Delete the file
        }
    }

    // Step 2c: Delete the review from the database.
    // Because of 'ON DELETE CASCADE' on the `review_photos` table, 
    // all related media records in the database will be deleted automatically.
    $sql_delete = "DELETE FROM reviews WHERE review_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $review_id);
    $stmt_delete->execute();

    if ($stmt_delete->affected_rows === 0) {
        // This case is unlikely if the first query found the review, but it's a good safety check.
        throw new Exception("Failed to delete the review from the database.");
    }
    
    $stmt_delete->close();
    
    // If everything is successful, commit the changes
    $conn->commit();

    // --- 3. REDIRECT BACK TO THE RECIPE PAGE ---
    // Add a success message parameter to the URL
    header("Location: recipe-details.php?id=" . $recipe_id . "&delete_success=1");
    exit();

} catch (Exception $e) {
    // If any error occurs, rollback all database changes
    $conn->rollback();
    
    // Log the error for the developer and show a generic message to the user
    error_log("Review Deletion Failed: " . $e->getMessage());
    die("An error occurred while trying to delete the review. Please try again.");
}

$conn->close();
?>