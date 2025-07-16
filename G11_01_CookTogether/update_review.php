<?php
session_start();
require_once 'connection.php';

// 1. Security and Validation
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access Denied. Please log in.");
}

$review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$comment = trim($_POST['comment'] ?? '');
$media_to_delete = $_POST['delete_media'] ?? [];

if (!$review_id || !$rating) {
    die("Invalid input provided.");
}

$current_user_id = $_SESSION['user_id'];
$recipe_id = null;

$conn->begin_transaction();
try {
    // 2. Verify ownership again (second layer of security)
    $sql_check = "SELECT user_id, recipe_id FROM reviews WHERE review_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $review_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$result_check) {
        throw new Exception("Review not found.");
    }
    if ($result_check['user_id'] !== $current_user_id) {
        throw new Exception("You do not have permission to update this review.");
    }
    $recipe_id = $result_check['recipe_id'];

    // 3. Delete marked media
    if (!empty($media_to_delete)) {
        $safe_delete_ids = array_map('intval', $media_to_delete);
        $placeholders = implode(',', array_fill(0, count($safe_delete_ids), '?'));
        
        $sql_get_paths = "SELECT file_path FROM review_photos WHERE rm_id IN ($placeholders) AND review_id = ?";
        $stmt_get_paths = $conn->prepare($sql_get_paths);
        $types = str_repeat('i', count($safe_delete_ids)) . 'i';
        $params = array_merge($safe_delete_ids, [$review_id]);
        $stmt_get_paths->bind_param($types, ...$params);
        $stmt_get_paths->execute();
        $paths_to_delete = $stmt_get_paths->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_get_paths->close();

        foreach ($paths_to_delete as $path) {
            if (file_exists($path['file_path'])) {
                unlink($path['file_path']);
            }
        }
        
        $sql_delete_media = "DELETE FROM review_photos WHERE rm_id IN ($placeholders)";
        $stmt_delete_media = $conn->prepare($sql_delete_media);
        $stmt_delete_media->bind_param(str_repeat('i', count($safe_delete_ids)), ...$safe_delete_ids);
        $stmt_delete_media->execute();
        $stmt_delete_media->close();
    }

    $upload_dir = 'uploads/review_media/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Process new photos
    if (isset($_FILES['reviewPhotos']) && !empty($_FILES['reviewPhotos']['name'][0])) {
        foreach ($_FILES['reviewPhotos']['name'] as $key => $name) {
            if ($_FILES['reviewPhotos']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['reviewPhotos']['tmp_name'][$key];
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $unique_filename = uniqid('review_img_', true) . '.' . $file_ext;
                $target_path = $upload_dir . $unique_filename;

                if (move_uploaded_file($tmp_name, $target_path)) {
                    $sql_insert_photo = "INSERT INTO review_photos (review_id, media_type, file_path) VALUES (?, 'image', ?)";
                    $stmt_insert_photo = $conn->prepare($sql_insert_photo);
                    $stmt_insert_photo->bind_param("is", $review_id, $target_path);
                    $stmt_insert_photo->execute();
                    $stmt_insert_photo->close();
                } else {
                    throw new Exception("Failed to move a new review photo.");
                }
            }
        }
    }

    // Process new video
    if (isset($_FILES['reviewVideo']) && $_FILES['reviewVideo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['reviewVideo']['tmp_name'];
        $name = $_FILES['reviewVideo']['name'];
        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $unique_filename = uniqid('review_vid_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $unique_filename;

        if (move_uploaded_file($tmp_name, $target_path)) {
            $sql_insert_video = "INSERT INTO review_photos (review_id, media_type, file_path) VALUES (?, 'video', ?)";
            $stmt_insert_video = $conn->prepare($sql_insert_video);
            $stmt_insert_video->bind_param("is", $review_id, $target_path);
            $stmt_insert_video->execute();
            $stmt_insert_video->close();
        } else {
            throw new Exception("Failed to move the new review video.");
        }
    }


    // 5. Update the main review data
    $sql_update = "UPDATE reviews SET rating = ?, comment = ? WHERE review_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("isi", $rating, $comment, $review_id);
    $stmt_update->execute();
    $stmt_update->close();

    // If everything was successful
    $conn->commit();
    header("Location: recipe-details.php?id=" . $recipe_id . "&edit_success=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Review Update Failed: " . $e->getMessage());
    die("An error occurred while updating the review.");
}

$conn->close();
?>
