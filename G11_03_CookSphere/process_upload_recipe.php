<?php
session_start();
require 'connection.php';

// Only allow logged-in chefs
if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'chef') {
    header("Location: index.php");
    exit();
}

// Helper to handle file upload
function handle_upload($file, $target_dir = 'media/') {
    $allowed_types = ['image/jpeg','image/png','image/gif','image/jpg','video/mp4','video/webm','video/ogg'];
    if (!in_array($file['type'], $allowed_types)) return false;
    if ($file['error'] !== 0) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . "." . $ext;
    $target = $target_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }
    return false;
}

// Sanitize input
function clean($str) {
    return htmlspecialchars(trim($str));
}

$user_id = $_SESSION['userid'];
$title = clean($_POST['title']);
$desc = clean($_POST['description']);
$mode = $_POST['mode'];
//$cuisine = clean($_POST['cuisine']);
//$dietary = clean($_POST['dietary']);
$cuisine = ($_POST['cuisine'] === 'Other') ? clean($_POST['cuisine_other']) : clean($_POST['cuisine']);
$dietary = ($_POST['dietary'] === 'Other') ? clean($_POST['dietary_other']) : clean($_POST['dietary']);


if ($mode == 'simple') {
    // --- Simple Recipe: one main instruction, multiple media ---
    //$main_instruction = clean($_POST['main_instruction']);

    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipe (UserID, Title, Description, CuisineType, DietaryType) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title, $desc, $cuisine, $dietary);
    $stmt->execute();
    $recipe_id = $stmt->insert_id;
    $stmt->close();

    // (Optional) Save main instruction somewhere if needed

    // Handle media uploads (attach to recipe, StepID=NULL)
    if (isset($_FILES['media_files']) && count($_FILES['media_files']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['media_files']['name']); $i++) {
            $tmp = [
                'name' => $_FILES['media_files']['name'][$i],
                'type' => $_FILES['media_files']['type'][$i],
                'tmp_name' => $_FILES['media_files']['tmp_name'][$i],
                'error' => $_FILES['media_files']['error'][$i],
                'size' => $_FILES['media_files']['size'][$i]
            ];
            if ($tmp['error'] == UPLOAD_ERR_NO_FILE) continue; // skip empty
            $media_path = handle_upload($tmp);
            if ($media_path) {
                $media_type = (strpos($tmp['type'], 'image') !== false) ? 'image' : 'video';
                $stmt = $conn->prepare("INSERT INTO media (RecipeID, StepID, MediaType, MediaPath, FileSize, Duration) VALUES (?, NULL, ?, ?, ?, NULL)");
                $stmt->bind_param("issi", $recipe_id, $media_type, $media_path, $tmp['size']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    header("Location: my_recipes.php?success=1");
    exit();

} else if ($mode == 'steps') {
    // --- Step-by-step Recipe ---
    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipe (UserID, Title, Description, CuisineType, DietaryType) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title, $desc, $cuisine, $dietary);
    $stmt->execute();
    $recipe_id = $stmt->insert_id;
    $stmt->close();

    // Insert each step using order in POST, not keys!
    $steps = array_values($_POST['steps'] ?? []);
    $files = isset($_FILES['steps']) ? $_FILES['steps'] : [];

    for ($i = 0; $i < count($steps); $i++) {
        $step = $steps[$i];
        $instruction = clean($step['instruction']);
        $step_num = $i+1; // 1-based order

        $stmt = $conn->prepare("INSERT INTO step (RecipeID, StepNumber, Instruction) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $recipe_id, $step_num, $instruction);
        $stmt->execute();
        $step_id = $stmt->insert_id;
        $stmt->close();

        // Handle media for this step: use array_values to match order!
        if (isset($files['name'][$i]['media'])) {
            $media_names = $files['name'][$i]['media'];
            $media_types = $files['type'][$i]['media'];
            $media_tmps = $files['tmp_name'][$i]['media'];
            $media_errors = $files['error'][$i]['media'];
            $media_sizes = $files['size'][$i]['media'];
            for ($k = 0; $k < count($media_names); $k++) {
                if ($media_errors[$k] == UPLOAD_ERR_NO_FILE) continue;
                $tmp = [
                    'name' => $media_names[$k],
                    'type' => $media_types[$k],
                    'tmp_name' => $media_tmps[$k],
                    'error' => $media_errors[$k],
                    'size' => $media_sizes[$k]
                ];
                $media_path = handle_upload($tmp);
                if ($media_path) {
                    $media_type = (strpos($tmp['type'], 'image') !== false) ? 'image' : 'video';
                    $stmt2 = $conn->prepare("INSERT INTO media (RecipeID, StepID, MediaType, MediaPath, FileSize, Duration) VALUES (?, ?, ?, ?, ?, NULL)");
                    $stmt2->bind_param("iissi", $recipe_id, $step_id, $media_type, $media_path, $tmp['size']);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
    }

    header("Location: my_recipes.php?success=1");
    exit();

} else {
    header("Location: upload_recipe.php?error=1");
    exit();
}
?>
