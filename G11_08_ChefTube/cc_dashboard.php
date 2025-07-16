<?php
// Custom logging function for profile picture debug
function pfp_debug_log($message) {
    $log_file = "C:/xampp/htdocs/cheftube/debug.log";
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] PFP_UPDATE: $message\n", FILE_APPEND | LOCK_EX);
}

// Log every page load
pfp_debug_log("=== CC_DASHBOARD PAGE LOADED ===");
pfp_debug_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
pfp_debug_log("POST data: " . print_r($_POST, true));
pfp_debug_log("FILES data: " . print_r($_FILES, true));

session_start();
require_once 'db_connect.php';

// Check if creator is logged in
if (!isset($_SESSION['creator_id'])) {
    header('Location: cc_login.php');
    exit();
}

$creator_id = $_SESSION['creator_id'];
$creator_name = $_SESSION['creator_name'];
$creator_username = $_SESSION['creator_username'];

pfp_debug_log("Logged in creator: " . $creator_id . " (" . $creator_name . ")");

$success_message = '';
$error_message = '';

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'confirm') {
    session_destroy();
    header('Location: cc_login.php');
    exit();
}

// Handle success message from video deletion
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success_message = "Video deleted successfully!";
}


// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pfp'])) {
    pfp_debug_log("=== PROFILE PICTURE UPDATE (NO GD) ===");
    pfp_debug_log("update_pfp POST value: " . $_POST['update_pfp']);
    pfp_debug_log("FILES new_pfp exists: " . (isset($_FILES['new_pfp']) ? 'YES' : 'NO'));

    if (isset($_FILES['new_pfp'])) {
        pfp_debug_log("Files data: " . print_r($_FILES['new_pfp'], true));

        try {
            $pfp_file = $_FILES['new_pfp'];

            // Basic validation
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 10 * 1024 * 1024; // 10MB

            pfp_debug_log("File upload error code: " . $pfp_file['error']);
            pfp_debug_log("File type: " . $pfp_file['type']);
            pfp_debug_log("File size: " . $pfp_file['size']);
            pfp_debug_log("Temp file: " . $pfp_file['tmp_name']);

            if ($pfp_file['error'] !== 0) {
                throw new Exception("File upload error code: " . $pfp_file['error']);
            }

            if (!in_array($pfp_file['type'], $allowed_types)) {
                throw new Exception("Please upload a valid image file (JPEG, PNG). Current type: " . $pfp_file['type']);
            }

            if ($pfp_file['size'] > $max_size) {
                throw new Exception("File size must be less than 10MB. Current size: " . $pfp_file['size']);
            }

            // Determine file extension
            $file_ext = strtolower(pathinfo($pfp_file['name'], PATHINFO_EXTENSION));

            // Create upload path using absolute path
            $base_dir = "C:/xampp/htdocs/cheftube";
            $creator_id = $_SESSION['creator_id'];
            $upload_dir = $base_dir . "/cc/$creator_id/pfp/";
            pfp_debug_log("Upload directory: " . $upload_dir);

            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create directory: " . $upload_dir);
                }
                pfp_debug_log("Created directory: " . $upload_dir);
            } else {
                pfp_debug_log("Directory already exists: " . $upload_dir);
            }

            $upload_path = $upload_dir . "pfp." . $file_ext;
            pfp_debug_log("Upload path: " . $upload_path);

            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Directory is not writable: " . $upload_dir);
            }
            pfp_debug_log("Directory is writable");

            // Delete old profile picture if it exists
            if (file_exists($upload_path)) {
                if (unlink($upload_path)) {
                    pfp_debug_log("Deleted old profile picture: " . $upload_path);
                } else {
                    pfp_debug_log("Failed to delete old profile picture: " . $upload_path);
                }
            } else {
                pfp_debug_log("No existing profile picture to delete");
            }

            // Move uploaded file to destination
            if (move_uploaded_file($pfp_file['tmp_name'], $upload_path)) {
                pfp_debug_log("Moved uploaded file to destination successfully");

                // Update database with new file name
                $new_filename = "pfp." . $file_ext;
                $stmt = $pdo->prepare("UPDATE creator SET pfp = ? WHERE creator_id = ?");
                if ($stmt->execute([$new_filename, $creator_id])) {
                    pfp_debug_log("Database updated successfully with new pfp filename");

                    pfp_debug_log("=== PROFILE PICTURE UPDATE COMPLETED SUCCESSFULLY ===");
                    $success_message = "Profile picture updated successfully!";
                } else {
                    $error_info = $stmt->errorInfo();
                    throw new Exception("Failed to update database. Error: " . print_r($error_info, true));
                }
            } else {
                throw new Exception("Failed to move uploaded file to " . $upload_path);
            }

        } catch (Exception $e) {
            pfp_debug_log("=== PROFILE PICTURE UPDATE FAILED ===");
            pfp_debug_log("Error: " . $e->getMessage());
            $error_message = "Profile picture update failed: " . $e->getMessage();
        }
    } else {
        pfp_debug_log("ERROR: No file uploaded in FILES array");
        $error_message = "No file was uploaded. Please select an image file.";
    }
}

