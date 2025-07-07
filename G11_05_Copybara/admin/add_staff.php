<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) {
    header("Location: ../login.php");
    exit;
}

$staffRoleId = 3; // Change if your staff role_id is different

$message = "";
$user_id = $_SESSION['user_id'];

// Fetch user info from DB
$stmt = $conn->prepare("SELECT name, email, profilepic FROM sys_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $profilepic);
$stmt->fetch();
$stmt->close();
if (empty($profilepic) || !file_exists('../' . $profilepic)) {
    $profilepic = 'profilepic/default.jpeg';
}
$profilepic_path = '../' . $profilepic;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name && $email && $password) {
        $stmt = $conn->prepare("SELECT user_id FROM sys_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = "Email already exists!";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO sys_user (name, email, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $name, $email, $hashed, $staffRoleId, $is_active);
            if ($stmt->execute()) {
                $message = "Staff added successfully!";
            } else {
                $message = "Error adding staff. Please try again.";
            }
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Staff - MRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; min-height: 100vh;}
        header.admin-header { width: 100%; background: #5481a7; color: white; padding: 1.3rem 0; font-size: 2rem; font-weight: 700; text-align: center; letter-spacing: 1px; position: fixed; top: 0; left: 0; z-index: 1000; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #253444; color: #fff; display: flex; flex-direction: column; z-index: 1100; }
        .sidebar-header { padding: 2rem 1rem 1rem 2rem; font-size: 1.1rem;  font-weight: bold; background: #1d2937; }
        .sidebar nav { flex: 1; display: flex; flex-direction: column; gap: 8px; padding: 1.5rem 0.5rem 1.5rem 2rem; }
        .sidebar-section-title { font-size: 0.85rem; margin-top: 1.5rem; margin-bottom: 0.7rem; font-weight: bold; color: #b8e0fc; }
        .sidebar nav a { color: #cdd9e5; text-decoration: none;     font-size: 0.9rem;    padding: 8px 14px; border-radius: 6px; transition: background 0.2s; font-weight: 500; display: block; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #4285F4; color: #fff; }
        .sidebar .logout-link { margin-top: auto; margin-bottom: 2rem; padding-left: 2rem; }
        .sidebar .logout-link a { color: #ffbdbd; background: #a94442; font-weight: bold; text-decoration: none;     font-size: 0.9rem;padding: 8px 14px; border-radius: 6px; display: inline-block; }
        .main-content { margin-left: 220px; padding-top: 70px; padding-bottom: 2rem; min-height: 100vh; background: #f7fafc; }
        .container { max-width: 400px; margin: 0 auto; padding: 0 20px; }
        h2 { color: #253444; margin: 2rem 0 1rem 0; font-size: 1.7rem; font-weight: bold; text-align: center; }
        form { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);}
        label { display: block; margin-top: 1.3rem; margin-bottom: 4px; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 8px 12px; border: 1px solid #d3d3d3; border-radius: 5px; box-sizing: border-box; }
        .actions { margin-top: 1.5rem; text-align: center;}
        button { background: #4285F4; color: #fff; padding: 8px 18px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500;}
        button:hover { background: #306ac3; }
        .msg { color: #a94442; margin-bottom: 1rem; font-weight: bold; text-align: center;}
        .success { color: #205e10; }
        .checkbox-label { display: inline-block; margin-top: 10px; }
        a.back-link { margin-left: 1rem; color: #4285F4; text-decoration: none; font-size: 1rem;}
        a.back-link:hover { text-decoration: underline; }
        @media (max-width: 900px) { .main-content { margin-left: 0; padding-top: 70px; } .sidebar { position: static; width: 100%; min-height: auto; flex-direction: row; } .sidebar-header, .sidebar nav, .sidebar .logout-link { padding-left: 1rem; } .container { padding: 0 8px; } header.admin-header { font-size: 1.2rem; } }
        @media (max-width: 600px) { .container { padding: 0 2px; } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<header class="admin-header">
    Maintenance Report System - Admin Dashboard
</header>
<aside class="sidebar">
<div class="sidebar-header" style="display: flex; align-items: center; gap: 10px;">
    <img src="<?= htmlspecialchars($profilepic_path) ?>" alt="Profile Picture"
         style="width: 24px; height: 24px; object-fit: cover; border-radius: 50%;">
    <div style="font-size: 1.1rem; color: #fff;">MRS Admin</div>
</div>
    <nav>
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <div class="sidebar-section-title">User Management</div>
        <a href="manage_staff.php" class="active"><i class="fas fa-user-tie"></i> Staff</a>
        <a href="manage_technician.php"><i class="fas fa-user-cog"></i> Technician </a>
        <div class="sidebar-section-title">Report Management</div>
        <a href="assign_report.php"><i class="fas fa-tasks"></i> Assign Report</a>
        <a href="view_report_history.php"><i class="fas fa-history"></i> View Report</a>
                        <div class="sidebar-section-title"> My Profile</div>     
        <a href="admin_profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </nav>
    <div class="logout-link"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>
<div class="main-content">
    <div class="container">
        <h2>Add Staff</h2>
        <?php if ($message): ?>
            <div class="msg<?= $message === "Staff added successfully!" ? " success" : "" ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" required>

            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <label class="checkbox-label">
                <input type="checkbox" name="is_active" checked> Active
            </label>

            <div class="actions">
                <button type="submit">Add Staff</button>
                <a href="manage_staff.php" class="back-link">Back to Staff List</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>