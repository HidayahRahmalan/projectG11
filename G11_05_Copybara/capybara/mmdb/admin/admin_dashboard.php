<?php
session_start();
require '../conn.php';

// Only allow admin
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) {
    header("Location: ../login.php");
    exit;
}
$name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

// Fetch user info from DB
$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();
if (empty($profilepic) || !file_exists('../' . $profilepic)) {
    $profilepic = 'profilepic/default.jpeg';
}
$profilepic_path = '../' . $profilepic;

// Quick stats queries
$totalReports = $conn->query("SELECT COUNT(*) FROM user_report")->fetch_row()[0];
$openReports = $conn->query("SELECT COUNT(*) FROM user_report WHERE status='Submitted' OR status='In Progress'")->fetch_row()[0];
$completedReports = $conn->query("SELECT COUNT(*) FROM user_report WHERE status='Completed'")->fetch_row()[0];
$overdueReports = $conn->query("SELECT COUNT(*) FROM user_report WHERE status!='Completed' AND date_reported < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_row()[0];
$totalUsers = $conn->query("SELECT COUNT(*) FROM sys_user")->fetch_row()[0];
$unassignedReports = $conn->query("SELECT COUNT(*) FROM user_report WHERE assigned_to IS NULL")->fetch_row()[0];

// Chart Data: Reports by Status
$statusRes = $conn->query("SELECT status, COUNT(*) as count FROM user_report GROUP BY status");
$statuses = [];
$statusCounts = [];
while ($row = $statusRes->fetch_assoc()) {
    $statuses[] = $row['status'];
    $statusCounts[] = $row['count'];
}

// Recent Activity
$recentActivity = $conn->query(
    "SELECT r.title, h.status, u.name as changed_by, h.changed_at 
     FROM report_hist h 
     JOIN user_report r ON h.report_id = r.report_id 
     JOIN sys_user u ON h.changed_by = u.user_id 
     ORDER BY h.changed_at DESC 
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - MRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
        }
        header.admin-header {
            width: 100%;
            background: #5481a7;
            color: white;
            padding: 1.3rem 0;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 220px;
            height: 100vh;
            background: #253444;
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 1100;
        }
        .sidebar-header { padding: 2rem 1rem 1rem 2rem; font-size: 1.1rem;  font-weight: bold; background: #1d2937; }
        .sidebar nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 1.5rem 0.5rem 1.5rem 2rem;
        }
        .sidebar-section-title { font-size: 0.85rem; margin-top: 1.5rem; margin-bottom: 0.7rem; font-weight: bold; color: #b8e0fc; }

        .sidebar nav a { color: #cdd9e5; text-decoration: none;     font-size: 0.9rem;    padding: 8px 14px; border-radius: 6px; transition: background 0.2s; font-weight: 500; display: block; }

        .sidebar nav a.active, .sidebar nav a:hover {
            background: #4285F4;
            color: #fff;
        }
        .sidebar .logout-link {
            margin-top: auto;
            margin-bottom: 2rem;
            padding-left: 2rem;
        }
        .sidebar .logout-link a { color: #ffbdbd; background: #a94442; font-weight: bold; text-decoration: none;     font-size: 0.9rem;padding: 8px 14px; border-radius: 6px; display: inline-block; }

        .main-content {
            margin-left: 220px;
            padding-top: 70px;
            padding-bottom: 2rem;
            min-height: 100vh;
            background: #f7fafc;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .welcome-bar {
            margin: 1.5rem 0 2rem 0;
            font-size: 1.1rem;
            color: #283e51;
        }
        .cards {
            display: flex;
            gap: 30px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 1.8rem 3rem;
            flex: 1 1 200px;
            min-width: 200px;
            max-width: 300px;
            text-align: center;
        }
        .card h3 { margin: 0 0 12px 0; color: #253444; font-size: 1.2rem; font-weight: bold; }
        .card p { font-size: 2rem; margin: 0; font-weight: bold;}
        .dashboard-section {
            margin-top: 3rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 2rem;
        }
        .dashboard-section h2 {
            margin-top: 0;
            color: #4b79a1;
            font-size: 1.3rem;
        }
        .dashboard-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .dashboard-section th, .dashboard-section td {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #e8e8e8;
            text-align: left;
        }
        .dashboard-section th {
            background: #f7fafc;
        }
        .dashboard-section tr:last-child td {
            border-bottom: none;
        }
        @media (max-width: 1100px) {
            .cards { gap: 14px; }
            .card { padding: 1.2rem 1.1rem; }
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding-top: 70px; }
            .sidebar { position: static; width: 100%; min-height: auto; flex-direction: row; }
            .sidebar-header, .sidebar nav, .sidebar .logout-link { padding-left: 1rem; }
            .dashboard-section { padding: 1rem; }
            .container { padding: 0 8px; }
            header.admin-header { font-size: 1.2rem; }
        }
        @media (max-width: 600px) {
            .cards { gap: 10px; flex-direction: column; }
            .card { min-width: 100%; }
            .container { padding: 0 2px; }
        }
    </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
    <header class="admin-header">
        Maintenance Report System - Admin Dashboard
    </header>
<aside class="sidebar">
<div class="sidebar-header" style="display: flex; align-items: center; gap: 10px;">
    <img src="<?= htmlspecialchars($profilepic_path) ?>" alt="Profile Picture"
         style="width: 24px; height: 24px; object-fit: cover; border-radius: 50%;">
    <div style="font-size: 1.1rem; color: #fff;">MRS Admin</div>
</div>
    <nav>
        <a href="admin_dashboard.php"  class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <div class="sidebar-section-title">User Management</div>
        <a href="manage_staff.php"><i class="fas fa-user-tie"></i> Staff</a>
        <a href="manage_technician.php"><i class="fas fa-user-cog"></i> Technician </a>
        <div class="sidebar-section-title">Report Management</div>
        <a href="assign_report.php"><i class="fas fa-tasks"></i> Assign Report</a>
        <a href="view_report_history.php"><i class="fas fa-history"></i> View Report</a>
                        <div class="sidebar-section-title"> My Profile</div>     
        <a href="admin_profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </nav>
    <div class="logout-link"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>
    <div class="main-content">
        <div class="container">
            <div class="welcome-bar">
                Welcome, <?= $name ?>!
            </div>
            <div class="cards">
                <div class="card"><h3>Total Reports</h3><p><?= $totalReports ?></p></div>
                <div class="card"><h3>Open Reports</h3><p><?= $openReports ?></p></div>
                <div class="card"><h3>Completed Reports</h3><p><?= $completedReports ?></p></div>
                <div class="card"><h3>Overdue Reports</h3><p><?= $overdueReports ?></p></div>
                <div class="card"><h3>Total Users</h3><p><?= $totalUsers ?></p></div>
                <div class="card"><h3>Unassigned Reports</h3><p><?= $unassignedReports ?></p></div>
            </div>

            <div class="dashboard-section">
                <h2>Reports by Status</h2>
                <canvas id="reportStatusChart" height="90"></canvas>
            </div>

            <div class="dashboard-section" style="margin-top:2rem;">
                <h2>Recent Activity</h2>
                <table>
                    <tr>
                        <th>Report</th>
                        <th>Status</th>
                        <th>Changed By</th>
                        <th>Changed At</th>
                    </tr>
                    <?php while($row = $recentActivity->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['changed_by']) ?></td>
                        <td><?= htmlspecialchars($row['changed_at']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($recentActivity->num_rows == 0): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;color:#888;">No recent activity.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <script>
        const ctx = document.getElementById('reportStatusChart').getContext('2d');
        const reportStatusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($statuses) ?>,
                datasets: [{
                    label: 'Reports by Status',
                    data: <?= json_encode($statusCounts) ?>,
                    backgroundColor: [
                        'rgba(66, 133, 244, 0.6)',
                        'rgba(251, 188, 5, 0.6)',
                        'rgba(52, 168, 83, 0.6)',
                        'rgba(234, 67, 53, 0.6)',
                        'rgba(123, 31, 162, 0.6)'
                    ],
                    borderColor: [
                        'rgba(66, 133, 244, 1)',
                        'rgba(251, 188, 5, 1)',
                        'rgba(52, 168, 83, 1)',
                        'rgba(234, 67, 53, 1)',
                        'rgba(123, 31, 162, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    </script>
</body>
</html>