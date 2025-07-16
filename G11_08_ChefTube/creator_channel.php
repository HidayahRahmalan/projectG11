<?php
require_once 'db_connect.php';

// Get creator ID from URL
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$creator_id = $_GET['id'];

// Get creator details
try {
    $stmt = $pdo->prepare("SELECT * FROM creator WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $creator = $stmt->fetch();
    
    if (!$creator) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Creator fetch error: " . $e->getMessage());
    header('Location: index.php');
    exit();
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

// Get creator's videos
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
    $pfp_path = "website/default_avatar.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($creator['name']); ?> - ChefTube</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Animated Background Elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 120, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 226, 0.15) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundMove 25s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translateX(0px) translateY(0px); }
            33% { transform: translateX(-20px) translateY(-15px); }
            66% { transform: translateX(15px) translateY(-20px); }
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
            transition: all 0.3s ease;
        }

        .header:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s ease;
        }

        .logo-section:hover {
            transform: scale(1.05);
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
            transform: translateX(-5px);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Main Content */
        .main-content {
            padding: 40px 30px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Creator Banner */
        .creator-banner {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .creator-banner::before {
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

        .creator-banner:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .creator-banner:hover::before {
            opacity: 1;
        }

        .creator-info {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 2;
        }

        .creator-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .creator-banner:hover .creator-avatar {
            border-color: rgba(229, 9, 20, 0.5);
            transform: scale(1.05);
        }

        .creator-details {
            flex: 1;
        }

        .creator-name {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ffffff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .creator-username {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }

        .creator-meta {
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
            margin-bottom: 20px;
        }

        .creator-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 500;
        }

        /* Videos Section */
        .videos-section {
            margin-top: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
        }

        .video-count {
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
        }

        /* Videos Grid */
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }

        .video-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
        }

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
            pointer-events: none;
        }

        .video-card:hover::before {
            opacity: 1;
        }

        .video-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.4),
                0 0 30px rgba(229, 9, 20, 0.2);
            border-color: rgba(229, 9, 20, 0.3);
        }

        .video-thumbnail {
            width: 100%;
            height: 200px;
            background: #000;
            overflow: hidden;
            position: relative;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .video-card:hover .video-thumbnail img {
            transform: scale(1.1);
        }

        .video-duration {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(5px);
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
            line-height: 1.4;
            margin-bottom: 12px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            color: #ffffff;
            transition: color 0.3s ease;
        }

        .video-card:hover .video-title {
            color: #ff6b6b;
        }

        .video-meta {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .video-stats {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        .tag-badge {
            display: inline-block;
            background: rgba(229, 9, 20, 0.2);
            color: #ff6b6b;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid rgba(229, 9, 20, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: rgba(255, 255, 255, 0.7);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 30px;
            opacity: 0.5;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }

        .empty-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #ffffff;
        }

        .empty-description {
            font-size: 18px;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .videos-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .creator-banner {
                padding: 30px 20px;
            }
            
            .creator-info {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .creator-avatar {
                width: 100px;
                height: 100px;
            }
            
            .creator-name {
                font-size: 28px;
            }
            
            .creator-stats {
                justify-content: center;
                gap: 20px;
            }
            
            .videos-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
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
    <main class="main-content">
        <!-- Creator Banner -->
        <div class="creator-banner fade-in">
            <div class="creator-info">
                <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="<?php echo htmlspecialchars($creator['name']); ?>" class="creator-avatar">
                
                <div class="creator-details">
                    <h1 class="creator-name"><?php echo htmlspecialchars($creator['name']); ?></h1>
                    <div class="creator-username">@<?php echo htmlspecialchars($creator['username']); ?></div>
                    <div class="creator-meta">
                        <i class="fas fa-calendar"></i> Joined <?php echo date('F Y', strtotime($creator['date_joined'])); ?>
                    </div>
                    
                    <div class="creator-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($total_videos); ?></div>
                            <div class="stat-label">Videos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($total_views); ?></div>
                            <div class="stat-label">Views</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($total_likes); ?></div>
                            <div class="stat-label">Likes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Videos Section -->
        <div class="videos-section fade-in" style="animation-delay: 0.2s;">
            <div class="section-header">
                <h2 class="section-title">Videos</h2>
                <div class="video-count"><?php echo number_format($total_videos); ?> videos</div>
            </div>

            <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎬</div>
                    <h3 class="empty-title">No videos yet</h3>
                    <p class="empty-description">
                        This creator hasn't uploaded any videos yet.
                        <br>Check back later for amazing culinary content!
                    </p>
                </div>
            <?php else: ?>
                <div class="videos-grid">
                    <?php foreach ($videos as $index => $video): ?>
                        <div class="video-card fade-in" style="animation-delay: <?php echo 0.3 + ($index * 0.1); ?>s;" onclick="window.location.href='user_play_video.php?id=<?php echo $video['vid_id']; ?>'">
                            <div class="video-thumbnail">
                                <?php 
                                $thumbnail_path = "cc/" . $creator_id . "/thumbnail/" . $video['thumbnail'];
                                if (file_exists($thumbnail_path) && $video['thumbnail'] != 'default_thumbnail.jpg'): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: linear-gradient(45deg, #1a1a2e, #16213e); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                                        <i class="fas fa-play" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($video['duration']): ?>
                                    <div class="video-duration">
                                        <?php echo date('i:s', strtotime($video['duration'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <div class="video-meta">
                                    <?php if ($video['tag_name']): ?>
                                        <span class="tag-badge"><?php echo htmlspecialchars($video['tag_name']); ?></span>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;">
                                        <?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?>
                                    </div>
                                </div>
                                <div class="video-stats">
                                    <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?></span>
                                    <span><i class="fas fa-thumbs-up"></i> <?php echo number_format($video['like']); ?></span>
                                    <span><i class="fas fa-comments"></i> <?php echo number_format($video['comment_count']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Video card hover effects
        document.querySelectorAll('.video-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });
    </script>
</body>
</html>