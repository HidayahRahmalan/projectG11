<?php
session_start();
include '../dbconnection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
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
$title = isset($_GET['title']) ? $_GET['title'] : '';

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
if ($title) {
    $query .= " AND r.Title LIKE ?";
    $params[] = '%' . $title . '%';
}
$query .= " ORDER BY r.Date DESC";

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
                        <a class="nav-link" href="dashboard.php">
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- Report -->
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php">
                            <span>Report</span>
                        </a>
                    </li>
                </ul>

            </nav>

            <!-- content -->
            <div class="main-panel">
                </br></br>
                <div class="row">
                    <div>
                        <h4 class="font-weight-bold">Reports</h4>
                    </div>
                </div>

                <!-- show count of reports of all and every status -->
                <div class="row" style="display: flex;">
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

                <div class="row" style="margin: 0;">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link <?= $statusParam === 'All' ? 'active' : '' ?>" href="report.php?status=All">
                                <span class="menu-title">All</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusParam === 'New' ? 'active' : '' ?>" href="report.php?status=New">
                                <span class="menu-title">New</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusParam === 'In Progress' ? 'active' : '' ?>" href="report.php?status=In Progress">
                                <span class="menu-title">In Progress</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusParam === 'Resolved' ? 'active' : '' ?>" href="report.php?status=Resolved">
                                <span class="menu-title">Resolved</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <hr style="margin-bottom: 35px; color: #112D4E">

                <!-- Filter Section -->
                <div class="row" class="background-color: transparent;">
                    <form method="GET" action="report.php" style="display: flex;">
                        <div class="form-group">
                            <label style="flex: 0 0 110px;">Search by : </label>

                            <select name="category" class="form-control" style="margin-right: 30px; border-radius: 10px;" id="category">
                                <option value="" <?= empty($category) ? 'selected' : '' ?>>All Categories</option>
                                <option value="Equipment" <?= $category === 'Equipment' ? 'selected' : '' ?>>Equipment</option>
                                <option value="Facility" <?= $category === 'Facility' ? 'selected' : '' ?>>Facility</option>
                                <option value="Safety" <?= $category === 'Safety' ? 'selected' : '' ?>>Safety</option>
                                <option value="Cleaning" <?= $category === 'Cleaning' ? 'selected' : '' ?>>Cleaning</option>
                                <option value="Landscaping" <?= $category === 'Landscaping' ? 'selected' : '' ?>>Landscaping</option>
                                <option value="IT" <?= $category === 'IT' ? 'selected' : '' ?>>IT Support</option>
                                <option value="Plumbing" <?= $category === 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
                                <option value="Electrical" <?= $category === 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                <option value="HVAC" <?= $category === 'HVAC' ? 'selected' : '' ?>>HVAC</option>
                                <option value="Grounds" <?= $category === 'Grounds' ? 'selected' : '' ?>>Grounds Maintenance</option>
                                <option value="Security" <?= $category === 'Security' ? 'selected' : '' ?>>Security</option>
                                <option value="Pest Control" <?= $category === 'Pest Control' ? 'selected' : '' ?>>Pest Control</option>
                            </select>

                            <select name="urgency" class="form-control" style="margin-right: 30px; border-radius: 10px;" id="urgency">
                                <option value="" <?= empty($urgency) ? 'selected' : '' ?>>All Urgency Levels</option>
                                <option value="Low" <?= $urgency === 'Low' ? 'selected' : '' ?>>Low</option>
                                <option value="Medium" <?= $urgency === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="High" <?= $urgency === 'High' ? 'selected' : '' ?>>High</option>
                                <option value="Critical" <?= $urgency === 'Critical' ? 'selected' : '' ?>>Critical</option>
                            </select>

                            <input type="text" name="uploader" class="form-control" style="margin-right: 30px; border-radius: 10px;" id="uploader" placeholder="Uploader Name" value="<?= htmlspecialchars($uploader); ?>">

                            <input type="text" name="title" class="form-control" style="margin-right: 40px; border-radius: 10px;" id="title" placeholder="Title Keyword" value="<?= htmlspecialchars($title); ?>">
                        </div>
                        <button type="submit" class="btn" style="height: 39px; border-radius: 10px; margin-right: 30px;">Search</button>
                        <?php if (!empty($category) || !empty($urgency) || !empty($uploader) || !empty($title)): ?>
                            <a href="report.php?status=<?= htmlspecialchars($statusParam); ?>" class="btn btn-secondary" style="height: 39px; border-radius: 10px; text-decoration: none;">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Report List based on selected menu in navigation bar -->
                <div class="row">
                    <div class="row report-list" id="reportList">
                        <div class="card">
                            <?php
                            // Success message
                            if (isset($_SESSION['status'])) {
                            ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo $_SESSION['status']; ?>
                                </div>
                            <?php
                                unset($_SESSION['status']);
                            }

                            // Error message
                            if (isset($_SESSION['EmailMessage'])) {
                            ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $_SESSION['EmailMessage']; ?>
                                </div>
                            <?php
                                unset($_SESSION['EmailMessage']);
                            }
                            ?>

                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Urgency Level</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $index => $report): ?>
                                        <tr>
                                            <td><?= $index + 1; ?></td>
                                            <td style="text-align: left;"><?= htmlspecialchars($report['Title']); ?></td>
                                            <td><?= htmlspecialchars($report['Category']); ?></td>
                                            <td>
                                                <span class="badge <?= $report['Urgency_level'] == 'Low' ? 'badge-success' : ($report['Urgency_level'] == 'Medium' ? 'badge-warning' : ($report['Urgency_level'] == 'High' ? 'badge-danger' : 'badge-critical')); ?>">
                                                    <?= htmlspecialchars($report['Urgency_level']); ?>
                                                </span>
                                            </td>
                                            <td><?= date('d-m-Y', strtotime($report['Date'])); ?></td>
                                            <td>
                                                <span class="badge <?= $report['Status'] == 'Resolved' ? 'badge-success' : ($report['Status'] == 'In Progress' ? 'badge-warning' : 'badge-danger'); ?>">
                                                    <?= htmlspecialchars($report['Status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn" style="height: 40px; border-radius: 10px;" onclick="showDetails(<?= $report['Report_id']; ?>)">View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Report details -->
                    <?php foreach ($reports as $report):
                        // Get submitter info from Systemlog
                        $submitQuery = "SELECT u.Name, u.Position, l.Action_time 
                                    FROM Systemlog l
                                    JOIN Users u ON l.User_id = u.User_id
                                    WHERE l.Report_id = ? AND l.Action_type = 'Submit'
                                    LIMIT 1";
                        $submitStmt = $conn->prepare($submitQuery);
                        $submitStmt->bind_param("i", $report['Report_id']);
                        $submitStmt->execute();
                        $submitResult = $submitStmt->get_result();
                        $submitInfo = $submitResult->fetch_assoc();
                        $submitStmt->close();

                        // Get last updater info from Systemlog
                        $updateQuery = "SELECT u.Name, l.Action_time 
                                   FROM Systemlog l
                                   JOIN Users u ON l.User_id = u.User_id
                                   WHERE l.Report_id = ? AND l.Action_type = 'Update'
                                   ORDER BY l.Action_time DESC
                                   LIMIT 1";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $report['Report_id']);
                        $updateStmt->execute();
                        $updateResult = $updateStmt->get_result();
                        $hasUpdates = $updateResult->num_rows > 0;
                        $updateInfo = $updateResult->fetch_assoc();
                        $updateStmt->close();
                    ?>
                        <div class="row report-details" id="reportDetails<?= $report['Report_id']; ?>">
                            <div class="card">
                                <div class="row" style="margin-bottom: 0;">
                                    <h4 class="font-weight-bold">Report Details</h4>
                                </div>
                                <hr></br>

                                <!-- Title -->
                                <div class="form-group">
                                    <label>Title</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($report['Title']); ?>" readonly>
                                </div>

                                <!-- Description -->
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea class="form-control" cols="50" rows="4" readonly><?= htmlspecialchars($report['Description']); ?></textarea>
                                </div>

                                <!-- Location -->
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($report['Location']); ?>" readonly>
                                </div>

                                <!-- Uploader's name : who create it (Action_type="Submit")-->
                                <div class="form-group">
                                    <label>Uploader's name</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($submitInfo['Name'] ?? 'Unknown'); ?> ( <?= htmlspecialchars($submitInfo['Position'] ?? ''); ?> )" readonly>
                                </div>

                                <!-- Submission date : when is it submitted (Action_type="Submit"). It's not the same as maintenance_report's date -->
                                <div class="form-group">
                                    <label>Submitted at</label>
                                    <input type="text" class="form-control" value="<?= date('d-m-Y H:i', strtotime($submitInfo['Action_time'] ?? 'Unknown')); ?>" readonly>
                                </div>

                                <!-- Proof -->
                                <div class="form-group">
                                    <label>Proof</label>
                                    <ul>
                                        <?php
                                        // Fetch multimedia for this report
                                        $mediaQuery = "SELECT * FROM Multimedia WHERE Report_id = ?";
                                        $mediaStmt = $conn->prepare($mediaQuery);
                                        $mediaStmt->bind_param("i", $report['Report_id']);
                                        $mediaStmt->execute();
                                        $mediaResult = $mediaStmt->get_result();

                                        while ($media = $mediaResult->fetch_assoc()) {
                                            $fileSizeFormatted = formatFileSize((int)$media['File_size']);
                                            echo '<li style="list-style: none;">';
                                            if (strpos($media['File_type'], 'image') !== false) {
                                                echo '<img src="uploads/' . htmlspecialchars($media['File_path']) . '" style="width: 400px; height: 300px; height: auto;" alt="Image">';
                                            } elseif (strpos($media['File_type'], 'video') !== false) {
                                                echo '<video controls style="width: 500px; max-height: 400px;"><source src="uploads/' . htmlspecialchars($media['File_path']) . '" type="' . htmlspecialchars($media['File_type']) . '">Your browser does not support the video tag.</video>';
                                            }
                                            echo '<h5 style="font-weight: semi-bold; margin-bottom: 5px;">Size: ' . $fileSizeFormatted . ' Uploaded on: ' . date('d-m-Y H:i', strtotime($media['Uploaded_at'])) . '</h5>';
                                            echo '</li>';
                                        }
                                        $mediaStmt->close();
                                        ?>
                                    </ul>
                                </div>

                                <?php if ($hasUpdates): ?>
                                    <div class="form-group">
                                        <small class="text-muted" style="margin-bottom: 10px; margin-left: 10px;">Last updated at <?= date('d-m-Y H:i', strtotime($updateInfo['Action_time']) ?? $submitInfo['Action_time'] ?? 'Unknown'); ?> by <?= htmlspecialchars($updateInfo['Name'] ?? 'Unknown'); ?></small> <!-- by who last updated it in system_log (Action_type="Update")-->
                                    </div>
                                <?php endif; ?>

                                <!-- Form -->
                                <form action="dbconnection/dbreport.php" method="POST" onsubmit="return confirmUpdate()">
                                    <input type="hidden" name="report_id" value="<?= $report['Report_id']; ?>">

                                    <!-- Resolution -->
                                    <div class="form-group">
                                        <label for="resolution">Resolution</label>
                                        <textarea name="resolution" id="resolution" class="form-control" cols="50" rows="4"><?= htmlspecialchars($report['Resolution'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label></label>
                                        <!-- Audio Recording Controls -->
                                        <button type="button" onclick="startDictation()" class="btn">Speak</button>
                                        <small id="speech-status" style="margin: 12px;"></small>
                                    </div>

                                    <!-- Resolution Cost -->
                                    <div class="form-group">
                                        <label for="resolutionCost">Resolution Cost (RM)</label>
                                        <input type="text" name="resolutionCost" class="form-control" value="<?= htmlspecialchars($report['Cost'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" class="form-control">
                                            <option value="New" <?= $report['Status'] == 'New' ? 'selected' : ''; ?> disabled>New</option>
                                            <option value="In Progress" <?= $report['Status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Resolved" <?= $report['Status'] == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </div>

                                    <div class="row" style="margin-bottom: 0;">
                                        <button type="submit" class="btn" style="margin-right: 15px;">Update Status</button>
                                        <button type="button" class="btn btn-secondary" onclick="hideDetails()">Close</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetails(reportId) {
            document.getElementById('reportList').style.display = 'none';
            document.getElementById('reportDetails' + reportId).style.display = 'block';
        }

        function hideDetails() {
            document.querySelectorAll('.report-details').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('reportList').style.display = 'block';
        }

        function startDictation() {
            const status = document.getElementById("speech-status");
            if (!('webkitSpeechRecognition' in window)) {
                alert("Speech Recognition not supported in this browser.");
                return;
            }

            const recognition = new webkitSpeechRecognition(); // Works in Chrome
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = "ms-MY";

            status.innerText = "Listening...";

            recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                document.getElementById("resolution").value += (document.getElementById("resolution").value ? " " : "") + transcript;
                status.innerText = "Captured!";
            };

            recognition.onerror = function(event) {
                status.innerText = "Error or blocked.";
            };

            recognition.onend = function() {
                setTimeout(() => status.innerText = "", 2000);
            };

            recognition.start();
        }

        function confirmUpdate() {
            return confirm('Are you sure you want to update this report ?');
        }
    </script>
</body>

</html>