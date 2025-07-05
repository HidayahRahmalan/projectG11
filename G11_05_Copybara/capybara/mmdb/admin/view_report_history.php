<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) {
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
if (empty($profilepic) || !file_exists('../' . $profilepic)) {
    $profilepic = 'profilepic/default.jpeg';
}
$profilepic_path = '../' . $profilepic;

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

function getHistories($conn, $report_id) {
    $stmt = $conn->prepare("SELECT h.*, u.name AS changer_name FROM report_hist h JOIN sys_user u ON h.changed_by = u.user_id WHERE h.report_id = ? ORDER BY h.changed_at DESC");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getAttachments($conn, $report_id) {
    $stmt = $conn->prepare("SELECT media_id, file_name, file_type FROM attachment WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$status_sql = $filter_status ? "WHERE r.status = ?" : "";
$count_query = "SELECT COUNT(*) FROM user_report r $status_sql";
$count_stmt = $conn->prepare($count_query);
if ($filter_status) {
    $count_stmt->bind_param("s", $filter_status);
}
$count_stmt->execute();
$totalReports = $count_stmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalReports / $limit);

$query = "SELECT r.*, s.name AS submitter_name, t.name AS technician_name 
    FROM user_report r 
    JOIN sys_user s ON r.submitted_by = s.user_id 
    LEFT JOIN sys_user t ON r.assigned_to = t.user_id 
    $status_sql 
    ORDER BY r.date_reported DESC LIMIT ? OFFSET ?";

$reports = $filter_status 
    ? $conn->prepare($query) 
    : $conn->prepare(str_replace("$status_sql", "", $query));

if ($filter_status) {
    $reports->bind_param("sii", $filter_status, $limit, $offset);
} else {
    $reports->bind_param("ii", $limit, $offset);
}
$reports->execute();
$reportResult = $reports->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Report History - MRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* [same style as before, not changed] */
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
        }
        header.admin-header {
            width: 100%;
            background: #5481a7;
            color: white;
            padding: 1.3rem 0;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 220px;
            height: 100vh;
            background: #253444;
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 1100;
        }
        .sidebar-header {
            padding: 2rem 1rem 1rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            background: #1d2937;
        }
        .sidebar nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 1.5rem 0.5rem 1.5rem 2rem;
        }
        .sidebar-section-title {
            font-size: 0.85rem;
            margin-top: 1.5rem;
            margin-bottom: 0.7rem;
            font-weight: bold;
            color: #b8e0fc;
        }
        .sidebar nav a {
            color: #cdd9e5;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 14px;
            border-radius: 6px;
            transition: background 0.2s;
            font-weight: 500;
            display: block;
        }
        .sidebar nav a.active, .sidebar nav a:hover {
            background: #4285F4;
            color: #fff;
        }
        .sidebar .logout-link {
            margin-top: auto;
            margin-bottom: 2rem;
            padding-left: 2rem;
        }
        .sidebar .logout-link a {
            color: #ffbdbd;
            background: #a94442;
            font-weight: bold;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 14px;
            border-radius: 6px;
            display: inline-block;
        }
        .main-content {
            margin-left: 220px;
            padding-top: 70px;
            padding-bottom: 2rem;
            min-height: 100vh;
            background: #f7fafc;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }
        h2 {
            color: #253444;
            margin: 1.5rem 0 1rem;
            font-size: 1.7rem;
            font-weight: bold;
        }
        .report-box {
            background: #fff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .attachments img, .attachments video {
            max-height: 80px;
            max-width: 120px;
            margin-right: 10px;
            border: 1px solid #ccc;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .history-table th, .history-table td {
            border-bottom: 1px solid #e8e8e8;
            padding: 0.7rem 1rem;
            text-align: left;
        }
        .history-table th {
            background: #f7fafc;
        }
        .history-table tr:last-child td {
            border-bottom: none;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 4px;
            background: #5481a7;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .pagination a.active, .pagination a:hover {
            background: #2c5282;
            font-weight: bold;
        }
        .attachment-preview-img {
            display:inline-block;
            vertical-align: middle;
            margin: 4px;
            border-radius: 6px;
            box-shadow: 0 0 3px #ccc;
        }
        form[method="get"] {
            margin-bottom: 1.5rem;
        }
        form[method="get"] label {
            font-weight: 500;
            color: #253444;
            margin-right: 8px;
        }
        form[method="get"] select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
            background: #fff;
            color: #253444;
        }
        @media (max-width: 1100px) {
            .container { padding: 0 8px; }
            .main-content { margin-left: 0; padding-top: 70px; }
            .cards { gap: 14px; }
        }
        @media (max-width: 900px) {
            header.admin-header { font-size: 1.2rem; }
            .main-content { margin-left: 0; padding-top: 70px; }
            .sidebar { position: static; width: 100%; min-height: auto; flex-direction: row; }
            .sidebar-header, .sidebar nav, .sidebar .logout-link { padding-left: 1rem; }
            .container { padding: 0 8px; }
        }
        @media (max-width: 600px) {
            .container { padding: 0 2px; }
            .report-box { padding: 0.6rem; }
            .history-table th, .history-table td { padding: 0.45rem 0.6rem; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="admin-header">Maintenance Report System - Admin Dashboard</header>
    <aside class="sidebar">
<div class="sidebar-header" style="display: flex; align-items: center; gap: 10px;">
    <img src="<?= htmlspecialchars($profilepic_path) ?>" alt="Profile Picture"
         style="width: 24px; height: 24px; object-fit: cover; border-radius: 50%;">
    <div style="font-size: 1.1rem; color: #fff;">MRS Admin</div>
</div>
        <nav>
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <div class="sidebar-section-title">User Management</div>
            <a href="manage_staff.php"><i class="fas fa-user-tie"></i> Staff</a>
            <a href="manage_technician.php"><i class="fas fa-user-cog"></i> Technician </a>
            <div class="sidebar-section-title">Report Management</div>
            <a href="assign_report.php"><i class="fas fa-tasks"></i> Assign Report</a>
            <a href="view_report_history.php" class="active"><i class="fas fa-history"></i> View Report</a>
                                    <div class="sidebar-section-title"> My Profile</div>     
        <a href="admin_profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        </nav>
        <div class="logout-link"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>
    <div class="main-content">
        <div class="container">
            <h2>Report History</h2>
            <form method="get">
                <label for="status">Filter by Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Assigned" <?= $filter_status == 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="In Progress" <?= $filter_status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed" <?= $filter_status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </form>
            <?php while ($row = $reportResult->fetch_assoc()): ?>
                <div class="report-box">
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                    <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?> | <strong>Reported by:</strong> <?= htmlspecialchars($row['submitter_name']) ?> | <strong>Technician:</strong> <?= $row['technician_name'] ?? 'Unassigned' ?></p>
                    <p><strong>Date Reported:</strong> <?= $row['date_reported'] ?></p>
                    <div class="attachments">
                        <strong>Attachments:</strong><br>
                        <?php $attachments = getAttachments($conn, $row['report_id']);
                        if (count($attachments) > 0): ?>
                            <?php foreach ($attachments as $att):
                                $label = $att['file_name'] ? htmlspecialchars($att['file_name']) : 'Attachment '.$att['media_id'];
                                $file_type = $att['file_type'] ?? '';
                                $file_name = $att['file_name'] ?? '';
                                $is_image = $is_video = false;
                                if ($file_type && strpos($file_type, 'image/') === 0) {
                                    $is_image = true;
                                } elseif ($file_name) {
                                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp'])) $is_image = true;
                                    if (in_array($ext, ['mp4','webm','ogg','mov','avi','mkv'])) $is_video = true;
                                } elseif ($file_type && strpos($file_type, 'video/') === 0) {
                                    $is_video = true;
                                }
                            ?>
                                <?php if ($is_image): ?>
                                    <img src="../attachment.php?media_id=<?= $att['media_id'] ?>" alt="<?= $label ?>" class="attachment-preview-img">
                                <?php elseif ($is_video): ?>
                                    <video class="attachment-preview-img" controls style="max-width:120px;max-height:80px;">
                                        <source src="../attachment.php?media_id=<?= $att['media_id'] ?>" type="<?= htmlspecialchars($file_type) ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                                <a href="../attachment.php?media_id=<?= $att['media_id'] ?>" target="_blank"> <?= $label ?> </a><br>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#888;">No attachments</span>
                        <?php endif; ?>
                    </div>
                    <table class="history-table">
                        <tr><th>Status</th><th>Changed By</th><th>Changed At</th><th>Notes</th></tr>
                        <?php $histories = getHistories($conn, $row['report_id']);
                        if ($histories->num_rows > 0):
                            while ($hist = $histories->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($hist['status']) ?></td>
                                    <td><?= htmlspecialchars($hist['changer_name']) ?></td>
                                    <td><?= htmlspecialchars($hist['changed_at']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($hist['notes'])) ?></td>
                                </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align:center;color:#aaa;">No history found</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endwhile; ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>