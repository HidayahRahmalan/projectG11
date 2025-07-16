<?php
// Debug logging function
function debug_log($message) {
    $log_file = "C:/xampp/htdocs/cheftube/debug.log";
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] USER_PLAY_VIDEO: $message\n", FILE_APPEND | LOCK_EX);
}

debug_log("=== USER PLAY VIDEO PAGE LOADED ===");
debug_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
debug_log("POST data: " . print_r($_POST, true));

session_start();
require_once 'db_connect.php';

// Get video ID from URL
if (!isset($_GET['id'])) {
    debug_log("Error: No video ID provided");
    header('Location: index.php');
    exit();
}

$video_id = $_GET['id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_logged_in = $user_id !== null;

debug_log("Video ID: $video_id");
debug_log("User logged in: " . ($is_logged_in ? "Yes (ID: $user_id)" : "No"));

$error_message = '';
$success_message = '';

// Handle voice command logging
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['log_voice_command'])) {
    debug_log("=== VOICE COMMAND LOGGING ===");
    
    $command = trim($_POST['command'] ?? '');
    $recognized_text = trim($_POST['recognized_text'] ?? '');
    $success = isset($_POST['success']) ? (bool)$_POST['success'] : true;
    
    debug_log("Voice command: $command, Recognized: $recognized_text, Success: " . ($success ? 'YES' : 'NO'));
    
    if (!empty($command) && !empty($recognized_text)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO voice_commands (user_id, vid_id, command, recognized_text, success, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $video_id, $command, $recognized_text, $success]);
            debug_log("Voice command logged successfully");
            
            // Return JSON response for AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                exit();
            }
        } catch (PDOException $e) {
            debug_log("Voice command logging error: " . $e->getMessage());
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit();
            }
        }
    }
}

// Handle like button
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_video'])) {
    debug_log("=== LIKE VIDEO ATTEMPT ===");
    debug_log("POST like_video value: " . $_POST['like_video']);
    
    if (!$is_logged_in) {
        $error_message = "Please sign in to like videos.";
        debug_log("Like failed: User not logged in");
    } else {
        try {
            debug_log("Starting like process for user $user_id and video $video_id");
            
            // First, let's verify the video exists
            $stmt = $pdo->prepare("SELECT vid_id FROM video WHERE vid_id = ?");
            $stmt->execute([$video_id]);
            $video_exists = $stmt->fetch();
            
            if (!$video_exists) {
                debug_log("ERROR: Video does not exist: $video_id");
                $error_message = "Video not found.";
            } else {
                debug_log("Video exists, proceeding with like operation");
                
                // Check if user already has interaction with this video
                $stmt = $pdo->prepare("SELECT `like` FROM interaction WHERE user_id = ? AND vid_id = ?");
                $stmt->execute([$user_id, $video_id]);
                $existing_interaction = $stmt->fetch();
                
                debug_log("Checking existing interaction for user $user_id and video $video_id");
                debug_log("Existing interaction result: " . print_r($existing_interaction, true));
                
                if ($existing_interaction) {
                    // User already has an interaction record
                    if ($existing_interaction['like'] == 0) {
                        // Update existing record to like
                        debug_log("Updating existing interaction to like");
                        $stmt = $pdo->prepare("UPDATE interaction SET `like` = 1 WHERE user_id = ? AND vid_id = ?");
                        $update_result = $stmt->execute([$user_id, $video_id]);
                        debug_log("Update result: " . ($update_result ? 'SUCCESS' : 'FAILED'));
                        debug_log("Rows affected: " . $stmt->rowCount());
                        
                        if ($update_result && $stmt->rowCount() > 0) {
                            // Increment like count in video table
                            $stmt = $pdo->prepare("UPDATE video SET `like` = `like` + 1 WHERE vid_id = ?");
                            $video_update_result = $stmt->execute([$video_id]);
                            debug_log("Video like count update result: " . ($video_update_result ? 'SUCCESS' : 'FAILED'));
                            
                            $success_message = "Video liked!";
                            debug_log("Like added: Updated existing interaction");
                        } else {
                            $error_message = "Failed to like video.";
                            debug_log("Failed to update existing interaction");
                        }
                    } else {
                        $error_message = "You have already liked this video.";
                        debug_log("Like failed: Already liked");
                    }
                } else {
                    // Create new interaction record
                    debug_log("Creating new interaction record with like");
                    $stmt = $pdo->prepare("INSERT INTO interaction (user_id, vid_id, `like`, date_comment) VALUES (?, ?, 1, CURDATE())");
                    $insert_result = $stmt->execute([$user_id, $video_id]);
                    debug_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
                    debug_log("Last insert ID or affected rows: " . $pdo->lastInsertId());
                    
                    if ($insert_result) {
                        // Verify the insert actually worked
                        $stmt = $pdo->prepare("SELECT * FROM interaction WHERE user_id = ? AND vid_id = ?");
                        $stmt->execute([$user_id, $video_id]);
                        $new_interaction = $stmt->fetch();
                        debug_log("Verification - New interaction: " . print_r($new_interaction, true));
                        
                        if ($new_interaction) {
                            // Increment like count in video table
                            $stmt = $pdo->prepare("UPDATE video SET `like` = `like` + 1 WHERE vid_id = ?");
                            $video_update_result = $stmt->execute([$video_id]);
                            debug_log("Video like count update result: " . ($video_update_result ? 'SUCCESS' : 'FAILED'));
                            
                            $success_message = "Video liked!";
                            debug_log("Like added: Created new interaction successfully");
                        } else {
                            $error_message = "Failed to create interaction record.";
                            debug_log("Insert appeared successful but record not found");
                        }
                    } else {
                        $error_message = "Failed to like video.";
                        debug_log("Failed to create new interaction");
                        debug_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
                    }
                }
            }
        } catch (PDOException $e) {
            debug_log("Like error: " . $e->getMessage());
            debug_log("SQL State: " . $e->getCode());
            debug_log("Error Info: " . print_r($e->errorInfo ?? [], true));
            $error_message = "Error liking video: " . $e->getMessage();
        }
    }
    debug_log("=== LIKE VIDEO ATTEMPT COMPLETED ===");
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    debug_log("=== COMMENT SUBMISSION ATTEMPT ===");
    debug_log("POST submit_comment value: " . $_POST['submit_comment']);
    
    if (!$is_logged_in) {
        $error_message = "Please sign in to comment.";
        debug_log("Comment failed: User not logged in");
    } else {
        $comment_text = trim($_POST['comment_text'] ?? '');
        debug_log("Comment text: '" . $comment_text . "'");
        
        if (empty($comment_text)) {
            $error_message = "Please enter a comment.";
            debug_log("Comment failed: Empty comment");
        } else {
            try {
                debug_log("Starting comment process for user $user_id and video $video_id");
                
                // First, let's verify the video exists
                $stmt = $pdo->prepare("SELECT vid_id FROM video WHERE vid_id = ?");
                $stmt->execute([$video_id]);
                $video_exists = $stmt->fetch();
                
                if (!$video_exists) {
                    debug_log("ERROR: Video does not exist for comment: $video_id");
                    $error_message = "Video not found.";
                } else {
                    debug_log("Video exists, proceeding with comment operation");
                    
                    // Check if user already has an interaction record
                    $stmt = $pdo->prepare("SELECT * FROM interaction WHERE user_id = ? AND vid_id = ?");
                    $stmt->execute([$user_id, $video_id]);
                    $existing_interaction = $stmt->fetch();
                    
                    debug_log("Comment submission - existing interaction: " . print_r($existing_interaction, true));
                    
                    if ($existing_interaction) {
                        // Update existing record with comment
                        debug_log("Updating existing interaction with comment");
                        $stmt = $pdo->prepare("UPDATE interaction SET comment = ?, date_comment = CURDATE() WHERE user_id = ? AND vid_id = ?");
                        $update_result = $stmt->execute([$comment_text, $user_id, $video_id]);
                        debug_log("Comment update result: " . ($update_result ? 'SUCCESS' : 'FAILED'));
                        debug_log("Rows affected: " . $stmt->rowCount());
                        
                        if ($update_result && $stmt->rowCount() > 0) {
                            $success_message = "Comment added successfully!";
                            debug_log("Comment added: Updated existing interaction");
                        } else {
                            $error_message = "Failed to add comment.";
                            debug_log("Failed to update existing interaction with comment");
                        }
                    } else {
                        // Create new interaction record with comment only
                        debug_log("Creating new interaction record with comment only");
                        $stmt = $pdo->prepare("INSERT INTO interaction (user_id, vid_id, `like`, comment, date_comment) VALUES (?, ?, 0, ?, CURDATE())");
                        $insert_result = $stmt->execute([$user_id, $video_id, $comment_text]);
                        debug_log("Comment insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
                        
                        if ($insert_result) {
                            // Verify the insert actually worked
                            $stmt = $pdo->prepare("SELECT * FROM interaction WHERE user_id = ? AND vid_id = ?");
                            $stmt->execute([$user_id, $video_id]);
                            $new_interaction = $stmt->fetch();
                            debug_log("Verification - New comment interaction: " . print_r($new_interaction, true));
                            
                            if ($new_interaction) {
                                $success_message = "Comment added successfully!";
                                debug_log("Comment added: Created new interaction successfully");
                            } else {
                                $error_message = "Failed to create comment record.";
                                debug_log("Insert appeared successful but record not found");
                            }
                        } else {
                            $error_message = "Failed to add comment.";
                            debug_log("Failed to create new interaction with comment");
                            debug_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
                        }
                    }
                }
            } catch (PDOException $e) {
                debug_log("Comment error: " . $e->getMessage());
                debug_log("SQL State: " . $e->getCode());
                debug_log("Error Info: " . print_r($e->errorInfo ?? [], true));
                $error_message = "Error adding comment: " . $e->getMessage();
            }
        }
    }
    debug_log("=== COMMENT SUBMISSION ATTEMPT COMPLETED ===");
}

