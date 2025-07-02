<?php
    session_start();
    include 'db.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Handle search and filters
    $search = $_GET['search'] ?? '';
    $filter_category = $_GET['category'] ?? '';
    $filter_urgency = $_GET['urgency'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_created = $_GET['created_at'] ?? '';

    $sql = "SELECT m.*, md.mediatype, md.filemedia, md.audio
            FROM maintenance m
            LEFT JOIN media md ON m.maintenance_id = md.maintenance_id
            WHERE 1";

    $params = [];
    $types = "";

    // Search by title
    if (!empty($search)) {
        $sql .= " AND m.title LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }

    // Filter by category
    if (!empty($filter_category)) {
        $sql .= " AND m.category = ?";
        $params[] = $filter_category;
        $types .= "s";
    }

    // Filter by urgency
    if (!empty($filter_urgency)) {
        $sql .= " AND m.urgency = ?";
        $params[] = $filter_urgency;
        $types .= "s";
    }

    // Filter by status
    if (!empty($filter_status)) {
        $sql .= " AND m.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    // Filter by created_at date
    if (!empty($filter_created)) {
        $sql .= " AND DATE(m.created_at) = ?";
        $params[] = $filter_created;
        $types .= "s";
    }

    $sql .= " ORDER BY m.maintenance_id DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Maintenance Requests</title>
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

        * {
            box-sizing: border-box;
        }

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

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-text-head);
            user-select: none;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        nav a {
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        nav a:hover,
        nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
        }

        main {
            flex-grow: 1;
            max-width: var(--max-width);
            width: 100%;
            padding: 3rem 2rem 4rem; /* Existing padding */
            margin: 0 auto;
        }

        h1 {
            font-weight: 700;
            font-size: 3rem;
            color: var(--color-text-head);
            margin-bottom: 2rem;
            user-select: none;
            text-align: center;
        }

        .filter-form {
            width: calc(100% - 4rem);
            margin: 0 auto 2rem auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .filter-form input[type="text"],
        .filter-form select,
        .filter-form input[type="date"] {
            padding: 0.8rem 1rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-family: 'Poppins', sans-serif;
            width: 200px;
        }

        .filter-form button {
            padding: 0.8rem 1.2rem;
            background-color: var(--color-button-bg);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: background-color var(--transition);
        }

        .filter-form button:hover {
            background-color: var(--color-button-bg-hover);
        }


        table {
            width: calc(100% - 4rem); /* Add margin on left and right */
            margin: 0 auto 20px auto;  /* Center the table */
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

        tr:hover {
            background-color: #f1f1f1;
        }

        .title-link {
            text-decoration: none;
            color: var(--color-button-bg);
            font-weight: 600;
        }

        .title-link:hover {
            text-decoration: underline;
        }

        audio {
            width: 100%;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo" tabindex="0">MaintainEase</div>
        <nav aria-label="Primary navigation">
            <a href="home.php" class="active" aria-current="page">Home</a>
            <?php if ($_SESSION['role'] === 'staff'): ?>
                <a href="insertmaintenance.php">Requests</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php">Users</a>
                <a href="viewquery.php">Maintenance List</a>
            <?php endif; ?>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Sign Out</a>
        </nav>
    </header>

    <h1>Maintenance Requests</h1>
    <!-- Filter and Search Form -->
    <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by Title" value="<?= htmlspecialchars($search) ?>" />

            <select name="category">
                <option value="">All Categories</option>
                <option value="Electrical" <?= $filter_category == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                <option value="Plumbing" <?= $filter_category == 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
                <option value="HVAC" <?= $filter_category == 'HVAC' ? 'selected' : '' ?>>HVAC</option>
                <!-- Add more categories as needed -->
            </select>

            <select name="urgency">
                <option value="">All Urgencies</option>
                <option value="Low" <?= $filter_urgency == 'Low' ? 'selected' : '' ?>>Low</option>
                <option value="Medium" <?= $filter_urgency == 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="High" <?= $filter_urgency == 'High' ? 'selected' : '' ?>>High</option>
            </select>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Open" <?= $filter_status == 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In Progress" <?= $filter_status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Closed" <?= $filter_status == 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>

            <input type="date" name="created_at" value="<?= htmlspecialchars($filter_created) ?>" />

            <button type="submit">Search & Filter</button>
        </form>

    <table>
        <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Urgency</th>
            <th>Location</th>
            <th>Description</th>
            <th>Created At</th>
            <th>Status</th>
            <th>Audio</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
            <td><a href="viewmaintenance.php?id=<?= $row['maintenance_id'] ?>" class="title-link"><?= htmlspecialchars($row['title']) ?></a></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['urgency']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>

                <!-- Audio file display -->
                <td>
                    <?php if (!empty($row['audio'])): ?>
                        <audio controls>
                            <source src="data:audio/wav;base64,<?= base64_encode($row['audio']) ?>" type="audio/wav">
                            Your browser does not support the audio element.
                        </audio>
                    <?php else: ?>
                        No file uploaded
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

</body>
</html>
