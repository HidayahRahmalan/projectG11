<?php
session_start();
require_once 'db_connect.php';

// Check if creator is logged in
if (!isset($_SESSION['creator_id'])) {
    header('Location: cc_login.php');
    exit();
}

$creator_id = $_SESSION['creator_id'];
$success_message = '';
$error_message = '';

// Get current creator data
try {
    $stmt = $pdo->prepare("SELECT * FROM creator WHERE creator_id = ?");
    $stmt->execute([$creator_id]);
    $creator = $stmt->fetch();
    
    if (!$creator) {
        header('Location: cc_login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validation
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($username)) $errors[] = "Username is required.";
    if (strlen($username) < 3) $errors[] = "Username must be at least 3 characters long.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    
    // Check if username or email is taken by other users
    if ($username !== $creator['username']) {
        $stmt = $pdo->prepare("SELECT creator_id FROM creator WHERE username = ? AND creator_id != ?");
        $stmt->execute([$username, $creator_id]);
        if ($stmt->fetch()) {
            $errors[] = "Username is already taken by another creator.";
        }
        
        // Also check user table
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username is already taken by a user.";
        }
    }
    
    if ($email !== $creator['email']) {
        $stmt = $pdo->prepare("SELECT creator_id FROM creator WHERE email = ? AND creator_id != ?");
        $stmt->execute([$email, $creator_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already registered by another creator.";
        }
        
        // Also check user table
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already registered by a user.";
        }
    }
    
    // Password change validation
    $update_password = false;
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password.";
        } elseif (!password_verify($current_password, $creator['password'])) {
            $errors[] = "Current password is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long.";
        } elseif (!preg_match('/\d/', $new_password)) {
            $errors[] = "New password must contain at least one number.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        } else {
            $update_password = true;
        }
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        try {
            if ($update_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE creator SET name = ?, username = ?, email = ?, password = ? WHERE creator_id = ?");
                $stmt->execute([$name, $username, $email, $hashed_password, $creator_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE creator SET name = ?, username = ?, email = ? WHERE creator_id = ?");
                $stmt->execute([$name, $username, $email, $creator_id]);
            }
            
            // Update session variables
            $_SESSION['creator_name'] = $name;
            $_SESSION['creator_username'] = $username;
            $_SESSION['creator_email'] = $email;
            
            $success_message = "Profile updated successfully!";
            
            // Refresh creator data
            $creator['name'] = $name;
            $creator['username'] = $username;
            $creator['email'] = $email;
            
        } catch (PDOException $e) {
            $error_message = "Failed to update profile. Please try again.";
            error_log("Profile update error: " . $e->getMessage());
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Get creator profile picture path
$pfp_path = "cc/$creator_id/pfp/pfp.png";
if (!file_exists($pfp_path)) {
    $pfp_path = "website/default_avatar.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - ChefTube Creator</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f0f;
            color: #fff;
            min-height: 100vh;
        }

        /* Top Navigation */
        .top-nav {
            background: #212121;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #3a3a3a;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            color: #e50914;
        }

        .back-btn {
            color: #aaa;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-weight: 500;
            border: 1px solid #3a3a3a;
        }

        .back-btn:hover {
            color: #fff;
            border-color: #e50914;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3a3a3a;
        }

        .profile-name {
            font-weight: 500;
            color: #aaa;
        }

        /* Main Content */
        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #aaa;
            font-size: 16px;
        }

        /* Profile Form */
        .profile-form {
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 12px;
            padding: 32px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #e50914;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            color: #fff;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: #333;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #e50914;
            background: #404040;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert.success {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid #2e7d32;
            color: #4caf50;
        }

        .alert.error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #3a3a3a;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            font-size: 16px;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #3a3a3a;
            color: #aaa;
        }

        .btn-secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        .btn-primary {
            background: #e50914;
            color: #fff;
        }

        .btn-primary:hover {
            background: #f40612;
        }

        .password-note {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 16px;
            }
            
            .profile-form {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-section">
                <img src="website/icon.png" alt="ChefTube" class="logo">
                <div class="brand-name">ChefTube</div>
            </div>
            <a href="cc_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="nav-right">
            <img src="<?php echo htmlspecialchars($pfp_path); ?>" alt="Profile" class="profile-avatar">
            <span class="profile-name"><?php echo htmlspecialchars($creator['name']); ?></span>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Update your creator profile information</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form class="profile-form" method="POST">
            <!-- Basic Information -->
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?php echo htmlspecialchars($creator['name']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-input" 
                               value="<?php echo htmlspecialchars($creator['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($creator['email']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Creator ID</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($creator['creator_id']); ?>" readonly style="background: #2a2a2a; color: #888;">
                </div>

                <div class="form-group">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-input" value="<?php echo date('F j, Y', strtotime($creator['date_joined'])); ?>" readonly style="background: #2a2a2a; color: #888;">
                </div>
            </div>

            <!-- Password Change -->
            <div class="form-section">
                <h3 class="section-title">Change Password</h3>
                
                <div class="password-note">
                    Leave password fields empty if you don't want to change your password.
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" 
                           placeholder="Enter your current password">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" 
                               placeholder="Min 8 characters, include numbers">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Confirm your new password">
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="cc_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </main>
</body>
</html>