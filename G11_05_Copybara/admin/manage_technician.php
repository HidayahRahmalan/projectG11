<?php
session_start();
require '../conn.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) {
    header("Location: ../login.php");
    exit;
}
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

$technicianRoleId = 2; // Change if your technician role_id is different
$result = $conn->query("SELECT user_id, name, email, specialization, is_active, date_created FROM sys_user WHERE role_id = $technicianRoleId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Technicians - MRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; }
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
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        h2 { color: #253444; margin: 2rem 0 1rem 0; font-size: 1.7rem; font-weight: bold; }
        .add-btn { background: #4285F4; color: #fff; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 1.2rem; font-size: 1rem; font-weight: 500; }
        .add-btn:hover { background: #306ac3; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden; }
        th, td { padding: 0.8rem 1rem; border-bottom: 1px solid #eaeaea; text-align: left; }
        th { background: #e8f0fe; color: #253444; font-size: 1.07rem; font-weight: bold; }
        tr:last-child td { border-bottom: none; }
        .status-active { color: #0a0; font-weight: bold; }
        .status-inactive { color: #a00; font-weight: bold; }
        .action-btn { padding: 4px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px; font-size: 0.98rem; }
        .action-btn.edit { background: #ffc107; color: #222; }
        .action-btn.delete { background: #dc3545; color: #fff; }
        .action-btn.edit:hover { background: #e0a800; }
        .action-btn.delete:hover { background: #c82333; }
        @media (max-width: 1100px) { .container { padding: 0 8px; } }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding-top: 70px; }
            .sidebar { position: static; width: 100%; min-height: auto; flex-direction: row; }
            .sidebar-header, .sidebar nav, .sidebar .logout-link { padding-left: 1rem; }
            .container { padding: 0 8px; }
            header.admin-header { font-size: 1.2rem; }
        }
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
        <div class="sidebar-section-title" >User Management</div>
        <a href="manage_staff.php"><i class="fas fa-user-tie"></i> Staff</a>
        <a href="manage_technician.php" class="active"><i class="fas fa-user-cog"></i> Technician </a>
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
        <h2>Technician Management</h2>
        <button class="add-btn" onclick="location.href='add_technician.php'">Add Technician</button>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Specialization</th>
                <th>Status</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['specialization']) ?></td>
                <td>
                    <?php if ($row['is_active']): ?>
                        <span class="status-active">Active</span>
                    <?php else: ?>
                        <span class="status-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['date_created']) ?></td>
                <td>
                    <a href="edit_technician.php?id=<?= $row['user_id'] ?>" class="action-btn edit">Edit</a>
                    <a href="delete_technician.php?id=<?= $row['user_id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this technician?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($result->num_rows == 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#888;">No technicians found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>