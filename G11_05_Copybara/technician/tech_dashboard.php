<?php
require '../conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../login.php");
    exit;
}

$tech_id = $_SESSION['user_id'];

// Fetch technician name, email, profile picture
$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();

// Fetch report counts for each status
$statuses = ['In Progress', 'Completed', 'Assigned'];
$status_counts = [];
foreach ($statuses as $status) {
    $query = $conn->prepare("SELECT COUNT(*) FROM user_report WHERE assigned_to = ? AND status = ?");
    $query->bind_param("is", $tech_id, $status);
    $query->execute();
    $query->bind_result($count);
    $query->fetch();
    $status_counts[$status] = $count;
    $query->close();
}

// Fetch recent "Assigned" reports for alert
$assignedReportsRes = $conn->prepare(
    "SELECT report_id, title, description, date_reported 
     FROM user_report 
     WHERE assigned_to = ? AND status = 'Assigned'
     ORDER BY date_reported DESC
     LIMIT 5"
);
$assignedReportsRes->bind_param("i", $tech_id);
$assignedReportsRes->execute();
$assignedReports = $assignedReportsRes->get_result();
$assignedAlertCount = $assignedReports->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technician Dashboard - MRS</title>
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
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { color: #253444; margin-bottom: 10px; }
        p { color: #555; }
        .status-cards { display: flex; gap: 20px; flex-wrap: wrap; margin: 20px 0; }
        .card { flex: 1 1 200px; padding: 20px; border-radius: 10px; color: white; font-weight: bold; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .in-progress { background: #ffb74d; }
        .completed { background: #66bb6a; }
        .assigned { background: #42a5f5; }
        .quick-tools { margin-top: 30px; }
        .tool-buttons { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; }
        .tool-buttons a { background: #4a90e2; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background 0.2s; }
        .tool-buttons a:hover { background: #2c6cd2; }
        .alert-assigned {
            background: #e6f2fb;
            border-left: 7px solid #4285F4;
            color: #253444;
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 1.08rem;
            position: relative;
            box-shadow: 0 2px 10px rgba(66, 133, 244, 0.06);
        }
        .alert-assigned .alert-title {
            font-weight: bold;
            font-size: 1.1rem;
            color: #1c4587;
        }
        .alert-assigned ul {
            margin: 1rem 0 0 1.5rem;
            padding: 0;
        }
        .alert-assigned li {
            margin-bottom: 0.6rem;
            font-size: 0.98rem;
        }
        .alert-assigned .view-link {
            color: #4285F4;
            text-decoration: underline;
            font-weight: bold;
            margin-left: 12px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="staff-header">Maintenance Report System - Technician</header>
<aside class="sidebar">
<div class="sidebar-header" style="display: flex; flex-direction: column; align-items: center; padding: 1.5rem 1rem;">
    <img src="../<?= htmlspecialchars($profilepic) ?>" alt="Profile Picture"
         style="width: 70px; height: 70px; object-fit: cover; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 0 4px rgba(0,0,0,0.15); margin-bottom: 8px;">
    <div style="font-weight: bold; font-size: 0.95rem; color: #b8e0fc; margin-bottom: 4px;">
        <?= htmlspecialchars($name) ?>
    </div>
    <div style="font-size: 1.1rem; color: #fff;">MRS Technician</div>
</div>
    <nav>
        <a href="tech_dashboard.php" class="active">  <i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="tech_profile.php">      <i class="fas fa-user"></i> Profile</a>
        <div class="sidebar-section-title">Task</div>
        <a href="tech_ass.php"><i class="fas fa-tasks"></i> Assignments</a>
        <a href="tech_archive.php">  <i class="fas fa-archive"></i> Archived Reports</a>
        <div class="sidebar-section-title">Help</div>
        <a href="https://www.annamalaiuniversity.ac.in/studport/download/engg/civil%20and%20structural/Building%20Repairs%20and%20Maintenance.pdf" target="_blank"> <i class="fas fa-book"></i> Maintenance Guide</a>
    </nav>
    <div class="logout-link">
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="main-content">
    <div class="container">
        <h2>Welcome, <?= htmlspecialchars($name) ?>!</h2>
        <p>This is your technician dashboard. You can monitor and manage all your assigned maintenance tasks here.</p>

        <!-- Alert for new assigned reports -->
        <?php if ($assignedAlertCount > 0): ?>
        <div class="alert-assigned">
            <span class="alert-title">
                <i class="fas fa-bell"></i>
                <?= $assignedAlertCount ?> new report<?= $assignedAlertCount > 1 ? 's' : '' ?> assigned to you!
            </span>
            <ul>
                <?php while($row = $assignedReports->fetch_assoc()): ?>
                <li>
                    <span style="font-weight:bold;"><?= htmlspecialchars($row['title']) ?></span>
                    <span style="color:#555;">(<?= date("d M Y, H:i", strtotime($row['date_reported'])) ?>)</span>
                    <br>
                    <span style="font-size:0.97em;"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 60, '...')) ?></span>
                    <a class="view-link" href="tech_ass.php?report_id=<?= $row['report_id'] ?>">View</a>
                </li>
                <?php endwhile; ?>
            </ul>
            <?php if ($assignedAlertCount > 5): ?>
            <div style="margin-top: 10px;">
                <a href="tech_ass.php" style="color:#1c4587;font-weight:bold;text-decoration:underline;">
                    View all assigned reports
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="status-cards">
            <div class="card assigned">
                <h3><?= $status_counts['Assigned'] ?></h3>
                <p>Assigned</p>
            </div>
            <div class="card in-progress">
                <h3><?= $status_counts['In Progress'] ?></h3>
                <p>In Progress</p>
            </div>
            <div class="card completed">
                <h3><?= $status_counts['Completed'] ?></h3>
                <p>Completed</p>
            </div>
        </div>

        <div class="quick-tools">
            <h3>Quick Tools</h3>
            <div class="tool-buttons">
                <a href="tech_ass.php">📋 View Assignments</a>
                <a href="tech_archive.php">🗂 Archived Reports</a>
                <a href="https://www.annamalaiuniversity.ac.in/studport/download/engg/civil%20and%20structural/Building%20Repairs%20and%20Maintenance.pdf" target="_blank">📘 Maintenance Guide</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>