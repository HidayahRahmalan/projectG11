<?php
// Enable error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'connection.php';

// Initialize variables
$name = $email = $password = $confirm_password = "";
$name_err = $email_err = $password_err = $confirm_password_err = "";
$success_message = "";

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter a name.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {

        if (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
            // This error will show if the format is wrong (e.g., no '@' symbol)
            $email_err = "Please enter a valid email address.";
        } else {
            $email = trim($_POST["email"]);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    if (empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        try {
            $sql_check = "SELECT user_id FROM user WHERE email = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $email_err = "This email is already taken.";
            } else {
                $sql_insert = "INSERT INTO user (name, email, password, role) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);

                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $param_role = 'Viewer'; // Automatically assign "Viewer" role

                $stmt_insert->bind_param("ssss", $name, $email, $param_password, $param_role);

                if ($stmt_insert->execute()) {
                    $success_message = "Registration successful! You can now <a href='login.php'>log in</a>.";
                    $name = $email = "";
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                $stmt_insert->close();
            }

            $stmt_check->close();

        } catch (mysqli_sql_exception $e) {
            die("Database error: " . $e->getMessage());
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />
    <style>
      .password-group { position: relative; }
      .password-toggle {
          position: absolute;
          right: 15px;
          top: 50%;
          transform: translateY(-50%);
          cursor: pointer;
          color: #aaa;
          z-index: 2;
      }
      #password-strength { margin-top: 10px; margin-bottom: 1.5rem; }
      #password-strength-bar-container {
          width: 100%; height: 8px; background-color: #e0e0e0;
          border-radius: 4px; overflow: hidden; margin-bottom: 8px;
      }
      #password-strength-bar { height: 100%; width: 0; transition: all 0.3s ease; }
      #password-strength-text { font-size: 0.9rem; font-weight: 500; }
      .strength-weak { background-color: #dc3545; color: #dc3545; }
      .strength-medium { background-color: #ffc107; color: #ffc107; }
      .strength-strong { background-color: #28a745; color: #28a745; }
      #password-requirements { list-style: none; padding: 0; margin-top: 8px; font-size: 0.85rem; color: #666; }
      #password-requirements li.valid { color: #28a745; }
      #password-requirements li.valid::before { content: '✓ '; font-weight: bold; }
      #password-requirements li::before { content: '• '; }
      .form-error { color: #dc3545; font-size: 0.9rem; margin-top: 5px; display: block; }
      .form-success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #c3e6cb; text-align: center; }
      .form-success a { color: #155724; font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
          <a href="index.php" class="logo">🍳 CookTogether</a>
          <div class="nav-links">
            <a class="nav-link" href="index.php">Home</a>
            <a class="nav-link" href="upload.php">Upload Recipe</a>
            <a class="nav-link" href="login.php">Login</a>
          </div>
        </div>
    </nav>

    <div class="container">
        <div class="auth-section">
            <h1 class="auth-title">Create Account</h1>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                
                <?php if(!empty($success_message)){ echo '<div class="form-success">' . $success_message . '</div>'; } ?>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your name...">
                    <span class="form-error"><?php echo $name_err; ?></span>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email...">
                    <span class="form-error"><?php echo $email_err; ?></span>
                </div>

                <div class="form-group password-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Create a password...">
                    <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                </div>
                <span class="form-error"><?php echo $password_err; ?></span>
                
                <div id="password-strength">
                    <div id="password-strength-bar-container"><div id="password-strength-bar"></div></div>
                    <span id="password-strength-text"></span>
                    <ul id="password-requirements">
                        <li id="req-length">At least 8 characters</li>
                        <li id="req-number">Contains a number</li>
                        <li id="req-symbol">Contains a symbol (!@#$%^&*)</li>
                        <li id="req-uppercase">Contains an uppercase letter</li>
                    </ul>
                </div>
                
                <div class="form-group password-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password...">
                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                    <span class="form-error"><?php echo $confirm_password_err; ?></span>
                </div>
                
                <button type="submit" class="submit-btn">Register</button>
            </form>
            
            <div class="auth-switch">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');
        
        const reqs = {
            length: document.getElementById('req-length'),
            number: document.getElementById('req-number'),
            symbol: document.getElementById('req-symbol'),
            uppercase: document.getElementById('req-uppercase')
        };

        passwordInput.addEventListener('input', function() {
            const pass = this.value;
            let score = 0;
            
            if (pass.length >= 8) { reqs.length.classList.add('valid'); score++; } 
            else { reqs.length.classList.remove('valid'); }
            
            if (/\d/.test(pass)) { reqs.number.classList.add('valid'); score++; } 
            else { reqs.number.classList.remove('valid'); }

            if (/[!@#$%^&*]/.test(pass)) { reqs.symbol.classList.add('valid'); score++; } 
            else { reqs.symbol.classList.remove('valid'); }

            if (/[A-Z]/.test(pass)) { reqs.uppercase.classList.add('valid'); score++; } 
            else { reqs.uppercase.classList.remove('valid'); }

            strengthBar.className = '';
            strengthText.className = '';
            
            if (pass.length > 0) {
                switch (score) {
                    case 0:
                    case 1:
                        strengthText.textContent = 'Strength: Weak';
                        strengthBar.classList.add('strength-weak');
                        strengthText.classList.add('strength-weak');
                        strengthBar.style.width = '25%';
                        break;
                    case 2:
                    case 3:
                        strengthText.textContent = 'Strength: Medium';
                        strengthBar.classList.add('strength-medium');
                        strengthText.classList.add('strength-medium');
                        strengthBar.style.width = '60%';
                        break;
                    case 4:
                        strengthText.textContent = 'Strength: Strong';
                        strengthBar.classList.add('strength-strong');
                        strengthText.classList.add('strength-strong');
                        strengthBar.style.width = '100%';
                        break;
                }
            } else {
                strengthText.textContent = '';
                strengthBar.style.width = '0%';
            }
        });
    </script>
</body>
</html>
