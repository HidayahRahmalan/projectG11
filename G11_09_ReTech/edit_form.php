<?php
session_start();
require 'db.php';

// ✅ Allow staff and admin only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$report_id) {
    die('Invalid report ID');
}

// ✅ Fetch the report (no user_id restriction so admin/staff can access any)
$stmt = $conn->prepare("
    SELECT r.*,
           ( SELECT media_id FROM MEDIA WHERE report_id = r.report_id ORDER BY media_id ASC LIMIT 1 ) AS first_media_id,
           ( SELECT file_path FROM MEDIA WHERE report_id = r.report_id ORDER BY media_id ASC LIMIT 1 ) AS first_media_path
    FROM REPORTS r
    WHERE r.report_id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
if (!$report) {
    die('Report not found');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $urgency = $_POST['urgency'] ?? 'Low';

    if ($title === '') $errors[] = 'Title is required.';
    if ($description === '') $errors[] = 'Description is required.';
    if ($location === '') $errors[] = 'Location is required.';

    $newFilePath = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['evidence'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading image.';
        } elseif (!in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp'])) {
            $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
        } else {
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $newFilePath = 'uploads/' . uniqid('evidence_', true) . ".$ext";
            if (!move_uploaded_file($f['tmp_name'], $newFilePath)) {
                $errors[] = 'Failed to save the uploaded image.';
            }
        }
    }

    if (!$errors) {
        // ✅ Update report (no user_id restriction)
        $upd = $conn->prepare("
            UPDATE REPORTS
            SET title = ?, description = ?, location = ?, urgency_level = ?
            WHERE report_id = ?
        ");
        $upd->bind_param("ssssi", $title, $description, $location, $urgency, $report_id);
        $upd->execute();

        // ✅ Save new image if uploaded
        if ($newFilePath) {
            $med = $conn->prepare("
                INSERT INTO MEDIA (report_id, file_path, uploaded_at)
                VALUES (?, ?, NOW())
            ");
            $med->bind_param("is", $report_id, $newFilePath);
            $med->execute();
        }

        // ✅ Insert log
        $detail = "Report #$report_id edited by {$role} user ID $user_id";
        $log = $conn->prepare("
            INSERT INTO LOGS (report_id, user_id, detail, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $log->bind_param("iis", $report_id, $user_id, $detail);
        $log->execute();

        // ✅ Redirect back
        $back = ($role === 'admin') ? 'dashboardadmin.php' : 'detailsReport_staff.php';
        header("Location: {$back}?updated=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Report #<?= $report_id ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <a href="<?= ($role === 'admin') ? 'view_reports.php' : 'detailsReport_staff.php' ?>" class="btn btn-secondary mb-4">&larr; Back</a>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Report #<?= $report_id ?></h4>
        </div>
        <div class="card-body">

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($report['title']) ?>" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($report['location']) ?>" class="form-control" required>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="4" class="form-control" required><?= htmlspecialchars($report['description']) ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Urgency Level</label>
                    <select name="urgency" class="form-select" required>
                        <?php
                        foreach (['Low', 'Medium', 'High', 'Critical'] as $level) {
                            $sel = $level === $report['urgency_level'] ? 'selected' : '';
                            echo "<option $sel>$level</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Replace Evidence (optional)</label>
                    <input class="form-control" type="file" name="evidence" accept="image/*">
                    <?php if ($report['first_media_path'] && file_exists($report['first_media_path'])): ?>
                        <small class="text-muted d-block mt-1">
                            Current image:
                            <img src="<?= htmlspecialchars($report['first_media_path']) ?>" style="max-width:120px;max-height:80px;border-radius:6px;">
                        </small>
                    <?php endif; ?>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
