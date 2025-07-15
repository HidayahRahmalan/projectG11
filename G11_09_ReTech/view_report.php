<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$sql = "
SELECT r.report_id, r.title, r.description, r.location,
       r.urgency_level, r.status, r.status_details, r.report_date,
       u.username AS reporter,
       (SELECT file_path FROM MEDIA m WHERE m.report_id = r.report_id ORDER BY m.media_id ASC LIMIT 1) AS evidence
FROM REPORTS r
JOIN USERS u ON r.user_id = u.id
ORDER BY r.report_date DESC
";

$result = $conn->query($sql);
$active = 'view_reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Maintenance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; padding: 30px; font-family: 'Segoe UI', sans-serif; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .report-image { max-width: 140px; max-height: 100px; object-fit: contain; border-radius: 6px; box-shadow: 0 0 4px rgba(0,0,0,0.1); }
        .badge { font-size: 0.85rem; padding: 6px 12px; }
    </style>
</head>
<body>

<div class="container">
    <h3 class="mb-4">All Maintenance Reports</h3>

    <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-info">No reports submitted yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Reporter</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Urgency</th>
                        <th>Evidence</th>
                        <th>Status</th>
                        <th>Status Details</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['report_id'] ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['reporter']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['description'])) ?></td>
                            <td><?= htmlspecialchars($row['location']) ?></td>
                            <td><?= htmlspecialchars($row['urgency_level']) ?></td>
                            <td>
                                <?php if ($row['evidence'] && file_exists($row['evidence'])): ?>
                                    <img src="<?= htmlspecialchars($row['evidence']) ?>" class="report-image" alt="Evidence">
                                <?php else: ?>
                                    <span class="text-muted">No image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badge = [
                                    'Pending' => 'warning text-dark',
                                    'In Progress' => 'info text-dark',
                                    'Completed' => 'success'
                                ][$row['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= $row['status'] ?></span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($row['status_details'] ?? '-')) ?></td>
                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                            <td>
                                <a href="edit_status.php?id=<?= $row['report_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
