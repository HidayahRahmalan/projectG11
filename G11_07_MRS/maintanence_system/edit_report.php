<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

// Check if user is logged in and report ID is provided
if (!isset($_SESSION["id"]) || !isset($_GET["id"])) {
    header("Location: view_reports.php");
    exit();
}

$report_id = $_GET["id"];
$errors = [];
$success = false;

// Get report details
$sql = "SELECT r.*, u.name as reporter_name, ul.label as urgency_label 
        FROM REPORT r 
        JOIN USERS u ON r.User_ID = u.User_ID 
        JOIN URGENCY_LEVEL ul ON r.Urgency_ID = ul.Urgency_ID 
        WHERE r.Report_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

// Get urgency levels for dropdown
$urgency_levels = $conn->query("SELECT * FROM URGENCY_LEVEL ORDER BY Urgency_ID");

// Get current status for display
$status_query = "SELECT status FROM STATUS_LOG WHERE Report_ID = ? ORDER BY Status_ID DESC LIMIT 1";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("i", $report_id);
$status_stmt->execute();
$current_status_result = $status_stmt->get_result();
$current_status = $current_status_result->fetch_assoc();
$current_status = $current_status ? $current_status['status'] : 'Pending';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $location = trim($_POST["location"]);
    $urgency_id = $_POST["urgency_id"];
    $status = isset($_POST["status"]) ? $_POST["status"] : null;
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if (empty($errors)) {
        // Update report
        $update_sql = "UPDATE REPORT SET 
                      title = ?, 
                      description = ?, 
                      location = ?, 
                      Urgency_ID = ?
                      WHERE Report_ID = ?";
                      
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssii", $title, $description, $location, $urgency_id, $report_id);
        
        if ($update_stmt->execute()) {
            // Add status log if status changed and status is set (for admin/manager)
            if ($status !== null && ($_SESSION["role"] == "Admin" || $_SESSION["role"] == "Manager")) {
                if ($status !== $current_status) {
                    $status_sql = "INSERT INTO STATUS_LOG (Report_ID, status, updated_by, updated_at) VALUES (?, ?, ?, NOW())";
                    $status_stmt = $conn->prepare($status_sql);
                    $status_stmt->bind_param("isi", $report_id, $status, $_SESSION["id"]);
                    $status_stmt->execute();
                }
            }
            
            $_SESSION["success_msg"] = "Report updated successfully!";
            $success = true;
        } else {
            $errors[] = "Error updating report";
        }
    }
    
    // Redirect after successful update
    if ($success) {
        header("Location: view_reports.php");
        exit();
    }
}

// Include header after all possible redirects
include "includes/header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .edit-header {
            background: linear-gradient(to right, #bbdefb, #e3f2fd);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .edit-header h1 {
            margin: 0;
            color: #1a237e;
            font-size: 24px;
            font-weight: 600;
        }

        .edit-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #1a237e;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
            outline: none;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-danger {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
        }

        .btn-primary:hover {
            background: #1565c0;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .status-select {
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }

        .urgency-select {
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }

        .metadata {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .metadata-item {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }

        .metadata-label {
            color: #666;
            min-width: 120px;
        }

        .metadata-value {
            color: #333;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header">
            <h1>Edit Report</h1>
            <a href="view_reports.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
        </div>

        <div class="edit-form">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="metadata">
                <div class="metadata-item">
                    <span class="metadata-label">Report ID:</span>
                    <span class="metadata-value">#<?php echo $report['Report_ID']; ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Created By:</span>
                    <span class="metadata-value"><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Created Date:</span>
                    <span class="metadata-value"><?php echo date('Y-m-d H:i', strtotime($report['report_date'])); ?></span>
                </div>
            </div>

            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($report['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" required><?php echo htmlspecialchars($report['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($report['location']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="urgency_id" class="urgency-select" required>
                        <?php while ($level = $urgency_levels->fetch_assoc()): ?>
                            <option value="<?php echo $level['Urgency_ID']; ?>" 
                                    <?php echo $report['Urgency_ID'] == $level['Urgency_ID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($level['label']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <?php if ($_SESSION["role"] == "Admin" || $_SESSION["role"] == "Manager"): ?>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="status-select">
                            <?php
                            $statuses = ["Pending", "In Progress", "Completed"];
                            foreach ($statuses as $status):
                            ?>
                                <option value="<?php echo $status; ?>" <?php echo $current_status == $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                    <a href="view_reports.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<?php include "includes/footer.php"; ?> 