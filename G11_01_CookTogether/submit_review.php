<?php
// Start the session to get user information
session_start();

// Include the database connection
require_once 'connection.php';

// --- 1. SECURITY AND PERMISSION CHECKS ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('HTTP/1.1 405 Method Not Allowed');
    die("Invalid request method.");
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] !== 'viewer') {
    header('HTTP/1.1 403 Forbidden');
    die("Access Denied. You must be a logged-in viewer to leave a review.");
}

// --- 2. VALIDATE SUBMITTED FORM DATA ---
$recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 5]]);
$comment = trim($_POST['comment'] ?? '');

$errors = [];
if (!$recipe_id || $recipe_id <= 0) {
    $errors[] = "Invalid Recipe ID provided.";
}
if (!$rating) {
    $errors[] = "A rating between 1 and 5 is required.";
}

if (!empty($errors)) {
    die("Error: " . implode(" ", $errors));
}

// ===================== INI BAHAGIAN YANG PERLU DITAMBAH =====================
// --- 3. CHECK FOR DUPLICATE REVIEW ---
// Pemeriksaan ini menghalang pengguna daripada menghantar ulasan lebih dari sekali
$sql_check = "SELECT 1 FROM reviews WHERE user_id = ? AND recipe_id = ? LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $user_id, $recipe_id);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $stmt_check->close();
    // Berhenti dan paparkan mesej ralat jika ulasan sudah wujud
    die("Error: You have already submitted a review for this recipe.");
}
$stmt_check->close();
// ===================== AKHIR BAHAGIAN TAMBAHAN =====================


// --- 4. DATABASE TRANSACTION & FILE HANDLING ---
$conn->begin_transaction();

try {
    // Step 4a: Insert the main review data
    $sql_review = "INSERT INTO reviews (recipe_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
    $stmt_review = $conn->prepare($sql_review);
    $stmt_review->bind_param("iiis", $recipe_id, $user_id, $rating, $comment);
    $stmt_review->execute();
    $new_review_id = $conn->insert_id;
    $stmt_review->close();

    // Step 4b: Handle ALL media uploads (logik ini tidak berubah)
    $upload_dir = 'uploads/review_media/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_media_types = [
        'image' => ['jpg', 'jpeg', 'png', 'gif'],
        'video' => ['mp4', 'mov', 'wmv', 'avi']
    ];
    $max_sizes = [
        'image' => 5 * 1024 * 1024,
        'video' => 50 * 1024 * 1024
    ];

    $files_to_process = [];
    
    if (isset($_FILES['reviewPhotos']) && !empty($_FILES['reviewPhotos']['name'][0])) {
        foreach ($_FILES['reviewPhotos']['name'] as $key => $name) {
            if ($_FILES['reviewPhotos']['error'][$key] === UPLOAD_ERR_OK) {
                $files_to_process[] = ['name' => $name, 'tmp_name' => $_FILES['reviewPhotos']['tmp_name'][$key], 'size' => $_FILES['reviewPhotos']['size'][$key], 'type' => 'image'];
            }
        }
    }
    
    if (isset($_FILES['reviewVideo']) && $_FILES['reviewVideo']['error'] === UPLOAD_ERR_OK) {
        $files_to_process[] = ['name' => $_FILES['reviewVideo']['name'], 'tmp_name' => $_FILES['reviewVideo']['tmp_name'], 'size' => $_FILES['reviewVideo']['size'], 'type' => 'video'];
    }

    foreach ($files_to_process as $file) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $media_type = $file['type'];

        if (!in_array($file_ext, $allowed_media_types[$media_type])) {
            throw new Exception("Invalid file type for '" . htmlspecialchars($file['name']) . "'.");
        }

        if ($file['size'] > $max_sizes[$media_type]) {
            $limit = $max_sizes[$media_type] / 1024 / 1024;
            throw new Exception("File '" . htmlspecialchars($file['name']) . "' is too large. Max size for " . $media_type . "s is " . $limit . "MB.");
        }
        
        $unique_filename = uniqid($media_type . '_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $unique_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $sql_photo = "INSERT INTO review_photos (review_id, file_path, media_type) VALUES (?, ?, ?)";
            $stmt_photo = $conn->prepare($sql_photo);
            $stmt_photo->bind_param("iss", $new_review_id, $target_path, $media_type);
            $stmt_photo->execute();
            $stmt_photo->close();
        } else {
            throw new Exception("Error: Could not move the uploaded file: " . htmlspecialchars($file['name']));
        }
    }

    $conn->commit();
    header("location: recipe-details.php?id=" . $recipe_id . "&review_success=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Review submission failed: " . $e->getMessage());
    die("An error occurred during submission. Details: " . $e->getMessage());
}
?>