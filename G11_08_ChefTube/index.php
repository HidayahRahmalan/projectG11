<?php
// Start session to check login status
session_start();
require_once 'db_connect.php';

// Check if user or creator is logged in
$is_user_logged_in = isset($_SESSION['user_id']);
$is_creator_logged_in = isset($_SESSION['creator_id']);

// Get user/creator info if logged in
$logged_in_user = null;
$logged_in_creator = null;
$user_pfp_path = 'website/default_avatar.png';

if ($is_user_logged_in) {
    $logged_in_user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? ''
    ];
    
    // Try to get user profile picture (if implemented in future)
    // For now, use default avatar
    $user_pfp_path = 'website/default_avatar.png';
}

if ($is_creator_logged_in) {
    $logged_in_creator = [
        'id' => $_SESSION['creator_id'],
        'username' => $_SESSION['creator_username'] ?? 'Creator',
        'name' => $_SESSION['creator_name'] ?? 'Creator',
        'email' => $_SESSION['creator_email'] ?? ''
    ];
    
    // Get creator profile picture
    $creator_pfp_path = "cc/" . $_SESSION['creator_id'] . "/pfp/pfp.png";
    if (file_exists($creator_pfp_path)) {
        $user_pfp_path = $creator_pfp_path;
    }
}

// Get search query and filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'latest';
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : 'all';

// Get all tags for filtering
try {
    $stmt = $pdo->prepare("SELECT tag_id, name FROM tag ORDER BY name ASC");
    $stmt->execute();
    $tags = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Tags fetch error: " . $e->getMessage());
    $tags = [];
}

// Build the main video query based on filters
$base_query = "
    SELECT 
        v.*,
        c.name as creator_name,
        c.username as creator_username,
        t.name as tag_name,
        (SELECT COUNT(*) FROM interaction WHERE vid_id = v.vid_id AND comment IS NOT NULL) as comment_count
    FROM video v
    LEFT JOIN creator c ON v.creator_id = c.creator_id
    LEFT JOIN tag t ON v.tag_id = t.tag_id
    WHERE 1=1
";

$params = [];

