<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle search and filters
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_urgency = $_GET['urgency'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_created = $_GET['created_at'] ?? '';

// Handle cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = $_GET['cancel_id'];

    $stmt = $conn->prepare("SELECT status FROM maintenance WHERE maintenance_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cancel_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    if ($request && $request['status'] != 'Cancelled' && $request['status'] != 'Completed') {
        $insert_stmt = $conn->prepare("INSERT INTO user_maintenance (maintenance_id, user_id, comment) VALUES (?, ?, 'Cancelled Request')");
        $insert_stmt->bind_param("ii", $cancel_id, $user_id);
        $insert_stmt->execute();

        // Insert audit log
        $audit_stmt = $conn->prepare("INSERT INTO audit_log (user_id, actiontype, actiontime) VALUES (?, 'Cancelled report', NOW())");
        $audit_stmt->bind_param("i", $user_id);
        $audit_stmt->execute();

        $update_stmt = $conn->prepare("UPDATE maintenance SET status = 'Cancelled' WHERE maintenance_id = ?");
        $update_stmt->bind_param("i", $cancel_id);
        $update_stmt->execute();

        header("Location: request_list.php?cancelled=true");
        exit();
    }
}

// Fetch maintenance requests with filters
$sql = "SELECT * FROM maintenance WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if (!empty($filter_category)) {
    $sql .= " AND category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

if (!empty($filter_urgency)) {
    $sql .= " AND urgency = ?";
    $params[] = $filter_urgency;
    $types .= "s";
}

if (!empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_created)) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $filter_created;
    $types .= "s";
}

$sql .= " ORDER BY maintenance_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Maintenance Requests</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.05);
            --color-button-bg: #111827;
            --color-button-bg-hover: #000000;
            --radius: 0.75rem;
            --transition: 0.3s ease;
            --max-width: 1200px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-body);
            font-size: 18px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            position: sticky;
            top: 0;
            background: var(--color-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            box-shadow: 0 2px 6px var(--color-shadow);
            z-index: 10;
        }

        .logo { font-weight: 700; font-size: 1.5rem; color: var(--color-text-head); user-select: none; }
        nav a {
            margin-left: 1.5rem;
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
        }

        nav a:hover, nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
        }

        main {
            flex-grow: 1;
            max-width: var(--max-width);
            width: 100%;
            padding: 3rem 2rem;
            margin: 0 auto;
        }

        h1 { font-weight: 700; font-size: 3rem; color: var(--color-text-head); margin-bottom: 2rem; text-align: center; }

        .filter-form {
            width: calc(100% - 4rem);
            margin: 0 auto 2rem auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .filter-form input, .filter-form select {
            padding: 0.8rem 1rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            width: 200px;
        }

        .filter-form button {
            padding: 0.8rem 1.2rem;
            background-color: var(--color-button-bg);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background-color var(--transition);
        }

        .filter-form button:hover { background-color: var(--color-button-bg-hover); }

        table {
            width: calc(100% - 4rem);
            margin: 0 auto 20px auto;
            border-collapse: collapse;
            background: var(--color-bg);
            box-shadow: 0 4px 12px var(--color-shadow);
            border-radius: var(--radius);
            overflow: hidden;
        }

        th, td {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #111827;
            color: white;
        }

        tr:hover { background-color: #f1f1f1; }

        .title-link {
            text-decoration: none;
            color: var(--color-button-bg);
            font-weight: 600;
        }

        .title-link:hover { text-decoration: underline; }

        .btn-cancel {
            background-color: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
            border-radius: 0.5rem;
            transition: background-color var(--transition);
            text-decoration: none;
        }

        .btn-cancel:hover { background-color: #dc2626; }

        .btn-see-more {
            background-color: #111827;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
            border-radius: 0.5rem;
            transition: background-color var(--transition);
        }

        .btn-see-more:hover { background-color: #000000; }

        .details-row { display: none; background-color: #f9f9f9; }
        .details-content { padding: 1rem; }
    </style>
</head>

<body>

<header>
    <div class="logo" tabindex="0">MaintainEase</div>
    <nav>
        <a href="index.php">Home</a>
        <a href="insertmaintenance.php">Submit Request</a>
        <a href="request_list.php" class="active" aria-current="page">My Requests</a>
        <a href="profile.php">Profile</a>
        <a href="about_us.php">About Us</a>
        <a href="logout.php">Sign Out</a>
    </nav>
</header>

<main>
    <h1>My Maintenance Requests</h1>

    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Search by Title" value="<?= htmlspecialchars($search) ?>" />

        <select name="category">
            <option value="">All Categories</option>
            <option value="Electrical" <?= $filter_category == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
            <option value="Plumbing" <?= $filter_category == 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
            <option value="HVAC" <?= $filter_category == 'HVAC' ? 'selected' : '' ?>>HVAC</option>
        </select>

        <select name="urgency">
            <option value="">All Urgencies</option>
            <option value="Low" <?= $filter_urgency == 'Low' ? 'selected' : '' ?>>Low</option>
            <option value="Medium" <?= $filter_urgency == 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="High" <?= $filter_urgency == 'High' ? 'selected' : '' ?>>High</option>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <option value="Open" <?= $filter_status == 'Open' ? 'selected' : '' ?>>Open</option>
            <option value="In Progress" <?= $filter_status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="Completed" <?= $filter_status == 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $filter_status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <input type="date" name="created_at" value="<?= htmlspecialchars($filter_created) ?>" />
        <button type="submit">Search & Filter</button>
    </form>

    <table>
        <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Urgency</th>
            <th>Created At</th>
            <th>Action</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><a href="editmaintenance.php?id=<?= $row['maintenance_id'] ?>" class="title-link"><?= htmlspecialchars($row['title']) ?></a></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['urgency']) ?></td>
                <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <?php if ($row['status'] != 'Cancelled' && $row['status'] != 'Completed'): ?>
                        <a href="request_list.php?cancel_id=<?= $row['maintenance_id'] ?>" class="btn-cancel">Cancel</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                    <button class="btn-see-more" onclick="toggleDetails('details-<?= $row['maintenance_id'] ?>')">See More</button>
                </td>
            </tr>
            <tr id="details-<?= $row['maintenance_id'] ?>" class="details-row">
                <td colspan="5" class="details-content">
                    <strong>Location:</strong> <?= htmlspecialchars($row['location']) ?><br><br>
                    <strong>Status:</strong> <?= htmlspecialchars($row['status']) ?><br><br>
                    <strong>Description:</strong> <?= nl2br(htmlspecialchars($row['description'])) ?><br><br>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>

<script>
    function toggleDetails(id) {
        var row = document.getElementById(id);
        row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    }
</script>

</body>
</html>
