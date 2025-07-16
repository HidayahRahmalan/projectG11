<?php

// --- SETUP AND SECURITY ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'connection.php';

// --- HELPER FUNCTION FOR FILE UPLOADS  ---
function handleFileUpload($file, $upload_dir, $prefix) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Silently ignore if no file was uploaded, or throw an error for other issues
        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error code: " . $file['error']);
        }
        return null;
    }

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception("Failed to create upload directory.");
    }

    $tmp_name = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception("Invalid file type: " . htmlspecialchars($file['name']));
    }

    $unique_filename = $prefix . uniqid('', true) . '.' . $file_ext;
    $target_path = $upload_dir . $unique_filename;

    if (move_uploaded_file($tmp_name, $target_path)) {
        return $target_path;
    } else {
        throw new Exception("Failed to move uploaded file: " . htmlspecialchars($file['name']));
    }
}


// --- 1. PERMISSION AND VALIDATION ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    die("Invalid request method.");
}
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['chef', 'student'])) {
    http_response_code(403); // Forbidden
    die("Access Denied.");
}
if (!isset($_POST['recipe_id']) || !filter_var($_POST['recipe_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    die("Invalid Recipe ID.");
}

// --- 2. GATHER DATA FROM FORM ---
$recipe_id = (int)$_POST['recipe_id'];
$user_id = $_SESSION['user_id'];
$upload_dir_main = 'uploads/';
$upload_dir_steps = 'uploads/'; // Can be the same or different

// Gather all POST data
$recipe_data = [
    'title' => trim($_POST['recipeTitle'] ?? ''),
    'description' => trim($_POST['recipeDescription'] ?? ''),
    'dietary_restrictions' => $_POST['dietaryRestrictions'] ?? '',
    'difficulty' => $_POST['difficulty'] ?? 'easy',
    'prep_time' => (int)($_POST['prepTime'] ?? 0),
    'cook_time' => (int)($_POST['cookTime'] ?? 0),
];

$posted_ingredients = isset($_POST['ingredients']) ? array_filter($_POST['ingredients'], 'trim') : [];
$media_to_delete = $_POST['delete_media'] ?? [];

$posted_steps = $_POST['steps'] ?? [];
$posted_step_ids = $_POST['step_ids'] ?? [];
$existing_step_images = $_POST['existing_step_images'] ?? [];

// --- VALIDATION  ---
$errors = [];
if (empty($recipe_data['title'])) { $errors[] = "Recipe Title is required."; }
if ($recipe_data['prep_time'] <= 0) { $errors[] = "Prep Time must be a valid positive number."; }
if ($recipe_data['cook_time'] <= 0) { $errors[] = "Cook Time must be a valid positive number."; }
if (empty($posted_ingredients)) { $errors[] = "At least one ingredient is required."; }

// Cuisine validation
$cuisine_input = $_POST['cuisineType'] ?? '';
$other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
if ($cuisine_input === 'other' && empty($other_cuisine_input)) {
    $errors[] = "Please specify the cuisine name when selecting 'Other'.";
}
if (!empty($errors)) {
    // In a real app, redirect with errors in session. For now, die is okay.
    die("Please fix the following errors: <br>" . implode("<br>", $errors));
}


// --- 3. DATABASE TRANSACTION ---
$conn->begin_transaction();
try {
    // --- STEP A: Verify Ownership ---
    $stmt_verify = $conn->prepare("SELECT user_id FROM recipes WHERE recipe_id = ?");
    $stmt_verify->bind_param("i", $recipe_id);
    $stmt_verify->execute();
    $owner = $stmt_verify->get_result()->fetch_assoc();
    if (!$owner || $owner['user_id'] != $user_id) {
        throw new Exception("Authorization failed. You do not own this recipe.");
    }
    $stmt_verify->close();

    // --- STEP B: Handle Cuisine  ---
    $final_cuisine_id = null;
    if ($cuisine_input === 'other') {
        $stmt_check = $conn->prepare("SELECT cuisine_id FROM cuisines WHERE cuisine_name = ?");
        $stmt_check->bind_param("s", $other_cuisine_input);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows > 0) {
            $final_cuisine_id = $result->fetch_assoc()['cuisine_id'];
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO cuisines (cuisine_name) VALUES (?)");
            $stmt_insert->bind_param("s", $other_cuisine_input);
            $stmt_insert->execute();
            $final_cuisine_id = $conn->insert_id;
            $stmt_insert->close();
        }
        $stmt_check->close();
    } elseif (is_numeric($cuisine_input)) {
        $final_cuisine_id = (int)$cuisine_input;
    }

    // --- STEP C: Update Main Recipe Details ---
    $sql_update_recipe = "UPDATE recipes SET title=?, description=?, cuisine_id=?, dietary_restrictions=?, difficulty=?, prep_time=?, cook_time=? WHERE recipe_id=?";
    $stmt_update_recipe = $conn->prepare($sql_update_recipe);
    $stmt_update_recipe->bind_param("ssissiii", 
        $recipe_data['title'], $recipe_data['description'], $final_cuisine_id,
        $recipe_data['dietary_restrictions'], $recipe_data['difficulty'],
        $recipe_data['prep_time'], $recipe_data['cook_time'], $recipe_id
    );
    $stmt_update_recipe->execute();
    $stmt_update_recipe->close();

    // --- STEP D: Handle Main Media  ---
    if (!empty($media_to_delete)) {
        // 1. Sanitize the incoming IDs to ensure they are all integers
        $safe_delete_ids = array_map('intval', $media_to_delete);
        
        // 2. Prepare a query to get the file paths before deleting the records
        // This prevents deleting a record but leaving the file on the server
        $placeholders = implode(',', array_fill(0, count($safe_delete_ids), '?'));
        $sql_get_paths = "SELECT file_path FROM media WHERE media_id IN ($placeholders) AND recipe_id = ?";
        
        // 3. Prepare and execute the query
        $stmt_get_paths = $conn->prepare($sql_get_paths);
        $types = str_repeat('i', count($safe_delete_ids)) . 'i';
        $params = array_merge($safe_delete_ids, [$recipe_id]); // Add recipe_id for extra security
        $stmt_get_paths->bind_param($types, ...$params);
        $stmt_get_paths->execute();
        $paths_to_delete = $stmt_get_paths->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_get_paths->close();

        // 4. Loop through the results and delete the files from the server
        foreach ($paths_to_delete as $file) {
            if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // 5. Now, safely delete the records from the database
        $sql_delete_media = "DELETE FROM media WHERE media_id IN ($placeholders)";
        $stmt_delete_media = $conn->prepare($sql_delete_media);
        // We only need the media IDs for this query
        $stmt_delete_media->bind_param(str_repeat('i', count($safe_delete_ids)), ...$safe_delete_ids);
        $stmt_delete_media->execute();
        $stmt_delete_media->close();
    }

    // This part for adding new media is already correct in your code.
    if (isset($_FILES['new_media'])) {
        foreach ($_FILES['new_media']['name'] as $key => $name) {
             if ($_FILES['new_media']['error'][$key] === UPLOAD_ERR_OK) {
                $file_array = [
                    'name' => $_FILES['new_media']['name'][$key],
                    'type' => $_FILES['new_media']['type'][$key],
                    'tmp_name' => $_FILES['new_media']['tmp_name'][$key],
                    'error' => $_FILES['new_media']['error'][$key],
                    'size' => $_FILES['new_media']['size'][$key]
                ];
                $target_path = handleFileUpload($file_array, $upload_dir_main, 'recipe_');
                if ($target_path) {
                    $media_type = strpos($file_array['type'], 'video') === 0 ? 'video' : 'image';
                    $stmt_media = $conn->prepare("INSERT INTO media (recipe_id, media_type, file_path) VALUES (?, ?, ?)");
                    $stmt_media->bind_param("iss", $recipe_id, $media_type, $target_path);
                    $stmt_media->execute();
                    $stmt_media->close();
                }
            }
        }
    }

    // --- STEP E: Update Ingredients ---
    $conn->query("DELETE FROM ingredients WHERE recipe_id = $recipe_id");
    if (!empty($posted_ingredients)) {
        $stmt_ing = $conn->prepare("INSERT INTO ingredients (recipe_id, ingredient_text, sort_order) VALUES (?, ?, ?)");
        foreach ($posted_ingredients as $index => $text) {
            // --- FIX: Store the calculation in a variable first ---
            $sort_order = $index + 1; 
            
            // --- Now pass the variable to bind_param ---
            $stmt_ing->bind_param("isi", $recipe_id, $text, $sort_order); 
            $stmt_ing->execute();
        }
        $stmt_ing->close();
    }

    // --- STEP F: Update Steps ---
    $step_ids_to_keep = [];
    if (!empty($posted_steps)) {
        $stmt_insert = $conn->prepare("INSERT INTO steps (recipe_id, step_text, step_image_path, sort_order) VALUES (?, ?, ?, ?)");
        $stmt_update = $conn->prepare("UPDATE steps SET step_text=?, step_image_path=?, sort_order=? WHERE step_id=? AND recipe_id=?");
        
        foreach ($posted_steps as $index => $text) {
            if (trim($text) === '') continue;

            $step_id = $posted_step_ids[$index];
            $sort_order = $index + 1;
            $db_image_path = $existing_step_images[$index] ?? null;

            if (isset($_FILES['step_images']['error'][$index]) && $_FILES['step_images']['error'][$index] === UPLOAD_ERR_OK) {
                if (!empty($db_image_path) && file_exists($db_image_path)) {
                    unlink($db_image_path);
                }
                $file_array = ['name' => $_FILES['step_images']['name'][$index], 'tmp_name' => $_FILES['step_images']['tmp_name'][$index], 'error' => $_FILES['step_images']['error'][$index]];
                $db_image_path = handleFileUpload($file_array, $upload_dir_steps, 'step_');
            }

            if ($step_id === 'new') {
                $stmt_insert->bind_param("issi", $recipe_id, $text, $db_image_path, $sort_order);
                $stmt_insert->execute();
                $step_ids_to_keep[] = $conn->insert_id;
            } else {
                $numeric_step_id = (int)$step_id;
                $stmt_update->bind_param("ssiii", $text, $db_image_path, $sort_order, $numeric_step_id, $recipe_id);
                $stmt_update->execute();
                $step_ids_to_keep[] = $numeric_step_id;
            }
        }
        $stmt_insert->close();
        $stmt_update->close();
    }
    
    // Cleanup deleted steps
     $sql_get_deleted_images = "SELECT step_image_path FROM steps WHERE recipe_id = ?";
    $params_for_get = [$recipe_id];
    $types_for_get = 'i';
    if (!empty($step_ids_to_keep)) {
        $placeholders = implode(',', array_fill(0, count($step_ids_to_keep), '?'));
        $sql_get_deleted_images .= " AND step_id NOT IN ($placeholders)";
        $types_for_get .= str_repeat('i', count($step_ids_to_keep));
        $params_for_get = array_merge($params_for_get, $step_ids_to_keep);
    }
    
    $stmt_get_images = $conn->prepare($sql_get_deleted_images);
    if ($stmt_get_images) { // Check if prepare was successful
        $stmt_get_images->bind_param($types_for_get, ...$params_for_get);
        $stmt_get_images->execute();
        $images_to_unlink = $stmt_get_images->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_get_images->close();

        foreach($images_to_unlink as $image){
            if(!empty($image['step_image_path']) && file_exists($image['step_image_path'])){
                unlink($image['step_image_path']);
            }
        }
    }

    // Delete the step records from the database
    $sql_delete_steps = "DELETE FROM steps WHERE recipe_id = ?";
    $params_for_delete = [$recipe_id];
    $types_for_delete = 'i';
    if (!empty($step_ids_to_keep)) {
        // We can reuse the placeholders from before
        $sql_delete_steps .= " AND step_id NOT IN ($placeholders)";
        $types_for_delete .= str_repeat('i', count($step_ids_to_keep));
        $params_for_delete = array_merge($params_for_delete, $step_ids_to_keep);
    }
    
    $stmt_delete_steps = $conn->prepare($sql_delete_steps);
    if ($stmt_delete_steps) { // Check if prepare was successful
        $stmt_delete_steps->bind_param($types_for_delete, ...$params_for_delete);
        $stmt_delete_steps->execute();
        $stmt_delete_steps->close();
    }
    // --- COMMIT AND REDIRECT ---
    $conn->commit();
    header("Location: recipe-details.php?id=" . $recipe_id . "&update_success=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    // Log the error for the developer and show a generic message to the user
    error_log("Recipe Update Failed for recipe_id $recipe_id: " . $e->getMessage());
    die("An error occurred while updating the recipe. Please try again or contact support.");
}

?>
