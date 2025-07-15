<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $username, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($input_password, $hashed_password)) {
            // Successful login
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;

            header("Location: " . ($role === 'admin' ? 'dashboardadmin.php' : 'dashboard_staff.php'));
            exit;
        }
    }
    $error = "Invalid username or password.";
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Maintenance Report System • Login</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-title {
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #008C9E;
        }

        .login-subtitle {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 24px;
        }

        .btn-primary {
            border-radius: 10px;
        }

        .form-control {
            border-radius: 10px;
        }

        .error-msg {
            animation: fade 0.5s ease-in-out;
        }

        @keyframes fade {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body>

    <div class="login-card">
        <!-- Page heading -->
        <h2 class="login-title text-center">🔧 Maintenance Report System</h2>
        <p class="login-subtitle text-center">Staff &nbsp;•&nbsp; Admin Portal</p>

        <!-- Error alert -->
        <?php if ($error): ?>
            <div class="alert alert-danger error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input id="username" name="username" type="text" class="form-control" placeholder="Enter username"
                    required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" class="form-control" placeholder="Enter password"
                    required>
            </div>
            <p class="text-center mt-3">
                Don’t have an account? <a href="register.php">Register here</a>
            </p>

            <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
        </form>
    </div>

</body>

</html>