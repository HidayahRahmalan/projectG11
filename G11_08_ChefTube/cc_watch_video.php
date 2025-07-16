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

$vid_id = $_GET['id'];
$creator_id = $_SESSION['creator_id'];
$creator_name = $_SESSION['creator_name'];

// Get video details
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            t.name as tag_name,
            c.name as creator_name,
            (SELECT COUNT(*) FROM interaction WHERE vid_id = v.vid_id AND comment IS NOT NULL) as comment_count
        FROM video v
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        LEFT JOIN creator c ON v.creator_id = c.creator_id
        WHERE v.vid_id = ? AND v.creator_id = ?
    ");
    $stmt->execute([$vid_id, $creator_id]);
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

// Get other videos from the same creator
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            t.name as tag_name,
            (SELECT COUNT(*) FROM interaction WHERE vid_id = v.vid_id AND comment IS NOT NULL) as comment_count
        FROM video v
        LEFT JOIN tag t ON v.tag_id = t.tag_id
        WHERE v.creator_id = ? AND v.vid_id != ?
        ORDER BY v.date_uploaded DESC
        LIMIT 10
    ");
    $stmt->execute([$creator_id, $vid_id]);
    $other_videos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Other videos fetch error: " . $e->getMessage());
    $other_videos = [];
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png";
}

