<?php
session_start();
require_once 'db_connect.php';

// Check if creator is logged in
if (!isset($_SESSION['creator_id'])) {
    header('Location: cc_login.php');
    exit();
}

// Get video ID from URL
if (!isset($_GET['id'])) {
    header('Location: cc_dashboard.php');
    exit();
}

$video_id = $_GET['id'];
$creator_id = $_SESSION['creator_id'];
$creator_name = $_SESSION['creator_name'];

$error_message = '';
$success_message = '';

// Get video details
try {
    $stmt = $pdo->prepare("
        SELECT v.*, t.name as tag_name
        FROM video v
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        WHERE v.vid_id = ? AND v.creator_id = ?
    ");
    $stmt->execute([$video_id, $creator_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        header('Location: cc_dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Video fetch error: " . $e->getMessage());
    header('Location: cc_dashboard.php');
    exit();
}

// Get all tags for dropdown
try {
    $stmt = $pdo->prepare("SELECT tag_id, name FROM tag ORDER BY name ASC");
    $stmt->execute();
    $tags = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Tags fetch error: " . $e->getMessage());
    $tags = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $tag_id = $_POST['tag_id'];
    
    $errors = [];
    
    // Validation
    if (empty($title)) $errors[] = "Please enter a video title.";
    if (strlen($title) > 100) $errors[] = "Title must be 100 characters or less.";
    if (empty($description)) $errors[] = "Please enter a video description.";
    if (strlen($description) > 1000) $errors[] = "Description must be 1000 characters or less.";
    if (empty($tag_id)) $errors[] = "Please select a category tag.";
    
    // If no errors, update the video
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE video SET title = ?, description = ?, tag_id = ? WHERE vid_id = ? AND creator_id = ?");
            $stmt->execute([$title, $description, $tag_id, $video_id, $creator_id]);
            
            $success_message = "Video updated successfully!";
            
            // Update video data for display
            $video['title'] = $title;
            $video['description'] = $description;
            $video['tag_id'] = $tag_id;
            
        } catch (PDOException $e) {
            $error_message = "Failed to update video. Please try again.";
            error_log("Video update error: " . $e->getMessage());
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - ChefTube Creator</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f0f;
            color: #fff;
            min-height: 100vh;
        }

        /* Top Navigation */
        .top-nav {
            background: #212121;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #3a3a3a;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            color: #e50914;
        }

        .back-btn {
            color: #aaa;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-weight: 500;
            border: 1px solid #3a3a3a;
        }

        .back-btn:hover {
            color: #fff;
            border-color: #e50914;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3a3a3a;
        }

        .profile-name {
            font-weight: 500;
            color: #aaa;
        }

        /* Main Content */
        .main-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #aaa;
            font-size: 16px;
        }

        /* Video Preview */
        .video-preview {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .preview-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #e50914;
        }

        .video-thumbnail-preview {
            width: 200px;
            height: 120px;
            background: #333;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .video-thumbnail-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #aaa;
        }

        /* Edit Form */
        .edit-form {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 32px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #e50914;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            color: #fff;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            background: #333;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #e50914;
            background: #404040;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .char-counter {
            text-align: right;
            color: #aaa;
            font-size: 14px;
            margin-top: 4px;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.error {
            color: #e50914;
        }

        /* Alert Messages */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert.success {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid #2e7d32;
            color: #4caf50;
        }

        .alert.error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #3a3a3a;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            font-size: 16px;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #3a3a3a;
            color: #aaa;
        }

        .btn-secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        .btn-primary {
            background: #e50914;
            color: #fff;
        }

        .btn-primary:hover {
            background: #f40612;
        }

        .btn-danger {
            background: #ff6b6b;
            color: #fff;
        }

        .btn-danger:hover {
            background: #ff5252;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 16px;
            }
            
            .edit-form {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-section">
                <img src="website/icon.png" alt="ChefTube" class="logo">
                <div class="brand-name">ChefTube</div>
            </div>
            <a href="cc_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="nav-right">
            <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="Profile" class="profile-avatar">
            <span class="profile-name"><?php echo htmlspecialchars($creator_name); ?></span>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Edit Video</h1>
            <p class="page-subtitle">Update your video details</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Video Preview -->
        <div class="video-preview">
            <h3 class="preview-header">Current Video</h3>
            <div style="display: flex; gap: 20px; align-items: start;">
                <div class="video-thumbnail-preview">
                    <?php 
                    $thumbnail_path = "cc/$creator_id/thumbnail/" . $video['thumbnail'];
                    if (file_exists($thumbnail_path) && $video['thumbnail'] != 'default_thumbnail.jpg'): 
                    ?>
                        <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: #666; display: flex; align-items: center; justify-content: center; color: #999;">
                            No Thumbnail
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 style="margin-bottom: 8px;"><?php echo htmlspecialchars($video['title']); ?></h4>
                    <div class="video-stats">
                        <span>👁 <?php echo number_format($video['views']); ?> views</span>
                        <span>👍 <?php echo number_format($video['like']); ?> likes</span>
                        <span>📅 <?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <form class="edit-form" method="POST">
            <!-- Video Details Section -->
            <div class="form-section">
                <h3 class="section-title">Video Information</h3>
                
                <div class="form-group">
                    <label class="form-label" for="title">Video Title</label>
                    <input type="text" id="title" name="title" class="form-input" 
                           placeholder="Enter your video title" maxlength="100" required
                           value="<?php echo htmlspecialchars($video['title']); ?>">
                    <div class="char-counter" id="titleCounter">0 / 100 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Video Description</label>
                    <textarea id="description" name="description" class="form-textarea" 
                              placeholder="Describe your recipe, techniques, and ingredients..." maxlength="1000" required><?php echo htmlspecialchars($video['description']); ?></textarea>
                    <div class="char-counter" id="descCounter">0 / 1000 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tag_id">Category</label>
                    <select id="tag_id" name="tag_id" class="form-select" required>
                        <option value="">Select a category</option>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['tag_id']; ?>" 
                                    <?php echo ($video['tag_id'] == $tag['tag_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="cc_dashboard.php" class="btn btn-secondary">Cancel</a>
                <a href="cc_watch_video.php?id=<?php echo $video['vid_id']; ?>" class="btn btn-secondary">Preview</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </main>

    <script>
        // Character counters
        function updateCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            function updateCounter() {
                const length = input.value.length;
                counter.textContent = `${length} / ${maxLength} characters`;
                
                if (length > maxLength * 0.9) {
                    counter.className = 'char-counter error';
                } else if (length > maxLength * 0.8) {
                    counter.className = 'char-counter warning';
                } else {
                    counter.className = 'char-counter';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter(); // Initial update
        }

        updateCharCounter('title', 'titleCounter', 100);
        updateCharCounter('description', 'descCounter', 1000);

        // Auto-resize textarea
        document.getElementById('description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Form validation
        document.querySelector('.edit-form').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const tagId = document.getElementById('tag_id').value;
            
            let errors = [];
            
            if (!title) errors.push('Video title is required');
            if (title.length > 100) errors.push('Video title must be 100 characters or less');
            if (!description) errors.push('Video description is required');
            if (description.length > 1000) errors.push('Video description must be 1000 characters or less');
            if (!tagId) errors.push('Please select a category');
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
        });
    </script>
</body>
</html>