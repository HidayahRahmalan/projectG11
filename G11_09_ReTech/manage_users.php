<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$result = $conn->query("SELECT * FROM users ORDER BY role, username");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { margin: 0; background: #f8f9fa; }
    .wrapper { display: flex; min-height: 100vh; }
    .content { flex-grow: 1; padding: 40px; }
    .box {
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 0 10px rgba(0,0,0,.05);
    }
  </style>
</head>
<body>

<div class="wrapper">
  <?php include 'adminSidebar.php'; ?>

  <div class="content">
    <div class="box mx-auto" style="max-width: 1000px;">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Manage Users</h2>
        <a href="add_user.php" class="btn btn-success">
          <i class="bi bi-plus-circle me-1"></i> Add User
        </a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-warning">No users found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th style="width: 160px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($u = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <span class="badge bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                    <?= $u['role'] ?>
                  </span>
                </td>
                <td>
                  <?php if ($_SESSION['user_id'] != $u['id']): ?>
                    <a href="delete_user.php?id=<?= $u['id'] ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure to delete this user?');">
                      🗑️ Delete
                    </a>
                  <?php else: ?>
                    <span class="text-muted">Current User</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
