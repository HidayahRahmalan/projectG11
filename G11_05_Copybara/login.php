<?php
require 'conn.php';
session_start();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, password, role_id, is_active FROM sys_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $name, $stored_password, $role_id, $is_active);
            $stmt->fetch();

            if (!$is_active) {
                $errors[] = "Your account is inactive.";
            } elseif ($password === $stored_password) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role_id;

                switch ($role_id) {
                    case 1:
                        header("Location: admin/admin_dashboard.php");
                        break;
                    case 2:
                        header("Location: technician/tech_dashboard.php");
                        break;
                    case 3:
                        header("Location: staff/staff_dashboard.php");
                        break;
                    default:
                        $errors[] = "Unknown role. Please contact the administrator.";
                        session_destroy();
                        exit;
                }
                exit;
            } else {
                $errors[] = "Invalid credentials.";
            }
        } else {
            $errors[] = "User not found.";
        }

        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - MRS</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

body {
    background: url('uploads/background.avif') no-repeat center top fixed;
    background-size: 1000px auto;
    background-repeat: no-repeat;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: #333;
}


        header {
            background-color: #2c3e50;
            color: #fff;
            text-align: center;
            padding: 1rem 0;
            font-size: 1.5rem;
            font-weight: bold;
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .login-container h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .login-container p {
            font-size: 16px;
            margin-bottom: 30px;
            color: #555;
        }

        .button {
            display: inline-block;
            padding: 12px 25px;
            margin: 10px;
            background-color: #4285F4;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
        }

        .button:hover {
            background-color: #3367D6;
            transform: scale(1.05);
        }

        footer {
            text-align: center;
            padding: 1rem;
            background-color: #f1f1f1;
            font-size: 14px;
        }
    </style>
</head>
<body>

<header>Maintenance Report System</header>

<main>
    <div class="login-container">
        <h2>Log In</h2>
        <?php foreach ($errors as $error): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Email" required><br><br>
            <input type="password" name="password" placeholder="Password" required><br><br>
            <button type="submit" class="button">Login</button>
        </form>
                <p>No account? <a href="register.php">Register</a></p>
    </div>
</main>

<footer>&copy; 2025 RMS. All rights reserved.</footer>

</body>
</html>
