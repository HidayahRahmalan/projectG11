<?php
// --- SETUP AND SECURITY ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'connection.php';

// --- 1. PERMISSION AND VALIDATION CHECKS ---

// Make sure user is logged in and has the correct role
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['chef', 'student'])) {
    die("Access Denied.");
}

// Make sure this is a POST request and the recipe_id is set
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['recipe_id'])) {
    header("location: home.php"); // Redirect to a safe page
    exit;
}

// --- 2. GATHER AND VALIDATE DATA FROM FORM ---

$recipe_id = (int)$_POST['recipe_id'];
$user_id = $_SESSION['user_id'];
$errors = []; // Initialize an array to hold potential errors

// Basic recipe info
$recipeTitle = trim($_POST['recipeTitle'] ?? '');
$dietaryRestrictions = $_POST['dietaryRestrictions'] ?? '';
$difficulty = $_POST['difficulty'] ?? 'easy';
$prepTime = trim($_POST['prepTime'] ?? 0);
$cookTime = trim($_POST['cookTime'] ?? 0);
$recipeDescription = trim($_POST['recipeDescription'] ?? '');

// Arrays of data
$posted_ingredients = isset($_POST['ingredients']) ? array_filter($_POST['ingredients'], 'trim') : [];
$posted_steps = isset($_POST['steps']) ? array_filter($_POST['steps'], 'trim') : [];
$media_to_delete = $_POST['delete_media'] ?? [];

// Basic validation
if (empty($recipeTitle)) { $errors[] = "Recipe Title is required."; }
if (empty($prepTime) || !filter_var($prepTime, FILTER_VALIDATE_INT) || $prepTime <= 0) { $errors[] = "Prep Time must be a valid positive number."; }
if (empty($cookTime) || !filter_var($cookTime, FILTER_VALIDATE_INT) || $cookTime <= 0) { $errors[] = "Cook Time must be a valid positive number."; }

// --- 3. IMPROVED CUISINE HANDLING LOGIC ---
$cuisine_input = $_POST['cuisineType'] ?? '';
$other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
$final_cuisine_id = null; // This will hold the ID we save to the recipes table

