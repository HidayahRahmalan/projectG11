<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Report ID missing.");
}

$report_id = intval($_GET['id']);

$sql = "SELECT r.*, u.username,
        (SELECT file_path FROM media WHERE report_id = r.report_id LIMIT 1) AS evidence
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.report_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Report not found.");
}

$report = $result->fetch_assoc();

if ($report['status'] !== 'Completed') {
    die("Report is not completed. Cannot print.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Report #<?= $report_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f9f9f9;
            padding: 30px;
        }
        .report-box {
            background: #fff;
            border: 1px solid #ccc;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,.1);
        }
        .logo {
            max-height: 70px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            margin-bottom: 30px;
            padding-bottom: 10px;
        }
        .report-title {
            font-size: 24px;
            font-weight: bold;
            margin-top: 10px;
        }
        .label {
            font-weight: 600;
            color: #333;
        }
        .evidence-box {
            margin-top: 30px;
            text-align: center;
        }
        .signature-box {
            margin-top: 60px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 40px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print mb-4 d-flex justify-content-between">
    <a href="javascript:window.print()" class="btn btn-primary">🖨️ Print This Report</a>
    <a href="view_reports.php" class="btn btn-secondary">🔙 Back to Admin Panel</a>
</div>

<div class="report-box mx-auto" style="max-width: 800px;">
    <div class="header">
        <img src="logo.png" class="logo" alt="System Logo" onerror="this.style.display='none';">
        <div class="report-title">Maintenance Report</div>
        <small>Report ID: <?= $report_id ?></small>
    </div>

    <div class="row mb-3">
        <div class="col-md-6"><span class="label">Title:</span> <?= htmlspecialchars($report['title']) ?></div>
        <div class="col-md-6"><span class="label">Location:</span> <?= htmlspecialchars($report['location']) ?></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><span class="label">Urgency Level:</span> <?= htmlspecialchars($report['urgency_level']) ?></div>
        <div class="col-md-6"><span class="label">Status:</span> <?= $report['status'] ?></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><span class="label">Submitted By:</span> <?= htmlspecialchars($report['username']) ?></div>
        <div class="col-md-6"><span class="label">Submitted On:</span> <?= date('d M Y', strtotime($report['report_date'])) ?></div>
    </div>

    <div class="signature-box mt-5">
        <p>Prepared by:</p>
        <div class="signature-line"></div>
        <p><small><?= htmlspecialchars($report['username']) ?> (Staff)</small></p>

        <p class="mt-4">Verified by (Admin):</p>
        <div class="signature-line"></div>
        <p><small><?= htmlspecialchars($_SESSION['username']) ?> (Admin)</small></p>
    </div>
</div>

</body>
</html>
