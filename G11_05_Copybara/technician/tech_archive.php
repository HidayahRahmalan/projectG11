<?php
require '../conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../login.php");
    exit;
}

$tech_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();


// Fetch completed reports
$sql = "SELECT * FROM user_report WHERE assigned_to = ? AND status = 'Completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Technician - Archived Reports</title>
  <style>
    body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; }
    header.staff-header { background: #4a90e2; color: white; padding: 1.3rem 0; font-size: 2rem; font-weight: 700; text-align: center; position: fixed; top: 0; width: 100%; z-index: 1000; }
    .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #253444; color: #fff; display: flex; flex-direction: column; z-index: 1100; }
        .sidebar-header { padding: 2rem 1rem 1rem 2rem; font-size: 1.1rem;  font-weight: bold; background: #1d2937; }
    .sidebar nav { flex: 1; display: flex; flex-direction: column; gap: 8px; padding: 1.5rem 0.5rem 1.5rem 2rem; }
        .sidebar nav a { color: #cdd9e5; text-decoration: none;     font-size: 0.9rem;    padding: 8px 14px; border-radius: 6px; transition: background 0.2s; font-weight: 500; display: block; }
    .sidebar nav a.active, .sidebar nav a:hover { background: #4285F4; color: #fff; }
    .sidebar .logout-link { margin-top: auto; margin-bottom: 2rem; padding-left: 2rem; }
        .sidebar .logout-link a { color: #ffbdbd; background: #a94442; font-weight: bold; text-decoration: none;     font-size: 0.9rem;padding: 8px 14px; border-radius: 6px; display: inline-block; }
        .sidebar-section-title { font-size: 0.85rem; margin-top: 1.5rem; margin-bottom: 0.7rem; font-weight: bold; color: #b8e0fc; }

    .main-content { margin-left: 220px; padding-top: 90px; padding-bottom: 2rem; min-height: 100vh; }
    .container { max-width: 1100px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    h2 { color: #253444; margin-bottom: 20px; font-size: 1.5rem; }

    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; font-size: 0.95rem; }
    th { background-color: #f2f2f2; }
    tr:hover { background-color: #f9f9f9; }

    .attachment-link { color: #2c3e50; text-decoration: underline; display: block; margin-bottom: 4px; }
    .preview-img { max-height: 80px; max-width: 80px; border-radius: 6px; margin: 4px; box-shadow: 0 1px 5px rgba(0,0,0,0.1); }
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
        <a href="tech_dashboard.php" >  <i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="tech_profile.php">      <i class="fas fa-user"></i> Profile</a>
        <div class="sidebar-section-title">Task</div>
        <a href="tech_ass.php"><i class="fas fa-tasks"></i> Assignments</a>
        <a href="tech_archive.php" class="active">  <i class="fas fa-archive"></i> Archived Reports</a>
        <div class="sidebar-section-title">Help</div>
        <a href="https://www.annamalaiuniversity.ac.in/studport/download/engg/civil%20and%20structural/Building%20Repairs%20and%20Maintenance.pdf" target="_blank"> <i class="fas fa-book"></i> Maintenance Guide</a>
    </nav>
    <div class="logout-link">
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="main-content">
  <div class="container">
    <h2>Completed Maintenance Reports</h2>
    <table>
      <thead>
        <tr>
          <th>Report ID</th>
          <th>Title</th>
          <th>Status</th>
          <th>Report Date</th>
          <th>Attachments</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($report = $reports->fetch_assoc()): ?>
          <tr>
            <td><?= $report['report_id'] ?></td>
            <td><?= htmlspecialchars($report['title']) ?></td>
            <td><?= htmlspecialchars($report['status']) ?></td>
            <td><?= date('d M Y, H:i A', strtotime($report['date_reported'])) ?></td>
            <td>
              <?php
              $rid = $report['report_id'];
              $aquery = $conn->prepare("SELECT media_id, file_type FROM attachment WHERE report_id = ?");
              $aquery->bind_param("i", $rid);
              $aquery->execute();
              $attachments = $aquery->get_result();
              while ($a = $attachments->fetch_assoc()) {
                  $media_id = $a['media_id'];
                  $type = $a['file_type'];
                  if (strpos($type, 'image/') === 0) {
                      echo "<a href='view_attachment.php?id=$media_id' target='_blank'>
                              <img src='view_attachment.php?id=$media_id' class='preview-img' alt='Attachment'>
                            </a>";
                  } else {
                      echo "<a class='attachment-link' href='view_attachment.php?id=$media_id' target='_blank'>Download #$media_id</a>";
                  }
              }
              ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