// Increment view count (only once per session per video)
$view_session_key = "viewed_video_$video_id";
if (!isset($_SESSION[$view_session_key])) {
    try {
        $stmt = $pdo->prepare("UPDATE video SET views = views + 1 WHERE vid_id = ?");
        $stmt->execute([$video_id]);
        $_SESSION[$view_session_key] = true;
        debug_log("View count incremented");
    } catch (PDOException $e) {
        debug_log("View increment error: " . $e->getMessage());
    }
}

// Get video details (with fresh data after potential like updates)
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            c.name as creator_name,
            c.username as creator_username,
            t.name as tag_name
        FROM video v
        LEFT JOIN creator c ON v.creator_id = c.creator_id
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        WHERE v.vid_id = ?
    ");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        debug_log("Error: Video not found in database");
        header('Location: index.php');
        exit();
    }
    
    debug_log("Video found: " . $video['title'] . " (Likes: " . $video['like'] . ")");
} catch (PDOException $e) {
    debug_log("Video fetch error: " . $e->getMessage());
    header('Location: index.php');
    exit();
}

// Get comments for this video (fresh data)
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.comment,
            i.date_comment,
            u.username,
            u.user_id
        FROM interaction i
        LEFT JOIN user u ON i.user_id = u.user_id
        WHERE i.vid_id = ? AND i.comment IS NOT NULL AND i.comment != ''
        ORDER BY i.date_comment DESC
    ");
    $stmt->execute([$video_id]);
    $comments = $stmt->fetchAll();
    
    debug_log("Comments loaded: " . count($comments) . " comments found");
} catch (PDOException $e) {
    debug_log("Comments fetch error: " . $e->getMessage());
    $comments = [];
}

// Get all other videos for sidebar
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            c.name as creator_name,
            t.name as tag_name
        FROM video v
        LEFT JOIN creator c ON v.creator_id = c.creator_id
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        WHERE v.vid_id != ?
        ORDER BY v.date_uploaded DESC
        LIMIT 20
    ");
    $stmt->execute([$video_id]);
    $sidebar_videos = $stmt->fetchAll();
    
    debug_log("Sidebar videos loaded: " . count($sidebar_videos) . " videos");
} catch (PDOException $e) {
    debug_log("Sidebar videos fetch error: " . $e->getMessage());
    $sidebar_videos = [];
}

// Check if current user has liked this video (fresh data)
$user_has_liked = false;
if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT `like` FROM interaction WHERE user_id = ? AND vid_id = ?");
        $stmt->execute([$user_id, $video_id]);
        $user_interaction = $stmt->fetch();
        $user_has_liked = $user_interaction && $user_interaction['like'] == 1;
        debug_log("User has liked check: " . ($user_has_liked ? 'YES' : 'NO'));
    } catch (PDOException $e) {
        debug_log("User like check error: " . $e->getMessage());
    }
}

