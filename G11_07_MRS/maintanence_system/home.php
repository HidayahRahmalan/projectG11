<?php
session_start();
require_once "config/database.php";
include "includes/header.php";

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
// Get total reports count for this user
$total_query = "SELECT COUNT(*) as total FROM REPORT WHERE User_ID = ?";
$stmt = $conn->prepare($total_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result();
$total_reports = $total_result->fetch_assoc()['total'];

// Get overall status counts for this user
$status_query = "SELECT 
    SUM(CASE WHEN COALESCE(sl.status, 'Pending') = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN sl.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN sl.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
    FROM REPORT r
    LEFT JOIN STATUS_LOG sl ON r.Report_ID = sl.Report_ID 
    AND sl.Status_ID = (
        SELECT MAX(Status_ID) 
        FROM STATUS_LOG 
        WHERE Report_ID = r.Report_ID
    )
    WHERE r.User_ID = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$status_result = $stmt->get_result();
$status_counts = $status_result->fetch_assoc();

// Get recent reports for this user
$reports_query = "SELECT 
    r.Report_ID,
    r.Title,
    r.Description,
    r.Location,
    r.report_date,
    MIN(m.file_path) as media_path, -- Just grab one
    ul.label as urgency_label,
    u.name as reporter_name,
    COALESCE(sl.status, 'Pending') as current_status
FROM REPORT r
LEFT JOIN MEDIA m ON r.Report_ID = m.Report_ID
LEFT JOIN URGENCY_LEVEL ul ON r.Urgency_ID = ul.Urgency_ID
LEFT JOIN USERS u ON r.User_ID = u.User_ID
LEFT JOIN STATUS_LOG sl ON r.Report_ID = sl.Report_ID 
    AND sl.Status_ID = (
        SELECT MAX(Status_ID) 
        FROM STATUS_LOG 
        WHERE Report_ID = r.Report_ID
    )
WHERE r.User_ID = ?
GROUP BY r.Report_ID
ORDER BY r.report_date DESC
LIMIT 10
";
$stmt = $conn->prepare($reports_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .dashboard-container {
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) var(--transition-curve);
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 24px;
            color: #1a237e;
            margin: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .stat-box {
            padding: 20px;
            background: #fff;
            text-align: left;
        }

        .stat-box:not(:last-child) {
            border-bottom: 1px solid #e0e0e0;
        }

        .stat-label {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }

        .stat-value.total { color: #1a237e; }
        .stat-value.pending { color: #f44336; }
        .stat-value.completed { color: #4caf50; }
        .stat-value.in-progress { color: #2196f3; }

        .reports-section {
            margin-top: 30px;
        }

        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .reports-title {
            font-size: 18px;
            color: #1a237e;
            margin: 0;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #bbdefb;
            box-shadow: 0 0 0 2px rgba(187, 222, 251, 0.2);
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .reports-table th {
            background: #bbdefb;
            color: #1a237e;
            font-weight: 500;
            text-align: left;
            padding: 12px 15px;
        }

        .reports-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .reports-table tr:last-child td {
            border-bottom: none;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .priority-high { background: #ffebee; color: #c62828; }
        .priority-medium { background: #fff3e0; color: #ef6c00; }
        .priority-low { background: #e8f5e9; color: #2e7d32; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #ffebee; color: #c62828; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }

        @media (max-width: 1200px) {
            .dashboard-container {
                margin-left: 0;
                padding-top: 80px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                width: 100%;
                margin-top: 15px;
            }

            .reports-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Reports:</div>
                <div class="stat-value total"><?php echo $total_reports; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Pending:</div>
                <div class="stat-value pending"><?php echo $status_counts['pending']; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Completed:</div>
                <div class="stat-value completed"><?php echo $status_counts['completed']; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">In Progress:</div>
                <div class="stat-value in-progress"><?php echo $status_counts['in_progress']; ?></div>
            </div>
        </div>

        <div class="reports-section">
            <div class="reports-header">
                <h2 class="reports-title">Your Reports Issue</h2>
                <div class="search-box">
                    <input type="text" placeholder="Search reports..." id="searchInput">
                </div>
            </div>

            <div style="background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(33,150,243,0.07); padding:24px 18px; margin-top:24px; margin-bottom:32px;">
                <table class="reports-table" style="width:100%; border-collapse:collapse; background:transparent;">
                    <thead>
                        <tr>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Title</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Description</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Media</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Priority</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Date</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Location/Asset</th>
                            <th style="background:#bbdefb; color:#1a237e; font-weight:500; text-align:left; padding:16px 14px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; color:#888; padding:40px 0;">
                                    <div style="display:flex; flex-direction:column; align-items:center;">
                                        <svg width="48" height="48" fill="#bbdefb" viewBox="0 0 24 24">
                                          <rect x="4" y="4" width="16" height="16" rx="3" fill="#bbdefb" opacity="0.2"/>
                                          <rect x="7" y="7" width="10" height="2" rx="1" fill="#1976d2"/>
                                          <rect x="7" y="11" width="7" height="2" rx="1" fill="#1976d2"/>
                                          <rect x="7" y="15" width="5" height="2" rx="1" fill="#1976d2"/>
                                        </svg>
                                        <div style="margin-top:12px; font-size:1.1rem;">
                                            There's no report being submitted
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($report = $reports->fetch_assoc()): ?>
                                <tr style="transition:background 0.2s;" onmouseover="this.style.background='#e3f2fd';" onmouseout="this.style.background='';">
                                    <td style="padding:16px 14px;"><?php echo htmlspecialchars($report['Title']); ?></td>
                                    <td style="padding:16px 14px;"><?php echo htmlspecialchars($report['Description']); ?></td>
                                    <td style="padding:16px 14px;">
                                        <?php
                                        $media = $report['media_path'];
                                        $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                        $media_path = '';
                                        if (!empty($media)) {
                                            // Prepend path if not already present
                                            if (strpos($media, 'assets/uploads/reports/') === 0) {
                                                $media_path = $media;
                                            } else {
                                                $media_path = 'assets/uploads/reports/' . $media;
                                            }
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                if (file_exists($media_path)) {
                                                    echo '<img src="' . htmlspecialchars($media_path) . '" alt="Report Media" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">';
                                                } else {
                                                    echo '<i class="bi bi-file-earmark" style="font-size:2rem;color:#bdbdbd;" title="Missing Image"></i>';
                                                }
                                            } elseif (in_array($ext, ['mp4', 'mov'])) {
                                                echo '<i class="bi bi-film" style="font-size:2rem;color:#1976d2;" title="Video"></i>';
                                            } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
                                                echo '<i class="bi bi-music-note-beamed" style="font-size:2rem;color:#1976d2;" title="Audio"></i>';
                                            } else {
                                                echo '<i class="bi bi-file-earmark" style="font-size:2rem;color:#bdbdbd;" title="File"></i>';
                                            }
                                        } else {
                                            echo '<i class="bi bi-file-earmark" style="font-size:2rem;color:#bdbdbd;" title="No Media"></i>';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding:16px 14px;">
                                        <?php
                                            $priority = strtolower($report['urgency_label']);
                                            $priorityColors = [
                                                'high' => 'background:#ffebee; color:#c62828;',
                                                'medium' => 'background:#fff3e0; color:#ef6c00;',
                                                'low' => 'background:#e8f5e9; color:#2e7d32;'
                                            ];
                                            $priorityStyle = isset($priorityColors[$priority]) ? $priorityColors[$priority] : 'background:#f5f5f5; color:#333;';
                                        ?>
                                        <span style="padding:6px 14px; border-radius:16px; font-size:13px; font-weight:600; <?php echo $priorityStyle; ?>">
                                            <?php echo htmlspecialchars($report['urgency_label']); ?>
                                        </span>
                                    </td>
                                    <td style="padding:16px 14px;"><?php echo date('Y-m-d', strtotime($report['report_date'])); ?></td>
                                    <td style="padding:16px 14px;"><?php echo htmlspecialchars($report['Location']); ?></td>
                                    <td style="padding:16px 14px;">
                                        <?php
                                            $status = strtolower(str_replace(' ', '-', $report['current_status']));
                                            $statusColors = [
                                                'completed' => 'background:#e8f5e9; color:#2e7d32;',
                                                'pending' => 'background:#ffebee; color:#c62828;',
                                                'in-progress' => 'background:#e3f2fd; color:#1565c0;'
                                            ];
                                            $statusStyle = isset($statusColors[$status]) ? $statusColors[$status] : 'background:#f5f5f5; color:#333;';
                                        ?>
                                        <span style="padding:6px 14px; border-radius:16px; font-size:13px; font-weight:600; <?php echo $statusStyle; ?>">
                                            <?php echo htmlspecialchars($report['current_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.reports-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?> 