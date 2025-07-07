<?php
session_start();
require '../conn.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Role 3 = STAFF
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 3) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);
$message = '';

// Fetch user info from DB
$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);

    if (empty($title) || empty($description) || empty($location)) {
        $message = '<div class="message error">All fields are required.</div>';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO user_report (title, description, location, submitted_by, status) VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("sssi", $title, $description, $location, $user_id);
            $stmt->execute();
            $report_id = $conn->insert_id;

            $fileCount = 0;
            if (!empty($_FILES['attachment']['name'][0])) {
                foreach ($_FILES['attachment']['tmp_name'] as $index => $tmpName) {
                    $fileName = $_FILES['attachment']['name'][$index];
                    $fileType = $_FILES['attachment']['type'][$index];
                    $fileSize = $_FILES['attachment']['size'][$index];
                    $uploadError = $_FILES['attachment']['error'][$index];

                    // DEBUG: Log upload errors (comment out in production)
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        error_log("Upload error for file $fileName: $uploadError");
                        continue;
                    }

                    // Validate file type and size
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
                    $maxFileSize = 50 * 1024 * 1024; // 50MB

                    if (!in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                        continue;
                    }

                    $fileData = file_get_contents($tmpName);
                    if (!$fileData) {
                        continue;
                    }

                    $stmtAttach = $conn->prepare("INSERT INTO attachment (report_id, media_data, file_name, file_type) VALUES (?, ?, ?, ?)");
                    $null = NULL;
                    $stmtAttach->bind_param("ibss", $report_id, $null, $fileName, $fileType);
                    $stmtAttach->send_long_data(1, $fileData);
                    $stmtAttach->execute();
                    $stmtAttach->close();
                    $fileCount++;
                }
            }

            $conn->commit();
            $message = '<div class="message success">Report submitted successfully' . ($fileCount ? " with $fileCount attachment(s)" : '') . '!</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="message error">Error: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Report - MRS</title>
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
        #attachment-preview img, #attachment-preview video { max-height: 80px; max-width: 80px; object-fit: cover; }
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
        <a href="staff_dashboard.php">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <div class="sidebar-section-title">My Profile</div>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <div class="sidebar-section-title"> Report Management</div>
        <a href="submit_report.php"  class="active">
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
        <h2>Submit Maintenance Report</h2>
        <?= $message ?>
        <form method="POST" enctype="multipart/form-data" autocomplete="off">
            <div class="form-group">
                <label for="title">Title<span style="color:red;">*</span></label>
                <input type="text" id="title" name="title" maxlength="128" required>
            </div>
            <div class="form-group">
                <label for="description">Description<span style="color:red;">*</span></label>
                <textarea id="description" name="description" maxlength="2000" required></textarea>
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
                <input type="text" id="location" name="location" maxlength="255" required>
            </div>
            <div class="form-group">
                <label>Attachments (Image/MP4, max 50MB each, max 20 files)</label>
                <div id="attachment-fields">
                    <div class="attachment-wrapper">
                        <input type="file" name="attachment[]" class="attachment-field" accept="image/*,video/mp4">
                        <button type="button" class="remove-btn" style="display:none;">❌</button>
                    </div>
                </div>
                <button type="button" id="addMoreBtn">Add More Attachments</button>
                <div id="attachment-preview" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;"></div>
            </div>
            <button type="submit" name="submit_report">Submit Report</button>
        </form>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const micBtn = document.getElementById('micBtn');
    const descField = document.getElementById('description');
    const langSelect = document.getElementById('lang-select');
    const previewContainer = document.getElementById('attachment-preview');
    const attachmentFields = document.getElementById('attachment-fields');
    const addMoreBtn = document.getElementById('addMoreBtn');
    const maxFiles = 20;

    // Speech recognition
    if ('webkitSpeechRecognition' in window && micBtn) {
        const recognition = new webkitSpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;

        micBtn.onclick = () => {
            recognition.lang = langSelect.value;
            recognition.start();
        };

        recognition.onresult = event => {
            const transcript = event.results[0][0].transcript;
            descField.value += transcript + ' ';
        };

        recognition.onerror = e => {
            alert("Speech recognition error: " + e.error);
        };
    }

    // File input preview & removal
    function handleFileInput(wrapper, input) {
        input.addEventListener('change', () => {
            // Remove previous preview for this wrapper
            document.querySelectorAll(`#attachment-preview [data-wrapper="${wrapper.dataset.id}"]`).forEach(el => el.remove());
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function (e) {
                    const previewBox = document.createElement('div');
                    previewBox.style.position = 'relative';
                    previewBox.dataset.wrapper = wrapper.dataset.id;

                    let media;
                    if (file.type.startsWith('image/')) {
                        media = document.createElement('img');
                    } else if (file.type === 'video/mp4') {
                        media = document.createElement('video');
                        media.controls = true;
                    }

                    if (media) {
                        media.src = e.target.result;
                        media.style.maxHeight = '250px';
                        media.style.maxWidth = '250px';
                        media.style.objectFit = 'cover';
                        previewBox.appendChild(media);

                        const delBtn = document.createElement('button');
                        delBtn.innerHTML = '❌';
                        delBtn.type = 'button';
                        delBtn.classList.add('remove-btn');
                        delBtn.style.position = 'absolute';
                        delBtn.style.top = '0';
                        delBtn.style.right = '0';

                        delBtn.onclick = () => {
                            previewBox.remove();
                            wrapper.remove();
                        };

                        previewBox.appendChild(delBtn);
                        previewContainer.appendChild(previewBox);
                    }
                };

                reader.readAsDataURL(file);

                // Show remove button in wrapper if more than one
                if (attachmentFields.children.length > 1) {
                    wrapper.querySelector('.remove-btn').style.display = '';
                }
            }
        });
    }

    // Add attachment input
    function createAttachmentInput() {
        if (attachmentFields.children.length >= maxFiles) {
            alert("Maximum " + maxFiles + " files allowed.");
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.classList.add('attachment-wrapper');
        wrapper.dataset.id = 'wrapper-' + Date.now() + '-' + Math.floor(Math.random()*10000);

        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'attachment[]';
        input.classList.add('attachment-field');
        input.accept = 'image/*,video/mp4';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.classList.add('remove-btn');
        removeBtn.textContent = '❌';

        removeBtn.onclick = () => {
            document.querySelectorAll(`#attachment-preview [data-wrapper="${wrapper.dataset.id}"]`).forEach(el => el.remove());
            wrapper.remove();
        };
        removeBtn.style.display = '';

        wrapper.appendChild(input);
        wrapper.appendChild(removeBtn);
        attachmentFields.appendChild(wrapper);

        handleFileInput(wrapper, input);
    }

    // Initial input
    document.querySelectorAll('.attachment-field').forEach((input, idx) => {
        const wrapper = input.closest('.attachment-wrapper');
        wrapper.dataset.id = 'wrapper-init-' + idx;
        if (idx === 0) {
            wrapper.querySelector('.remove-btn').style.display = 'none';
        }
        handleFileInput(wrapper, input);
    });

    // Add more
    addMoreBtn.addEventListener('click', createAttachmentInput);
});
</script>
</body>
</html>