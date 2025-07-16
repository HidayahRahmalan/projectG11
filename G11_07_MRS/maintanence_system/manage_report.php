<?php
session_start();
require_once "config/database.php";
include "includes/header.php";

// Check if staff is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'Staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['id'];

// Get report ID from URL
$report_id = $_GET['id'] ?? null;
if (!$report_id) {
    die("No report ID provided.");
}

// Fetch report details
$sql = "SELECT * FROM report WHERE Report_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $report_id); // Report_ID is varchar(4)
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    die("Report not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Report</title>
    <style>
        body {
            font-family: Arial;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }

        .form-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        form {
            max-width: 600px;
            width: 100%;
            background: #f0f0f0;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        input[type="submit"] {
            margin-top: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        a.back {
            display: block;
            margin-top: 20px;
            text-decoration: none;
            color: #555;
            text-align: center;
        }

        h2 {
            text-align: center;
        }
    </style>
</head>
<body>

<div class="form-container">
    <form method="POST" action="update_report.php">
        <h2>Manage Report</h2>

        <input type="hidden" name="Report_ID" value="<?= htmlspecialchars($report['Report_ID']) ?>">

        <label>Title:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($report['title']) ?>" required>

        <label>Description:</label>
        <textarea name="description" required><?= htmlspecialchars($report['description']) ?></textarea>

        <label>Location:</label>
        <input type="text" name="location" value="<?= htmlspecialchars($report['location']) ?>" required>

        <label>Report Date:</label>
        <input type="text" value="<?= htmlspecialchars($report['report_date']) ?>" disabled>

        <input type="submit" value="Update Report">
        <a class="back" href="report_management.php">← Back to Report Management</a>
    </form>
</div>

</body>
</html>
