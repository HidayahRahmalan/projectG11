<?php
// --- SETUP AND SECURITY ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'connection.php';

// --- 1. PERMISSION AND VALIDATION CHECKS ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['chef', 'student'])) {
    die("Access Denied.");
}
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['recipe_id'])) {
    header("location: home.php");
    exit;
}

// --- 2. GATHER AND VALIDATE DATA FROM FORM ---
$recipe_id = (int)$_POST['recipe_id'];
$user_id = $_SESSION['user_id'];
$errors = [];
$upload_dir = 'uploads/'; // ---Define upload directory early

// Basic recipe info
$recipeTitle = trim($_POST['recipeTitle'] ?? '');
$dietaryRestrictions = $_POST['dietaryRestrictions'] ?? '';
$difficulty = $_POST['difficulty'] ?? 'easy';
$prepTime = trim($_POST['prepTime'] ?? 0);
$cookTime = trim($_POST['cookTime'] ?? 0);
$recipeDescription = trim($_POST['recipeDescription'] ?? '');

// Arrays of data
$posted_ingredients = isset($_POST['ingredients']) ? array_filter($_POST['ingredients'], 'trim') : [];
$media_to_delete = $_POST['delete_media'] ?? [];

// --- NEW: Gather all step-related data arrays ---
$posted_steps = $_POST['steps'] ?? [];
$posted_step_ids = $_POST['step_ids'] ?? [];
$existing_step_images = $_POST['existing_step_images'] ?? [];


// Basic validation
if (empty($recipeTitle)) { $errors[] = "Recipe Title is required."; }
if (empty($prepTime) || !filter_var($prepTime, FILTER_VALIDATE_INT) || $prepTime <= 0) { $errors[] = "Prep Time must be a valid positive number."; }
if (empty($cookTime) || !filter_var($cookTime, FILTER_VALIDATE_INT) || $cookTime <= 0) { $errors[] = "Cook Time must be a valid positive number."; }

// --- 3. IMPROVED CUISINE HANDLING LOGIC ---
$cuisine_input = $_POST['cuisineType'] ?? '';
$other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
$final_cuisine_id = null;

if ($cuisine_input === 'other') {
    if (empty($other_cuisine_input)) {
        $errors[] = "Please specify the cuisine name when selecting 'Other'.";
    } else {
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
    }
} elseif (!empty($cuisine_input) && is_numeric($cuisine_input)) {
    $final_cuisine_id = (int)$cuisine_input;
} else {
    $final_cuisine_id = null;
}

// --- 4. DATABASE TRANSACTION (only if no validation errors) ---
if (!empty($errors)) {
    die("Please fix the following errors: <br>" . implode("<br>", $errors));
}

$conn->begin_transaction();

