<?php
session_start();
require_once 'db_connect.php';

// Check if creator is already logged in
if (isset($_SESSION['creator_id'])) {
    header('Location: cc_dashboard.php');
    exit();
}

$error_messages = [];
$success_message = '';

// Determine current step
$step = 1;
if (isset($_POST['step'])) {
    $step = (int)$_POST['step'];
} elseif (isset($_SESSION['new_creator_id'])) {
    $step = 3; // Account created, go to profile picture
} elseif (isset($_SESSION['reg_email'])) {
    $step = 2; // Email validated, go to registration form
}

// Step 1: Email validation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step']) && $_POST['step'] == '1') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error_messages[] = "Please enter your email address.";
        $step = 1;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Please enter a valid email address.";
        $step = 1;
    } else {
        try {
            // Check if email exists in creator table
            $stmt = $pdo->prepare("SELECT email FROM creator WHERE email = ?");
            $stmt->execute([$email]);
            $creator_exists = $stmt->fetch();
            
            // Check if email exists in user table
            $stmt = $pdo->prepare("SELECT email FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user_exists = $stmt->fetch();
            
            if ($creator_exists || $user_exists) {
                $error_messages[] = "This email is already registered. Please use a different email or sign in.";
                $step = 1;
            } else {
                $step = 2; // Move to registration form
                $_SESSION['reg_email'] = $email;
            }
        } catch (PDOException $e) {
            $error_messages[] = "Database error. Please try again.";
            error_log("Email check error: " . $e->getMessage());
            $step = 1;
        }
    }
}

// Step 2: Full registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step']) && $_POST['step'] == '2') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reg_email'];
    
    // Validation
    if (empty($name)) $error_messages[] = "Please enter your full name.";
    if (empty($username)) $error_messages[] = "Please enter a username.";
    if (strlen($username) < 3) $error_messages[] = "Username must be at least 3 characters long.";
    if (empty($password)) $error_messages[] = "Please enter a password.";
    if (strlen($password) < 8) $error_messages[] = "Password must be at least 8 characters long.";
    if (!preg_match('/\d/', $password)) $error_messages[] = "Password must contain at least one number.";
    if ($password !== $confirm_password) $error_messages[] = "Passwords do not match.";
    
    // Check username availability
    if (!empty($username)) {
        try {
            $stmt = $pdo->prepare("SELECT username FROM creator WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error_messages[] = "Username is already taken. Please choose a different username.";
            }
        } catch (PDOException $e) {
            $error_messages[] = "Database error. Please try again.";
        }
    }
    
    // If no errors, create account
    if (empty($error_messages)) {
        try {
            // Generate creator ID
            $date = date('Ymd');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM creator WHERE creator_id LIKE ?");
            $stmt->execute(["C%$date"]);
            $count = $stmt->fetch()['count'] + 1;
            $creator_id = 'C' . str_pad($count, 4, '0', STR_PAD_LEFT) . $date;
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into database with default pfp initially
            $stmt = $pdo->prepare("INSERT INTO creator (creator_id, name, username, password, email, date_joined, pfp) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)");
            $stmt->execute([$creator_id, $name, $username, $hashed_password, $email, 'pfp.png']);
            
            // Create folders using absolute paths
            $creator_folder = __DIR__ . "/cc/$creator_id";
            if (!file_exists($creator_folder)) {
                if (!mkdir($creator_folder, 0755, true)) {
                    error_log("Failed to create creator folder: $creator_folder");
                    $error_messages[] = "Failed to create user directory. Please try again.";
                    $step = 2;
                } else {
                    // Create subfolders
                    mkdir("$creator_folder/video", 0755, true);
                    mkdir("$creator_folder/pfp", 0755, true);
                    mkdir("$creator_folder/thumbnail", 0755, true);
                    error_log("Successfully created folders for creator: $creator_id");
                }
            }
            
            if (empty($error_messages)) {
                // Store creator info for profile picture upload
                $_SESSION['new_creator_id'] = $creator_id;
                $_SESSION['new_creator_name'] = $name;
                $step = 3; // Move to profile picture upload
            }
            
        } catch (PDOException $e) {
            $error_messages[] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
            $step = 2;
        }
    } else {
        $step = 2; // Stay on registration form if errors
    }
}

