<?php
require_once "includes/session.php";
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

require_once "config/database.php";
include "includes/header.php";

// Get filter values
$urgency_id = isset($_GET["urgency_id"]) ? $_GET["urgency_id"] : "";
$status = isset($_GET["status"]) ? $_GET["status"] : "";
$search = isset($_GET["search"]) ? $_GET["search"] : "";
$category_id = isset($_GET["category_id"]) ? $_GET["category_id"] : "";

// Pagination
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build query
$where_conditions = [];
$params = [];
$param_types = "";

if ($urgency_id) {
    $where_conditions[] = "r.Urgency_ID = ?";
    $params[] = $urgency_id;
    $param_types .= "s";
}

if ($status) {
    $where_conditions[] = "COALESCE(sl.status, 'Pending') = ?";
    $params[] = $status;
    $param_types .= "s";
}

if ($search) {
    $where_conditions[] = "(r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if ($category_id) {
    $where_conditions[] = "r.Category_ID = ?";
    $params[] = $category_id;
    $param_types .= "i";
}

$user_id = $_SESSION['id'];
$where_conditions[] = "r.User_ID = ?";
$params[] = $user_id;
$param_types .= "i";

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
$count_sql = "SELECT COUNT(DISTINCT r.Report_ID) as total 
              FROM REPORT r 
              JOIN USERS u ON r.User_ID = u.User_ID 
              LEFT JOIN STATUS_LOG sl ON r.Report_ID = sl.Report_ID 
              AND sl.Status_ID = (
                  SELECT MAX(Status_ID) 
                  FROM STATUS_LOG 
                  WHERE Report_ID = r.Report_ID
              ) 
              $where_clause";

$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()["total"];
$total_pages = ceil($total_records / $records_per_page);