// Add search filter
if (!empty($search_query)) {
    $base_query .= " AND (v.title LIKE ? OR v.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add tag filter
if ($tag_filter !== 'all') {
    $base_query .= " AND v.tag_id = ?";
    $params[] = $tag_filter;
}

// Add sorting based on current tab
switch ($current_tab) {
    case 'trending':
        $base_query .= " ORDER BY v.views DESC, v.date_uploaded DESC";
        break;
    case 'most_viewed':
        $base_query .= " ORDER BY v.views DESC";
        break;
    case 'latest':
    default:
        $base_query .= " ORDER BY v.date_uploaded DESC";
        break;
}

$base_query .= " LIMIT 50";

// Execute the query
try {
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Videos fetch error: " . $e->getMessage());
    $videos = [];
}

// Get statistics for tabs
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM video");
    $stmt->execute();
    $total_videos = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as trending FROM video WHERE views > 0 ORDER BY views DESC");
    $stmt->execute();
    $trending_count = $stmt->fetch()['trending'];
} catch (PDOException $e) {
    $total_videos = 0;
    $trending_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube</title>
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
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 120, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 226, 0.2) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundMove 20s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translateX(0px) translateY(0px); }
            33% { transform: translateX(-30px) translateY(-20px); }
            66% { transform: translateX(20px) translateY(-30px); }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(100vh) translateX(0px); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) translateX(100px); opacity: 0; }
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-left: 30px;
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
            transition: all 0.3s ease;
        }

        .logo:hover {
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.5);
        }

        .brand-name {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-center {
            flex: 1;
            max-width: 700px;
            margin: 0 40px;
            position: relative;
        }

        .search-container {
            display: flex;
            height: 50px;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .search-container:hover,
        .search-container:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            background: rgba(255, 255, 255, 0.15);
        }

        .search-input {
            flex: 1;
            padding: 0 25px;
            border: none;
            background: transparent;
            font-size: 16px;
            color: white;
            outline: none;
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-btn {
            width: 70px;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: white;
        }

        .search-btn:hover {
            background: linear-gradient(45deg, #ff6b6b, #e50914);
            transform: scale(1.05);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 30px;
        }

        .auth-btn {
            padding: 12px 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            background: transparent;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }

        .auth-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .auth-btn:hover::before {
            left: 100%;
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .auth-btn.register {
            background: linear-gradient(45deg, #065fd4, #4fc3f7);
            border-color: transparent;
        }

        .auth-btn.register:hover {
            background: linear-gradient(45deg, #4fc3f7, #065fd4);
        }

        /* User Profile Section */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            border-color: rgba(229, 9, 20, 0.5);
            transform: scale(1.1);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #ffffff;
        }

        .user-type {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1001;
            margin-top: 10px;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.3s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
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

        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
        }

        /* Navigation Tabs */
        .nav-tabs {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 30px;
            overflow-x: auto;
            white-space: nowrap;
        }

        .tabs-container {
            display: flex;
            gap: 40px;
            padding: 15px 0;
        }

        .tab-item {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-item::before {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .tab-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-2px);
        }

        .tab-item.active {
            color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }

        .tab-item.active::before {
            width: 100%;
        }

        /* Tag Filters */
        .tag-filters {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            overflow-x: auto;
            white-space: nowrap;
        }

        .tag-container {
            display: flex;
            gap: 12px;
        }

        .tag-filter {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .tag-filter:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .tag-filter.active {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            color: white;
            border-color: transparent;
        }

        /* Main Content */
        .main-content {
            padding: 40px 30px;
            width: 100%;
            max-width: none;
            margin: 0;
        }

        /* Video Grid */
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            padding: 0;
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
            margin: 0;
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
            display: flex;
            gap: 15px;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .creator-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .video-card:hover .creator-avatar {
            border-color: rgba(229, 9, 20, 0.5);
            transform: scale(1.1);
        }

        .video-details {
            flex: 1;
            min-width: 0;
        }

        .video-title {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 8px;
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
        }

        .creator-name {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .creator-name:hover {
            color: #ff6b6b;
        }

        .video-stats {
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tag-badge {
            display: inline-block;
            background: rgba(229, 9, 20, 0.2);
            color: #ff6b6b;
            padding: 2px 8px;
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
        @media (max-width: 1400px) {
            .videos-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 25px;
            }
        }

        @media (max-width: 1200px) {
            .videos-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
            
            .main-content {
                padding: 30px 20px;
            }
        }

        @media (max-width: 768px) {
            .header-left, .header-right {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .header-center {
                margin: 0 20px;
            }
            
            .videos-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .main-content {
                padding: 20px 15px;
            }
            
            .nav-tabs, .tag-filters {
                padding-left: 15px;
                padding-right: 15px;
            }

            .user-info {
                display: none;
            }

            .user-profile {
                gap: 10px;
            }
        }

        /* Loading animation */
        @keyframes shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: calc(200px + 100%) 0; }
        }

        .loading {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            background-size: 200px 100%;
            animation: shimmer 1.5s infinite;
        }

        /* Scroll animations */
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

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(229, 9, 20, 0.6);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(229, 9, 20, 0.8);
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <div class="particles"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <a href="index.php" class="logo-section">
                <img src="website/icon.png" alt="ChefTube" class="logo">
                <div class="brand-name">ChefTube</div>
            </a>
        </div>

        <div class="header-center">
            <form class="search-container" method="GET" action="">
                <input type="text" name="search" class="search-input" placeholder="Search cooking videos..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
                <input type="hidden" name="tag" value="<?php echo $tag_filter; ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <div class="header-right">
            <?php if ($is_user_logged_in || $is_creator_logged_in): ?>
                <!-- User is logged in -->
                <div class="user-profile">
                    <img src="<?php echo htmlspecialchars($user_pfp_path); ?>" alt="Profile" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name">
                            <?php 
                            if ($is_creator_logged_in) {
                                echo htmlspecialchars($logged_in_creator['name']);
                            } else {
                                echo htmlspecialchars($logged_in_user['username']);
                            }
                            ?>
                        </div>
                        <div class="user-type">
                            <?php echo $is_creator_logged_in ? 'Creator' : 'Viewer'; ?>
                        </div>
                    </div>
                    <div class="user-dropdown">
                        <button class="dropdown-toggle" onclick="toggleUserDropdown()">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="userDropdown">
                            <?php if ($is_creator_logged_in): ?>
                                <a href="cc_dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Creator Dashboard
                                </a>
                                <a href="cc_add_video.php" class="dropdown-item">
                                    <i class="fas fa-plus"></i> Upload Video
                                </a>
                                <a href="cc_profile_edit.php" class="dropdown-item">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </a>
                                <a href="cc_logout.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i> Sign Out
                                </a>
                            <?php else: ?>
                                <a href="user_profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                                <a href="user_favorites.php" class="dropdown-item">
                                    <i class="fas fa-heart"></i> Favorites
                                </a>
                                <a href="cc_register.php" class="dropdown-item">
                                    <i class="fas fa-video"></i> Become a Creator
                                </a>
                                <a href="user_logout.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i> Sign Out
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- User is not logged in -->
                <a href="user_login.php" class="auth-btn">Sign In</a>
                <a href="user_register.php" class="auth-btn register">Register</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="nav-tabs">
        <div class="tabs-container">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'latest'])); ?>" 
               class="tab-item <?php echo $current_tab === 'latest' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Latest
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'trending'])); ?>" 
               class="tab-item <?php echo $current_tab === 'trending' ? 'active' : ''; ?>">
                <i class="fas fa-fire"></i> Trending
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'most_viewed'])); ?>" 
               class="tab-item <?php echo $current_tab === 'most_viewed' ? 'active' : ''; ?>">
                <i class="fas fa-eye"></i> Most Viewed
            </a>
        </div>
    </nav>

    <!-- Tag Filters -->
    <div class="tag-filters">
        <div class="tag-container">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tag' => 'all'])); ?>" 
               class="tag-filter <?php echo $tag_filter === 'all' ? 'active' : ''; ?>">
                All
            </a>
            <?php foreach ($tags as $tag): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['tag' => $tag['tag_id']])); ?>" 
                   class="tag-filter <?php echo $tag_filter === $tag['tag_id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($tag['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (empty($videos)): ?>
            <div class="empty-state fade-in">
                <div class="empty-icon">🎬</div>
                <h2 class="empty-title">No videos found</h2>
                <p class="empty-description">
                    <?php if (!empty($search_query)): ?>
                        No videos match your search for "<?php echo htmlspecialchars($search_query); ?>".
                        <br>Try different keywords or browse all videos.
                    <?php else: ?>
                        No cooking videos have been uploaded yet.
                        <br>Check back later for amazing culinary content!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="videos-grid">
                <?php foreach ($videos as $index => $video): ?>
                    <div class="video-card fade-in" style="animation-delay: <?php echo $index * 0.1; ?>s;" onclick="window.location.href='user_play_video.php?id=<?php echo $video['vid_id']; ?>'">
                        <div class="video-thumbnail">
                            <?php 
                            $thumbnail_path = "cc/" . $video['creator_id'] . "/thumbnail/" . $video['thumbnail'];
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
                            <?php 
                            $creator_pfp_path = "cc/" . $video['creator_id'] . "/pfp/pfp.png";
                            if (file_exists($creator_pfp_path)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($creator_pfp_path); ?>" alt="<?php echo htmlspecialchars($video['creator_name']); ?>" class="creator-avatar">
                            <?php else: ?>
                                <div class="creator-avatar" style="background: linear-gradient(45deg, #e50914, #ff6b6b); display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; font-weight: 600;">
                                    <?php echo strtoupper(substr($video['creator_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="video-details">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <div class="video-meta">
                                    <a href="creator_channel.php?id=<?php echo $video['creator_id']; ?>" class="creator-name" onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($video['creator_name']); ?>
                                    </a>
                                    <?php if ($video['tag_name']): ?>
                                        <span class="tag-badge"><?php echo htmlspecialchars($video['tag_name']); ?></span>
                                    <?php endif; ?>
                                    <div class="video-stats">
                                        <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?></span>
                                        <span>•</span>
                                        <span><?php echo date('M j, Y', strtotime($video['date_uploaded'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // User dropdown functionality
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const dropdown = document.getElementById('userDropdown');
            
            if (userDropdown && !userDropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Create floating particles
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 20 + 10) + 's';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

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

        // Observe all video cards
        document.querySelectorAll('.video-card').forEach(card => {
            observer.observe(card);
        });

        // Enhanced search functionality
        const searchInput = document.querySelector('.search-input');
        const searchContainer = document.querySelector('.search-container');

        searchInput.addEventListener('focus', () => {
            searchContainer.style.transform = 'translateY(-2px) scale(1.02)';
        });

        searchInput.addEventListener('blur', () => {
            if (!searchInput.value) {
                searchContainer.style.transform = '';
            }
        });

        // Prevent form submission on empty search
        document.querySelector('.search-container').addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (!searchInput.value.trim() && !searchInput.value) {
                const url = new URL(window.location);
                url.searchParams.delete('search');
                window.location.href = url.toString();
                e.preventDefault();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }
        });

        // Smooth scroll and loading animation for tab/filter changes
        document.querySelectorAll('.tab-item, .tag-filter').forEach(link => {
            link.addEventListener('click', function(e) {
                // Add loading state
                const currentGrid = document.querySelector('.videos-grid');
                if (currentGrid) {
                    currentGrid.style.opacity = '0.5';
                    currentGrid.style.pointerEvents = 'none';
                }
                
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            });
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

        // Dynamic title update
        const originalTitle = document.title;
        let titleInterval;

        function animateTitle() {
            const titles = [originalTitle, '🎬 ' + originalTitle, '🍳 ' + originalTitle];
            let index = 0;
            
            titleInterval = setInterval(() => {
                document.title = titles[index];
                index = (index + 1) % titles.length;
            }, 3000);
        }

        // Start title animation when page is not visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                animateTitle();
            } else {
                clearInterval(titleInterval);
                document.title = originalTitle;
            }
        });

        // Performance optimization: Pause animations when not visible
        let animationsPaused = false;
        
        document.addEventListener('visibilitychange', function() {
            const particles = document.querySelectorAll('.particle');
            const backgroundAnimation = document.body;
            
            if (document.hidden && !animationsPaused) {
                particles.forEach(p => p.style.animationPlayState = 'paused');
                backgroundAnimation.style.animationPlayState = 'paused';
                animationsPaused = true;
            } else if (!document.hidden && animationsPaused) {
                particles.forEach(p => p.style.animationPlayState = 'running');
                backgroundAnimation.style.animationPlayState = 'running';
                animationsPaused = false;
            }
        });
    </script>
</body>
</html>