<?php
require 'conn.php';
session_start();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $role_id = 3; // Default role for staff (assuming 3 = staff)

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM sys_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email is already registered.";
        } else {
            // Insert new user with plain password (not recommended)
            $stmt = $conn->prepare("INSERT INTO sys_user (name, email, password, role_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $email, $password, $role_id);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role_id;
                header('Location: staff_dashboard.php');
                exit;
            } else {
                $errors[] = "Error while creating the account.";
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - MRS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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

<header>Register</header>

<main>
    <div class="login-container">
        <h2>Create an Account</h2>
        <?php foreach ($errors as $error): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
        <form method="post">
            <input type="text" name="name" placeholder="Full Name" required><br><br>
            <input type="email" name="email" placeholder="Email" required><br><br>
            <input type="password" name="password" placeholder="Password" required><br><br>
            <input type="password" name="confirm" placeholder="Confirm Password" required><br><br>
            <button type="submit" class="button">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</main>

<footer>&copy; 2025 RMS. All rights reserved.</footer>

</body>
</html>