// Get reports
$sql = "SELECT * FROM REPORT r $where_clause ORDER BY report_date DESC LIMIT $records_per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get filter options
$urgency_levels = $conn->query("SELECT * FROM URGENCY_LEVEL ORDER BY Urgency_ID");
$statuses = ["Pending", "In Progress", "Completed"];
$categories = $conn->query("SELECT * FROM CATEGORY ORDER BY Category_ID");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .page-header {
            background: var(--primary-bg);
            padding: 20px;
            margin: -25px -25px 25px -25px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 24px;
            color: #1a237e;
        }

        .reports-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-left: var(--sidebar-width);
            transition: all var(--transition-speed) var(--transition-curve);
            width: calc(100% - var(--sidebar-width));
        }

        @media (max-width: 1200px) {
            .reports-container {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 25px 25px 25px;
            }
        }

        .filters-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 500;
            color: #1a237e;
            margin-bottom: 8px;
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            color: #333;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
            border-color: #bbdefb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(187, 222, 251, 0.25);
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: #bbdefb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(187, 222, 251, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
        }

        .reports-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }

        .reports-table th {
            background: var(--primary-bg);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #1a237e;
            border: none;
            white-space: nowrap;
        }

        .reports-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e3f2fd;
            color: #333;
            font-size: 14px;
            vertical-align: middle;
        }

        .reports-table tr:hover {
            background-color: #f5f5f5;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 500;
        }

        .priority-high {
            background: #ffebee;
            color: #d32f2f;
        }

        .priority-medium {
            background: #fff3e0;
            color: #f57c00;
        }

        .priority-low {
            background: #e8f5e9;
            color: #388e3c;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-completed {
            background: #e8f5e9;
            color: #388e3c;
        }

        .status-progress {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-pending {
            background: #f5f5f5;
            color: #616161;
        }

        .media-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 500;
            background: #e3f2fd;
            color: #1976d2;
        }

        .media-badge i {
            margin-right: 5px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .btn-view {
            background: #1976d2;
        }

        .btn-edit {
            background: #43a047;
        }

        .btn-delete {
            background: #e53935;
        }

        .btn-action:hover {
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            color: #bbdefb;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: row;
                gap: 8px;
                width: 100%;
                justify-content: center;
            }
            .reports-table td, .reports-table th {
                white-space: nowrap;
            }
            .btn.btn-primary, .btn.btn-danger {
                width: 100%;
                min-width: 80px;
                margin-bottom: 0;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="page-header">
            <h1>View Reports</h1>
        </div>
        
        <div class="reports-container">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success" style="background:#e8f5e9; color:#2e7d32; border-left:5px solid #2e7d32; padding:14px 20px; border-radius:8px; margin-bottom:18px; font-size:1rem;">
                    <i class="bi bi-check-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger" style="background:#ffebee; color:#c62828; border-left:5px solid #c62828; padding:14px 20px; border-radius:8px; margin-bottom:18px; font-size:1rem;">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>
            <div class="filters-section">
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($category['Category_ID']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Priority</label>
                    <select class="filter-select" id="priorityFilter">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search reports...">
                    </div>
                </div>
            </div>
            
            <!-- Reports Table -->
            <div class="table-responsive">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>User ID</th>
                            <th>Category ID</th>
                            <th>Urgency ID</th>
                            <th>Media</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($report = $result->fetch_assoc()) { ?>
                            <tr>
                                <td>#<?php echo $report["Report_ID"]; ?></td>
                                <td><?php echo htmlspecialchars($report["title"]); ?></td>
                                <td><?php echo htmlspecialchars($report["description"]); ?></td>
                                <td><?php echo htmlspecialchars($report["location"]); ?></td>
                                <td><?php echo htmlspecialchars($report["report_date"]); ?></td>
                                <td><?php echo htmlspecialchars($report["User_ID"]); ?></td>
                                <td><?php echo htmlspecialchars($report["Category_ID"]); ?></td>
                                <td><?php echo htmlspecialchars($report["Urgency_ID"]); ?></td>
                                <td>
                                    <?php
                                    $media = '';
                                    $media_query = $conn->prepare("SELECT file_path FROM media WHERE Report_ID = ? ORDER BY Media_ID ASC LIMIT 1");
                                    $media_query->bind_param("s", $report["Report_ID"]);
                                    $media_query->execute();
                                    $media_result = $media_query->get_result();
                                    if ($media_row = $media_result->fetch_assoc()) {
                                        $media = $media_row['file_path'];
                                    }
                                    $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                    $media_path = '';
                                    if (!empty($media)) {
                                        if (strpos($media, 'assets/uploads/reports/') === 0) {
                                            $media_path = $media;
                                        } else {
                                            $media_path = 'assets/uploads/reports/' . $media;
                                        }
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            if (file_exists($media_path)) {
                                                echo '<img src="' . htmlspecialchars($media_path) . '" alt="Report Media" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">';
                                            } else {
                                                echo '<i class="bi bi-file-earmark" style="font-size:1.5rem;color:#bdbdbd;" title="Missing Image"></i>';
                                            }
                                        } elseif (in_array($ext, ['mp4', 'mov'])) {
                                            echo '<i class="bi bi-film" style="font-size:1.5rem;color:#1976d2;" title="Video"></i>';
                                        } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
                                            echo '<i class="bi bi-music-note-beamed" style="font-size:1.5rem;color:#1976d2;" title="Audio"></i>';
                                        } else {
                                            echo '<i class="bi bi-file-earmark" style="font-size:1.5rem;color:#bdbdbd;" title="File"></i>';
                                        }
                                    } else {
                                        echo '<i class="bi bi-file-earmark" style="font-size:1.5rem;color:#bdbdbd;" title="No Media"></i>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $report["Report_ID"]; ?>" 
                                           class="btn btn-primary btn-sm">
                                            View
                                        </a>
                                        <form action="delete_report.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                            <input type="hidden" name="report_id" value="<?php echo $report["Report_ID"]; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&urgency_id=<?php echo $urgency_id; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                            <li class="page-item <?php echo $page == $i ? "active" : ""; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&urgency_id=<?php echo $urgency_id; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php } ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? "disabled" : ""; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&urgency_id=<?php echo $urgency_id; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php } ?>
        </div>
    </div>

    <script>
        // Filter functionality
        const filters = {
            category: document.getElementById('categoryFilter'),
            status: document.getElementById('statusFilter'),
            priority: document.getElementById('priorityFilter'),
            search: document.querySelector('.search-input')
        };

        function applyFilters() {
            const params = new URLSearchParams(window.location.search);
            
            if (filters.category.value) params.set('category_id', filters.category.value);
            if (filters.status.value) params.set('status', filters.status.value);
            if (filters.priority.value) params.set('urgency_id', filters.priority.value);
            if (filters.search.value) params.set('search', filters.search.value);
            
            // Reset to first page when applying new filters
            params.set('page', '1');
            
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        // Add debounce for search input
        let searchTimeout;
        filters.search.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 500);
        });

        // Apply filters immediately for other inputs
        filters.category.addEventListener('change', applyFilters);
        filters.status.addEventListener('change', applyFilters);
        filters.priority.addEventListener('change', applyFilters);
    </script>
</body>
</html>

<?php include "includes/footer.php"; ?> 