// Step 3: Profile picture upload (PNG only, following cc_dashboard.php approach)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step']) && $_POST['step'] == '3') {
    $creator_id = $_SESSION['new_creator_id'];
    
    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] == 0) {
        try {
            $pfp_file = $_FILES['pfp'];

            // PNG only validation
            $allowed_types = ['image/png'];
            $max_size = 10 * 1024 * 1024; // 10MB

            if ($pfp_file['error'] !== 0) {
                throw new Exception("File upload error code: " . $pfp_file['error']);
            }

            if (!in_array($pfp_file['type'], $allowed_types)) {
                throw new Exception("Please upload a PNG image file only. Current type: " . $pfp_file['type']);
            }

            if ($pfp_file['size'] > $max_size) {
                throw new Exception("File size must be less than 10MB. Current size: " . $pfp_file['size']);
            }

            // Create upload path using absolute path (PNG only)
            $base_dir = __DIR__;
            $upload_dir = $base_dir . "/cc/$creator_id/pfp/";
            
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create directory: " . $upload_dir);
                }
            }

            $upload_path = $upload_dir . "pfp.png";

            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Directory is not writable: " . $upload_dir);
            }

            // Delete old profile picture if it exists
            if (file_exists($upload_path)) {
                if (unlink($upload_path)) {
                    error_log("Deleted old profile picture: " . $upload_path);
                } else {
                    error_log("Failed to delete old profile picture: " . $upload_path);
                }
            }

            // Move uploaded file to destination
            if (move_uploaded_file($pfp_file['tmp_name'], $upload_path)) {
                error_log("Profile picture uploaded successfully: $upload_path");

                // Update database with PNG filename
                $stmt = $pdo->prepare("UPDATE creator SET pfp = ? WHERE creator_id = ?");
                if ($stmt->execute(['pfp.png', $creator_id])) {
                    error_log("Database updated successfully with PNG pfp filename");
                    $this_completes_registration = true;
                } else {
                    $error_info = $stmt->errorInfo();
                    throw new Exception("Failed to update database. Error: " . print_r($error_info, true));
                }
            } else {
                throw new Exception("Failed to move uploaded file to " . $upload_path);
            }

        } catch (Exception $e) {
            error_log("Profile picture upload failed: " . $e->getMessage());
            $error_messages[] = "Profile picture upload failed: " . $e->getMessage();
            $step = 3;
        }
    } else {
        // Skip profile picture upload - keep pfp.png as default
        $this_completes_registration = true;
    }
    
    // Complete registration process
    if (isset($this_completes_registration)) {
        // Auto-login and redirect
        $stmt = $pdo->prepare("SELECT * FROM creator WHERE creator_id = ?");
        $stmt->execute([$creator_id]);
        $creator = $stmt->fetch();
        
        $_SESSION['creator_id'] = $creator['creator_id'];
        $_SESSION['creator_username'] = $creator['username'];
        $_SESSION['creator_name'] = $creator['name'];
        $_SESSION['creator_email'] = $creator['email'];
        
        // Clean up registration session data
        unset($_SESSION['reg_email']);
        unset($_SESSION['new_creator_id']);
        unset($_SESSION['new_creator_name']);
        
        $success_message = "Welcome to ChefTube, " . $creator['name'] . "! Your creator account has been successfully created.";
        
        // Redirect after 2 seconds
        echo "<script>
            setTimeout(function() {
                window.location.href = 'cc_dashboard.php';
            }, 2000);
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefTube - Join as Creator</title>
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
            padding: 8px 0;
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

        .continue-btn, .register-btn, .upload-btn {
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

        .continue-btn:hover, .register-btn:hover, .upload-btn:hover {
            background: #f40612;
        }

        .skip-btn {
            width: 100%;
            padding: 16px;
            background: transparent;
            color: #b3b3b3;
            border: 2px solid #333;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .skip-btn:hover {
            border-color: #e50914;
            color: #e50914;
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

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #333;
            color: #888;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }

        .step.active {
            background: #e50914;
            color: #fff;
        }

        .step.completed {
            background: #4caf50;
            color: #fff;
        }

        .file-upload-area {
            border: 2px dashed #333;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: #e50914;
        }

        .file-upload-area.dragover {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }

        .upload-icon {
            font-size: 48px;
            color: #888;
            margin-bottom: 20px;
        }

        .upload-text {
            color: #b3b3b3;
            margin-bottom: 15px;
        }

        .file-input {
            display: none;
        }

        .file-select-btn {
            background: transparent;
            border: 2px solid #333;
            color: #fff;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-select-btn:hover {
            border-color: #e50914;
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
            <div class="subtitle">Creator Portal</div>
        </div>

        <?php if ($step <= 3): ?>
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
        </div>
        <?php endif; ?>

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

        <?php if ($step == 1): ?>
            <h1>Join ChefTube</h1>
            
            <div class="benefits-section">
                <div class="benefits-title">Why you should join ChefTube</div>
                <ul class="benefits-list">
                    <li>Reach thousands of culinary students worldwide</li>
                    <li>Monetize your cooking expertise and recipes</li>
                    <li>Build your personal chef brand and following</li>
                    <li>Connect with passionate food enthusiasts</li>
                    <li>Share your culinary knowledge and techniques</li>
                    <li>Get discovered by restaurants and food networks</li>
                    <li>Access detailed analytics on your content performance</li>
                    <li>Join a community of professional chefs and creators</li>
                </ul>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="step" value="1">
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <button type="submit" class="continue-btn">Continue</button>
            </form>

        <?php elseif ($step == 2): ?>
            <h1>Create Your Account</h1>
            
            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="Enter your full name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" placeholder="Choose a unique username" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
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

        <?php elseif ($step == 3): ?>
            <h1>Upload Profile Picture</h1>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="step" value="3">
                
                <div class="file-upload-area" onclick="document.getElementById('pfp').click()">
                    <div class="upload-icon">📷</div>
                    <div class="upload-text">Click to upload your profile picture</div>
                    <div style="color: #888; font-size: 14px;">PNG images only (Max 10MB)</div>
                    <input type="file" id="pfp" name="pfp" class="file-input" accept="image/png">
                </div>

                <button type="submit" class="upload-btn">Upload & Complete Registration</button>
                <button type="submit" class="skip-btn">Skip for Now</button>
            </form>
        <?php endif; ?>

        <div class="back-to-login">
            Already have an account? <a href="cc_login.php">Sign In</a>
        </div>
    </div>

    <script>
        // Username availability check
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

            // File upload preview (PNG only)
            const fileInput = document.getElementById('pfp');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Validate PNG only
                        if (file.type !== 'image/png') {
                            alert('Please select a PNG image file only.');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const uploadArea = document.querySelector('.file-upload-area');
                            uploadArea.innerHTML = `
                                <img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                                <div style="margin-top: 15px; color: #4caf50;">✓ ${file.name} selected</div>
                            `;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Drag and drop (PNG only)
            const uploadArea = document.querySelector('.file-upload-area');
            if (uploadArea) {
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });

                uploadArea.addEventListener('dragleave', function() {
                    this.classList.remove('dragover');
                });

                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const file = files[0];
                        if (file.type !== 'image/png') {
                            alert('Please select a PNG image file only.');
                            return;
                        }
                        document.getElementById('pfp').files = files;
                        document.getElementById('pfp').dispatchEvent(new Event('change'));
                    }
                });
            }
        });
    </script>
</body>
</html>