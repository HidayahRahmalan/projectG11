<?php
session_start();
require_once 'db_connect.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id, username, password, email FROM user WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                
                $success_message = "Login successful! Redirecting...";
                
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 1000);
                </script>";
            } else {
                $error_message = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            $error_message = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube - Sign In</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('website/background_signin.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .container {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 60px 68px 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            object-fit: cover;
            border-radius: 8px;
        }

        .brand-name {
            font-size: 28px;
            font-weight: bold;
            color: #e50914;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #b3b3b3;
            font-size: 16px;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 28px;
            color: #fff;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            background: #333;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            background: #454545;
        }

        .form-input::placeholder {
            color: #8c8c8c;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: #e50914;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-bottom: 20px;
        }

        .login-btn:hover {
            background: #f40612;
        }

        .error-message {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .success-message {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid #2e7d32;
            color: #4caf50;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .links-section {
            text-align: center;
            margin-top: 20px;
        }

        .links-section a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            margin: 0 10px;
        }

        .links-section a:hover {
            color: #e50914;
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #333;
        }

        .divider span {
            background: rgba(0, 0, 0, 0.85);
            padding: 0 20px;
            color: #737373;
        }

        .register-btn {
            width: 100%;
            padding: 16px;
            background: transparent;
            color: #fff;
            border: 2px solid #333;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .register-btn:hover {
            border-color: #e50914;
            color: #e50914;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <img src="website/icon.png" alt="ChefTube" class="logo">
            <div class="brand-name">ChefTube</div>
            <div class="subtitle">Watch & Learn Cooking</div>
        </div>

        <h1>Sign In</h1>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Username or Email" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password" required>
            </div>

            <button type="submit" name="login" class="login-btn">Sign In</button>
        </form>

        <div class="divider">
            <span>New to ChefTube?</span>
        </div>

        <button type="button" class="register-btn" onclick="window.location.href='user_register.php'">
            Create Account
        </button>

        <div class="links-section">
            <a href="index.php">← Back to Home</a>
            <span style="color: #737373;">|</span>
            <a href="cc_login.php">Creator Login</a>
        </div>
    </div>
</body>
</html>