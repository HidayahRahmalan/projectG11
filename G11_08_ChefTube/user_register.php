<?php
session_start();
require_once 'db_connect.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_messages = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username)) $error_messages[] = "Please enter a username.";
    if (strlen($username) < 3) $error_messages[] = "Username must be at least 3 characters long.";
    if (empty($email)) $error_messages[] = "Please enter your email address.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error_messages[] = "Please enter a valid email address.";
    if (empty($password)) $error_messages[] = "Please enter a password.";
    if (strlen($password) < 8) $error_messages[] = "Password must be at least 8 characters long.";
    if (!preg_match('/\d/', $password)) $error_messages[] = "Password must contain at least one number.";
    if ($password !== $confirm_password) $error_messages[] = "Passwords do not match.";
    
    // Check if username or email already exists
    if (empty($error_messages)) {
        try {
            // Check username in both user and creator tables
            $stmt = $pdo->prepare("SELECT username FROM user WHERE username = ? UNION SELECT username FROM creator WHERE username = ?");
            $stmt->execute([$username, $username]);
            if ($stmt->fetch()) {
                $error_messages[] = "Username is already taken. Please choose a different username.";
            }
            
            // Check email in both user and creator tables
            $stmt = $pdo->prepare("SELECT email FROM user WHERE email = ? UNION SELECT email FROM creator WHERE email = ?");
            $stmt->execute([$email, $email]);
            if ($stmt->fetch()) {
                $error_messages[] = "This email is already registered. Please use a different email or sign in.";
            }
        } catch (PDOException $e) {
            $error_messages[] = "Database error. Please try again.";
            error_log("Registration validation error: " . $e->getMessage());
        }
    }
    
    // If no errors, create account
    if (empty($error_messages)) {
        try {
            // Generate user ID
            $date = date('Ymd');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user WHERE user_id LIKE ?");
            $stmt->execute(["U%$date"]);
            $count = $stmt->fetch()['count'] + 1;
            $user_id = 'U' . str_pad($count, 4, '0', STR_PAD_LEFT) . $date;
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO user (user_id, username, password, email, date_joined, pfp) VALUES (?, ?, ?, ?, CURDATE(), ?)");
            $stmt->execute([$user_id, $username, $hashed_password, $email, 'default.png']);
            
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_username'] = $username;
            $_SESSION['user_email'] = $email;
            
            $success_message = "Account created successfully! Welcome to ChefTube!";
            
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
            
        } catch (PDOException $e) {
            $error_messages[] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube - Join the Community</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px 0;
        }

        .container {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 40px 50px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin: 20px;
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
            margin-bottom: 20px;
            color: #fff;
            text-align: center;
        }

        .benefits-section {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .benefits-title {
            color: #e50914;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
        }

        .benefits-list {
            list-style: none;
            color: #b3b3b3;
        }

        .benefits-list li {
            padding: 6px 0;
            position: relative;
            padding-left: 25px;
        }

        .benefits-list li::before {
            content: "✓";
            color: #e50914;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #b3b3b3;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            background: #333;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            background: #454545;
            transform: scale(1.02);
        }

        .form-input::placeholder {
            color: #8c8c8c;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .register-btn {
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

        .register-btn:hover {
            background: #f40612;
        }

        .error-messages {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .error-messages ul {
            margin: 0;
            padding-left: 20px;
        }

        .success-message {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid #2e7d32;
            color: #4caf50;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 16px;
            text-align: center;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
            color: #737373;
        }

        .back-to-login a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-to-login a:hover {
            color: #e50914;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .brand-name {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <img src="website/icon.png" alt="ChefTube" class="logo">
            <div class="brand-name">ChefTube</div>
            <div class="subtitle">Join the Culinary Community</div>
        </div>

        <h1>Create Your Account</h1>
        
        <div class="benefits-section">
            <div class="benefits-title">Why join ChefTube?</div>
            <ul class="benefits-list">
                <li>Watch unlimited cooking tutorials</li>
                <li>Save your favorite recipes</li>
                <li>Comment and interact with creators</li>
                <li>Get personalized recommendations</li>
                <li>Join a community of food lovers</li>
            </ul>
        </div>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_messages)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Choose a unique username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="Enter your email address" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Min 8 characters, include numbers" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                </div>
            </div>

            <button type="submit" class="register-btn">Create Account</button>
        </form>

        <div class="back-to-login">
            Already have an account? <a href="user_login.php">Sign In</a>
            <br><br>
            Want to create content? <a href="cc_register.php">Join as Creator</a>
        </div>
    </div>

    <script>
        // Username availability check (optional enhancement)
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) {
                usernameInput.addEventListener('blur', function() {
                    const username = this.value.trim();
                    if (username.length >= 3) {
                        // You can add AJAX call here for real-time username checking
                        console.log('Checking username availability:', username);
                    }
                });
            }
        });
    </script>
</body>
</html>