// Debug: Let's check what's in the interaction table for this video
try {
    $stmt = $pdo->prepare("SELECT * FROM interaction WHERE vid_id = ?");
    $stmt->execute([$video_id]);
    $all_interactions = $stmt->fetchAll();
    debug_log("All interactions for video $video_id: " . print_r($all_interactions, true));
    
    // Also check if our specific user has any interactions at all
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM interaction WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_interactions = $stmt->fetchAll();
        debug_log("All interactions for user $user_id: " . print_r($user_interactions, true));
    }
} catch (PDOException $e) {
    debug_log("Debug interaction fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - ChefTube Voice Control</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f23 100%);
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }

        .brand-name {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-btn {
            margin-left: 30px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        /* Main Content */
        .main-content {
            display: flex;
            gap: 30px;
            padding: 30px;
        }

        .video-section {
            flex: 1;
        }

        /* Video Player */
        .video-player-container {
            width: 100%;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .video-js {
            width: 100%;
            height: 600px;
            border-radius: 20px;
        }

        /* Voice Control Panel Styles */
        .voice-control-panel {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 20px;
            padding: 20px;
            min-width: 300px;
            z-index: 2000;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        .voice-control-panel.continuous-mode {
            border-color: rgba(76, 175, 80, 0.8);
            box-shadow: 0 0 30px rgba(76, 175, 80, 0.3);
            background: rgba(0, 50, 0, 0.9);
        }

        .voice-control-panel.listening {
            border-color: rgba(76, 175, 80, 0.8);
            box-shadow: 0 0 30px rgba(76, 175, 80, 0.3);
        }

        .voice-control-panel.processing {
            border-color: rgba(255, 193, 7, 0.8);
            box-shadow: 0 0 30px rgba(255, 193, 7, 0.3);
        }

        .voice-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .voice-title {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
        }

        .voice-title.continuous {
            color: #4caf50;
        }

        .voice-toggle {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .voice-toggle:hover {
            transform: scale(1.1);
        }

        .voice-toggle.continuous {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            animation: pulse 1.5s infinite;
        }

        .voice-toggle.listening {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            animation: pulse 1.5s infinite;
        }

        .voice-toggle.processing {
            background: linear-gradient(45deg, #ff9800, #ffb74d);
            animation: spin 1s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .voice-status {
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
            min-height: 20px;
        }

        .voice-status.ready {
            color: #aaa;
        }

        .voice-status.continuous {
            color: #4caf50;
            font-weight: 600;
        }

        .voice-status.listening {
            color: #4caf50;
            font-weight: 600;
        }

        .voice-status.processing {
            color: #ff9800;
            font-weight: 600;
        }

        .voice-status.success {
            color: #4caf50;
            font-weight: 600;
        }

        .voice-status.error {
            color: #f44336;
            font-weight: 600;
        }

        .voice-commands-list {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .voice-commands-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #ff6b6b;
        }

        .voice-command-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .voice-command-item:last-child {
            border-bottom: none;
        }

        .command-text {
            color: #4caf50;
            font-weight: 500;
        }

        .command-action {
            color: #aaa;
        }

        .voice-feedback {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            border-radius: 10px;
            padding: 10px;
            margin-top: 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: #4caf50;
            display: none;
            animation: fadeInOut 3s ease;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0; transform: translateY(10px); }
            20%, 80% { opacity: 1; transform: translateY(0); }
        }

        .voice-stats {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 15px;
            font-size: 12px;
            color: #aaa;
        }

        /* Minimize button */
        .voice-minimize {
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            border-radius: 3px;
            transition: color 0.3s ease;
        }

        .voice-minimize:hover {
            color: #fff;
        }

        .voice-control-panel.minimized {
            transform: translateX(calc(100% - 70px));
        }

        .voice-control-panel.minimized .voice-panel-content {
            display: none;
        }

        /* Voice Command Overlay */
        .voice-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 10px 15px;
            display: none;
            z-index: 1000;
            animation: slideInLeft 0.5s ease;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .voice-overlay.show {
            display: block;
        }

        .voice-overlay-text {
            color: #4caf50;
            font-size: 14px;
            font-weight: 600;
        }

        /* Continuous Mode Indicator */
        .continuous-indicator {
            position: fixed;
            top: 80px;
            right: 30px;
            background: rgba(76, 175, 80, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            z-index: 1999;
            display: none;
            animation: slideInRight 0.5s ease;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .continuous-indicator.show {
            display: block;
        }

        /* Floating voice button when minimized */
        .voice-float-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            z-index: 2001;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
            transition: all 0.3s ease;
        }

        .voice-float-btn:hover {
            transform: scale(1.1);
        }

        .voice-float-btn.continuous {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            animation: pulse 1.5s infinite;
        }

        /* Video Info */
        .video-info {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
        }

        .video-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
            gap: 15px;
        }

        .video-stats {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .video-actions {
            display: flex;
            gap: 15px;
        }

        .like-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }

        .like-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .like-btn.liked {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border-color: transparent;
        }

        .like-btn:disabled {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
        }

        .creator-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
        }

        .creator-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .creator-details h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .creator-details a {
            color: #ffffff;
            text-decoration: none;
        }

        .creator-details a:hover {
            color: #ff6b6b;
        }

        .creator-meta {
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
        }

        .video-description {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            line-height: 1.6;
            white-space: pre-wrap;
            border-left: 4px solid rgba(229, 9, 20, 0.5);
        }

        /* Comments Section */
        .comments-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
        }

        .comments-header {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .comment-form {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .comment-textarea {
            width: 100%;
            min-height: 100px;
            padding: 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            font-family: inherit;
            font-size: 16px;
            resize: vertical;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .comment-textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .comment-textarea:focus {
            outline: none;
            border-color: rgba(229, 9, 20, 0.5);
            background: rgba(255, 255, 255, 0.08);
        }

        .comment-submit {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .comment-submit:hover {
            background: linear-gradient(45deg, #ff6b6b, #e50914);
            transform: translateY(-2px);
        }

        .login-prompt {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            padding: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
        }

        .login-prompt a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
        }

        .comment-item {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: #ff6b6b;
        }

        .comment-date {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
        }

        .comment-text {
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Sidebar */
        .sidebar {
            width: 400px;
            flex-shrink: 0;
        }

        .sidebar-header {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .sidebar-video {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-video:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .sidebar-thumbnail {
            width: 180px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .sidebar-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-title {
            font-size: 15px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 8px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .sidebar-meta {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .alert.success {
            background: rgba(76, 175, 80, 0.2);
            border-color: rgba(76, 175, 80, 0.5);
            color: #a5d6a7;
        }

        .alert.error {
            background: rgba(244, 67, 54, 0.2);
            border-color: rgba(244, 67, 54, 0.5);
            color: #ffcdd2;
        }

        .tag-badge {
            display: inline-block;
            background: rgba(229, 9, 20, 0.2);
            color: #ff6b6b;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(229, 9, 20, 0.3);
            margin-left: 10px;
        }

        /* Debug info */
        .debug-info {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: #00ff00;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-width: 300px;
            z-index: 2000;
            display: none;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .voice-control-panel {
                bottom: 20px;
                right: 20px;
                left: 20px;
                min-width: auto;
                max-width: calc(100vw - 40px);
            }
            
            .voice-float-btn {
                bottom: 20px;
                right: 20px;
            }

            .main-content {
                flex-direction: column;
                padding: 20px;
            }
            .sidebar {
                width: 100%;
            }
            
            .video-js {
                height: 300px;
            }
            
            .video-title {
                font-size: 22px;
            }
            
            .video-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header {
                padding: 0 15px;
            }
            
            .back-btn {
                margin-left: 15px;
                padding: 8px 15px;
                font-size: 14px;
            }
        }

        @media (max-width: 1400px) {
            .main-content {
                flex-direction: column;
                padding: 20px;
            }
            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Debug Info (hidden by default) -->
    <div class="debug-info" id="debugInfo">
        Video ID: <?php echo $video_id; ?><br>
        User ID: <?php echo $user_id; ?><br>
        User Liked: <?php echo $user_has_liked ? 'YES' : 'NO'; ?><br>
        Video Likes: <?php echo $video['like']; ?><br>
        Comments: <?php echo count($comments); ?>
    </div>

    <!-- Continuous Mode Indicator -->
    <div class="continuous-indicator" id="continuousIndicator">
        🎤 Continuous Voice Control Active - Press 'V' or 'Esc' to stop
    </div>

    <!-- Header -->
    <header class="header">
        <a href="index.php" class="logo-section">
            <img src="website/icon.png" alt="ChefTube" class="logo">
            <div class="brand-name">ChefTube</div>
        </a>
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="video-section">
            <!-- Video Player -->
            <div class="video-player-container">
                <!-- Voice Command Overlay -->
                <div class="voice-overlay" id="voiceOverlay">
                    <div class="voice-overlay-text" id="voiceOverlayText">Voice command recognized!</div>
                </div>
                
                <video
                    id="videoPlayer"
                    class="video-js vjs-default-skin"
                    controls
                    preload="auto"
                    data-setup="{}">
                    <p class="vjs-no-js">
                        To view this video please enable JavaScript, and consider upgrading to a web browser that
                        <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                    </p>
                </video>
            </div>

            <!-- Video Info -->
            <div class="video-info">
                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <h1 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h1>
                
                <div class="video-meta">
                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?> views</span>
                        <span>•</span>
                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?></span>
                        <?php if ($video['tag_name']): ?>
                            <span class="tag-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($video['tag_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="video-actions">
                        <?php if ($is_logged_in): ?>
                            <form method="POST" style="display: inline;" onsubmit="console.log('Form submitting:', this);">
                                <button type="submit" name="like_video" value="1" class="like-btn <?php echo $user_has_liked ? 'liked' : ''; ?>" 
                                        <?php echo $user_has_liked ? 'disabled' : ''; ?>
                                        onclick="console.log('Like button clicked');">
                                    <i class="fas fa-thumbs-up"></i>
                                    <?php echo number_format($video['like']); ?>
                                    <?php echo $user_has_liked ? ' (Liked)' : ' Like'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="user_login.php" class="like-btn">
                                <i class="fas fa-thumbs-up"></i>
                                <?php echo number_format($video['like']); ?> (Sign in to like)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="creator-info">
                    <?php 
                    $creator_pfp_path = "cc/" . $video['creator_id'] . "/pfp/pfp.png";
                    if (file_exists($creator_pfp_path)): 
                    ?>
                        <img src="<?php echo htmlspecialchars($creator_pfp_path); ?>" alt="<?php echo htmlspecialchars($video['creator_name']); ?>" class="creator-avatar">
                    <?php else: ?>
                        <div class="creator-avatar" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #e50914, #ff6b6b); color: white; font-weight: 600; font-size: 20px;">
                            <?php echo strtoupper(substr($video['creator_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="creator-details">
                        <h3><a href="creator_channel.php?id=<?php echo $video['creator_id']; ?>"><?php echo htmlspecialchars($video['creator_name']); ?></a></h3>
                        <div class="creator-meta">@<?php echo htmlspecialchars($video['creator_username']); ?></div>
                    </div>
                </div>

                <div class="video-description">
                    <?php echo nl2br(htmlspecialchars($video['description'])); ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="comments-section">
                <h3 class="comments-header">
                    <i class="fas fa-comments"></i> <?php echo count($comments); ?> Comments
                </h3>
                
                <!-- Comment Form -->
                <?php if ($is_logged_in): ?>
                    <form method="POST" class="comment-form" onsubmit="console.log('Comment form submitting:', this);">
                        <textarea name="comment_text" class="comment-textarea" placeholder="Add a comment..." required></textarea>
                        <button type="submit" name="submit_comment" value="1" class="comment-submit" onclick="console.log('Comment button clicked');">
                            <i class="fas fa-paper-plane"></i> Comment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="login-prompt">
                        <i class="fas fa-sign-in-alt" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                        <a href="user_login.php">Sign in</a> to add a comment
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <div style="text-align: center; color: rgba(255,255,255,0.6); padding: 50px; background: rgba(255,255,255,0.05); border-radius: 15px;">
                            <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <div style="font-size: 18px;">No comments yet. Be the first to comment!</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <i class="fas fa-user-circle" style="color: #ff6b6b;"></i>
                                    <span class="comment-author"><?php echo htmlspecialchars($comment['username'] ?? 'Unknown User'); ?></span>
                                    <span class="comment-date"><?php echo date('M j, Y', strtotime($comment['date_comment'])); ?></span>
                                </div>
                                <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <h3 class="sidebar-header">
                <i class="fas fa-play-circle"></i> More Videos
            </h3>
            
            <?php if (empty($sidebar_videos)): ?>
                <div style="text-align: center; color: rgba(255,255,255,0.6); padding: 30px; background: rgba(255,255,255,0.05); border-radius: 15px;">
                    <i class="fas fa-video-slash" style="font-size: 36px; margin-bottom: 10px; opacity: 0.3;"></i>
                    <div>No other videos available</div>
                </div>
            <?php else: ?>
                <?php foreach ($sidebar_videos as $sidebar_video): ?>
                    <div class="sidebar-video" onclick="window.location.href='user_play_video.php?id=<?php echo $sidebar_video['vid_id']; ?>'">
                        <div class="sidebar-thumbnail">
                            <?php 
                            $sidebar_thumbnail_path = "cc/" . $sidebar_video['creator_id'] . "/thumbnail/" . $sidebar_video['thumbnail'];
                            if (file_exists($sidebar_thumbnail_path) && $sidebar_video['thumbnail'] != 'default_thumbnail.jpg'): 
                            ?>
                                <img src="<?php echo htmlspecialchars($sidebar_thumbnail_path); ?>" alt="<?php echo htmlspecialchars($sidebar_video['title']); ?>">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(45deg, #1a1a2e, #16213e); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                                    <i class="fas fa-play" style="font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="sidebar-info">
                            <div class="sidebar-title"><?php echo htmlspecialchars($sidebar_video['title']); ?></div>
                            <div class="sidebar-meta">
                                <div><?php echo htmlspecialchars($sidebar_video['creator_name']); ?></div>
                                <div>
                                    <i class="fas fa-eye"></i> <?php echo number_format($sidebar_video['views']); ?> views
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Voice Control Panel -->
    <div class="voice-control-panel" id="voicePanel">
        <div class="voice-panel-header">
            <div class="voice-title" id="voiceTitle">🎤 Voice Control</div>
            <button class="voice-minimize" onclick="toggleVoicePanel()" title="Minimize">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        
        <div class="voice-panel-content">
            <div style="text-align: center; margin-bottom: 15px;">
                <button class="voice-toggle" id="voiceToggle" onclick="toggleVoiceRecognition()">
                    <i class="fas fa-microphone" id="voiceIcon"></i>
                </button>
            </div>
            
            <div class="voice-status" id="voiceStatus">Press 'V' to start continuous voice control</div>
            
            <div class="voice-feedback" id="voiceFeedback"></div>
            
            <div class="voice-commands-list">
                <div class="voice-commands-title">Available Commands:</div>
                <div class="voice-command-item">
                    <span class="command-text">"Play video"</span>
                    <span class="command-action">▶️ Play</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Pause video"</span>
                    <span class="command-action">⏸️ Pause</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Volume up"</span>
                    <span class="command-action">🔊 +Volume</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Volume down"</span>
                    <span class="command-action">🔉 -Volume</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Mute"</span>
                    <span class="command-action">🔇 Mute</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Stop mute"</span>
                    <span class="command-action">🔊 Unmute</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Full screen"</span>
                    <span class="command-action">⛶ Fullscreen</span>
                </div>
                <div class="voice-command-item">
                    <span class="command-text">"Restart video"</span>
                    <span class="command-action">🔄 Restart</span>
                </div>
            </div>
            
            <div class="voice-stats">
                <span>Commands: <span id="commandCount">0</span></span>
                <span>Success: <span id="successRate">0%</span></span>
            </div>
        </div>
    </div>

    <!-- Floating Voice Button (shown when minimized) -->
    <button class="voice-float-btn" id="voiceFloatBtn" onclick="toggleVoicePanel()">
        <i class="fas fa-microphone"></i>
    </button>

    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    <script>
        console.log('Page loaded - Debug info:');
        console.log('Video ID:', '<?php echo $video_id; ?>');
        console.log('User ID:', '<?php echo $user_id; ?>');
        console.log('Is logged in:', <?php echo $is_logged_in ? 'true' : 'false'; ?>);
        console.log('User has liked:', <?php echo $user_has_liked ? 'true' : 'false'; ?>);

        // Toggle debug info with Ctrl+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                const debugInfo = document.getElementById('debugInfo');
                debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
            }
        });

        // Enhanced Voice Control System with Fixed Fullscreen and Continuous Mode
        class VoiceControl {
            constructor() {
                this.recognition = null;
                this.isListening = false;
                this.isContinuousMode = false;
                this.player = null;
                this.commandCount = 0;
                this.successCount = 0;
                this.isSupported = false;
                this.restartTimeout = null;
                this.currentFullscreenState = false;
                
                this.initializeVoiceRecognition();
                this.setupUI();
                this.setupFullscreenListeners();
                
                // FIXED: Voice commands mapping (changed "unmute" to "stop mute")
                this.commands = {
                    // Playback controls
                    'play': ['play', 'play video', 'start', 'start video', 'begin'],
                    'pause': ['pause', 'pause video', 'halt'],
                    'restart': ['restart', 'restart video', 'start over', 'begin again', 'replay'],
                    
                    // Volume controls
                    'volume_up': ['volume up', 'louder', 'increase volume', 'turn up'],
                    'volume_down': ['volume down', 'quieter', 'decrease volume', 'turn down'],
                    'mute': ['mute', 'silence', 'quiet', 'mute video'],
                    'unmute': ['stop mute', 'unmute', 'sound on', 'audio on', 'turn sound on'], // FIXED: "stop mute" added as primary
                    
                    // Navigation
                    'fullscreen': ['full screen', 'fullscreen', 'expand', 'maximize'],
                    'exit_fullscreen': ['exit full screen', 'exit fullscreen', 'minimize', 'normal screen'],
                    
                    // Speed controls
                    'speed_up': ['speed up', 'faster', 'increase speed'],
                    'speed_down': ['slow down', 'slower', 'decrease speed'],
                    'normal_speed': ['normal speed', 'regular speed', 'default speed'],
                    
                    // Seeking
                    'skip_forward': ['skip forward', 'fast forward', 'jump ahead'],
                    'skip_backward': ['skip backward', 'rewind', 'go back'],
                    
                    // Interface
                    'show_controls': ['show controls', 'display controls'],
                    'hide_controls': ['hide controls', 'remove controls']
                };
            }

            // FIXED: Setup fullscreen event listeners to track state properly
            setupFullscreenListeners() {
                // Listen for fullscreen change events
                document.addEventListener('fullscreenchange', () => {
                    this.currentFullscreenState = !!document.fullscreenElement;
                    console.log('Fullscreen state changed:', this.currentFullscreenState);
                });
                
                document.addEventListener('webkitfullscreenchange', () => {
                    this.currentFullscreenState = !!document.webkitFullscreenElement;
                    console.log('Webkit fullscreen state changed:', this.currentFullscreenState);
                });
                
                document.addEventListener('mozfullscreenchange', () => {
                    this.currentFullscreenState = !!document.mozFullScreenElement;
                    console.log('Moz fullscreen state changed:', this.currentFullscreenState);
                });
                
                document.addEventListener('MSFullscreenChange', () => {
                    this.currentFullscreenState = !!document.msFullscreenElement;
                    console.log('MS fullscreen state changed:', this.currentFullscreenState);
                });
            }
            
            initializeVoiceRecognition() {
                // Check for browser support
                if ('webkitSpeechRecognition' in window) {
                    this.recognition = new webkitSpeechRecognition();
                    this.isSupported = true;
                } else if ('SpeechRecognition' in window) {
                    this.recognition = new SpeechRecognition();
                    this.isSupported = true;
                } else {
                    console.log('Speech recognition not supported');
                    this.updateStatus('Voice control not supported in this browser', 'error');
                    return;
                }
                
                // Configure speech recognition for continuous mode
                this.recognition.continuous = true;  // Enable continuous recognition
                this.recognition.interimResults = false;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 1;
                
                // Event listeners
                this.recognition.onstart = () => {
                    console.log('Voice recognition started');
                    this.isListening = true;
                    this.updateUI();
                    if (this.isContinuousMode) {
                        this.updateStatus('Continuous listening active - Speak commands', 'continuous');
                        this.showContinuousIndicator(true);
                    } else {
                        this.updateStatus('Listening... Speak your command', 'listening');
                    }
                };
                
                this.recognition.onend = () => {
                    console.log('Voice recognition ended');
                    this.isListening = false;
                    this.updateUI();
                    
                    // Auto-restart if in continuous mode
                    if (this.isContinuousMode) {
                        console.log('Restarting recognition for continuous mode');
                        this.restartTimeout = setTimeout(() => {
                            if (this.isContinuousMode && !this.isListening) {
                                try {
                                    this.recognition.start();
                                } catch (error) {
                                    console.error('Failed to restart recognition:', error);
                                    this.stopContinuousMode();
                                }
                            }
                        }, 100); // Short delay to prevent rapid restart issues
                    } else {
                        this.updateStatus('Press \'V\' to start voice control', 'ready');
                        this.showContinuousIndicator(false);
                    }
                };
                
                this.recognition.onresult = (event) => {
                    const result = event.results[event.results.length - 1][0];
                    const command = result.transcript.toLowerCase().trim();
                    const confidence = result.confidence;
                    
                    console.log('Voice command recognized:', command, 'Confidence:', confidence);
                    this.processCommand(command, confidence);
                };
                
                this.recognition.onerror = (event) => {
                    console.error('Voice recognition error:', event.error);
                    this.isListening = false;
                    this.updateUI();
                    
                    let errorMessage = 'Voice recognition error';
                    switch(event.error) {
                        case 'no-speech':
                            if (this.isContinuousMode) {
                                // Don't show error for no-speech in continuous mode, just restart
                                this.restartContinuousRecognition();
                                return;
                            }
                            errorMessage = 'No speech detected. Try again.';
                            break;
                        case 'audio-capture':
                            errorMessage = 'Microphone not available';
                            this.stopContinuousMode();
                            break;
                        case 'not-allowed':
                            errorMessage = 'Microphone permission denied';
                            this.stopContinuousMode();
                            break;
                        case 'network':
                            errorMessage = 'Network error occurred';
                            break;
                        default:
                            errorMessage = `Error: ${event.error}`;
                    }
                    
                    if (this.isContinuousMode && event.error !== 'audio-capture' && event.error !== 'not-allowed') {
                        // Try to restart in continuous mode unless it's a permission issue
                        this.restartContinuousRecognition();
                    } else {
                        this.updateStatus(errorMessage, 'error');
                    }
                };
            }
            
            restartContinuousRecognition() {
                if (this.isContinuousMode) {
                    this.restartTimeout = setTimeout(() => {
                        if (this.isContinuousMode && !this.isListening) {
                            try {
                                this.recognition.start();
                            } catch (error) {
                                console.error('Failed to restart continuous recognition:', error);
                                this.stopContinuousMode();
                            }
                        }
                    }, 500);
                }
            }
            
            setupUI() {
                // Update UI based on support
                if (!this.isSupported) {
                    document.getElementById('voiceToggle').disabled = true;
                    document.getElementById('voiceToggle').style.opacity = '0.5';
                    return;
                }
                
                // Request microphone permission on first interaction
                document.getElementById('voiceToggle').addEventListener('click', () => {
                    if (!this.isListening) {
                        navigator.mediaDevices.getUserMedia({ audio: true })
                            .then(() => {
                                console.log('Microphone permission granted');
                            })
                            .catch((err) => {
                                console.error('Microphone permission denied:', err);
                                this.updateStatus('Microphone permission required', 'error');
                            });
                    }
                });
            }
            
            setPlayer(player) {
                this.player = player;
                console.log('Video player connected to voice control');
                
                // FIXED: Track fullscreen state changes through Video.js events
                player.on('fullscreenchange', () => {
                    this.currentFullscreenState = player.isFullscreen();
                    console.log('Video.js fullscreen state changed:', this.currentFullscreenState);
                });
            }
            
            startContinuousMode() {
                console.log('Starting continuous voice mode');
                this.isContinuousMode = true;
                this.clearRestartTimeout();
                
                if (!this.isListening) {
                    try {
                        this.recognition.start();
                    } catch (error) {
                        console.error('Failed to start continuous recognition:', error);
                        this.updateStatus('Failed to start continuous voice control', 'error');
                        this.isContinuousMode = false;
                    }
                }
                
                this.updateUI();
                this.updateStatus('Starting continuous voice control...', 'continuous');
            }
            
            stopContinuousMode() {
                console.log('Stopping continuous voice mode');
                this.isContinuousMode = false;
                this.clearRestartTimeout();
                
                if (this.isListening) {
                    this.recognition.stop();
                }
                
                this.updateUI();
                this.showContinuousIndicator(false);
                this.updateStatus('Press \'V\' to start voice control', 'ready');
            }
            
            clearRestartTimeout() {
                if (this.restartTimeout) {
                    clearTimeout(this.restartTimeout);
                    this.restartTimeout = null;
                }
            }
            
            processCommand(recognizedText, confidence) {
                this.commandCount++;
                this.updateStatus('Processing command...', 'processing');
                
                // Find matching command
                let matchedCommand = null;
                let matchedAction = null;
                
                for (const [action, phrases] of Object.entries(this.commands)) {
                    for (const phrase of phrases) {
                        if (recognizedText.includes(phrase)) {
                            matchedCommand = phrase;
                            matchedAction = action;
                            break;
                        }
                    }
                    if (matchedCommand) break;
                }
                
                if (matchedCommand && this.player) {
                    this.executeCommand(matchedAction, matchedCommand, recognizedText);
                } else {
                    this.updateStatus(`Command not recognized: "${recognizedText}"`, 'error');
                    this.logCommand('unknown', recognizedText, false);
                    this.showFeedback(`❌ Command not recognized`, 'error');
                    
                    // In continuous mode, quickly return to listening status
                    if (this.isContinuousMode) {
                        setTimeout(() => {
                            this.updateStatus('Continuous listening active - Speak commands', 'continuous');
                        }, 2000);
                    }
                }
                
                this.updateStats();
            }
            
            executeCommand(action, matchedCommand, recognizedText) {
                try {
                    let success = true;
                    let feedbackMessage = '';
                    
                    switch(action) {
                        case 'play':
                            this.player.play();
                            feedbackMessage = '▶️ Playing video';
                            break;
                            
                        case 'pause':
                            this.player.pause();
                            feedbackMessage = '⏸️ Video paused';
                            break;
                            
                        case 'restart':
                            // FIXED: Better restart implementation
                            this.player.pause();
                            this.player.currentTime(0);
                            // Add a small delay to ensure the seek completes
                            setTimeout(() => {
                                this.player.play();
                            }, 100);
                            feedbackMessage = '🔄 Video restarted';
                            break;
                            
                        case 'volume_up':
                            const currentVol = this.player.volume();
                            const newVolUp = Math.min(currentVol + 0.2, 1);
                            this.player.volume(newVolUp);
                            feedbackMessage = `🔊 Volume: ${Math.round(newVolUp * 100)}%`;
                            break;
                            
                        case 'volume_down':
                            const currentVolDown = this.player.volume();
                            const newVolDown = Math.max(currentVolDown - 0.2, 0);
                            this.player.volume(newVolDown);
                            feedbackMessage = `🔉 Volume: ${Math.round(newVolDown * 100)}%`;
                            break;
                            
                        case 'mute':
                            this.player.muted(true);
                            feedbackMessage = '🔇 Video muted';
                            break;
                            
                        case 'unmute':
                            // FIXED: Proper unmute implementation
                            this.player.muted(false);
                            feedbackMessage = '🔊 Video unmuted';
                            break;
                            
                        case 'fullscreen':
                            // FIXED: Completely rewritten fullscreen logic to handle multiple calls correctly
                            console.log('Fullscreen command - Current state:', this.currentFullscreenState);
                            
                            if (this.currentFullscreenState) {
                                feedbackMessage = '⛶ Already in fullscreen';
                                success = false;
                            } else {
                                try {
                                    // Method 1: Try Video.js fullscreen API first
                                    if (this.player.requestFullscreen) {
                                        console.log('Using Video.js requestFullscreen');
                                        this.player.requestFullscreen();
                                        feedbackMessage = '⛶ Entering fullscreen';
                                    }
                                    // Method 2: Try native HTML5 fullscreen API on video element
                                    else {
                                        console.log('Using native fullscreen API');
                                        const videoElement = this.player.el();
                                        
                                        if (videoElement.requestFullscreen) {
                                            videoElement.requestFullscreen();
                                        } else if (videoElement.webkitRequestFullscreen) {
                                            videoElement.webkitRequestFullscreen();
                                        } else if (videoElement.mozRequestFullScreen) {
                                            videoElement.mozRequestFullScreen();
                                        } else if (videoElement.msRequestFullscreen) {
                                            videoElement.msRequestFullscreen();
                                        } else {
                                            throw new Error('Fullscreen API not supported');
                                        }
                                        feedbackMessage = '⛶ Entering fullscreen';
                                    }
                                    
                                    // Update state immediately (will be confirmed by event listener)
                                    setTimeout(() => {
                                        this.currentFullscreenState = true;
                                    }, 100);
                                    
                                } catch (fullscreenError) {
                                    console.error('Fullscreen error:', fullscreenError);
                                    feedbackMessage = '❌ Fullscreen not supported';
                                    success = false;
                                }
                            }
                            break;
                            
                        case 'exit_fullscreen':
                            // FIXED: Improved exit fullscreen logic
                            console.log('Exit fullscreen command - Current state:', this.currentFullscreenState);
                            
                            if (!this.currentFullscreenState) {
                                feedbackMessage = '🔳 Not in fullscreen mode';
                                success = false;
                            } else {
                                try {
                                    // Try Video.js exit fullscreen first
                                    if (this.player.exitFullscreen && typeof this.player.exitFullscreen === 'function') {
                                        console.log('Using Video.js exitFullscreen');
                                        this.player.exitFullscreen();
                                    }
                                    // Fallback to native APIs
                                    else if (document.exitFullscreen) {
                                        document.exitFullscreen();
                                    } else if (document.webkitExitFullscreen) {
                                        document.webkitExitFullscreen();
                                    } else if (document.mozCancelFullScreen) {
                                        document.mozCancelFullScreen();
                                    } else if (document.msExitFullscreen) {
                                        document.msExitFullscreen();
                                    }
                                    
                                    feedbackMessage = '🔳 Exiting fullscreen';
                                    
                                    // Update state immediately (will be confirmed by event listener)
                                    setTimeout(() => {
                                        this.currentFullscreenState = false;
                                    }, 100);
                                    
                                } catch (exitError) {
                                    console.error('Exit fullscreen error:', exitError);
                                    feedbackMessage = '❌ Failed to exit fullscreen';
                                    success = false;
                                }
                            }
                            break;
                            
                        case 'speed_up':
                            const currentRate = this.player.playbackRate();
                            const newRateUp = Math.min(currentRate + 0.25, 2);
                            this.player.playbackRate(newRateUp);
                            feedbackMessage = `⚡ Speed: ${newRateUp}x`;
                            break;
                            
                        case 'speed_down':
                            const currentRateDown = this.player.playbackRate();
                            const newRateDown = Math.max(currentRateDown - 0.25, 0.5);
                            this.player.playbackRate(newRateDown);
                            feedbackMessage = `🐌 Speed: ${newRateDown}x`;
                            break;
                            
                        case 'normal_speed':
                            this.player.playbackRate(1);
                            feedbackMessage = '⚡ Normal speed';
                            break;
                            
                        case 'skip_forward':
                            const currentTime = this.player.currentTime();
                            this.player.currentTime(currentTime + 10);
                            feedbackMessage = '⏭️ Skipped forward 10s';
                            break;
                            
                        case 'skip_backward':
                            const currentTimeBack = this.player.currentTime();
                            this.player.currentTime(Math.max(currentTimeBack - 10, 0));
                            feedbackMessage = '⏮️ Skipped back 10s';
                            break;
                            
                        default:
                            success = false;
                            feedbackMessage = '❌ Command not implemented';
                    }
                    
                    if (success) {
                        this.successCount++;
                        this.updateStatus(`✓ ${feedbackMessage}`, 'success');
                        this.showFeedback(feedbackMessage, 'success');
                        this.showVideoOverlay(feedbackMessage);
                        
                        // Return to continuous listening status if in continuous mode
                        if (this.isContinuousMode) {
                            setTimeout(() => {
                                this.updateStatus('Continuous listening active - Speak commands', 'continuous');
                            }, 2000);
                        }
                    }
                    
                    this.logCommand(action, recognizedText, success);
                    
                } catch (error) {
                    console.error('Error executing command:', error);
                    this.updateStatus(`Error: ${error.message}`, 'error');
                    this.showFeedback('❌ Command failed', 'error');
                    this.logCommand(action, recognizedText, false);
                    
                    // Return to continuous listening status if in continuous mode
                    if (this.isContinuousMode) {
                        setTimeout(() => {
                            this.updateStatus('Continuous listening active - Speak commands', 'continuous');
                        }, 2000);
                    }
                }
            }
            
            logCommand(command, recognizedText, success) {
                // Log to database via AJAX
                const formData = new FormData();
                formData.append('log_voice_command', '1');
                formData.append('command', command);
                formData.append('recognized_text', recognizedText);
                formData.append('success', success ? '1' : '0');
                formData.append('ajax', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Voice command logged:', data);
                })
                .catch(error => {
                    console.error('Failed to log voice command:', error);
                });
            }
            
            toggleRecognition() {
                if (!this.isSupported) return;
                
                if (this.isContinuousMode) {
                    this.stopContinuousMode();
                } else {
                    this.startContinuousMode();
                }
            }
            
            updateUI() {
                const toggle = document.getElementById('voiceToggle');
                const icon = document.getElementById('voiceIcon');
                const panel = document.getElementById('voicePanel');
                const floatBtn = document.getElementById('voiceFloatBtn');
                const title = document.getElementById('voiceTitle');
                
                if (this.isContinuousMode) {
                    toggle.classList.add('continuous');
                    panel.classList.add('continuous-mode');
                    floatBtn.classList.add('continuous');
                    title.classList.add('continuous');
                    title.textContent = '🎤 Continuous Voice Control';
                    icon.className = 'fas fa-stop';
                } else {
                    toggle.classList.remove('continuous', 'listening', 'processing');
                    panel.classList.remove('continuous-mode', 'listening', 'processing');
                    floatBtn.classList.remove('continuous');
                    title.classList.remove('continuous');
                    title.textContent = '🎤 Voice Control';
                    icon.className = 'fas fa-microphone';
                }
                
                if (this.isListening && this.isContinuousMode) {
                    toggle.classList.add('listening');
                    panel.classList.add('listening');
                } else if (this.isListening) {
                    toggle.classList.add('listening');
                    panel.classList.add('listening');
                }
            }
            
            updateStatus(message, type) {
                const status = document.getElementById('voiceStatus');
                status.textContent = message;
                status.className = `voice-status ${type}`;
                
                // Don't auto-clear status in continuous mode unless it's an error
                if (type !== 'ready' && type !== 'continuous') {
                    setTimeout(() => {
                        if (status.className.includes(type) && !this.isContinuousMode) {
                            this.updateStatus('Press \'V\' to start voice control', 'ready');
                        } else if (status.className.includes(type) && this.isContinuousMode) {
                            this.updateStatus('Continuous listening active - Speak commands', 'continuous');
                        }
                    }, 3000);
                }
            }
            
            showFeedback(message, type) {
                const feedback = document.getElementById('voiceFeedback');
                feedback.textContent = message;
                feedback.className = `voice-feedback ${type}`;
                feedback.style.display = 'block';
                
                setTimeout(() => {
                    feedback.style.display = 'none';
                }, 3000);
            }
            
            showVideoOverlay(message) {
                const overlay = document.getElementById('voiceOverlay');
                const overlayText = document.getElementById('voiceOverlayText');
                
                overlayText.textContent = message;
                overlay.classList.add('show');
                
                setTimeout(() => {
                    overlay.classList.remove('show');
                }, 2000);
            }
            
            showContinuousIndicator(show) {
                const indicator = document.getElementById('continuousIndicator');
                if (show) {
                    indicator.classList.add('show');
                } else {
                    indicator.classList.remove('show');
                }
            }
            
            updateStats() {
                document.getElementById('commandCount').textContent = this.commandCount;
                const successRate = this.commandCount > 0 ? Math.round((this.successCount / this.commandCount) * 100) : 0;
                document.getElementById('successRate').textContent = successRate + '%';
            }
        }
        
        // Initialize voice control
        let voiceControl;
        let player;
        const videoId = '<?php echo $video_id; ?>';
        const videoPath = 'cc/<?php echo $video['creator_id']; ?>/video/<?php echo $video['video']; ?>';

        console.log('Video path:', videoPath);

        // Initialize video player when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing video player');
            
            // Initialize voice control
            voiceControl = new VoiceControl();
            
            if (typeof videojs !== 'undefined') {
                console.log('Video.js loaded successfully');
                initializeVideoPlayer();
            } else {
                console.log('Video.js failed to load, using fallback');
                initializeFallbackPlayer();
            }
        });

        function initializeVideoPlayer() {
            try {
                player = videojs('videoPlayer', {
                    controls: true,
                    responsive: true,
                    fluid: true,
                    playbackRates: [0.5, 1, 1.25, 1.5, 2],
                    sources: [{
                        src: videoPath,
                        type: 'video/mp4'
                    }]
                });

                player.ready(function() {
                    console.log('Video.js player is ready');
                    voiceControl.setPlayer(player);
                });

            } catch (error) {
                console.error('Error initializing Video.js player:', error);
                initializeFallbackPlayer();
            }
        }

        function initializeFallbackPlayer() {
            console.log('Using fallback HTML5 player');
            const videoElement = document.getElementById('videoPlayer');
            
            videoElement.innerHTML = '';
            videoElement.className = 'video-js';
            videoElement.controls = true;
            videoElement.style.width = '100%';
            videoElement.style.height = '600px';
            
            const source = document.createElement('source');
            source.src = videoPath;
            source.type = 'video/mp4';
            videoElement.appendChild(source);
        }
        
        // UI Functions
        function toggleVoiceRecognition() {
            if (voiceControl) {
                voiceControl.toggleRecognition();
            }
        }
        
        function toggleVoicePanel() {
            const panel = document.getElementById('voicePanel');
            const floatBtn = document.getElementById('voiceFloatBtn');
            
            if (panel.classList.contains('minimized')) {
                panel.classList.remove('minimized');
                floatBtn.style.display = 'none';
            } else {
                panel.classList.add('minimized');
                floatBtn.style.display = 'flex';
            }
        }
        
        // Enhanced Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'V' to toggle continuous voice recognition
            if (e.key === 'v' || e.key === 'V') {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    toggleVoiceRecognition();
                }
            }
            
            // Press 'Escape' to stop continuous voice recognition
            if (e.key === 'Escape') {
                if (voiceControl && voiceControl.isContinuousMode) {
                    e.preventDefault();
                    voiceControl.stopContinuousMode();
                }
            }
        });

        // Form debugging
        document.querySelectorAll('form').forEach((form, index) => {
            console.log(`Form ${index}:`, form);
            console.log('Form action:', form.action);
            console.log('Form method:', form.method);
            console.log('Form elements:', form.elements);
            
            form.addEventListener('submit', function(e) {
                console.log('Form submitting:', this);
                console.log('Form data:', new FormData(this));
                
                // Log all form data
                const formData = new FormData(this);
                for (let [key, value] of formData.entries()) {
                    console.log(`${key}: ${value}`);
                }
            });
        });

        // Button debugging
        document.querySelectorAll('button').forEach((btn, index) => {
            console.log(`Button ${index}:`, btn);
            console.log('Button name:', btn.name);
            console.log('Button value:', btn.value);
            console.log('Button type:', btn.type);
        });
        
        console.log('🎤 Enhanced Continuous Voice Control System Loaded');
        console.log('📝 Press "V" key to start/stop continuous voice control');
        console.log('⏹️ Press "Escape" to stop continuous mode');
        console.log('🗣️ Available commands: play, pause, volume up/down, mute/stop mute, fullscreen, restart');
        console.log('🔄 Continuous mode: Microphone stays on and listens for multiple commands');
        console.log('✅ FIXES: Fullscreen now works repeatedly, "unmute" changed to "stop mute", proper state tracking');
    </script>
</body>
</html>