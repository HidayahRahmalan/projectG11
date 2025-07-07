<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 3) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


$name = htmlspecialchars($_SESSION['name']);

$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - MRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; }
        header.staff-header { background: #4a90e2; color: white; padding: 1.3rem 0; font-size: 2rem; font-weight: 700; text-align: center; position: fixed; top: 0; width: 100%; z-index: 1000; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #253444; color: #fff; display: flex; flex-direction: column; z-index: 1100; }
        .sidebar-header { padding: 2rem 1rem 1rem 2rem; font-size: 1.1rem;  font-weight: bold; background: #1d2937; }
        .sidebar nav { flex: 1; display: flex; flex-direction: column; gap: 8px; padding: 1.5rem 0.5rem 1.5rem 2rem; }
        .sidebar-section-title { font-size: 0.85rem; margin-top: 1.5rem; margin-bottom: 0.7rem; font-weight: bold; color: #b8e0fc; }
        .sidebar nav a { color: #cdd9e5; text-decoration: none;     font-size: 0.9rem;    padding: 8px 14px; border-radius: 6px; transition: background 0.2s; font-weight: 500; display: block; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #4285F4; color: #fff; }
        .sidebar .logout-link { margin-top: auto; margin-bottom: 2rem; padding-left: 2rem; }
        .sidebar .logout-link a { color: #ffbdbd; background: #a94442; font-weight: bold; text-decoration: none;     font-size: 0.9rem;padding: 8px 14px; border-radius: 6px; display: inline-block; }
        .main-content { margin-left: 220px; padding-top: 70px; padding-bottom: 2rem; min-height: 100vh; background: #f7fafc; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .card-box { background: #ffffff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-box h2 { font-size: 1.5rem; margin-bottom: 10px; color: #253444; }
        .card-box p { font-size: 1rem; color: #555; }
    </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
<header class="staff-header">Maintenance Report System - Staff Dashboard</header>
<aside class="sidebar">

<div class="sidebar-header" style="display: flex; flex-direction: column; align-items: center; padding: 1.5rem 1rem;">
    <img src="../<?= htmlspecialchars($profilepic) ?>" alt="Profile Picture"
         style="width: 70px; height: 70px; object-fit: cover; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 0 4px rgba(0,0,0,0.15); margin-bottom: 8px;">
    <div style="font-weight: bold; font-size: 0.95rem; color: #b8e0fc; margin-bottom: 4px;">
        <?= htmlspecialchars($name) ?>
    </div>
    <div style="font-size: 1.1rem; color: #fff;">MRS Staff</div>
</div>    <nav>
        <a href="staff_dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <div class="sidebar-section-title">My Profile</div>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <div class="sidebar-section-title"> Report Management</div>
        <a href="submit_report.php">
            <i class="fas fa-plus-circle"></i> Submit Report
        </a>
        <a href="view_report.php">
            <i class="fas fa-file-alt"></i> My Report
        </a>
    </nav>
    <div class="logout-link">
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
<div class="main-content">
    <div class="container">
        <div class="card-box">
            <h2>Welcome, <?= $name ?>!</h2>
            <p>This is your dashboard. Use the sidebar to manage your reports and profile.</p>
        </div>
        <div class="card-box">
            <h2>Quick Links</h2>
            <ul style="list-style: none; padding: 0;">
                <li><a href="submit_report.php">➤ Submit a New Report</a></li>
                <li><a href="view_report.php">➤ View My Report History</a></li>
                <li><a href="profile.php">➤ Edit My Profile</a></li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
