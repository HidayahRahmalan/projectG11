<?php
session_start();
include 'db.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch all users
$sql = "SELECT * FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Staff Management</title>
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
            padding: 3rem 2rem 4rem;
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

        tr:hover {
            background-color: #f1f1f1;
        }

        .actions a {
            color: var(--color-button-bg);
            text-decoration: none;
            font-weight: 600;
        }

        .actions a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<header>
    <div class="logo" tabindex="0">MaintainEase</div>
    <nav aria-label="Primary navigation">
        <a href="index.php" class="active" aria-current="page">Home</a>
        <?php if ($_SESSION['role'] === 'staff'): ?>
            <a href="insertmaintenance.php">Requests</a>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php">Users</a>
            <a href="viewquery.php">Maintenance List</a>
        <?php endif; ?>
        <a href="profile.php">Profile</a>
        <a href="about_us.php">About Us</a>
        <a href="logout.php">Sign Out</a>
    </nav>
</header>

<main>
    <h1>Admin Dashboard - Staff Details</h1>

    <table>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td class="actions">
                    <a href="edit_staff.php?id=<?= $row['user_id'] ?>">Edit</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>

</body>
</html>