// Handle video deletion
if (isset($_POST['delete_video']) && isset($_POST['vid_id'])) {
    $vid_id = $_POST['vid_id'];
    
    try {
        // Get video details for file deletion
        $stmt = $pdo->prepare("SELECT video, thumbnail FROM video WHERE vid_id = ? AND creator_id = ?");
        $stmt->execute([$vid_id, $creator_id]);
        $video_data = $stmt->fetch();
        
        if ($video_data) {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM video WHERE vid_id = ? AND creator_id = ?");
            $stmt->execute([$vid_id, $creator_id]);
            
            // Delete video file
            $video_path = "C:/xampp/htdocs/cheftube/cc/$creator_id/video/" . $video_data['video'];
            if (file_exists($video_path)) {
                unlink($video_path);
            }
            
            // Delete thumbnail file
            $thumbnail_path = "C:/xampp/htdocs/cheftube/cc/$creator_id/thumbnail/" . $video_data['thumbnail'];
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
            
            $success_message = "Video deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Failed to delete video. Please try again.";
        error_log("Delete video error: " . $e->getMessage());
    }
}

// Get creator statistics
try {
    // Total videos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_videos FROM video WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $total_videos = $stmt->fetch()['total_videos'];
    
    // Total views
    $stmt = $pdo->prepare("SELECT SUM(views) as total_views FROM video WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $total_views = $stmt->fetch()['total_views'] ?? 0;
    
    // Total likes
    $stmt = $pdo->prepare("SELECT SUM(`like`) as total_likes FROM video WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $total_likes = $stmt->fetch()['total_likes'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Statistics error: " . $e->getMessage());
    $total_videos = $total_views = $total_likes = 0;
}

// Get videos with additional data
// Get videos with additional data
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            t.name as tag_name,
            (SELECT COUNT(*) FROM interaction WHERE vid_id = v.vid_id AND comment IS NOT NULL) as comment_count
        FROM video v
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        WHERE v.creator_id = ?
        ORDER BY v.date_uploaded DESC
    ");
    $stmt->execute([$creator_id]);
    $videos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Videos fetch error: " . $e->getMessage());
    $videos = [];
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png"; // fallback
}

// Current page for navigation highlighting
$current_page = isset($_GET['page']) ? $_GET['page'] : 'videos';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube Creator Dashboard</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #0f0f0f 100%);
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Animated Background Elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(229, 9, 20, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(229, 9, 20, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.02) 0%, transparent 50%);
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(1deg); }
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

        .nav-menu {
            display: flex;
            gap: 30px;
            margin-left: 40px;
        }

        .nav-item {
            color: #aaa;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-item:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .add-video-btn {
            background: #e50914;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-video-btn:hover {
            background: #f40612;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 4px;
            border-radius: 20px;
            transition: background-color 0.2s ease;
            position: relative;
        }

        .profile-section:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .profile-avatar-container {
            position: relative;
            display: inline-block;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3a3a3a;
        }

        .edit-pfp-icon {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 10px;
            color: white;
            transition: all 0.2s ease;
        }

        .profile-avatar-container:hover .edit-pfp-icon {
            display: flex;
        }

        .edit-pfp-icon:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }

        .profile-name {
            font-weight: 500;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Dropdown Menu */
        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: #282828;
            border: 1px solid #3a3a3a;
            border-radius: 8px;
            min-width: 200px;
            box-shadow: 0 4px 32px rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1001;
        }

        .profile-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #3a3a3a;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item.logout {
            color: #ff6b6b;
        }

        /* Main Content */
        .main-content {
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.8s ease;
        }

        .page-header {
            margin-bottom: 32px;
            animation: slideInLeft 0.8s ease;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #fff, #aaa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            animation: slideInRight 0.8s ease;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.8) 0%, rgba(40, 40, 40, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(58, 58, 58, 0.3);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(229, 9, 20, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            border-color: rgba(229, 9, 20, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            animation: pulse 2s ease-in-out infinite;
        }

        .stat-label {
            color: #ccc;
            font-size: 14px;
            font-weight: 500;
        }

        /* Videos Grid */
        .videos-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 28px;
            animation: fadeIn 1s ease 0.3s both;
        }

        @media (min-width: 1200px) {
            .videos-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .video-card {
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.9) 0%, rgba(40, 40, 40, 0.9) 100%);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(58, 58, 58, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            animation: fadeIn 0.6s ease forwards;
            animation-delay: calc(var(--animation-order) * 0.1s);
        }

        .video-card:nth-child(1) { --animation-order: 1; }
        .video-card:nth-child(2) { --animation-order: 2; }
        .video-card:nth-child(3) { --animation-order: 3; }
        .video-card:nth-child(4) { --animation-order: 4; }
        .video-card:nth-child(5) { --animation-order: 5; }
        .video-card:nth-child(6) { --animation-order: 6; }
        .video-card:nth-child(7) { --animation-order: 7; }
        .video-card:nth-child(8) { --animation-order: 8; }

        .video-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.1), rgba(255, 107, 107, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .video-card:hover {
            transform: translateY(-12px) scale(1.02);
            border-color: rgba(229, 9, 20, 0.6);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.4),
                0 0 30px rgba(229, 9, 20, 0.2);
        }

        .video-card:hover::before {
            opacity: 1;
        }

        .video-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(45deg, #333, #555);
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .video-thumbnail img {
            transition: transform 0.4s ease;
        }

        .video-card:hover .video-thumbnail img {
            transform: scale(1.1);
        }

        .video-duration {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            z-index: 2;
        }

        .video-info {
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .video-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.4;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            transition: color 0.3s ease;
        }

        .video-card:hover .video-title {
            color: #e50914;
        }

        .video-stats {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #bbb;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #aaa;
            margin-bottom: 16px;
        }

        .video-tag {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .video-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .action-btn {
            padding: 8px 16px;
            border: 1px solid rgba(58, 58, 58, 0.5);
            background: rgba(30, 30, 30, 0.8);
            backdrop-filter: blur(10px);
            color: #ccc;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(229, 9, 20, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .action-btn:hover {
            border-color: #e50914;
            color: #e50914;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(229, 9, 20, 0.3);
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn.delete {
            border-color: rgba(255, 107, 107, 0.5);
            color: #ff6b6b;
        }

        .action-btn.delete:hover {
            background: #ff6b6b;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #aaa;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #fff;
        }

        .empty-description {
            font-size: 16px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .empty-cta {
            background: #e50914;
            color: #fff;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background-color 0.2s ease;
        }

        .empty-cta:hover {
            background: #f40612;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            opacity: 1;
            transform: translateY(0);
            transition: all 0.5s ease;
            animation: slideInDown 0.5s ease;
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

        .alert.fade-out {
            opacity: 0;
            transform: translateY(-20px);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 5px rgba(229, 9, 20, 0.3); }
            50% { box-shadow: 0 0 20px rgba(229, 9, 20, 0.6); }
        }

        /* Profile Picture Update Modal */
        .pfp-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2500;
        }

        .pfp-modal-content {
            background: #282828;
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .pfp-modal-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #fff;
        }

        .pfp-upload-area {
            border: 2px dashed #3a3a3a;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            margin-bottom: 20px;
        }

        .pfp-upload-area:hover {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.05);
        }

        .pfp-upload-area.dragover {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }

        .pfp-upload-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #666;
        }

        .pfp-upload-text {
            color: #aaa;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .pfp-upload-subtext {
            color: #666;
            font-size: 14px;
        }

        .pfp-file-input {
            display: none;
        }

        .pfp-preview {
            margin-top: 20px;
            display: none;
        }

        .pfp-preview img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e50914;
        }

        .pfp-preview-text {
            color: #4caf50;
            margin-top: 12px;
            font-weight: 500;
        }

        .pfp-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }

        .pfp-modal-btn {
            padding: 12px 24px;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 16px;
        }

        .pfp-modal-btn.primary {
            background: #e50914;
            border-color: #e50914;
            color: #fff;
        }

        .pfp-modal-btn.primary:hover {
            background: #f40612;
        }

        .pfp-modal-btn.primary:disabled {
            background: #666;
            border-color: #666;
            cursor: not-allowed;
        }

        .pfp-modal-btn.secondary {
            background: transparent;
            color: #aaa;
        }

        .pfp-modal-btn.secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        /* Profile Settings Page Styles */
        .profile-settings-container {
            max-width: 600px;
            margin: 0 auto;
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 32px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #3a3a3a;
        }

        .profile-avatar-large-container {
            position: relative;
            display: inline-block;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3a3a3a;
        }

        .edit-pfp-icon-large {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 32px;
            height: 32px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            color: white;
            transition: all 0.2s ease;
        }

        .profile-avatar-large-container:hover .edit-pfp-icon-large {
            display: flex;
        }

        .edit-pfp-icon-large:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }

        .profile-info h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .profile-info p {
            color: #aaa;
            font-size: 16px;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: #282828;
            border-radius: 8px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .modal-text {
            color: #aaa;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 20px;
            border: 1px solid #3a3a3a;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .modal-btn.primary {
            background: #e50914;
            border-color: #e50914;
            color: #fff;
        }

        .modal-btn.primary:hover {
            background: #f40612;
        }

        .modal-btn.secondary {
            background: transparent;
            color: #aaa;
        }

        .modal-btn.secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .videos-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 16px;
            }
            
            .nav-menu {
                margin-left: 20px;
                gap: 20px;
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
            <div class="nav-menu">
                <a href="?page=videos" class="nav-item <?php echo $current_page == 'videos' ? 'active' : ''; ?>">All Videos</a>
                <a href="?page=profile" class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">Profile Settings</a>
            </div>
        </div>
        <div class="nav-right">
            <a href="cc_add_video.php" class="add-video-btn">
                <span>➕</span>
                Add Video
            </a>
            <div class="profile-section" onclick="toggleProfileDropdown()">
                <div class="profile-avatar-container">
                    <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="Profile" class="profile-avatar">
                    <div class="edit-pfp-icon" onclick="event.stopPropagation(); showPfpModal();">
                        ✏️
                    </div>
                </div>
                <span class="profile-name"><?php echo htmlspecialchars($creator_name); ?></span>
                <span>▼</span>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="?page=profile" class="dropdown-item">Profile Settings</a>
                    <a href="cc_profile_edit.php" class="dropdown-item">Edit Profile</a>
                    <a href="#" class="dropdown-item logout" onclick="showLogoutModal()">Sign Out</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($current_page == 'videos'): ?>
            <div class="page-header">
                <h1 class="page-title">Your Videos</h1>
                
                <!-- Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_videos); ?></div>
                        <div class="stat-label">Total Videos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_views); ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($total_likes); ?></div>
                        <div class="stat-label">Total Likes</div>
                    </div>
                </div>
            </div>

            <!-- Videos Grid -->
            <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎬</div>
                    <h2 class="empty-title">Welcome to ChefTube!</h2>
                    <p class="empty-description">
                        Ready to share your culinary expertise? Upload your first cooking video and start building your audience.
                        <br><br>
                        <strong>Tips for new creators:</strong><br>
                        • Start with simple, popular recipes<br>
                        • Ensure good lighting and clear audio<br>
                        • Keep videos engaging and concise<br>
                        • Add detailed descriptions and tags
                    </p>
                    <a href="cc_add_video.php" class="empty-cta">Upload Your First Video</a>
                </div>
            <?php else: ?>
                <div class="videos-container">
                    <?php foreach ($videos as $video): ?>
                        <div class="video-card">
                            <div class="video-thumbnail" onclick="window.location.href='cc_watch_video.php?id=<?php echo $video['vid_id']; ?>'">
                                <?php 
                                $thumbnail_path = "cc/$creator_id/thumbnail/" . $video['thumbnail'];
                                if (file_exists($thumbnail_path) && $video['thumbnail'] != 'default_thumbnail.jpg'): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: #333; display: flex; align-items: center; justify-content: center; color: #666;">
                                        No Thumbnail
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($video['duration']): ?>
                                    <div class="video-duration"><?php echo date('i:s', strtotime($video['duration'])); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                
                                <div class="video-stats">
                                    <span><?php echo number_format($video['views']); ?> views</span>
                                    <span><?php echo number_format($video['like']); ?> likes</span>
                                    <span><?php echo number_format($video['comment_count']); ?> comments</span>
                                </div>
                                
                                <div class="video-meta">
                                    <span><?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?></span>
                                    <?php if ($video['tag_name']): ?>
                                        <span class="video-tag"><?php echo htmlspecialchars($video['tag_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="video-actions">
                                    <a href="cc_video_edit.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">Edit</a>
                                    <a href="cc_video_analytics.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">Analytics</a>
                                    <button class="action-btn delete" onclick="showDeleteModal('<?php echo $video['vid_id']; ?>', '<?php echo htmlspecialchars($video['title']); ?>')">Delete</button>
                                    <a href="cc_watch_video.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">Watch</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($current_page == 'profile'): ?>
            <div class="page-header">
                <h1 class="page-title">Profile Settings</h1>
            </div>
            
            <div class="profile-settings-container">
                <div class="profile-header">
                    <div class="profile-avatar-large-container">
                        <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="Profile" class="profile-avatar-large">
                        <div class="edit-pfp-icon-large" onclick="showPfpModal();">
                            ✏️
                        </div>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($creator_name); ?></h2>
                        <p>@<?php echo htmlspecialchars($creator_username); ?></p>
                        <p style="margin-top: 8px; color: #666;">Creator ID: <?php echo htmlspecialchars($creator_id); ?></p>
                    </div>
                </div>
                
                <div style="text-align: center; color: #aaa; padding: 40px;">
                    <p>Additional profile settings will be available here soon...</p>
                    <a href="cc_profile_edit.php" class="empty-cta" style="margin-top: 20px; display: inline-block;">Edit Profile Details</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Profile Picture Update Modal -->
    <div class="pfp-modal" id="pfpModal">
        <div class="pfp-modal-content">
            <h3 class="pfp-modal-title">Update Profile Picture</h3>
            
            <form method="POST" enctype="multipart/form-data" id="pfpForm">
                <div class="pfp-upload-area" onclick="document.getElementById('pfpInput').click()">
                    <div class="pfp-upload-icon">📷</div>
                    <div class="pfp-upload-text">Click to select or drag & drop your new profile picture</div>
                    <div class="pfp-upload-subtext">Supports JPEG, PNG, GIF, WebP (Max 10MB)</div>
                    <input type="file" id="pfpInput" name="new_pfp" class="pfp-file-input" accept="image/*" required>
                </div>
                
                <div class="pfp-preview" id="pfpPreview">
                    <img id="pfpPreviewImg" src="" alt="Preview">
                    <div class="pfp-preview-text">New profile picture preview</div>
                </div>
                
                <div class="pfp-modal-actions">
                    <button type="button" class="pfp-modal-btn secondary" onclick="hidePfpModal()">Cancel</button>
                    <button type="submit" name="update_pfp" value="1" class="pfp-modal-btn primary" id="pfpSubmitBtn" disabled>Update Picture</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-content">
            <h3 class="modal-title">Sign Out</h3>
            <p class="modal-text">Are you sure you want to sign out of your ChefTube creator account?</p>
            <div class="modal-actions">
                <button class="modal-btn secondary" onclick="hideLogoutModal()">Cancel</button>
                <button class="modal-btn primary" onclick="confirmLogout()">Sign Out</button>
            </div>
        </div>
    </div>

    <!-- Delete Video Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Delete Video</h3>
            <p class="modal-text">Are you sure you want to delete "<span id="deleteVideoTitle"></span>"? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="modal-btn secondary" onclick="hideDeleteModal()">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="vid_id" id="deleteVideoId">
                    <button type="submit" name="delete_video" class="modal-btn primary">Delete Video</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Profile dropdown functions
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        document.addEventListener('click', function(event) {
            const profileSection = document.querySelector('.profile-section');
            const dropdown = document.getElementById('profileDropdown');
            
            if (!profileSection.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Profile Picture Modal functions
        function showPfpModal() {
            document.getElementById('pfpModal').style.display = 'flex';
        }

        function hidePfpModal() {
            document.getElementById('pfpModal').style.display = 'none';
            resetPfpForm();
        }

        function resetPfpForm() {
            document.getElementById('pfpForm').reset();
            document.getElementById('pfpPreview').style.display = 'none';
            document.getElementById('pfpSubmitBtn').disabled = true;
        }

        // File upload handling for profile picture
        document.getElementById('pfpInput').addEventListener('change', function() {
            handlePfpFile(this.files[0]);
        });

        function handlePfpFile(file) {
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG, PNG, GIF, WebP).');
                resetPfpForm();
                return;
            }
            
            // Validate file size (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 10MB.');
                resetPfpForm();
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('pfpPreviewImg').src = e.target.result;
                document.getElementById('pfpPreview').style.display = 'block';
                document.getElementById('pfpSubmitBtn').disabled = false;
            };
            reader.readAsDataURL(file);
        }

        // Drag and drop functionality for profile picture
        const pfpUploadArea = document.querySelector('.pfp-upload-area');

        pfpUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        pfpUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        pfpUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('pfpInput').files = files;
                handlePfpFile(files[0]);
            }
        });

        // Form submission handling
        document.getElementById('pfpForm').addEventListener('submit', function(e) {
            pfp_debug_log("=== JAVASCRIPT FORM SUBMISSION STARTED ===");
            
            const submitBtn = document.getElementById('pfpSubmitBtn');
            const fileInput = document.getElementById('pfpInput');
            
            // Check if file is selected
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select an image file before submitting.');
                return false;
            }
            
            // Log form data for debugging
            console.log('Form submission - File selected:', fileInput.files[0].name);
            console.log('Submit button name:', submitBtn.name);
            console.log('Submit button value:', submitBtn.value);
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
            
            // Allow form to submit normally
            return true;
        });

        // Logout modal functions
        function showLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
            document.getElementById('profileDropdown').classList.remove('show');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }

        function confirmLogout() {
            window.location.href = '?logout=confirm';
        }

        // Delete modal functions
        function showDeleteModal(videoId, videoTitle) {
            document.getElementById('deleteVideoTitle').textContent = videoTitle;
            document.getElementById('deleteVideoId').value = videoId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideLogoutModal();
                hideDeleteModal();
                hidePfpModal();
            }
        });

        // Close modals when clicking outside
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

        document.getElementById('pfpModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePfpModal();
            }
        });
    </script>
</body>
</html>