<?php
session_start();
include 'db.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$user_id = $_GET['id'];

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found.";
    exit;
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $role = $_POST['role'];

    $update_stmt = $conn->prepare("UPDATE users SET name = ?, role = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $name, $role, $user_id);

    if ($update_stmt->execute()) {
        header("Location: admin_dashboard.php");
        exit;
    } else {
        echo "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Staff</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background: var(--color-bg);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: 0 4px 12px var(--color-shadow);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        input, select, button {
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        button {
            background-color: var(--color-button-bg);
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color var(--transition);
        }

        button:hover {
            background-color: var(--color-button-bg-hover);
        }

        .back-link {
            display: block;
            margin-top: 1rem;
            text-decoration: none;
            color: var(--color-button-bg);
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<header>
    <div class="logo" tabindex="0">MaintainEase</div>
    <nav aria-label="Primary navigation">
        <a href="index.php">Home</a>
        <a href="admin_dashboard.php">Users</a>
        <a href="viewquery.php">Maintenance List</a>
        <a href="profile.php">Profile</a>
        <a href="about_us.php">About Us</a>
        <a href="logout.php">Sign Out</a>
    </nav>
</header>

<main>
    <div class="card">
        <?php if (!empty($user['profilepic'])): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($user['profilepic']) ?>" alt="Profile Picture" class="profile-pic">
        <?php else: ?>
            <img src="noprofile.jpg" alt="No Profile Picture" class="profile-pic">
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

            <select name="role" required>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>

            <button type="submit">Update Staff</button>
        </form>

        <a href="admin_dashboard.php" class="back-link">Cancel</a>
    </div>
</main>

</body>

</html>
