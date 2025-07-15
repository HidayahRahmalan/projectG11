<?php
session_start();
require 'db.php';

// ✅ Allow both staff and admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$report_id) {
    die('Invalid report ID');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ✅ Fetch report
$stmt = $conn->prepare("SELECT * FROM REPORTS WHERE report_id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Report not found or not authorized.");
}

$errors = [];
$success_message = '';

// ✅ Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $status_details = trim($_POST['status_details'] ?? '');

    if (!in_array($status, ['Pending', 'In Progress', 'Completed'])) {
        $errors[] = "Invalid status.";
    }

    if (!$errors) {
        // ✅ Update report status
        $update = $conn->prepare("UPDATE REPORTS SET status = ? WHERE report_id = ?");
        $update->bind_param("si", $status, $report_id);

        if (!$update->execute()) {
            die("Update Error: " . $update->error);
        }

        // ✅ Insert log entry
        $log = $conn->prepare("
            INSERT INTO LOGS (report_id, user_id, status_details)
            VALUES (?, ?, ?)
        ");
        $log->bind_param("iis", $report_id, $user_id, $status_details);

        if (!$log->execute()) {
            die("Log Insert Error: " . $log->error);
        }

        $success_message = "✅ Report status updated successfully.";
        $report['status'] = $status; // reflect new status
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <?php
        $backPage = ($_SESSION['role'] === 'admin') ? 'view_reports.php' : 'detailsReport_staff.php';
        ?>
        <a href="<?= $backPage ?>" class="btn btn-secondary mb-4">&larr; Back</a>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>Edit Status for Report #<?= $report_id ?></h4>
            </div>
            <div class="card-body">

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?= $success_message ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
                <?php endif; ?>

                <div class="mb-4">
                    <h5 class="text-secondary">📄 Report Info</h5>
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Title:</strong> <?= htmlspecialchars($report['title']) ?></li>
                        <li class="list-group-item"><strong>Description:</strong> <?= nl2br(htmlspecialchars($report['description'])) ?></li>
                        <li class="list-group-item"><strong>Current Status:</strong> <?= htmlspecialchars($report['status']) ?></li>
                        <li class="list-group-item">
                            <strong>Created At:</strong>
                            <?= !empty($report['report_date']) ? date("d M Y, h:i A", strtotime($report['report_date'])) : '<span class="text-muted">N/A</span>' ?>
                        </li>
                    </ul>
                </div>

                <!-- ✅ Update Form -->
                <h5 class="text-secondary">🛠️ Update Status</h5>
                <form method="post" action="edit_status.php?id=<?= $report_id ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Status</label>
                        <select name="status" class="form-select" required>
                            <?php
                            $statuses = ['Pending', 'In Progress', 'Completed'];
                            foreach ($statuses as $s) {
                                $sel = ($report['status'] === $s) ? 'selected' : '';
                                echo "<option value=\"$s\" $sel>$s</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Details / Notes</label>
                        <textarea name="status_details" rows="4" class="form-control"
                                  placeholder="Add explanation or remarks..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">✅ Update Status</button>

                    <a href="report_history.php?id=<?= $report['report_id'] ?>" class="btn btn-info btn-sm">
                        🕒 View Status History
                    </a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
