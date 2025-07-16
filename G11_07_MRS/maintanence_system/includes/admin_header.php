<?php
require_once "session.php";

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if staff is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #bbdefb;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --header-height: 60px;
            --transition-speed: 0.5s;
            --transition-curve: cubic-bezier(0.4, 0, 0.2, 1);
            --menu-item-height: 45px;
        }

        body {
            min-height: 100vh;
            background: #f8f9fa;
            overflow-x: hidden;
            font-family: 'DM Sans', sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(to bottom, #bbdefb, #e3f2fd);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            transition: all var(--transition-speed) var(--transition-curve);
            backdrop-filter: blur(10px);
            will-change: width, transform;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            text-align: center;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .logo-text {
            margin: 0;
            color: #1a237e;
            white-space: nowrap;
            transition: all var(--transition-speed) var(--transition-curve);
            font-weight: 600;
        }

        .collapsed .logo-text {
            opacity: 0;
            transform: translateX(-20px);
            width: 0;
            margin: 0;
        }

        .profile-section {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition-speed) var(--transition-curve);
            background: rgba(255, 255, 255, 0.05);
        }

        .collapsed .profile-section {
            padding: 10px;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed) var(--transition-curve);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-pic i {
            font-size: 50px;
            color: #1976d2;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .collapsed .profile-pic {
            width: 40px;
            height: 40px;
            margin-bottom: 5px;
            transform: scale(0.9);
        }

        .collapsed .profile-pic img {
            transform: scale(1.1);
        }

        .collapsed .profile-pic i {
            font-size: 20px;
        }

        .username {
            font-size: 18px;
            font-weight: 600;
            color: #1a237e;
            margin: 0;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all var(--transition-speed) var(--transition-curve);
            position: relative;
        }

        .username::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #1976d2;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .username:hover::after {
            width: 100%;
        }

        .collapsed .username {
            font-size: 0;
            opacity: 0;
            transform: translateY(-10px);
        }

        .nav-menu {
            padding: 20px 10px;
            list-style: none;
            margin: 0;
            transition: padding var(--transition-speed) var(--transition-curve);
        }

        .collapsed .nav-menu {
            padding: 10px 5px;
        }

        .nav-item {
            margin-bottom: 5px;
            border-radius: 8px;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #1a237e;
            text-decoration: none;
            border-radius: 8px;
            transition: all var(--transition-speed) var(--transition-curve);
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #1976d2;
        }

        .nav-link.active {
            background: #1976d2;
            color: white;
        }

        .nav-icon {
            font-size: 20px;
            min-width: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .nav-text {
            margin-left: 10px;
            opacity: 1;
            transform: translateX(0);
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .collapsed .nav-text {
            opacity: 0;
            transform: translateX(-10px);
            width: 0;
            margin: 0;
        }

        .toggle-btn {
            background: transparent;
            border: none;
            padding: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a237e;
            border-radius: 8px;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #1976d2;
        }

        .toggle-icon {
            font-size: 24px;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .collapsed .toggle-icon {
            transform: rotate(180deg);
        }

        @media (max-width: 1200px) {
            .sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                height: 100vh;
                width: 280px !important;
                background: #bbdefb;
                z-index: 2000 !important;
                box-shadow: 2px 0 10px rgba(0,0,0,0.15);
                overflow-y: auto;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .sidebar.mobile-visible {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-top: 80px !important;
            }
            .burger-menu {
                display: flex !important;
            }
        }

        .burger-menu {
            position: fixed;
            top: 22px;
            left: 22px;
            z-index: 1100;
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.7);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(33,150,243,0.10), 0 1.5px 6px rgba(33,150,243,0.07);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            backdrop-filter: blur(8px);
            transition: background 0.2s, box-shadow 0.2s, transform 0.15s;
        }
        .burger-menu:hover, .burger-menu:focus {
            background: rgba(187,222,251,0.95);
            box-shadow: 0 6px 32px rgba(33,150,243,0.18);
            transform: scale(1.07);
            outline: none;
        }
        .burger-lines {
            width: 30px;
            height: 24px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .burger-line {
            display: block;
            height: 4.5px;
            width: 100%;
            background: #1976d2;
            border-radius: 3px;
            box-shadow: 0 1px 2px rgba(33,150,243,0.10);
            transition: 0.35s cubic-bezier(.68,-0.55,.27,1.55);
        }
        .burger-menu.active .burger-line1 {
            transform: translateY(9.5px) rotate(45deg);
        }
        .burger-menu.active .burger-line2 {
            opacity: 0;
            transform: scaleX(0.5);
        }
        .burger-menu.active .burger-line3 {
            transform: translateY(-9.5px) rotate(-45deg);
        }
    </style>
</head>
<body>
    <button class="burger-menu" id="burgerMenu" aria-label="Toggle sidebar" aria-pressed="false">
      <div class="burger-lines">
        <span class="burger-line burger-line1"></span>
        <span class="burger-line burger-line2"></span>
        <span class="burger-line burger-line3"></span>
      </div>
    </button>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <h1 class="logo-text"></h1>
            </div>
        </div>

        <div class="profile-section">
            <?php if ($_SESSION['role'] === 'Admin'): ?>
            <a href="admin_profile.php" class="profile-pic">
                <?php if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])): ?>
                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" 
                         alt="Profile Picture"
                         onerror="this.onerror=null; this.src='assets/images/default-avatar.png';">
                <?php else: ?>
                    <i class="bi bi-person"></i>
                <?php endif; ?>
            </a>
            <a href="admin_profile.php" class="username"><?php echo htmlspecialchars($_SESSION['name']); ?></a>
            <?php else: ?>
            <div class="profile-pic">
                <i class="bi bi-person"></i>
            </div>
            <span class="username"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <?php endif; ?>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-house"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_manage_reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_reports.php' ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-list-ul"></i>
                    <span class="nav-text">Manage Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-people"></i>
                    <span class="nav-text">Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="nav-icon bi bi-person"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="nav-icon bi bi-box-arrow-right"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.querySelector('.sidebar');
                const burgerMenu = document.getElementById('burgerMenu');
                const mainContent = document.querySelector('.main-content');
                let isMobile = window.innerWidth <= 1200;

                function updateSidebarState() {
                    if (isMobile) {
                        sidebar.classList.remove('collapsed');
                        sidebar.classList.remove('mobile-visible');
                    }
                }

                burgerMenu.addEventListener('click', function() {
                    if (isMobile) {
                        sidebar.classList.toggle('mobile-visible');
                        burgerMenu.classList.toggle('active');
                        burgerMenu.setAttribute('aria-pressed', burgerMenu.classList.contains('active') ? 'true' : 'false');
                    } else {
                        sidebar.classList.toggle('collapsed');
                        burgerMenu.classList.toggle('active');
                        burgerMenu.setAttribute('aria-pressed', burgerMenu.classList.contains('active') ? 'true' : 'false');
                    }
                });

                window.addEventListener('resize', function() {
                    isMobile = window.innerWidth <= 1200;
                    updateSidebarState();
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    if (isMobile && 
                        !sidebar.contains(event.target) && 
                        !burgerMenu.contains(event.target) && 
                        sidebar.classList.contains('mobile-visible')) {
                        sidebar.classList.remove('mobile-visible');
                        burgerMenu.classList.remove('active');
                        burgerMenu.setAttribute('aria-pressed', 'false');
                    }
                });

                // Initial state
                updateSidebarState();
            });
        </script>
    </div>
</body>
</html> 