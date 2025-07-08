<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'chef') {
    header("Location: index.php");
    exit();
}

function uploadGeneralMedia($recipeID, $files, $conn) {
    $target_dir = 'media/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] === 0) {
            $tmp_name = $files['tmp_name'][$index];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mediaType = strpos($files['type'][$index], 'image') !== false ? 'image' : 'video';
            $uniqueName = uniqid() . '.' . $ext;
            $mediaPath = $target_dir . $uniqueName;

            if (move_uploaded_file($tmp_name, $mediaPath)) {
                $fileSize = filesize($mediaPath);
                $duration = 0;
                $stmt = $conn->prepare("INSERT INTO media (RecipeID, StepID, MediaPath, MediaType, FileSize, Duration) VALUES (?, NULL, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $recipeID, $mediaPath, $mediaType, $fileSize, $duration);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function uploadStepMedia($recipeID, $stepID, $files, $conn) {
    $target_dir = 'media/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] === 0) {
            $tmp_name = $files['tmp_name'][$index];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mediaType = strpos($files['type'][$index], 'image') !== false ? 'image' : 'video';
            $uniqueName = uniqid() . '.' . $ext;
            $mediaPath = $target_dir . $uniqueName;

            if (move_uploaded_file($tmp_name, $mediaPath)) {
                $fileSize = filesize($mediaPath);
                $duration = 0;
                $stmt = $conn->prepare("INSERT INTO media (RecipeID, StepID, MediaPath, MediaType, FileSize, Duration) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssi", $recipeID, $stepID, $mediaPath, $mediaType, $fileSize, $duration);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function getNextStepNumber($recipeID, $conn) {
    $stmt = $conn->prepare("SELECT MAX(StepNumber) AS MaxNum FROM step WHERE RecipeID = ?");
    $stmt->bind_param("i", $recipeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return isset($row['MaxNum']) ? $row['MaxNum'] + 1 : 1;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recipeID = $_POST['recipe_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $cuisine = $_POST['cuisine'] === 'Other' ? trim($_POST['customCuisine']) : $_POST['cuisine'];
    $dietary = $_POST['dietary'] === 'Other' ? trim($_POST['customDietary']) : $_POST['dietary'];
    $mode = $_POST['mode'];

    if (isset($_POST['remove_media'])) {
        foreach ($_POST['remove_media'] as $stepID => $media_files) {
            foreach ($media_files as $mediaPath) {
                $safePath = trim($mediaPath);
                $fullPath = __DIR__ . '/' . $safePath;

                $mediaDir = realpath(__DIR__ . '/media');
                $resolvedPath = realpath($fullPath);

                if ($resolvedPath && strpos($resolvedPath, $mediaDir) === 0 && file_exists($resolvedPath)) {
                    unlink($resolvedPath);
                }

                $stmt = $conn->prepare("DELETE FROM media WHERE StepID = ? AND MediaPath = ?");
                $stmt->bind_param("is", $stepID, $safePath);
                $stmt->execute();
            }
        }
    }

    if (empty($title) || empty($description) || empty($cuisine) || empty($dietary)) {
        die("All fields are required.");
    }

    $stmt = $conn->prepare("UPDATE recipe SET Title = ?, Description = ?, CuisineType = ?, DietaryType = ? WHERE RecipeID = ? AND UserID = ?");
    $stmt->bind_param("ssssii", $title, $description, $cuisine, $dietary, $recipeID, $_SESSION['userid']);
    $stmt->execute();
    $stmt->close();

    if (isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'][0])) {
        uploadGeneralMedia($recipeID, $_FILES['media_files'], $conn);
    }

    if ($mode === 'steps' && isset($_POST['steps'])) {
        foreach ($_POST['steps'] as $key => $step) {
            $instruction = trim($step['instruction']);
            if (empty($instruction)) continue;

            if (is_numeric($key)) {
                $stepID = $key;
                $stmt = $conn->prepare("UPDATE step SET Instruction = ? WHERE StepID = ? AND RecipeID = ?");
                $stmt->bind_param("sii", $instruction, $stepID, $recipeID);
                $stmt->execute();
                $stmt->close();

                if (isset($_FILES['steps']['name'][$key]['media'])) {
                    $files = [
                        'name' => $_FILES['steps']['name'][$key]['media'],
                        'type' => $_FILES['steps']['type'][$key]['media'],
                        'tmp_name' => $_FILES['steps']['tmp_name'][$key]['media'],
                        'error' => $_FILES['steps']['error'][$key]['media'],
                        'size' => $_FILES['steps']['size'][$key]['media']
                    ];
                    uploadStepMedia($recipeID, $stepID, $files, $conn);
                }
            } else {
                $stepNumber = getNextStepNumber($recipeID, $conn);
                $stmt = $conn->prepare("INSERT INTO step (RecipeID, StepNumber, Instruction) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $recipeID, $stepNumber, $instruction);
                $stmt->execute();
                $newStepID = $stmt->insert_id;
                $stmt->close();

                if (isset($_FILES['steps']['name'][$key]['media'])) {
                    $files = [
                        'name' => $_FILES['steps']['name'][$key]['media'],
                        'type' => $_FILES['steps']['type'][$key]['media'],
                        'tmp_name' => $_FILES['steps']['tmp_name'][$key]['media'],
                        'error' => $_FILES['steps']['error'][$key]['media'],
                        'size' => $_FILES['steps']['size'][$key]['media']
                    ];
                    uploadStepMedia($recipeID, $newStepID, $files, $conn);
                }
            }
        }
    }

    header("Location: my_recipes.php?status=updated");
    exit();
}
?>