try {
    // --- Step 4a: Verify Ownership  ---
    $sql_verify = "SELECT user_id FROM recipes WHERE recipe_id = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("i", $recipe_id);
    $stmt_verify->execute();
    $owner = $stmt_verify->get_result()->fetch_assoc();
    $stmt_verify->close();
    if (!$owner || $owner['user_id'] != $user_id) {
        throw new Exception("You do not have permission to edit this recipe.");
    }

    // --- Step 4b: Update the main 'recipes' table ---
    $sql_update_recipe = "UPDATE recipes SET title = ?, description = ?, cuisine_id = ?, dietary_restrictions = ?, difficulty = ?, prep_time = ?, cook_time = ? WHERE recipe_id = ?";
    $stmt_update_recipe = $conn->prepare($sql_update_recipe);
    $stmt_update_recipe->bind_param("ssisssii", $recipeTitle, $recipeDescription, $final_cuisine_id, $dietaryRestrictions, $difficulty, $prepTime, $cookTime, $recipe_id);
    $stmt_update_recipe->execute();
    $stmt_update_recipe->close();

    // --- Step 4c: Delete marked main media ---
    if (!empty($media_to_delete)) {
        // ... your existing code for deleting main media is fine ...
    }

    // --- Step 4d: Upload any NEW main media files (NO CHANGES NEEDED HERE) ---
    if (isset($_FILES['new_media']) && !empty($_FILES['new_media']['name'][0])) {
        // ... your existing code for uploading new main media is fine ...
    }

    // --- Step 4e: Update ingredients and steps ---

    // ----- INGREDIENTS: The delete-and-reinsert strategy is fine for simple text data. -----
    $stmt_del_ing = $conn->prepare("DELETE FROM ingredients WHERE recipe_id = ?");
    $stmt_del_ing->bind_param("i", $recipe_id);
    $stmt_del_ing->execute();
    $stmt_del_ing->close();

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

    // ----- STEPS: Use the advanced update/insert/delete strategy -----
    $step_ids_to_keep = []; // Array to track which steps were NOT deleted by the user

    if (!empty($posted_steps)) {
        foreach ($posted_steps as $index => $text) {
            // Skip any steps that were submitted but are empty
            if (trim($text) === '') {
                continue;
            }

            $step_id = $posted_step_ids[$index];
            $sort_order = $index + 1;
            $db_image_path = $existing_step_images[$index] ?? null; // Start with existing image path

            // Check if a new file was uploaded for this specific step
            if (isset($_FILES['step_images']['name'][$index]) && $_FILES['step_images']['error'][$index] === UPLOAD_ERR_OK) {
                // A new file is being uploaded, so delete the old one if it exists
                if (!empty($db_image_path) && file_exists($db_image_path)) {
                    unlink($db_image_path);
                }

                // Upload the new file
                $tmp_name = $_FILES['step_images']['tmp_name'][$index];
                $file_ext = strtolower(pathinfo($_FILES['step_images']['name'][$index], PATHINFO_EXTENSION));
                $unique_filename = uniqid('step_', true) . '.' . $file_ext;
                $target_path = $upload_dir . $unique_filename;

                if (move_uploaded_file($tmp_name, $target_path)) {
                    $db_image_path = $target_path; // Set the new path for the database
                } else {
                    throw new Exception("Failed to move uploaded step image.");
                }
            }

            // Now, decide whether to INSERT a new step or UPDATE an existing one
            if ($step_id === 'new') {
                // This is a brand new step, so INSERT it
                $sql = "INSERT INTO steps (recipe_id, step_text, step_image_path, sort_order) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issi", $recipe_id, $text, $db_image_path, $sort_order);
                $stmt->execute();
                $step_ids_to_keep[] = $conn->insert_id; // Add the newly created ID to our keep list
                $stmt->close();
            } else {
                // This is an existing step, so UPDATE it
                $numeric_step_id = (int)$step_id;
                $sql = "UPDATE steps SET step_text=?, step_image_path=?, sort_order=? WHERE step_id=? AND recipe_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiii", $text, $db_image_path, $sort_order, $numeric_step_id, $recipe_id);
                $stmt->execute();
                $step_ids_to_keep[] = $numeric_step_id; // Add the existing ID to our keep list
                $stmt->close();
            }
        }
    }

    // ----- Final Cleanup: Delete steps that were removed from the form -----
    // First, get the image paths for steps that are about to be deleted, so we can remove the files
    $sql_get_deleted_images = "SELECT step_image_path FROM steps WHERE recipe_id = ?";
    $params = [$recipe_id];
    $types = 'i';
    if (!empty($step_ids_to_keep)) {
        $placeholders = implode(',', array_fill(0, count($step_ids_to_keep), '?'));
        $sql_get_deleted_images .= " AND step_id NOT IN ($placeholders)";
        $types .= str_repeat('i', count($step_ids_to_keep));
        $params = array_merge($params, $step_ids_to_keep);
    }
    
    $stmt_get_images = $conn->prepare($sql_get_deleted_images);
    $stmt_get_images->bind_param($types, ...$params);
    $stmt_get_images->execute();
    $images_to_unlink = $stmt_get_images->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_get_images->close();

    foreach($images_to_unlink as $image){
        if(!empty($image['step_image_path']) && file_exists($image['step_image_path'])){
            unlink($image['step_image_path']);
        }
    }

    // Now, delete the step records from the database
    $sql_delete_steps = "DELETE FROM steps WHERE recipe_id = ?";
    if (!empty($step_ids_to_keep)) {
        $sql_delete_steps .= " AND step_id NOT IN ($placeholders)";
    }
    $stmt_delete_steps = $conn->prepare($sql_delete_steps);
    // Parameters are the same as for the image fetching query above
    $stmt_delete_steps->bind_param($types, ...$params);
    $stmt_delete_steps->execute();
    $stmt_delete_steps->close();


    // --- COMMIT AND REDIRECT ---
    $conn->commit();
    header("location: recipe-details.php?id=" . $recipe_id . "&update_success=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("An error occurred while updating the recipe. Please try again. <br>Error: " . $e->getMessage());
}
?>