if ($cuisine_input === 'other') {
    if (empty($other_cuisine_input)) {
        $errors[] = "Please specify the cuisine name when selecting 'Other'.";
    } else {
        // User selected "Other" and typed a new cuisine name.
        // 1. Check if this new cuisine already exists.
        $stmt_check = $conn->prepare("SELECT cuisine_id FROM cuisines WHERE cuisine_name = ?");
        $stmt_check->bind_param("s", $other_cuisine_input);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            // It exists, so get its ID.
            $final_cuisine_id = $result->fetch_assoc()['cuisine_id'];
        } else {
            // It's brand new, so INSERT it and get the new ID.
            $stmt_insert = $conn->prepare("INSERT INTO cuisines (cuisine_name) VALUES (?)");
            $stmt_insert->bind_param("s", $other_cuisine_input);
            $stmt_insert->execute();
            $final_cuisine_id = $conn->insert_id; // Get the ID of the new entry
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
} elseif (!empty($cuisine_input) && is_numeric($cuisine_input)) {
    // User selected an existing cuisine from the dropdown. The value is the ID.
    $final_cuisine_id = (int)$cuisine_input;
} else {
    // User selected nothing, so we will store NULL in the database.
    $final_cuisine_id = null;
}

// --- 4. DATABASE TRANSACTION (only if no validation errors) ---

if (!empty($errors)) {
    // If there are validation errors, stop and display them.
    // In a real app, you'd redirect back with errors stored in the session.
    die("Please fix the following errors: <br>" . implode("<br>", $errors));
}

$conn->begin_transaction();

try {
    // --- Step 4a: Verify Ownership AGAIN on the server-side (Critical Security) ---
    $sql_verify = "SELECT user_id FROM recipes WHERE recipe_id = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("i", $recipe_id);
    $stmt_verify->execute();
    $owner = $stmt_verify->get_result()->fetch_assoc();
    $stmt_verify->close();

    if (!$owner || $owner['user_id'] != $user_id) {
        throw new Exception("You do not have permission to edit this recipe.");
    }

    // --- Step 4b: Update the main 'recipes' table (IMPROVED QUERY) ---
    $sql_update_recipe = "UPDATE recipes SET 
                            title = ?, 
                            description = ?, 
                            cuisine_id = ?, 
                            dietary_restrictions = ?, 
                            difficulty = ?, 
                            prep_time = ?, 
                            cook_time = ? 
                          WHERE recipe_id = ?";
    $stmt_update_recipe = $conn->prepare($sql_update_recipe);
    // Bind param types are now 'ssisssii'
    $stmt_update_recipe->bind_param("ssisssii", $recipeTitle, $recipeDescription, $final_cuisine_id, $dietaryRestrictions, $difficulty, $prepTime, $cookTime, $recipe_id);
    $stmt_update_recipe->execute();
    $stmt_update_recipe->close();

    // --- Step 4c: Delete media marked for deletion (No changes needed here) ---
    if (!empty($media_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($media_to_delete), '?'));
        $sql_get_paths = "SELECT file_path FROM media WHERE media_id IN ($placeholders) AND recipe_id = ?"; // Added recipe_id for security
        $stmt_get_paths = $conn->prepare($sql_get_paths);
        $types = str_repeat('i', count($media_to_delete)) . 'i';
        $params = array_merge($media_to_delete, [$recipe_id]);
        $stmt_get_paths->bind_param($types, ...$params);
        $stmt_get_paths->execute();
        $paths_to_delete = $stmt_get_paths->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_get_paths->close();

        // Delete records from database
        $sql_delete_media = "DELETE FROM media WHERE media_id IN ($placeholders)";
        $stmt_delete_media = $conn->prepare($sql_delete_media);
        $stmt_delete_media->bind_param(str_repeat('i', count($media_to_delete)), ...$media_to_delete);
        $stmt_delete_media->execute();
        $stmt_delete_media->close();

        // Delete files from server
        foreach ($paths_to_delete as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
    }

    // --- Step 4d: Upload any NEW media files (No changes needed here) ---
    if (isset($_FILES['new_media']) && !empty($_FILES['new_media']['name'][0])) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        foreach ($_FILES['new_media']['name'] as $key => $name) {
            if ($_FILES['new_media']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['new_media']['tmp_name'][$key];
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $unique_filename = uniqid('recipe_', true) . '.' . $file_ext;
                $target_path = $upload_dir . $unique_filename;
                if (move_uploaded_file($tmp_name, $target_path)) {
                    $media_type = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video';
                    $sql_media = "INSERT INTO media (recipe_id, media_type, file_path) VALUES (?, ?, ?)";
                    $stmt_media = $conn->prepare($sql_media);
                    $stmt_media->bind_param("iss", $recipe_id, $media_type, $target_path);
                    $stmt_media->execute();
                    $stmt_media->close();
                } else { throw new Exception("Failed to move a new uploaded file."); }
            }
        }
    }

    // --- Step 4e: Update ingredients and steps (No changes needed here) ---
    // Delete all old ingredients for this recipe
    $stmt_del_ing = $conn->prepare("DELETE FROM ingredients WHERE recipe_id = ?");
    $stmt_del_ing->bind_param("i", $recipe_id);
    $stmt_del_ing->execute();
    $stmt_del_ing->close();

    // Insert the new list of ingredients
    if(!empty($posted_ingredients)) {
        $sql_ingredient = "INSERT INTO ingredients (recipe_id, ingredient_text, sort_order) VALUES (?, ?, ?)";
        $stmt_ingredient = $conn->prepare($sql_ingredient);
        foreach ($posted_ingredients as $index => $text) {
            $sort_order = $index + 1;
            $stmt_ingredient->bind_param("isi", $recipe_id, $text, $sort_order);
            $stmt_ingredient->execute();
        }
        $stmt_ingredient->close();
    }

    // Delete all old steps for this recipe
    $stmt_del_steps = $conn->prepare("DELETE FROM steps WHERE recipe_id = ?");
    $stmt_del_steps->bind_param("i", $recipe_id);
    $stmt_del_steps->execute();
    $stmt_del_steps->close();

    // Insert the new list of steps
    if(!empty($posted_steps)) {
        $sql_step = "INSERT INTO steps (recipe_id, step_text, sort_order) VALUES (?, ?, ?)";
        $stmt_step = $conn->prepare($sql_step);
        foreach ($posted_steps as $index => $text) {
            $sort_order = $index + 1;
            $stmt_step->bind_param("isi", $recipe_id, $text, $sort_order);
            $stmt_step->execute();
        }
        $stmt_step->close();
    }

    // If we get here without any errors, commit all the changes.
    $conn->commit();

    // Redirect back to the recipe page with a success message
    header("location: recipe-details.php?id=" . $recipe_id . "&update_success=1");
    exit();

} catch (Exception $e) {
    // If any error occurred, cancel everything
    $conn->rollback();
    // Provide a user-friendly error message
    die("An error occurred while updating the recipe. Please try again. <br>Error: " . $e->getMessage());
}
?>