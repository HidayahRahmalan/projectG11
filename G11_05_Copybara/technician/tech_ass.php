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


// Fetch assigned reports
$sql = "SELECT ur.*, su.name
        FROM user_report ur
        JOIN sys_user su ON ur.submitted_by = su.user_id
        WHERE ur.assigned_to = ? AND ur.status != 'Completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technician - Assigned Reports</title>
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

        .inline-form select, .inline-form input[type="submit"], .inline-form input[type="file"] {
            padding: 5px; font-size: 0.9rem; margin-bottom: 4px;
        }

        .attachment-link { color: #2c3e50; text-decoration: underline; display: block; margin-bottom: 4px; }
        .preview-box button {
          font-size: 12px;
          padding: 2px 6px;
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
        <a href="tech_dashboard.php" >  <i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="tech_profile.php">      <i class="fas fa-user"></i> Profile</a>
        <div class="sidebar-section-title">Task</div>
        <a href="tech_ass.php" class="active"><i class="fas fa-tasks"></i> Assignments</a>
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
    <h2>My Assigned Maintenance Tasks</h2>
    <table>
      <thead>
        <tr>
          <th>Report ID</th>
          <th>Title</th>
            <th>Reporter</th>
          <th>Location</th>
          <th>Status</th>
          <th>Report Date</th>
          <th>Update Status</th>
          <th>Attachments</th>
          <th>Upload</th>
        </tr>
      </thead>
      <tbody>
        <?php while($report = $reports->fetch_assoc()): ?>
          <tr>
            <td><?= $report['report_id'] ?></td>
            <td><?= htmlspecialchars($report['title']) ?></td>
            <td><?= htmlspecialchars($report['name']) ?></td>
            <td><?= htmlspecialchars($report['location']) ?></td>
            <td><?= htmlspecialchars($report['status']) ?></td>
            <td><?= date('d M Y, H:i A', strtotime($report['date_reported'])) ?></td>
            <td>
            <form method="post" action="update_status.php" class="inline-form">
            <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
            
            <select name="new_status" required>
                <option value="">--Select--</option>
                <?php if ($report['status'] === 'Assigned'): ?>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <?php elseif ($report['status'] === 'In Progress'): ?>
                <option value="Completed">Completed</option>
                <?php endif; ?>
            </select><br>

            <textarea name="note" placeholder="Optional note..." rows="2" style="width:100%; margin-top:5px;"></textarea><br>

            <input type="submit" value="Update" style="margin-top:5px;">
            </form>

            </td>

            <td>
            <?php
            $rid = $report['report_id'];
            $aquery = $conn->prepare("SELECT media_id, file_type, file_name FROM attachment WHERE report_id = ?");
            $aquery->bind_param("i", $rid);
            $aquery->execute();
            $attachments = $aquery->get_result();

            while ($a = $attachments->fetch_assoc()) {
                $media_id = $a['media_id'];
                $mime = $a['file_type'];
                $filename = htmlspecialchars($a['file_name']);
                $fileLink = "view_attachment.php?id=$media_id";

                if (strpos($mime, 'image/') === 0) {
                    echo "<a href='$fileLink' target='_blank'>
                            <img src='$fileLink' 
                                style='display:block; max-height: 80px; max-width: 80px; border-radius: 6px; margin: 4px auto; box-shadow: 0 1px 5px rgba(0,0,0,0.1);' 
                                alt='$filename'>
                        </a>
                        <a href='$fileLink' target='_blank' class='attachment-link' style='text-align:center; display:block;'>$filename</a>";
                } else {
                    echo "<a class='attachment-link' href='$fileLink' target='_blank'>$filename</a><br>";
                }
            }
            $aquery->close();
            ?>
            </td>

            <td>
            <form action="upload_attachment.php" method="post" enctype="multipart/form-data" class="inline-form" onsubmit="return confirm('Upload selected attachments?');">
              <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
              <div class="file-input-wrapper" id="fileWrapper<?= $report['report_id'] ?>">
                <div class="file-group">
                  <input type="file" name="attachments[]" accept="image/*,video/mp4" onchange="previewFiles(this, <?= $report['report_id'] ?>)">
                </div>
              </div>
              <div id="preview<?= $report['report_id'] ?>" style="margin-top: 10px;"></div>
              <input type="submit" value="Upload">
            </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function previewFiles(input, reportId) {
  const previewContainer = document.getElementById('preview' + reportId);
  previewContainer.innerHTML = ''; // Clear previous previews

  const dt = new DataTransfer(); // We'll use this to rebuild the file list

  Array.from(input.files).forEach((file, index) => {
    const reader = new FileReader();
    reader.onload = function (e) {
      const container = document.createElement('div');
      container.classList.add('preview-box');
      container.style.position = 'relative';
      container.style.display = 'inline-block';
      container.style.marginRight = '6px';
      container.style.marginBottom = '8px';

      let media;
      if (file.type.startsWith('image/')) {
        media = document.createElement('img');
        media.src = e.target.result;
        media.style.maxWidth = '80px';
        media.style.maxHeight = '80px';
      } else if (file.type === 'video/mp4') {
        media = document.createElement('video');
        media.src = e.target.result;
        media.controls = true;
        media.style.maxWidth = '100px';
        media.style.maxHeight = '80px';
      }

      if (media) {
        media.style.borderRadius = '6px';
        media.style.boxShadow = '0 1px 5px rgba(0,0,0,0.1)';
        container.appendChild(media);
      }

      const delBtn = document.createElement('button');
      delBtn.innerHTML = '❌';
      delBtn.type = 'button';
      delBtn.style.position = 'absolute';
      delBtn.style.top = '0';
      delBtn.style.right = '0';
      delBtn.style.background = 'rgba(255,0,0,0.7)';
      delBtn.style.color = 'white';
      delBtn.style.border = 'none';
      delBtn.style.borderRadius = '4px';
      delBtn.style.cursor = 'pointer';

      delBtn.onclick = function () {
        container.remove();

        // Remove this file from the FileList by rebuilding it
        const newDt = new DataTransfer();
        Array.from(input.files).forEach((f, i) => {
          if (i !== index) newDt.items.add(f);
        });
        input.files = newDt.files;

        previewFiles(input, reportId); // Rebuild preview after file is removed
      };

      container.appendChild(delBtn);
      previewContainer.appendChild(container);
    };
    reader.readAsDataURL(file);
  });
}
</script>


</body>
</html>