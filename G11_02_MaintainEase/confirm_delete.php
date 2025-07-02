<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$report_id = $_POST['report_id'] ?? 0;

// Verify report exists and user has permission
$sql = "SELECT m.*, u.name 
        FROM maintenance m
        JOIN users u ON m.user_id = u.user_id
        WHERE m.maintenance_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    $_SESSION['error'] = "Report not found.";
    header("Location: viewquery.php");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$is_creator = ($report['user_id'] == $_SESSION['user_id']);

if (!$is_admin && !$is_creator) {
    $_SESSION['error'] = "You don't have permission to delete this report.";
    header("Location: view_reports.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Confirm Deletion</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        .confirmation-box {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .btn {
            padding: 8px 16px;
            margin: 5px;
            cursor: pointer;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            text-decoration: none;
            padding: 8px 16px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <h2>Confirm Deletion</h2>
    
    <div class="confirmation-box">
        <p>Are you sure you want to delete the following report?</p>
        
        <h3><?= htmlspecialchars($report['title']) ?></h3>
        <p><strong>Description:</strong> <?= htmlspecialchars($report['description']) ?></p>
        <p><strong>Submitted by:</strong> <?= htmlspecialchars($report['name']) ?></p>
        <p><strong>Created:</strong> <?= $report['created_at'] ?></p>
        
        <form method="POST" action="delete_report.php">
            <input type="hidden" name="report_id" value="<?= $report_id ?>">
            <button type="submit" class="btn btn-delete">Yes, Delete Permanently</button>
            <a href="viewquery.php?id=<?= $report_id ?>" class="btn btn-cancel">Cancel</a>
        </form>
    </div>
</body>
</html>
