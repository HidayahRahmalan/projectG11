<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Count report stats
$sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
    FROM reports
";
$result = $conn->query($sql);
$countData = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { margin: 0; background: #f8f9fa; }
    .wrapper { display: flex; min-height: 100vh; }
    .content { flex-grow: 1; padding: 40px; }
    .summary-cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 30px;
    }
    .summary-card {
        flex: 1;
        min-width: 200px;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }
    .summary-card h6 { font-size: 16px; color: #777; }
    .summary-card p { font-size: 28px; font-weight: 700; margin: 0; }
  </style>
</head>
<body class="d-flex">


  <?php include 'adminSidebar.php'; ?>

  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>👋 Welcome, <?= htmlspecialchars($username) ?></h3>
        <div class="d-flex gap-2">
            <a href="export_reports.php" class="btn btn-outline-primary">📤 Export CSV</a>
            <a href="manage_users.php" class="btn btn-outline-dark">👥 Manage Users</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card border-start border-primary">
            <h6>Total Reports</h6>
            <p><?= $countData['total'] ?></p>
        </div>
        <div class="summary-card border-start border-warning">
            <h6>Pending</h6>
            <p><?= $countData['pending'] ?></p>
        </div>
        <div class="summary-card border-start border-info">
            <h6>In Progress</h6>
            <p><?= $countData['in_progress'] ?></p>
        </div>
        <div class="summary-card border-start border-success">
            <h6>Completed</h6>
            <p><?= $countData['completed'] ?></p>
        </div>
    </div>

    <!-- Chart -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">📊 Report Status Overview</h5>
        <canvas id="reportChart" height="100"></canvas>
      </div>
    </div>
  </div>


<script>
const ctx = document.getElementById('reportChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Pending', 'In Progress', 'Completed'],
        datasets: [{
            label: 'Number of Reports',
            data: [
                <?= $countData['pending'] ?>,
                <?= $countData['in_progress'] ?>,
                <?= $countData['completed'] ?>
            ],
            backgroundColor: ['#fbc02d', '#29b6f6', '#66bb6a'],
            borderRadius: 6,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
