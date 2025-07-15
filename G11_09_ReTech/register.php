<?php
session_start();
require 'db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = 'staff'; // fixed role

    if ($username === '' || $email === '' || $password === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username or email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "❌ Username or email already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new staff
            $insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $username, $email, $hashed_password, $role);

            if ($insert->execute()) {
                $success = "✅ Account created successfully. You can now login.";
            } else {
                $error = "❌ Failed to register. Please try again.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Register Staff Account</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #f5f7fa, #c3cfe2);
        }
        .register-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="register-card">
    <h2 class="text-center mb-3">📝 Register Staff</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <a href="login.php" class="btn btn-success w-100 mt-2">➡️ Go to Login</a>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" type="text" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
        </div>
        <!-- Hidden fixed role -->
        <input type="hidden" name="role" value="staff">
        <button type="submit" class="btn btn-primary w-100">Create Staff Account</button>
        <a href="login.php" class="btn btn-secondary w-100 mt-2">← Back to Login</a>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
