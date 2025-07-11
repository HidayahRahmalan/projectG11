<?php
session_start();
require_once 'connection.php';

// 1. Pengesahan Awal & Dapatkan ID Ulasan
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access Denied. Please log in.");
}

$review_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$review_id) {
    die("Invalid review ID.");
}

// 2. Ambil data ulasan & sahkan pemilikan
$current_user_id = $_SESSION['user_id'];
$review = null;
$review_media = [];
$recipe_id = null;

// Ambil ulasan, media yang berkaitan, dan ID resepi asal dalam beberapa query
// Pertama, dapatkan ulasan dan sahkan pemilik
$sql_review = "SELECT user_id, recipe_id, rating, comment FROM reviews WHERE review_id = ?";
$stmt_review = $conn->prepare($sql_review);
$stmt_review->bind_param("i", $review_id);
$stmt_review->execute();
$review = $stmt_review->get_result()->fetch_assoc();
$stmt_review->close();

if (!$review) {
    die("Review not found.");
}

// Pemeriksaan keselamatan PALING PENTING
if ($review['user_id'] !== $current_user_id) {
    die("Access Denied. You do not have permission to edit this review.");
}

$recipe_id = $review['recipe_id'];

// Kedua, dapatkan media yang berkaitan
$sql_media = "SELECT rm_id, file_path, media_type FROM review_photos WHERE review_id = ?";
$stmt_media = $conn->prepare($sql_media);
$stmt_media->bind_param("i", $review_id);
$stmt_media->execute();
$review_media = $stmt_media->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_media->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Your Review - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .edit-container { max-width: 700px; margin: 3rem auto; padding: 2rem; background: #fff; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { font-weight: bold; margin-bottom: 0.5rem; display: block; }
        .current-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem; }
        .media-item { position: relative; }
        .media-item img, .media-item video { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; }
        .delete-checkbox { position: absolute; top: 5px; right: 5px; }
        .delete-checkbox input { width: 20px; height: 20px; }
        .user-info {
            display: flex;
            flex-direction: column; /* Stack name and role vertically */
            align-items: flex-end;  /* Align text to the right */
            line-height: 1.2;
            color: #333;
            margin-right: -10px; /* Bring it a bit closer to the avatar */
        }
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .user-role {
            font-size: 0.75rem;
            color: #777;
            text-transform: capitalize; /* Makes "chef" look like "Chef" */
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
          <a href="home.php" class="logo">🍳 CookTogether</a>
          <div class="nav-links">
            <a class="nav-link" href="home.php">Home</a>
            <?php if (isset($_SESSION["loggedin"]) && in_array($_SESSION["role"], ['chef', 'student'])): ?><a class="nav-link" href="upload.php">Upload Recipe</a><?php endif; ?>
            <?php if (isset($_SESSION["loggedin"])): ?><a class="nav-link" href="logout.php">Logout</a>
            <div class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['name']); ?>"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
            <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    </div>
            <?php else: ?>
                <a class="nav-link" href="login.php">Login</a><?php endif; ?>
          </div>
        </div>
    </nav>

    <div class="container">
        <div class="edit-container">
            <h2>Edit Your Review</h2>
            <form action="update_review.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                
                <div class="form-group">
                    <label for="rating" class="form-label">Your Rating *</label>
                    <select name="rating" id="rating" class="form-input" required>
                        <option value="5" <?php if ($review['rating'] == 5) echo 'selected'; ?>>★★★★★ Excellent</option>
                        <option value="4" <?php if ($review['rating'] == 4) echo 'selected'; ?>>★★★★☆ Great</option>
                        <option value="3" <?php if ($review['rating'] == 3) echo 'selected'; ?>>★★★☆☆ Good</option>
                        <option value="2" <?php if ($review['rating'] == 2) echo 'selected'; ?>>★★☆☆☆ Okay</option>
                        <option value="1" <?php if ($review['rating'] == 1) echo 'selected'; ?>>★☆☆☆☆ Poor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="comment" class="form-label">Your Comment</label>
                    <textarea name="comment" id="comment" rows="5" class="form-textarea"><?php echo htmlspecialchars($review['comment']); ?></textarea>
                </div>

                <?php if (!empty($review_media)): ?>
                <div class="form-group">
                    <label class="form-label">Current Media (Check to delete)</label>
                    <div class="current-media-grid">
                        <?php foreach($review_media as $media): ?>
                        <div class="media-item">
                            <?php if($media['media_type'] == 'image'): ?>
                                <img src="<?php echo htmlspecialchars($media['file_path']); ?>" alt="Review image">
                            <?php else: ?>
                                <video src="<?php echo htmlspecialchars($media['file_path']); ?>"></video>
                            <?php endif; ?>
                            <div class="delete-checkbox">
                                <input type="checkbox" name="delete_media[]" value="<?php echo $media['rm_id']; ?>" title="Mark to delete">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="reviewPhotos" class="form-label">Add New Photos</label>
                    <input type="file" name="reviewPhotos[]" id="reviewPhotos" class="form-input" multiple accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="reviewVideo" class="form-label">Add New Video</label>
                    <input type="file" name="reviewVideo" id="reviewVideo" class="form-input" accept="video/*">
                </div>

                <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="submit-btn">Update Review</button>
                    <a href="recipe-details.php?id=<?php echo $recipe_id; ?>" class="submit-btn" style="background-color: #6c757d;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>