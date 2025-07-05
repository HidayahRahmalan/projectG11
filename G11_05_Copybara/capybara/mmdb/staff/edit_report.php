<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 3) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

// Fetch report details
$stmt = $conn->prepare("SELECT * FROM user_report WHERE report_id = ? AND submitted_by = ? AND status = 'Pending'");
$stmt->bind_param("ii", $report_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) {
    echo "<p>Invalid or non-editable report.</p>";
    exit;
}

// Fetch existing attachments
$stmt = $conn->prepare("SELECT media_id, file_name, file_type FROM attachment WHERE report_id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$message = '';

// Update report if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);

    if (empty($title) || empty($description) || empty($location)) {
        $message = '<div class="message error">All fields are required.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE user_report SET title = ?, description = ?, location = ? WHERE report_id = ?");
        $stmt->bind_param("sssi", $title, $description, $location, $report_id);
        $stmt->execute();

        // Delete selected attachments
        if (!empty($_POST['delete_attachments'])) {
            foreach ($_POST['delete_attachments'] as $media_id) {
                $stmtDel = $conn->prepare("DELETE FROM attachment WHERE media_id = ? AND report_id = ?");
                $stmtDel->bind_param("ii", $media_id, $report_id);
                $stmtDel->execute();
            }
        }

        // Handle new attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
                $fileData = file_get_contents($tmpName);
                $fileName = $_FILES['attachments']['name'][$index];
                $fileType = $_FILES['attachments']['type'][$index];
                $fileSize = $_FILES['attachments']['size'][$index];
                $uploadError = $_FILES['attachments']['error'][$index];

                // Validate file type and size (same as submit_report.php)
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
                $maxFileSize = 50 * 1024 * 1024; // 50MB

                if ($uploadError !== UPLOAD_ERR_OK || !in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                    continue;
                }

                if (!$fileData) {
                    continue;
                }

                $stmtAttach = $conn->prepare("INSERT INTO attachment (report_id, media_data, file_name, file_type) VALUES (?, ?, ?, ?)");
                $null = NULL;
                $stmtAttach->bind_param("ibss", $report_id, $null, $fileName, $fileType);
                $stmtAttach->send_long_data(1, $fileData);
                $stmtAttach->execute();
                $stmtAttach->close();
            }
        }

        $message = '<div class="message success">Report updated successfully.</div>';
        // Refresh attachments after update
        $stmt = $conn->prepare("SELECT media_id, file_name, file_type FROM attachment WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Report - MRS</title>
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
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 6px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        button, #micBtn, #addMoreBtn { background: #4285F4; color: white; padding: 10px 18px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        button:hover, #micBtn:hover, #addMoreBtn:hover { background: #2c6cd2; }
        .attachment-wrapper { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .remove-btn { background-color: #e74c3c; border: none; color: white; font-weight: bold; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
        .remove-btn:hover { background-color: #c0392b; }
        #attachment-preview img, #attachment-preview video, .existing-img { max-height: 250px; max-width: 250px; object-fit: cover; margin-right: 8px; margin-bottom: 6px; }
        .existing-attachment-block { display: inline-block; position: relative; margin-right: 8px; }
        .existing-file-link { font-size: 0.96rem; }

        /* Custom red X delete button for attachment delete */
        .x-delete-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff5f7f;
            color: #fff;
            border: none;
            border-radius: 12px;
            width: 48px;
            height: 48px;
            font-size: 2.2rem;
            font-weight: bold;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.93;
            transition: background 0.2s, opacity 0.2s;
        }
        .x-delete-btn:hover {
            background: #ff2b54;
            opacity: 1;
        }
        .existing-attachment-block {
            display: inline-block;
            position: relative;
            margin: 7px 15px 7px 0;
            vertical-align: top;
        }
        .existing-attachment-block video, .existing-attachment-block img {
            border-radius: 7px;
        }
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
    <h2>Edit Maintenance Report</h2>
    <?= $message ?>
    <form method="POST" enctype="multipart/form-data" autocomplete="off" id="edit-report-form">
      <div class="form-group">
        <label for="title">Title<span style="color:red;">*</span></label>
        <input type="text" id="title" name="title" maxlength="128" value="<?= htmlspecialchars($report['title']) ?>" required>
      </div>
      <div class="form-group">
        <label for="description">Description<span style="color:red;">*</span></label>
        <textarea id="description" name="description" maxlength="2000" required><?= htmlspecialchars($report['description']) ?></textarea>
        <div style="margin-top: 8px;">
          <label for="lang-select">Speech Language:</label>
          <select id="lang-select">
            <option value="en-US">English</option>
            <option value="ms-MY">Malay</option>
          </select>
          <button type="button" id="micBtn">🎤 Speak</button>
        </div>
      </div>
      <div class="form-group">
        <label for="location">Location<span style="color:red;">*</span></label>
        <input type="text" id="location" name="location" maxlength="255" value="<?= htmlspecialchars($report['location']) ?>" required>
      </div>
      <div class="form-group">
        <label>Current Attachments</label><br>
        <div id="existing-attachments">
        <?php foreach ($attachments as $att): ?>
          <?php
            $media_id = $att['media_id'];
            $file_type = $att['file_type'];
            $file_name = htmlspecialchars($att['file_name']);
          ?>
          <div class="existing-attachment-block" data-media-id="<?= $media_id ?>">
            <?php if (strpos($file_type, 'image/') === 0): ?>
                <img class="existing-img" src="../attachment.php?media_id=<?= $media_id ?>" alt="Attachment">
            <?php elseif ($file_type === 'video/mp4'): ?>
                <video class="existing-img" src="../attachment.php?media_id=<?= $media_id ?>" controls></video>
            <?php else: ?>
                <a class="existing-file-link" href="../attachment.php?media_id=<?= $media_id ?>" target="_blank">
                    <?= $file_name ?>
                </a>
            <?php endif; ?>
            <!-- Red X delete button -->
            <button type="button" class="x-delete-btn" title="Delete" data-media-id="<?= $media_id ?>">&#10006;</button>
            <!-- The actual hidden checkbox, toggled by JS -->
            <input type="checkbox" name="delete_attachments[]" value="<?= $media_id ?>" class="delete-attachment-checkbox" style="display:none;">
          </div>
        <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="attachments">Add More Attachment(s) (Image/MP4, max 50MB each, max 20 files)</label>
        <input type="file" id="attachment" name="attachments[]" multiple accept="image/*,video/mp4">
        <div id="attachment-preview" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;"></div>
      </div>
      <button type="submit" name="update_report">Update Report</button>
    </form>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // For preview
    const fileInput = document.getElementById('attachment');
    const previewContainer = document.getElementById('attachment-preview');
    const maxFiles = 20;

    fileInput.addEventListener('change', function () {
        previewContainer.innerHTML = '';
        if (this.files.length > maxFiles) {
            alert("Maximum " + maxFiles + " files allowed.");
            this.value = '';
            return;
        }
        Array.from(this.files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    previewContainer.appendChild(img);
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'video/mp4') {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const vid = document.createElement('video');
                    vid.controls = true;
                    vid.src = e.target.result;
                    previewContainer.appendChild(vid);
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Speech-to-text
    const micBtn = document.getElementById('micBtn');
    const descField = document.getElementById('description');
    const langSelect = document.getElementById('lang-select');
    if (micBtn && 'webkitSpeechRecognition' in window) {
      const recognition = new webkitSpeechRecognition();
      recognition.continuous = false;
      recognition.interimResults = false;
      micBtn.onclick = function () {
        recognition.lang = langSelect.value;
        recognition.start();
      };
      recognition.onresult = function (event) {
        const transcript = event.results[0][0].transcript;
        descField.value += transcript + ' ';
      };
      recognition.onerror = function (e) {
        alert("Speech recognition error: " + e.error);
      };
    }

    // X delete button for existing attachments
    // When clicked, toggle the corresponding checkbox
    document.querySelectorAll('.x-delete-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const mediaId = this.getAttribute('data-media-id');
            const block = this.closest('.existing-attachment-block');
            const checkbox = block.querySelector('.delete-attachment-checkbox');
            if (checkbox) {
                checkbox.checked = true;
                // animate fade out then hide
                block.style.transition = "opacity 0.3s";
                block.style.opacity = "0.2";
                setTimeout(function(){
                    block.style.display = "none";
                }, 280);
            }
        });
    });
});
</script>
</body>
</html>