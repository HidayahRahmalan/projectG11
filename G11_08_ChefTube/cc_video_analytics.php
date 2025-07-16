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
        SELECT v.*, t.name as tag_name
        FROM video v
        LEFT JOIN tag t ON v.tag_id = t.tag_id
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

// Get video statistics
try {
    // Comments count and details
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_comments,
            COUNT(DISTINCT i.user_id) as unique_commenters
        FROM interaction i
        WHERE i.vid_id = ? AND i.comment IS NOT NULL AND i.comment != ''
    ");
    $stmt->execute([$vid_id]);
    $comment_stats = $stmt->fetch();
    
    // Recent comments
    $stmt = $pdo->prepare("
        SELECT 
            i.comment,
            i.date_comment,
            u.username
        FROM interaction i
        LEFT JOIN user u ON i.user_id = u.user_id
        WHERE i.vid_id = ? AND i.comment IS NOT NULL AND i.comment != ''
        ORDER BY i.date_comment DESC
        LIMIT 5
    ");
    $stmt->execute([$vid_id]);
    $recent_comments = $stmt->fetchAll();
    
    // Engagement rate calculation
    $total_interactions = $video['like'] + $comment_stats['total_comments'];
    $engagement_rate = $video['views'] > 0 ? ($total_interactions / $video['views']) * 100 : 0;
    
} catch (PDOException $e) {
    error_log("Analytics fetch error: " . $e->getMessage());
    $comment_stats = ['total_comments' => 0, 'unique_commenters' => 0];
    $recent_comments = [];
    $engagement_rate = 0;
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png";
}

// Calculate days since upload
$days_since_upload = (time() - strtotime($video['date_uploaded'])) / (60 * 60 * 24);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo htmlspecialchars($video['title']); ?> - ChefTube Creator</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .page-header {
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

        /* Video Info */
        .video-info {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            gap: 20px;
            align-items: start;
        }

        .video-thumbnail {
            width: 200px;
            height: 120px;
            background: #333;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-details {
            flex: 1;
        }

        .video-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .video-meta {
            color: #aaa;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .video-actions {
            display: flex;
            gap: 12px;
        }

        .action-btn {
            padding: 8px 16px;
            background: transparent;
            border: 1px solid #3a3a3a;
            color: #aaa;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .action-btn:hover {
            border-color: #e50914;
            color: #e50914;
        }

        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .analytics-card {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .analytics-card:hover {
            border-color: #e50914;
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #e50914;
        }

        .card-icon {
            font-size: 20px;
            color: #aaa;
        }

        .metric-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-label {
            color: #aaa;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .metric-change {
            font-size: 14px;
            font-weight: 500;
        }

        .metric-change.positive {
            color: #4caf50;
        }

        .metric-change.neutral {
            color: #aaa;
        }

        /* Detailed Stats */
        .detailed-stats {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .stats-section {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #e50914;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #3a3a3a;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #aaa;
        }

        .stat-value {
            font-weight: 600;
        }

        /* Comments Section */
        .comments-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .comment-item {
            padding: 12px 0;
            border-bottom: 1px solid #3a3a3a;
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .comment-author {
            font-weight: 600;
            color: #ff6b6b;
            font-size: 14px;
        }

        .comment-date {
            color: #666;
            font-size: 12px;
        }

        .comment-text {
            color: #ccc;
            font-size: 14px;
            line-height: 1.4;
        }

        .no-comments {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }

        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #e50914, #ff6b6b);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 16px;
            }
            
            .video-info {
                flex-direction: column;
            }
            
            .video-thumbnail {
                width: 100%;
                max-width: 300px;
                margin: 0 auto;
            }
            
            .detailed-stats {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
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
            <h1 class="page-title">Video Analytics</h1>
            <p class="page-subtitle">Detailed performance metrics for your video</p>
        </div>

        <!-- Video Info -->
        <div class="video-info">
            <div class="video-thumbnail">
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
            <div class="video-details">
                <h2 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h2>
                <div class="video-meta">
                    <?php if ($video['tag_name']): ?>
                        <span style="background: #e50914; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 10px;">
                            <?php echo htmlspecialchars($video['tag_name']); ?>
                        </span>
                    <?php endif; ?>
                    Uploaded on <?php echo date('F j, Y', strtotime($video['date_uploaded'])); ?>
                    (<?php echo floor($days_since_upload); ?> days ago)
                </div>
                <div class="video-actions">
                    <a href="cc_watch_video.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">👁 Watch</a>
                    <a href="cc_video_edit.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">✏️ Edit</a>
                    <a href="user_play_video.php?id=<?php echo $video['vid_id']; ?>" class="action-btn">🔗 Public View</a>
                </div>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="card-header">
                    <div class="card-title">Total Views</div>
                    <div class="card-icon">👁</div>
                </div>
                <div class="metric-value"><?php echo number_format($video['views']); ?></div>
                <div class="metric-label">Views since publication</div>
                <div class="metric-change neutral">
                    ~<?php echo $days_since_upload > 0 ? number_format($video['views'] / max(1, $days_since_upload), 1) : 0; ?> views/day average
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-header">
                    <div class="card-title">Total Likes</div>
                    <div class="card-icon">👍</div>
                </div>
                <div class="metric-value"><?php echo number_format($video['like']); ?></div>
                <div class="metric-label">Likes received</div>
                <div class="metric-change <?php echo $video['views'] > 0 ? 'positive' : 'neutral'; ?>">
                    <?php echo $video['views'] > 0 ? number_format(($video['like'] / $video['views']) * 100, 1) : 0; ?>% like rate
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-header">
                    <div class="card-title">Comments</div>
                    <div class="card-icon">💬</div>
                </div>
                <div class="metric-value"><?php echo number_format($comment_stats['total_comments']); ?></div>
                <div class="metric-label">Total comments</div>
                <div class="metric-change neutral">
                    From <?php echo number_format($comment_stats['unique_commenters']); ?> unique viewers
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-header">
                    <div class="card-title">Engagement</div>
                    <div class="card-icon">📊</div>
                </div>
                <div class="metric-value"><?php echo number_format($engagement_rate, 1); ?>%</div>
                <div class="metric-label">Engagement rate</div>
                <div class="metric-change <?php echo $engagement_rate > 5 ? 'positive' : 'neutral'; ?>">
                    <?php echo $engagement_rate > 5 ? 'Great engagement!' : 'Room for improvement'; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Stats -->
        <div class="detailed-stats">
            <div class="stats-section">
                <h3 class="section-title">📈 Performance Metrics</h3>
                
                <div class="stat-row">
                    <div class="stat-label">Video ID</div>
                    <div class="stat-value"><?php echo htmlspecialchars($video['vid_id']); ?></div>
                </div>
                
                <div class="stat-row">
                    <div class="stat-label">Upload Date</div>
                    <div class="stat-value"><?php echo date('F j, Y g:i A', strtotime($video['date_uploaded'])); ?></div>
                </div>
                
                <div class="stat-row">
                    <div class="stat-label">Days Published</div>
                    <div class="stat-value"><?php echo floor($days_since_upload); ?> days</div>
                </div>
                
                <?php if ($video['duration']): ?>
                <div class="stat-row">
                    <div class="stat-label">Duration</div>
                    <div class="stat-value"><?php echo date('i:s', strtotime($video['duration'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="stat-row">
                    <div class="stat-label">Average Views/Day</div>
                    <div class="stat-value"><?php echo number_format($video['views'] / max(1, $days_since_upload), 1); ?></div>
                </div>
                
                <div class="stat-row">
                    <div class="stat-label">Like-to-View Ratio</div>
                    <div class="stat-value"><?php echo $video['views'] > 0 ? number_format(($video['like'] / $video['views']) * 100, 2) : 0; ?>%</div>
                </div>
                
                <div class="stat-row">
                    <div class="stat-label">Comment-to-View Ratio</div>
                    <div class="stat-value"><?php echo $video['views'] > 0 ? number_format(($comment_stats['total_comments'] / $video['views']) * 100, 2) : 0; ?>%</div>
                </div>
                
                <!-- Performance indicator -->
                <div style="margin-top: 20px;">
                    <div style="color: #aaa; margin-bottom: 8px;">Overall Performance</div>
                    <?php 
                    $performance_score = min(100, ($engagement_rate * 10)); 
                    $performance_label = $performance_score >= 50 ? 'Excellent' : ($performance_score >= 20 ? 'Good' : 'Needs Improvement');
                    $performance_color = $performance_score >= 50 ? '#4caf50' : ($performance_score >= 20 ? '#ff9800' : '#e50914');
                    ?>
                    <div style="color: <?php echo $performance_color; ?>; font-weight: 600; margin-bottom: 8px;">
                        <?php echo $performance_label; ?> (<?php echo number_format($performance_score); ?>%)
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $performance_score; ?>%; background: <?php echo $performance_color; ?>;"></div>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h3 class="section-title">💬 Recent Comments</h3>
                
                <?php if (empty($recent_comments)): ?>
                    <div class="no-comments">
                        <div style="font-size: 32px; margin-bottom: 12px;">💭</div>
                        <div>No comments yet</div>
                        <div style="font-size: 12px; color: #555; margin-top: 8px;">
                            Encourage viewers to share their thoughts!
                        </div>
                    </div>
                <?php else: ?>
                    <div class="comments-list">
                        <?php foreach ($recent_comments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <div class="comment-author"><?php echo htmlspecialchars($comment['username'] ?? 'Anonymous'); ?></div>
                                    <div class="comment-date"><?php echo date('M j', strtotime($comment['date_comment'])); ?></div>
                                </div>
                                <div class="comment-text"><?php echo htmlspecialchars(substr($comment['comment'], 0, 100)) . (strlen($comment['comment']) > 100 ? '...' : ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($recent_comments) >= 5): ?>
                        <div style="text-align: center; margin-top: 16px;">
                            <a href="cc_watch_video.php?id=<?php echo $video['vid_id']; ?>#comments" class="action-btn" style="display: inline-block;">
                                View All Comments
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>