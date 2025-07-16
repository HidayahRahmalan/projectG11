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
$search = $_GET['search'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Main report query
$sql = "
SELECT 
    r.Report_ID,
    r.title,
    r.description,
    r.location,
    r.report_date,
    u.label AS urgency_label,
    sl.status AS latest_status,
    sl.updated_by,
    m.file_path AS media_path
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
LEFT JOIN urgency_level u ON r.Urgency_ID = u.Urgency_ID
LEFT JOIN (
    SELECT Report_ID, MIN(Media_ID) AS first_media_id
    FROM media
    GROUP BY Report_ID
) first_media ON r.Report_ID = first_media.Report_ID
LEFT JOIN media m ON m.Media_ID = first_media.first_media_id
WHERE (
    sl.status = 'Pending' 
    OR (sl.status IN ('In Progress', 'Completed', 'Cancel') AND sl.updated_by = $staff_id)
)
";


if (!empty($search)) {
    $safeSearch = $conn->real_escape_string($search);
    $sql .= " AND (r.title LIKE '%$safeSearch%' OR r.location LIKE '%$safeSearch%')";
}
if (!empty($from_date)) {
    $safeFrom = $conn->real_escape_string($from_date);
    $sql .= " AND r.report_date >= '$safeFrom'";
}
if (!empty($to_date)) {
    $safeTo = $conn->real_escape_string($to_date);
    $sql .= " AND r.report_date <= '$safeTo'";
}

$sql .= " ORDER BY r.report_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
    }
    .main-wrapper {
        padding: 40px 20px;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }
    .main-content {
        max-width: 1200px;
        width: 100%;
    }
    .filter-form {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background-color: #fff;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .filter-form input[type="text"],
    .filter-form input[type="date"] {
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid #ccc;
        flex: 1;
        min-width: 180px;
    }
    .filter-form button,
    .filter-form a {
        background-color: #007bff;
        color: white;
        padding: 8px 14px;
        border-radius: 6px;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    .table-container {
        overflow-x: auto;
        border-radius: 8px;
        background-color: white;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px 16px;
        border: 1px solid #ddd;
        text-align: left;
        vertical-align: top;
    }
    th {
        background-color: #a3d4f7;
    }
    .evidence-img {
        width: 200px;
        height: auto;
        margin: 3px;
        display: inline-block;
    }
    form.inline-form {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    button.update-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 5px;
        cursor: pointer;
    }
    input[type="file"] {
        display: none;
    }
    </style>

    <script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form.inline-form").forEach(function (form) {
        const select = form.querySelector("select[name='status']");
        const updateBtn = form.querySelector("button.update-btn");
        const currentStatus = select.dataset.current;

        const options = select.options;

        function disableOption(val) {
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === val) {
                    options[i].disabled = true;
                }
            }
        }

        if (currentStatus === "Pending") {
            disableOption("Completed");
        } else if (currentStatus === "In Progress") {
            disableOption("Pending");
        } else if (currentStatus === "Completed" || currentStatus === "Cancel") {
            disableOption("Pending");
            disableOption("In Progress");
            disableOption("Cancel");
            updateBtn.disabled = true;
            updateBtn.style.backgroundColor = "#ccc";
            updateBtn.style.cursor = "not-allowed";
        }
    });
});
</script>

    <!-- <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("select[name='status']").forEach(function (select) {
            const fileInput = select.closest("form").querySelector("input[type='file']");
            const updateBtn = select.closest("form").querySelector("button.update-btn");

            const toggleFileInput = () => {
                if (select.value === "Completed") {
                    fileInput.required = true;
                    fileInput.style.display = "inline-block";
                } else {
                    fileInput.required = false;
                    fileInput.style.display = "none";
                }
            };

            toggleFileInput(); // initial check
            select.addEventListener("change", toggleFileInput);
        });
    });
    </script> -->
</head>
<body>

<?php if (isset($_SESSION['status_success'])): ?>
    <script>alert("<?= $_SESSION['status_success'] ?>");</script>
    <?php unset($_SESSION['status_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['status_error'])): ?>
    <script>alert("<?= $_SESSION['status_error'] ?>");</script>
    <?php unset($_SESSION['status_error']); ?>
<?php endif; ?>

<div class="main-wrapper">
    <div class="main-content">
        <h2>Report Management</h2>

        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by title or location" value="<?= htmlspecialchars($search) ?>">
            <label>From: <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"></label>
            <label>To: <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"></label>
            <button type="submit">Filter</button>
            <a href="report_management.php">Reset</a>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Main Image</th>
                        <th>Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>View</th>
                        <th>Change Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $reportId = htmlspecialchars($row['Report_ID']);
                        $evidenceQuery = $conn->query("SELECT file_path FROM media WHERE Report_ID = '$reportId' ORDER BY Media_ID ASC");
                        $evidences = [];
                        while ($ev = $evidenceQuery->fetch_assoc()) {
                            $evidences[] = $ev['file_path'];
                        }
                    ?>
                    <tr>
                        <td><?= $reportId ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td>
                            <?php if (!empty($row['media_path'])): ?>
                                <img src="assets/uploads/reports/<?= htmlspecialchars($row['media_path']) ?>" style="width: 100px;">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['report_date']) ?></td>
                        <td><?= htmlspecialchars($row['urgency_label'] ?? 'Not Set') ?></td>
                        <td><?= htmlspecialchars($row['latest_status'] ?? 'Not Updated') ?></td>
                        <td>
                            <a href="staff_view_report.php?id=<?= $reportId ?>" 
                               style="padding: 6px 12px; background-color: #007bff; color: white; border-radius: 4px; text-decoration: none;">
                                View
                            </a>
                        </td>
                        <td>
                            <?php $currentStatus = $row['latest_status'] ?? ''; ?>
                            <form class="inline-form" method="POST" action="update_report.php" enctype="multipart/form-data">
                                <input type="hidden" name="Report_ID" value="<?= $reportId ?>">
                                <select name="status" data-current="<?= $currentStatus ?>">
                                <option value="Pending" <?= $currentStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="In Progress" <?= $currentStatus === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= $currentStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancel" <?= $currentStatus === 'Cancel' ? 'selected' : '' ?>>Cancel</option>
                            </select>
                            <input type="file" name="evidence[]" multiple>
                            <button type="submit" class="update-btn">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>