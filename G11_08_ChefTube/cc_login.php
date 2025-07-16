<?php
// Custom logging function for login debug
function login_debug_log($message) {
    $log_file = "C:/xampp/htdocs/cheftube/debug.log";
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] LOGIN DEBUG: $message\n", FILE_APPEND | LOCK_EX);
}

login_debug_log("=== LOGIN PAGE LOADED ===");
login_debug_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
login_debug_log("POST data: " . print_r($_POST, true));

session_start();
require_once 'db_connect.php';

// Check if creator is already logged in
if (isset($_SESSION['creator_id'])) {
    login_debug_log("User already logged in, redirecting to dashboard");
    header('Location: cc_dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission - Check for POST request first
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    login_debug_log("=== POST REQUEST RECEIVED ===");
    
    // Check if this is a login attempt
    if (isset($_POST['login']) || (isset($_POST['username']) && isset($_POST['password']))) {
        login_debug_log("=== LOGIN ATTEMPT STARTED ===");
        
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        login_debug_log("Username entered: " . $username);
        login_debug_log("Password length: " . strlen($password));
        
        if (empty($username) || empty($password)) {
            $error_message = "Please fill in all fields.";
            login_debug_log("Error: Empty username or password");
        } else {
            try {
                login_debug_log("Attempting database connection...");
                
                // Check if creator exists
                $stmt = $pdo->prepare("SELECT creator_id, username, password, name, email FROM creator WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $creator = $stmt->fetch();
                
                login_debug_log("Database query executed successfully");
                
                if ($creator) {
                    login_debug_log("Creator found in database:");
                    login_debug_log("- Creator ID: " . $creator['creator_id']);
                    login_debug_log("- Name: " . $creator['name']);
                    login_debug_log("- Username: " . $creator['username']);
                    login_debug_log("- Email: " . $creator['email']);
                    
                    // Test password verification
                    login_debug_log("Testing password verification...");
                    $password_check = password_verify($password, $creator['password']);
                    login_debug_log("Password verification result: " . ($password_check ? 'TRUE (SUCCESS)' : 'FALSE (FAILED)'));
                    
                    if ($password_check) {
                        login_debug_log("Password verified successfully! Proceeding with login...");
                        
                        // Login successful
                        $_SESSION['creator_id'] = $creator['creator_id'];
                        $_SESSION['creator_username'] = $creator['username'];
                        $_SESSION['creator_name'] = $creator['name'];
                        $_SESSION['creator_email'] = $creator['email'];
                        
                        login_debug_log("Session variables set:");
                        login_debug_log("- Session creator_id: " . $_SESSION['creator_id']);
                        login_debug_log("- Session creator_name: " . $_SESSION['creator_name']);
                        
                        // Force redirect with JavaScript as backup
                        login_debug_log("Attempting redirect to cc_dashboard.php");
                        $success_message = "Login successful! Redirecting...";
                        
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'cc_dashboard.php';
                            }, 1000);
                        </script>";
                        
                        // Also try PHP redirect
                        if (!headers_sent()) {
                            header('Location: cc_dashboard.php');
                            exit();
                        }
                    } else {
                        login_debug_log("Password verification FAILED");
                        login_debug_log("Expected password hash: " . $creator['password']);
                        login_debug_log("Entered password: " . $password);
                        
                        // Test with common passwords
                        $common_passwords = ['password', '123456', 'admin', $username];
                        foreach ($common_passwords as $test_pass) {
                            if (password_verify($test_pass, $creator['password'])) {
                                login_debug_log("Password appears to be: " . $test_pass);
                                break;
                            }
                        }
                        
                        $error_message = "Invalid username/email or password.";
                    }
                } else {
                    login_debug_log("No creator found with username/email: " . $username);
                    
                    // List all creators for debugging
                    $stmt = $pdo->prepare("SELECT username, email FROM creator");
                    $stmt->execute();
                    $all_creators = $stmt->fetchAll();
                    login_debug_log("Available creators in database:");
                    foreach ($all_creators as $c) {
                        login_debug_log("- Username: " . $c['username'] . ", Email: " . $c['email']);
                    }
                    
                    $error_message = "Invalid username/email or password.";
                }
            } catch (PDOException $e) {
                login_debug_log("Database error: " . $e->getMessage());
                $error_message = "Login failed. Please try again.";
            } catch (Exception $e) {
                login_debug_log("General error: " . $e->getMessage());
                $error_message = "An unexpected error occurred.";
            }
        }
        
        login_debug_log("=== LOGIN ATTEMPT COMPLETED ===");
    } else {
        login_debug_log("POST request received but no login data found");
    }
} else {
    login_debug_log("Page loaded without POST request (GET request)");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube - Creator Sign In</title>
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
            position: relative;
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
            margin-bottom: 12px;
        }

        .login-btn:hover {
            background: #f40612;
        }

        .login-btn:active {
            background: #d40813;
        }

        .login-btn:disabled {
            background: #666;
            cursor: not-allowed;
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



        .form-help {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 60px;
            font-size: 13px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            color: #b3b3b3;
        }

        .remember-me input {
            margin-right: 8px;
            transform: scale(1.2);
        }

        .forgot-password {
            color: #b3b3b3;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: #fff;
        }

        .signup-section {
            color: #737373;
            font-size: 16px;
        }

        .signup-section a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .signup-section a:hover {
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

        @media (max-width: 480px) {
            .container {
                padding: 40px 30px;
                margin: 20px;
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
            <div class="subtitle">Creator Portal</div>
        </div>

        <h1>Sign In</h1>



        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Form with better debugging -->
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Username or Email" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required autocomplete="username">
            </div>

            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password" 
                       required autocomplete="current-password">
            </div>

            <button type="submit" name="login" value="1" class="login-btn" id="loginBtn">Sign In</button>

            <div class="form-help">
                <label class="remember-me">
                    <input type="checkbox" name="remember_me">
                    Remember me
                </label>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>
        </form>

        <div class="divider">
            <span>New to ChefTube?</span>
        </div>

        <button type="button" class="register-btn" onclick="window.location.href='cc_register.php'">
            Create Creator Account
        </button>

        <div class="signup-section" style="text-align: center; margin-top: 20px;">
            <span>Are you a viewer? </span>
            <a href="user_login.php">Sign in as User</a>
        </div>
    </div>

    <script>
        console.log('Login page loaded');

        // Enhanced form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const inputs = document.querySelectorAll('.form-input');
            
            // Add interactive effects
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Enhanced form submission
            form.addEventListener('submit', function(e) {
                console.log('Form submission started');
                
                const username = form.querySelector('input[name="username"]').value;
                const password = form.querySelector('input[name="password"]').value;
                
                console.log('Username:', username);
                console.log('Password length:', password.length);
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
                
                loginBtn.innerHTML = 'Signing In...';
                loginBtn.disabled = true;
                
                console.log('Form submitting...');
                
                // Let the form submit normally
                return true;
            });

            // Test form submission function
            window.testFormSubmission = function() {
                console.log('Testing form submission');
                form.querySelector('input[name="username"]').value = 'asdada';
                form.querySelector('input[name="password"]').value = 'test123';
                form.submit();
            };
        });
    </script>
</body>
</html>