<?php
require 'conn.php';

session_start();
$role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MRS - Welcome</title>
    <link rel="stylesheet" href="style.css">
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
.main-content {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.about-link {
    margin-top: 20px;
    text-align: center;
}
.button-secondary {
    background-color: #000;
    color: #fff;
}

.button-secondary:hover {
    background-color: #333;
}


    </style>
</head>
<body>

<header>Maintenance Report System</header>
<main>
    <div class="main-content">
        <div class="login-container">
            <h2>Welcome to Maintenance Report System</h2>
            <p>Please register or log in to continue:</p>
            <a href="register.php" class="button">Register</a>
            <a href="login.php" class="button">Login</a>
        </div>

        <!-- About Us button -->
<div class="about-link">
    <a href="indexmadam.php" class="button button-secondary">About Us</a>
</div>
    </div>
</main>


<footer>&copy; 2025 RMS. All rights reserved.</footer>

</body>
</html>
