<?php
session_start();
require_once "config/database.php";
include "includes/staff_header.php";

// Check if staff is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'Staff') {
    header("Location: index.php");
    exit();
}

$staff_id = $_SESSION['id'];

// =============================
// Fetch report statistics
// =============================
$report_stats_query = "
    SELECT 
        SUM(CASE WHEN sl.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN sl.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN sl.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        COUNT(*) AS total
    FROM report r
    JOIN (
        SELECT sl1.*
        FROM status_log sl1
        INNER JOIN (
            SELECT Report_ID, MAX(Status_ID) AS max_status
            FROM status_log
            GROUP BY Report_ID
        ) latest ON sl1.Report_ID = latest.Report_ID AND sl1.Status_ID = latest.max_status
    ) sl ON r.Report_ID = CAST(sl.Report_ID AS CHAR)
    WHERE sl.updated_by = ?
";

$stmt = $conn->prepare($report_stats_query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$stats_result = $stmt->get_result()->fetch_assoc();

// =============================
// Fetch assigned reports
// =============================
$reports_query = "
    SELECT 
        r.Report_ID,
        r.Title,
        r.Description,
        r.Location,
        r.report_date,
        MIN(m.file_path) as media_path,
        ul.label as urgency_label,
        u.name as reporter_name,
        sl.status as current_status
    FROM REPORT r
    LEFT JOIN MEDIA m ON r.Report_ID = m.Report_ID
    LEFT JOIN URGENCY_LEVEL ul ON r.Urgency_ID = ul.Urgency_ID
    LEFT JOIN USERS u ON r.User_ID = u.User_ID
    LEFT JOIN STATUS_LOG sl ON r.Report_ID = sl.Report_ID 
        AND sl.Status_ID = (
            SELECT MAX(Status_ID) 
            FROM STATUS_LOG 
            WHERE Report_ID = r.Report_ID
              AND updated_by = ?
        )
    WHERE sl.updated_by = ? AND sl.status = 'In Progress' OR sl.status = 'Completed'
    GROUP BY r.Report_ID
    ORDER BY r.report_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($reports_query);
$stmt->bind_param("ii", $staff_id, $staff_id);
$stmt->execute();
$assigned_reports = $stmt->get_result();

// Fetch pending (unassigned) reports
$pending_query = "
SELECT 
    r.Report_ID,
    r.Title,
    r.Description,
    r.Location,
    r.report_date,
    MIN(m.file_path) as media_path,
    ul.label as urgency_label,
    u.name as reporter_name,
    sl.status
FROM report r
LEFT JOIN media m ON r.Report_ID = m.Report_ID
LEFT JOIN urgency_level ul ON r.Urgency_ID = ul.Urgency_ID
LEFT JOIN users u ON r.User_ID = u.User_ID
LEFT JOIN (
    SELECT sl1.*
    FROM status_log sl1
    JOIN (
        SELECT Report_ID, MAX(Status_ID) AS max_status
        FROM status_log
        GROUP BY Report_ID
    ) latest ON sl1.Report_ID = latest.Report_ID AND sl1.Status_ID = latest.max_status
) sl ON r.Report_ID = sl.Report_ID
WHERE sl.status = 'Pending'
GROUP BY r.Report_ID
ORDER BY r.report_date DESC
LIMIT 10

";


$stmt = $conn->prepare($pending_query);
$stmt->execute();
$pending_result = $stmt->get_result();


$report_stats_query = "
    SELECT 
        SUM(CASE WHEN sl.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN sl.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN sl.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        COUNT(*) AS total
    FROM report r
    JOIN (
        SELECT sl1.*
        FROM status_log sl1
        INNER JOIN (
            SELECT Report_ID, MAX(Status_ID) AS max_status
            FROM status_log
            GROUP BY Report_ID
        ) latest ON sl1.Report_ID = latest.Report_ID AND sl1.Status_ID = latest.max_status
    ) sl ON r.Report_ID = CAST(sl.Report_ID AS CHAR)
    WHERE sl.updated_by = ?
";

$stmt = $conn->prepare($report_stats_query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$stats_result = $stmt->get_result()->fetch_assoc();

?>

<!-- Bootstrap UI -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container mt-5">
    <h2 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['name']) ?> (Staff)</h2>

    <!-- Report Statistics -->
    <div class="row text-center mb-4">
        <?php
        $stat_types = ['total' => 'info', 'pending' => 'warning', 'in_progress' => 'primary', 'completed' => 'success'];
        foreach ($stat_types as $label => $color): ?>
        <div class="col-md-3">
            <div class="card border-<?= $color ?> shadow">
                <div class="card-body">
                    <h5 class="card-title text-<?= $color ?>"><?= ucwords(str_replace('_', ' ', $label)) ?></h5>
                    <p class="card-text display-6"><?= $stats_result[$label] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Assigned Reports Table -->
    <div class="card shadow mb-5">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Latest Assigned Reports</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Urgency</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assigned_reports->num_rows > 0): ?>
                            <?php while ($row = $assigned_reports->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Title']) ?></td>
                                    <td><?= htmlspecialchars($row['Description']) ?></td>
                                    <td><?= htmlspecialchars($row['Location']) ?></td>
                                    <td><?= htmlspecialchars($row['report_date']) ?></td>
                                    <td><?= htmlspecialchars($row['urgency_label'] ?? 'Normal') ?></td>
                                    <td>
                                        <?php
                                            $status = $row['current_status'];
                                            if ($status === 'Pending') {
                                                $badge = 'warning';
                                            } elseif ($status === 'In Progress') {
                                                $badge = 'primary';
                                            } elseif ($status === 'Completed') {
                                                $badge = 'success';
                                            } else {
                                                $badge = 'secondary';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= $status ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No reports assigned yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pending Reports Table -->
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Pending Reports (Unassigned)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Urgency</th>
                            <th>Reporter</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php if ($pending_result->num_rows > 0): ?>
                                <?php while ($row = $pending_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($row['media_path'])): ?>
                                                <img src="assets/uploads/reports/<?= htmlspecialchars($row['media_path']) ?>" alt="Report Image" width="80" height="80" style="object-fit: cover; border-radius: 5px;">
                                            <?php else: ?>
                                                <span class="text-muted"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['Title'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['Description'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['Location'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['report_date'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['urgency_label'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['reporter_name'] ?? '') ?></td>
                                <td>
                                    <a href="report_management.php?id=<?= urlencode($row['Report_ID']) ?>" class="btn btn-sm btn-primary">Manage</a>
                                </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No pending reports.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                </table>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
