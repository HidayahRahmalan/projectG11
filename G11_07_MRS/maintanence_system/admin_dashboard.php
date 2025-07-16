
<?php
session_start();
require_once "config/database.php";
include "includes/admin_header.php";

// Protect: only Admins
if (!isset($_SESSION['id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch stats
$total_users = $conn->query("SELECT COUNT(*) AS total FROM USERS")->fetch_assoc()['total'];
$total_reports = $conn->query("SELECT COUNT(*) AS total FROM REPORT")->fetch_assoc()['total'];
$total_staff = $conn->query("SELECT COUNT(*) AS total FROM USERS WHERE LOWER(role) = 'staff'")->fetch_assoc()['total'];

$status_counts = $conn->query("
    SELECT
      SUM(CASE WHEN sl.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
      SUM(CASE WHEN sl.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
      SUM(CASE WHEN sl.status = 'Completed' THEN 1 ELSE 0 END) AS completed
    FROM STATUS_LOG sl
    WHERE sl.Status_ID IN (
      SELECT MAX(Status_ID) FROM STATUS_LOG GROUP BY Report_ID
    )
")->fetch_assoc();

$recent_reports = $conn->query("
  SELECT r.Report_ID, r.Title, u.name AS reporter, r.report_date
  FROM REPORT r
  JOIN USERS u ON r.User_ID = u.User_ID
  ORDER BY r.report_date DESC
  LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      padding-top: 60px;
      background-color: #f8f9fa;
    }
    .dashboard-container {
      margin-left: 80px; /* shifts page to the right */
    }
    .card h5, .card h6 {
      margin-bottom: 0.5rem;
    }
    .table th, .table td {
      vertical-align: middle;
    }
  </style>
</head>
<body>

<div class="container dashboard-container">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Admin Dashboard</h1>
    <span class="badge bg-dark fs-6">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card bg-primary text-white shadow-sm">
        <div class="card-body text-center">
          <h5>Total Users</h5>
          <p class="display-6"><?= $total_users ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-success text-white shadow-sm">
        <div class="card-body text-center">
          <h5>Total Reports</h5>
          <p class="display-6"><?= $total_reports ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-info text-white shadow-sm">
        <div class="card-body text-center">
          <h5>Total Staff</h5>
          <p class="display-6"><?= $total_staff ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Status Overview -->
  <h4 class="mb-3">Status Overview</h4>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-warning shadow-sm">
        <div class="card-body text-center">
          <h6 class="text-warning">Pending</h6>
          <p class="fs-3"><?= $status_counts['pending'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-primary shadow-sm">
        <div class="card-body text-center">
          <h6 class="text-primary">In Progress</h6>
          <p class="fs-3"><?= $status_counts['in_progress'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body text-center">
          <h6 class="text-success">Completed</h6>
          <p class="fs-3"><?= $status_counts['completed'] ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Reports -->
  <h4 class="mb-3">Latest Reports</h4>
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>Report ID</th>
          <th>Title</th>
          <th>Reporter</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $recent_reports->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['Report_ID']) ?></td>
            <td><?= htmlspecialchars($r['Title']) ?></td>
            <td><?= htmlspecialchars($r['reporter']) ?></td>
            <td><?= htmlspecialchars($r['report_date']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include "includes/footer.php"; ?>
</body>
</html>