// Handle video deletion
if (isset($_POST['delete_video'])) {
    try {
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM video WHERE vid_id = ? AND creator_id = ?");
        $stmt->execute([$vid_id, $creator_id]);
        
        // Delete video file
        $video_path = "cc/$creator_id/video/" . $video['video'];
        if (file_exists($video_path)) {
            unlink($video_path);
        }
        
        // Delete thumbnail file
        $thumbnail_path = "cc/$creator_id/thumbnail/" . $video['thumbnail'];
        if (file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        // Redirect to dashboard with success message
        header('Location: cc_dashboard.php?deleted=1');
        exit();
    } catch (PDOException $e) {
        error_log("Delete video error: " . $e->getMessage());
        $error_message = "Failed to delete video. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - ChefTube</title>
    <link rel="icon" type="image/png" href="website/icon.png">
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .video-player-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
            margin-bottom: 40px;
        }

        .video-main {
            min-width: 0;
        }

        .video-player-wrapper {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .video-js {
            width: 100%;
            height: 500px;
        }

        .video-info-section {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #3a3a3a;
        }

        .video-title-large {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .video-stats-large {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #3a3a3a;
        }

        .video-stats-left {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #aaa;
        }

        .video-actions-large {
            display: flex;
            gap: 12px;
        }

        .action-btn {
            padding: 8px 16px;
            border: 1px solid #3a3a3a;
            background: transparent;
            color: #aaa;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:hover {
            border-color: #e50914;
            color: #e50914;
        }

        .action-btn.delete {
            border-color: #ff6b6b;
            color: #ff6b6b;
        }

        .action-btn.delete:hover {
            background: #ff6b6b;
            color: #fff;
        }

        .video-description-large {
            margin-top: 16px;
            color: #ccc;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .video-tag {
            background: #e50914;
            color: #fff;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Sidebar */
        .video-sidebar {
            background: #1e1e1e;
            border-radius: 8px;
            border: 1px solid #3a3a3a;
            height: fit-content;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid #3a3a3a;
            font-size: 16px;
            font-weight: 600;
        }

        .sidebar-video-list {
            padding: 12px;
        }

        .sidebar-video-item {
            display: flex;
            gap: 12px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-bottom: 8px;
            text-decoration: none;
            color: inherit;
        }

        .sidebar-video-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-thumbnail {
            width: 120px;
            height: 68px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .sidebar-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-duration {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 11px;
        }

        .sidebar-video-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-video-title {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            line-height: 1.3;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .sidebar-video-stats {
            font-size: 12px;
            color: #aaa;
        }

        .sidebar-placeholder {
            padding: 40px 20px;
            text-align: center;
            color: #666;
        }

        .sidebar-placeholder-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        /* Delete confirmation modal */
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

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert.error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .video-player-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .video-sidebar {
                order: 2;
            }
            
            .video-main {
                order: 1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .video-js {
                height: 300px;
            }
            
            .video-stats-large {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .video-actions-large {
                width: 100%;
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
        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="video-player-container">
            <!-- Main Video Area -->
            <div class="video-main">
                <div class="video-player-wrapper">
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
                
                <div class="video-info-section">
                    <h1 class="video-title-large"><?php echo htmlspecialchars($video['title']); ?></h1>
                    
                    <div class="video-stats-large">
                        <div class="video-stats-left">
                            <span><?php echo number_format($video['views']); ?> views</span>
                            <span><?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?></span>
                            <?php if ($video['tag_name']): ?>
                                <span class="video-tag"><?php echo htmlspecialchars($video['tag_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="video-actions-large">
                            <a href="cc_video_edit.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">Edit</a>
                            <a href="cc_video_analytics.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">Analytics</a>
                            <button class="action-btn delete" onclick="showDeleteModal()">Delete</button>
                        </div>
                    </div>
                    
                    <div class="video-description-large"><?php echo nl2br(htmlspecialchars($video['description'])); ?></div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="video-sidebar">
                <div class="sidebar-header">More from <?php echo htmlspecialchars($creator_name); ?></div>
                <div class="sidebar-video-list">
                    <?php if (empty($other_videos)): ?>
                        <div class="sidebar-placeholder">
                            <div class="sidebar-placeholder-icon">🎬</div>
                            <div>Upload more videos to see them here!</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($other_videos as $other_video): ?>
                            <a href="cc_watch_video.php?id=<?php echo $other_video['vid_id']; ?>" class="sidebar-video-item">
                                <div class="sidebar-thumbnail">
                                    <?php 
                                    $thumbnail_path = "cc/$creator_id/thumbnail/" . $other_video['thumbnail'];
                                    if (file_exists($thumbnail_path) && $other_video['thumbnail'] != 'default_thumbnail.jpg'): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" alt="<?php echo htmlspecialchars($other_video['title']); ?>">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #333; display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px;">
                                            No Thumbnail
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($other_video['duration']): ?>
                                        <div class="sidebar-duration"><?php echo date('i:s', strtotime($other_video['duration'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="sidebar-video-info">
                                    <div class="sidebar-video-title"><?php echo htmlspecialchars($other_video['title']); ?></div>
                                    <div class="sidebar-video-stats">
                                        <?php echo number_format($other_video['views']); ?> views • <?php echo date('M j, Y', strtotime($other_video['date_uploaded'])); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Video Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Delete Video</h3>
            <p class="modal-text">Are you sure you want to delete "<?php echo htmlspecialchars($video['title']); ?>"? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="modal-btn secondary" onclick="hideDeleteModal()">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="delete_video" class="modal-btn primary">Delete Video</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    <script>
        let player;
        const videoId = '<?php echo $video['vid_id']; ?>';
        const videoPath = 'cc/<?php echo $creator_id; ?>/video/<?php echo $video['video']; ?>';

        // Initialize video player when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Check if Video.js is available
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
                    
                    // Load saved progress
                    const savedTime = localStorage.getItem(`cheftube_progress_${videoId}`);
                    if (savedTime && parseFloat(savedTime) > 5) {
                        console.log('Resuming from:', savedTime);
                        player.currentTime(parseFloat(savedTime));
                    }
                });

                // Save progress every 5 seconds
                player.on('timeupdate', function() {
                    const currentTime = player.currentTime();
                    if (currentTime > 0) {
                        localStorage.setItem(`cheftube_progress_${videoId}`, currentTime.toString());
                    }
                });

                // Clear progress when video ends
                player.on('ended', function() {
                    localStorage.removeItem(`cheftube_progress_${videoId}`);
                });

            } catch (error) {
                console.error('Error initializing Video.js player:', error);
                initializeFallbackPlayer();
            }
        }

        function initializeFallbackPlayer() {
            console.log('Using fallback HTML5 player');
            const videoElement = document.getElementById('videoPlayer');
            
            // Reset video element
            videoElement.innerHTML = '';
            videoElement.className = 'video-js';
            videoElement.controls = true;
            videoElement.style.width = '100%';
            videoElement.style.height = '500px';
            
            // Create source element
            const source = document.createElement('source');
            source.src = videoPath;
            source.type = 'video/mp4';
            videoElement.appendChild(source);
            
            // Load saved progress
            const savedTime = localStorage.getItem(`cheftube_progress_${videoId}`);
            if (savedTime && parseFloat(savedTime) > 5) {
                videoElement.addEventListener('loadedmetadata', function() {
                    videoElement.currentTime = parseFloat(savedTime);
                });
            }
            
            // Save progress
            videoElement.addEventListener('timeupdate', function() {
                if (videoElement.currentTime > 0) {
                    localStorage.setItem(`cheftube_progress_${videoId}`, videoElement.currentTime.toString());
                }
            });
            
            // Clear progress when video ends
            videoElement.addEventListener('ended', function() {
                localStorage.removeItem(`cheftube_progress_${videoId}`);
            });
        }

        // Delete modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideDeleteModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
    </script>
</body>
</html>