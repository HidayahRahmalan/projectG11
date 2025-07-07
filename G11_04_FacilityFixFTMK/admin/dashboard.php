<?php
session_start();
include '../dbconnection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch counts for each report status
$countQuery = "SELECT Status, COUNT(*) as count FROM Maintenance_report GROUP BY Status";
$countResult = $conn->query($countQuery);
$statusCounts = [];
while ($row = $countResult->fetch_assoc()) {
    $statusCounts[$row['Status']] = $row['count'];
}

// Calculate total count
$totalCount = array_sum($statusCounts);

// Fetch counts for each category
$categoryQuery = "SELECT Category, COUNT(*) as count FROM Maintenance_report GROUP BY Category";
$categoryResult = $conn->query($categoryQuery);
$categoryCounts = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categoryCounts[$row['Category']] = $row['count'];
}

// Fetch reports based on selected status and filters
$statusParam = isset($_GET['status']) ? $_GET['status'] : 'All';
if ($statusParam === 'All') {
    $status = "'New', 'In Progress', 'Resolved'";
} else {
    $status = "'" . $statusParam . "'";
}
$category = isset($_GET['category']) ? $_GET['category'] : '';
$urgency = isset($_GET['urgency']) ? $_GET['urgency'] : '';
$uploader = isset($_GET['uploader']) ? $_GET['uploader'] : '';

// Build the query with filters
$query = "SELECT r.*, 
                 (SELECT MAX(Action_time) FROM Systemlog WHERE Report_id = r.Report_id) AS last_updated,
                 u.Name AS uploader_name
          FROM Maintenance_report r
          JOIN Users u ON r.User_id = u.User_id
          WHERE r.Status IN ($status)";

if ($category) {
    $query .= " AND r.Category = ?";
    $params[] = $category;
}
if ($urgency) {
    $query .= " AND r.Urgency_level = ?";
    $params[] = $urgency;
}
if ($uploader) {
    $query .= " AND u.Name LIKE ?";
    $params[] = '%' . $uploader . '%';
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>FacilityFix.FTMK</title>
    <link rel="stylesheet" href="..\assests\style.css">
</head>

<body>
    <div class="container-scroller-2">
        <!-- mavigation bar -->
        <nav class="navbar fixed-top">
            <div>
                <h1>FacilityFix.FTMK</h1>
            </div>
            <div>
                <ul>
                    <a href="..\logout.php">
                        <i class="text"></i>
                        Logout
                    </a>
                </ul>
            </div>
        </nav>

        <div class="page-body-wrapper">
            <!-- sidebar -->
            <nav class="sidebar">
                <div class="row">
                    <img class="profile-img" src="../uploads/profile picture.jpg" alt="Profile picture">
                </div>
                <h3>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></h3>
                <hr></br>
                <ul class="nav">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- Report -->
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">
                            <span>Report</span>
                        </a>
                    </li>
                </ul>

            </nav>

            <!-- content -->
            <div class="main-panel">
                </br></br>

                <!-- show count of reports of all and every status -->
                <div class="row">
                    <div class="card card-color">
                        <small>All</small>
                        <p class="center"><?= $totalCount; ?></p>
                    </div>
                    <div class="card card-color badge-danger">
                        <small>New</small>
                        <p class="center"><?= isset($statusCounts['New']) ? $statusCounts['New'] : 0; ?></p>
                    </div>
                    <div class="card card-color badge-warning">
                        <small>In Progress</small>
                        <p class="center"><?= isset($statusCounts['In Progress']) ? $statusCounts['In Progress'] : 0; ?></p>
                    </div>
                    <div class="card card-color badge-success">
                        <small>Resolved</small>
                        <p class="center"><?= isset($statusCounts['Resolved']) ? $statusCounts['Resolved'] : 0; ?></p>
                    </div>
                </div>

                <div class="row">
                    <div class="card" style="width: 500px;">
                        <div class="row" style="margin-bottom: 15px;">
                            <strong>Reports by Category</strong>
                        </div>

                        <?php
                        // Define all possible categories
                        $allCategories = ['Equipment', 'Facility', 'Safety', 'Cleaning', 'Landscaping', 'IT', 'Plumbing', 'Electrical', 'HVAC', 'Grounds', 'Security', 'Pest Control'];

                        foreach ($allCategories as $cat):
                            $count = $categoryCounts[$cat] ?? 0;
                            $percentage = $totalCount > 0 ? ($count / $totalCount * 100) : 0;
                        ?>
                            <div class="row" style="display: flex; margin-bottom: 15px;">
                                <small style="width: 82px;"><?= htmlspecialchars($cat) ?></small>
                                <div class="bar-container">
                                    <div class="bar" style="width: <?= $percentage ?>%;">
                                        <?php if ($percentage >= 20): ?>
                                            <span class="bar-label"><?= $count ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <hr style="margin-bottom: 35px; color: #112D4E">
            </div>
        </div>
    </div>

    <script>
    </script>
</body>

</html>