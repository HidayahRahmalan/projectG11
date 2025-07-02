<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilepic'])) {
    if ($_FILES['profilepic']['error'] === UPLOAD_ERR_OK) {
        $fileData = file_get_contents($_FILES['profilepic']['tmp_name']);
        $update_stmt = $conn->prepare("UPDATE users SET profilepic = ? WHERE user_id = ?");
        $update_stmt->bind_param("bi", $fileData, $user_id);
        $update_stmt->send_long_data(0, $fileData);

        if ($update_stmt->execute()) {
            header("Location: profile.php");
            exit;
        } else {
            $error = "Failed to update profile picture.";
        }
    } else {
        $error = "Error uploading file.";
    }
}

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Your Profile</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.1);
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
            padding: 3rem 2rem;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background-color: #fff;
            border-radius: var(--radius);
            box-shadow: 0 4px 12px var(--color-shadow);
            padding: 2rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        h1 {
            font-weight: 700;
            font-size: 2.5rem;
            color: var(--color-text-head);
            margin-bottom: 1.5rem;
        }

        .profile-pic {
            width: 200px;
            height: 200px;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: 0 4px 12px var(--color-shadow);
            margin-bottom: 1.5rem;
        }

        .user-info {
            font-size: 1.2rem;
            color: var(--color-text-head);
            margin-bottom: 1.5rem;
        }

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        input[type="file"] {
            font-family: 'Poppins', sans-serif;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            padding: 0.5rem;
        }

        button {
            padding: 0.8rem 1.5rem;
            background-color: var(--color-button-bg);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background-color var(--transition);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        button:hover {
            background-color: var(--color-button-bg-hover);
        }

        .error {
            color: red;
            margin-top: 1rem;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo" tabindex="0">MaintainEase</div>
        <nav aria-label="Primary navigation">
            <a href="index.php" class="active" aria-current="page">Home</a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'staff'): ?>
                    <a href="insertmaintenance.php">Submit Request</a>
                    <a href="request_list.php">My Requests</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Users</a>
                    <a href="viewquery.php">Maintenance List</a>
                <?php endif; ?>

                <a href="profile.php">Profile</a>
                <a href="about_us.php">About Us</a>
                <a href="logout.php">Sign Out</a>
            <?php else: ?>
                <a href="about_us.php">About Us</a>
                <a href="login.php">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <div class="card">
            <h1>Your Profile</h1>

            <?php if (!empty($user['profilepic'])): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($user['profilepic']) ?>" alt="Profile Picture" class="profile-pic" />
            <?php else: ?>
                <img src="noprofile.jpg" alt="No Profile Picture" class="profile-pic" />
            <?php endif; ?>

            <div class="user-info">
                <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="profilepic" accept="image/*" required>
                <button type="submit">Update Profile Picture</button>
            </form>

            <?php if (isset($error)): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
        </div>
    </main>

</body>

</html>
