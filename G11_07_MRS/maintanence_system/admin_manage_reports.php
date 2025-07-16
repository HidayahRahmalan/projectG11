
<?php
session_start();
require_once "config/database.php";
include "includes/admin_header.php";

// Protect: Only Admins
if (!isset($_SESSION['id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle delete request
if (isset($_GET['delete'])) {
    $reportId = $_GET['delete'];
    $deleteStmt = $conn->prepare("DELETE FROM report WHERE Report_ID = ?");
    $deleteStmt->bind_param("s", $reportId);
    $deleteStmt->execute();
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Main query
$sql = "
SELECT 
    r.Report_ID,
    r.title,
    r.description,
    r.location,
    r.report_date,
    u.label AS urgency_label,
    sl.status AS latest_status,
    m.file_path AS media_path,
    updater.name AS staff_in_charge
FROM report r
LEFT JOIN (
    SELECT sl1.*
    FROM status_log sl1
    INNER JOIN (
        SELECT Report_ID, MAX(Status_ID) AS max_id
        FROM status_log
        GROUP BY Report_ID
    ) sl2 ON sl1.Report_ID = sl2.Report_ID AND sl1.Status_ID = sl2.max_id
) sl ON r.Report_ID = sl.Report_ID
LEFT JOIN users updater ON updater.User_ID = sl.updated_by
LEFT JOIN urgency_level u ON r.Urgency_ID = u.Urgency_ID
LEFT JOIN (
    SELECT Report_ID, MIN(Media_ID) AS first_media_id
    FROM media
    GROUP BY Report_ID
) first_media ON r.Report_ID = first_media.Report_ID
LEFT JOIN media m ON m.Media_ID = first_media.first_media_id
WHERE 1 = 1
";

// Apply filters
if (!empty($statusFilter)) {
    $sql .= " AND sl.status = '" . $conn->real_escape_string($statusFilter) . "'";
}
if (!empty($startDate)) {
    $sql .= " AND DATE(r.report_date) >= '" . $conn->real_escape_string($startDate) . "'";
}
if (!empty($endDate)) {
    $sql .= " AND DATE(r.report_date) <= '" . $conn->real_escape_string($endDate) . "'";
}
if (!empty($search)) {
    $safeSearch = $conn->real_escape_string($search);
    $sql .= " AND (r.title LIKE '%$safeSearch%' OR r.location LIKE '%$safeSearch%')";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Reports</title>
    <style>

            body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f6f9fc;
            margin: 0;
            padding: 30px 60px 30px 80px; /* Add left margin */
            color: #333;
        }

        h2 {
            color: #1a4c87;
            margin-bottom: 20px;
        }

        .filter-box {
            background-color: #fff;
            padding: 20px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-box form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }

        .filter-box label {
            font-weight: 500;
            margin-right: 5px;
        }

        select, input[type="date"], input[type="text"] {
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ccc;
            min-width: 150px;
        }

        .filter-box button {
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 5px;
            border: none;
            background-color: #1a73e8;
            color: white;
            cursor: pointer;
        }

        .filter-box button:hover {
            background-color: #125dc2;
        }

        .filter-box .reset {
            background-color: #6c757d;
        }

        .filter-box .reset:hover {
            background-color: #565e64;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 16px 14px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        th {
            background-color: #1a4c87;
            color: #ffffff;
            font-size: 15px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9fbfd;
        }

        td img {
            width: 80px;
            height: auto;
            border-radius: 4px;
        }

        td a {
            color: #1a73e8;
            text-decoration: none;
        }

        td a:hover {
            text-decoration: underline;
        }

        td form {
            display: inline-block;
            margin: 0 2px;
        }

        td form button, td form select {
            font-size: 13px;
        }

        td form button {
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        td form button:hover {
            background-color: #125dc2;
        }

        td form .delete-btn {
            background-color: #e74c3c;
        }

        td form .delete-btn:hover {
            background-color: #c0392b;
        }

        @media (max-width: 1000px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead {
                display: none;
            }

            tr {
                margin-bottom: 15px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 6px rgba(0,0,0,0.1);
                overflow: hidden;
            }

            td {
                display: flex;
                justify-content: space-between;
                padding: 12px;
                border-bottom: 1px solid #eee;
            }

            td::before {
                content: attr(data-label);
                font-weight: bold;
                color: #1a4c87;
            }
        }

        td .evidence-media {
            margin: 4px 0;
            display: block;
        }
        td img.evidence-img {
            width: 200px;
            height: auto;
            border-radius: 4px;
            margin: 3px;
        }
        td video.evidence-video {
            width: 200px;
            height: auto;
            border-radius: 4px;
            margin: 3px;
        }

        td audio {
            width: 200px;
            margin: 3px 0;
            display: block;
        }

    </style>
</head>
<body>

<h2>🗂️ Manage Reports</h2>

<div class="filter-box">
    <form method="GET">
        <label>Status:</label>
        <select name="status">
            <option value="">-- All --</option>
            <option value="Pending" <?= $statusFilter == 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="In Progress" <?= $statusFilter == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="Completed" <?= $statusFilter == 'Completed' ? 'selected' : '' ?>>Completed</option>
        </select>

        <label>Start Date:</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">

        <label>End Date:</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">

        <label>Search:</label>
        <input type="text" name="search" placeholder="Title or Location" value="<?= htmlspecialchars($search) ?>">

        <button type="submit">Apply</button>
        <button type="button" class="reset" onclick="window.location.href='admin_manage_reports.php'">Reset</button>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Report ID</th>
            <th>Title</th>
            <th>Description</th>
            <th>Location</th>
            <th>Date</th>
            <th>Urgency</th>
            <th>Media</th>
            <th>Status</th>
            <th>Staff</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
                $reportId = $row['Report_ID'];
                $urgencyLabel = $row['urgency_label'] ?? '-';
                $urgencyColor = match (strtolower($urgencyLabel)) {
                    'high' => '#e74c3c',
                    'medium' => '#f39c12',
                    'low' => '#2ecc71',
                    default => '#999',
                };
                $currentStatus = $row['latest_status'] ?? 'Pending';
                $staffInCharge = ($currentStatus == 'Pending') ? 'No staff yet' : ($row['staff_in_charge'] ?? '-');
            ?>
            <tr>
                <td><?= htmlspecialchars($reportId) ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['description'])) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['report_date']) ?></td>
                <td style="color: <?= $urgencyColor ?>; font-weight: bold;">
                    <?= htmlspecialchars($urgencyLabel) ?>
                </td>
                <td>
                    <?php if ($row['media_path']): ?>
                        <img src="assets/uploads/reports/<?= htmlspecialchars($row['media_path']) ?>" alt="Main Image" style="width:80px;">
                    <?php else: ?>
                        <span style="color: #888;">No Image</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($currentStatus) ?></td>
                <td><?= htmlspecialchars($staffInCharge) ?></td>
                <td>
                    <form method="post" action="admin_change_status.php" style="margin-bottom: 4px;">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <select name="status" required>
                            <option value="Pending" <?= $currentStatus == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="In Progress" <?= $currentStatus == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= $currentStatus == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <button type="submit">✔</button>
                    </form>

                    <form method="get" action="admin_view_reports.php" style="margin-bottom: 4px;">
                        <input type="hidden" name="id" value="<?= $reportId ?>">
                        <button type="submit">👁</button>
                    </form>

                    <form method="get" onsubmit="return confirm('Delete this report?');">
                        <input type="hidden" name="delete" value="<?= $reportId ?>">
                        <button type="submit" class="delete-btn">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
