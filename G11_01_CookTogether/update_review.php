<?php
session_start();
require_once 'connection.php';

// 1. Keselamatan dan Pengesahan
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
    // 2. Sahkan pemilikan ulasan sekali lagi (lapisan keselamatan kedua)
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

    // 3. Padam media yang ditanda
    if (!empty($media_to_delete)) {
        // Pastikan semua ID adalah integer
        $safe_delete_ids = array_map('intval', $media_to_delete);
        $placeholders = implode(',', array_fill(0, count($safe_delete_ids), '?'));
        
        // Ambil laluan fail untuk dipadam dari server
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
        
        // Padam rekod dari pangkalan data
        $sql_delete_media = "DELETE FROM review_photos WHERE rm_id IN ($placeholders)";
        $stmt_delete_media = $conn->prepare($sql_delete_media);
        $stmt_delete_media->bind_param(str_repeat('i', count($safe_delete_ids)), ...$safe_delete_ids);
        $stmt_delete_media->execute();
        $stmt_delete_media->close();
    }

    // 4. Proses muat naik fail baru (logik sama seperti submit_review.php)
    // (Anda boleh copy-paste logik dari fail submit_review.php ke sini)
    // ...

    // 5. Kemas kini data ulasan utama
    $sql_update = "UPDATE reviews SET rating = ?, comment = ? WHERE review_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("isi", $rating, $comment, $review_id);
    $stmt_update->execute();
    $stmt_update->close();

    // Jika semuanya berjaya
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