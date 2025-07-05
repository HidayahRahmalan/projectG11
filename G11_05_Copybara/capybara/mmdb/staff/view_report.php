<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 3) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = htmlspecialchars($_SESSION['name']);

// Fetch user info from DB
$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();
// Fetch reports
$stmt = $conn->prepare("SELECT * FROM user_report WHERE submitted_by = ? ORDER BY date_reported DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();
$reports = $results->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch attachments for all reports
$attachments = [];
if (count($reports) > 0) {
    $report_ids = array_column($reports, 'report_id');
    $in = str_repeat('?,', count($report_ids) - 1) . '?';
    $types = str_repeat('i', count($report_ids));

    $stmt = $conn->prepare("SELECT report_id, media_id, file_name, file_type FROM attachment WHERE report_id IN ($in)");
    $stmt->bind_param($types, ...$report_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attachments[$row['report_id']][] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Report History - MRS</title>
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
    .main-content { margin-left: 220px; padding-top: 70px; padding-bottom: 2rem; min-height: 100vh; }
    .container { max-width: 900px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    h2 { color: #253444; margin-bottom: 20px; font-size: 1.5rem; }
    .report-block { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; background: #fefefe; }
    .report-block h3 { margin: 0 0 10px; }
    .report-block p { margin: 6px 0; }
    .attachments img { max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 6px; margin-right: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    .report-actions a {
      text-decoration: none;
      padding: 6px 12px;
      border-radius: 5px;
      margin-right: 10px;
      font-weight: 500;
    }
    .report-actions .edit-btn { background: #ffc107; color: #000; }
    .report-actions .delete-btn { background: #dc3545; color: #fff; }
  </style>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<header class="staff-header">Maintenance Report System - Staff</header>
<aside class="sidebar">
<div class="sidebar-header" style="display: flex; flex-direction: column; align-items: center; padding: 1.5rem 1rem;">
    <img src="../<?= htmlspecialchars($profilepic) ?>" alt="Profile Picture"
         style="width: 70px; height: 70px; object-fit: cover; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 0 4px rgba(0,0,0,0.15); margin-bottom: 8px;">
    <div style="font-weight: bold; font-size: 0.95rem; color: #b8e0fc; margin-bottom: 4px;">
        <?= htmlspecialchars($name) ?>
    </div>
    <div style="font-size: 1.1rem; color: #fff;">MRS Staff</div>
</div>    <nav>
        <a href="staff_dashboard.php" >
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
        <a href="view_report.php"class="active">
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
    <h2>My Report History</h2>
    <?php if (count($reports) > 0): ?>
      <?php foreach ($reports as $report): ?>
        <div class="report-block">
          <h3><?= htmlspecialchars($report['title']) ?> (Report ID: <?= $report['report_id'] ?>)</h3>
          <p><strong>Status:</strong> <?= htmlspecialchars($report['status']) ?></p>
          <p><strong>Date Reported:</strong> <?= date('d M Y, H:i A', strtotime($report['date_reported'])) ?></p>
          <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($report['description'])) ?></p>
          <p><strong>Location:</strong> <?= htmlspecialchars($report['location']) ?></p>

          <?php if (isset($attachments[$report['report_id']])): ?>
            <p><strong>Attachments:</strong></p>
            <div class="attachments">
              <?php foreach ($attachments[$report['report_id']] as $att): ?>
                <?php if (strpos($att['file_type'], 'image/') === 0): ?>
                  <a href="../attachment.php?media_id=<?= $att['media_id'] ?>" target="_blank">
                    <img src="../attachment.php?media_id=<?= $att['media_id'] ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                  </a>
                <?php else: ?>
                  <a href="../attachment.php?media_id=<?= $att['media_id'] ?>" target="_blank"><?= htmlspecialchars($att['file_name']) ?></a><br>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p><em>No attachments uploaded.</em></p>
          <?php endif; ?>

          <?php if ($report['status'] === 'Pending'): ?>
            <div class="report-actions" style="margin-top: 10px;">
              <a class="edit-btn" href="edit_report.php?report_id=<?= $report['report_id'] ?>">✏️ Edit</a>
              <a class="delete-btn" href="delete_report.php?report_id=<?= $report['report_id'] ?>" onclick="return confirm('Are you sure you want to delete this report?');">🗑️ Delete</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>You haven't submitted any reports yet.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
