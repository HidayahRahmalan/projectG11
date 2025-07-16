<?php
session_start();
require_once "config/database.php";
include "includes/admin_header.php";

// Protect Admin Access
if (!isset($_SESSION['id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Add new staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash("default123", PASSWORD_DEFAULT); // default password

    $stmt = $conn->prepare("INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $role, $password);
    $stmt->execute();
}

// Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $userId = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE User_ID=?");
    $stmt->bind_param("sssi", $name, $email, $role, $userId);
    $stmt->execute();
}

// Delete user
if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE User_ID = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
}

// Fetch users
$result = $conn->query("SELECT User_ID, name, email, role FROM users ORDER BY role, name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7fb;
            margin: 20px;
        }
        .container {
            margin-left: 60px; /* Shift page to the right */
        }
        h2, h3 {
            color: #1a4c87;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: left;
        }
        th {
            background-color: #1a4c87;
            color: white;
        }
        form.inline-form {
            display: inline-block;
        }
        input[type=text], input[type=email], select {
            padding: 6px;
            width: 100%;
            margin-bottom: 6px;
            box-sizing: border-box;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            cursor: pointer;
            color: white;
            border-radius: 3px;
        }
        .btn-add {
            background-color: #27ae60;
        }
        .btn-edit {
            background-color: #3498db;
        }
        .btn-delete {
            background-color: #e74c3c;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .form-section {
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 6px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">

<h2>User Management Panel</h2>

<div class="form-section">
    <h3>Add New Staff</h3>
    <form method="post">
        <input type="hidden" name="add_user" value="1">
        <label>Name:</label>
        <input type="text" name="name" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Role:</label>
        <select name="role" required>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
        </select>

        <button type="submit" class="btn btn-add">Add Staff</button>
    </form>
</div>

<h3>All Users</h3>
<table>
    <thead>
        <tr>
            <th>User_ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Edit</th>
            <th>Delete</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['User_ID'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= ucfirst($row['role']) ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="user_id" value="<?= $row['User_ID'] ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" required>
                        <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
                        <select name="role">
                            <option value="customer" <?= $row['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="staff" <?= $row['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <button type="submit" class="btn btn-edit">Save</button>
                    </form>
                </td>
                <td>
                    <form method="get" class="inline-form" onsubmit="return confirm('Delete this user?');">
                        <input type="hidden" name="delete" value="<?= $row['User_ID'] ?>">
                        <button type="submit" class="btn btn-delete">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</div>
</body>
</html>
