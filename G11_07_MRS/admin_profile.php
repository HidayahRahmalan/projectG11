<?php
session_start();
// Check if staff is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['id'];
require_once "config/database.php";
include "includes/admin_header.php";

// Add profile_picture column if it doesn't exist
$check_column = "SHOW COLUMNS FROM USERS LIKE 'profile_picture'";
$column_exists = $conn->query($check_column)->num_rows > 0;

if (!$column_exists) {
    $add_column = "ALTER TABLE USERS ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL";
    try {
        $conn->query($add_column);
    } catch (Exception $e) {
        // If there's an error, log it but don't stop execution
        error_log("Error adding profile_picture column: " . $e->getMessage());
    }
}

// Ensure uploads directory exists
$upload_dir = "uploads/profile_pictures";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    // For security, set proper permissions after creation
    chmod($upload_dir, 0755);
}

// Get user data
$user_id = $_SESSION['id'];
$sql = "SELECT * FROM USERS WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success_message = '';
$error_message = '';

// Handle profile update
if (isset($_POST["update_profile"])) {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    
    // Verify current password if trying to change password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $error_message = "Current password is required to change password.";
        } else if (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE USERS SET name = ?, email = ?, password = ? WHERE User_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $email, $hashed_password, $user_id);
        }
    } else {
        // Update without changing password
        $sql = "UPDATE USERS SET name = ?, email = ? WHERE User_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $email, $user_id);
    }
    
    if (empty($error_message) && $stmt->execute()) {
        $success_message = "Profile updated successfully!";
        $_SESSION['name'] = $name;
    } else if (empty($error_message)) {
        $error_message = "Failed to update profile.";
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_picture']['name'];
    $filetype = $_FILES['profile_picture']['type'];
    $filesize = $_FILES['profile_picture']['size'];
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        $error_message = "Please upload an image file (JPG, PNG, or GIF).";
    } else if ($filesize > 5242880) { // 5MB max
        $error_message = "File size must be less than 5MB.";
    } else {
        // Create uploads directory if it doesn't exist
        $upload_path = "uploads/profile_pictures/";
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        
        // Generate unique filename
        $new_filename = "profile_" . $user_id . "." . $ext;
        $upload_file = $upload_path . $new_filename;
        
        // Delete old profile picture if exists
        $old_files = glob($upload_path . "profile_" . $user_id . ".*");
        foreach ($old_files as $old_file) {
            if (is_file($old_file)) {
                unlink($old_file);
            }
        }
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_file)) {
            // Update profile picture path in database
            $sql = "UPDATE USERS SET profile_picture = ? WHERE User_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_filename, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Profile picture updated successfully!";
                $_SESSION['profile_picture'] = $new_filename;
            } else {
                $error_message = "Failed to update profile picture in database.";
            }
        } else {
            $error_message = "Failed to upload profile picture.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        :root {
            --primary-bg: #bbdefb;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --header-height: 60px;
            --transition-speed: 0.5s;
            --transition-curve: cubic-bezier(0.4, 0, 0.2, 1);
        }

        .container-fluid {
            margin-left: var(--sidebar-width);
            padding: 25px;
            min-width: 800px;
            transition: all var(--transition-speed) var(--transition-curve);
            will-change: margin-left;
        }

        .container-fluid.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .page-header {
            background: linear-gradient(to right, var(--primary-bg), #e3f2fd);
            padding: 25px 30px;
            margin: -25px -25px 25px -25px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all var(--transition-speed) var(--transition-curve);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 24px;
            color: #1a237e;
            font-weight: 600;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        .profile-sidebar {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            height: fit-content;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-main {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 3px solid var(--primary-bg);
            overflow: hidden;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all var(--transition-speed) var(--transition-curve);
        }

        .profile-avatar i {
            font-size: 64px;
            color: #1976d2;
        }

        .profile-info {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: #1a237e;
            margin: 0 0 5px;
        }

        .profile-role {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-item:hover {
            background: #e3f2fd;
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #1976d2;
            margin: 0;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0;
        }

        .profile-actions {
            margin-top: 30px;
        }

        .btn-profile {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #1976d2;
        }

        .btn-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-profile:active {
            transform: translateY(0);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a237e;
            margin: 0 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #1a237e;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            transition: all 0.3s;
            background-color: white;
        }

        .form-control:hover {
            border-color: #bbdefb;
        }

        .form-control:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
            outline: none;
        }

        .activity-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .activity-item {
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .activity-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .activity-title {
            font-weight: 500;
            color: #1a237e;
        }

        .activity-date {
            font-size: 12px;
            color: #666;
        }

        .activity-content {
            font-size: 14px;
            color: #333;
            margin: 0;
        }

        @media (max-width: 1200px) {
            .container-fluid {
                margin-left: 0;
                padding: 80px 25px 25px 25px;
                min-width: auto;
                width: auto;
            }

            .profile-container {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                order: -1;
            }
        }

        @media (min-width: 1201px) {
            .main-content.expanded .container-fluid {
                margin-left: var(--sidebar-collapsed-width);
            }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .alert i {
            font-size: 20px;
        }

        .profile-avatar {
            position: relative;
            cursor: pointer;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .profile-avatar:hover .avatar-overlay {
            opacity: 1;
        }

        .avatar-upload-btn {
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .avatar-upload-btn i {
            font-size: 24px;
        }

        .avatar-upload-btn span {
            font-size: 12px;
        }

        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e3f2fd;
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            position: relative;
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #1976d2;
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .tab-btn.active {
            color: #1976d2;
        }

        .tab-btn.active::after {
            transform: scaleX(1);
        }

        .tab-btn:hover {
            color: #1976d2;
        }

        .tab-content {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.hidden {
            display: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .form-divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: #e0e0e0;
        }

        .form-divider span {
            background: white;
            padding: 0 15px;
            color: #666;
            position: relative;
            font-size: 14px;
        }

        .password-input {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s;
        }

        .password-toggle:hover {
            color: #1976d2;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2);
        }

        .btn-reset {
            background: #f5f5f5;
            color: #666;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
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
    <div class="container-fluid">
        <div class="page-header">
            <h1>Profile Settings</h1>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php if (isset($user['profile_picture']) && !empty($user['profile_picture'])): ?>
                        <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             onerror="this.onerror=null; this.src='assets/images/default-avatar.png';">
                    <?php else: ?>
                        <i class="bi bi-person"></i>
                    <?php endif; ?>
                    <div class="avatar-overlay">
                        <label for="profile_picture" class="avatar-upload-btn">
                            <i class="bi bi-camera"></i>
                            <span>Change Photo</span>
                        </label>
                    </div>
                </div>

                <div class="profile-info">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="profile-role"><i class="bi bi-shield-check"></i> <?php echo htmlspecialchars($user['role']); ?></p>
                </div>

                <div class="profile-actions">
                    <button class="btn-profile btn-primary">
                        <i class="bi bi-pencil-square"></i>
                        Edit Profile
                    </button>
                    <button class="btn-profile btn-secondary">
                        <i class="bi bi-shield-lock"></i>
                        Security Settings
                    </button>
                </div>
            </div>

            <div class="profile-main">
                <div class="profile-tabs">
                    <button class="tab-btn active" data-tab="personal">
                        <i class="bi bi-person-badge"></i>
                        Personal Info
                    </button>
                </div>

                <div class="tab-content" id="personal-tab">
                    <form action="" method="POST" class="profile-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="form-label">
                                    <i class="bi bi-person"></i>
                                    Full Name
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label">
                                    <i class="bi bi-telephone"></i>
                                    Phone Number
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="department" class="form-label">
                                    <i class="bi bi-building"></i>
                                    Department
                                </label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-divider">
                            <span>Security Settings</span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password" class="form-label">
                                    <i class="bi bi-key"></i>
                                    Current Password
                                </label>
                                <div class="password-input">
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <button type="button" class="password-toggle">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Required only if changing password</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">
                                    <i class="bi bi-lock"></i>
                                    New Password
                                </label>
                                <div class="password-input">
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <button type="button" class="password-toggle">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Leave blank to keep current password</div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <input type="hidden" name="update_profile" value="1">
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-check-lg"></i>
                                Save Changes
                            </button>
                            <button type="reset" class="btn-reset">
                                <i class="bi bi-x-lg"></i>
                                Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <form id="avatar-form" action="" method="POST" enctype="multipart/form-data" style="display: none;">
        <input type="file" name="profile_picture" id="profile_picture" accept="image/*">
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar state management
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const container = document.querySelector('.container-fluid');

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.classList.contains('collapsed')) {
                    container.classList.add('expanded');
                } else {
                    container.classList.remove('expanded');
                }
            });
        });

        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

        if (sidebar.classList.contains('collapsed')) {
            container.classList.add('expanded');
        }

        // Profile picture upload
        const avatarForm = document.getElementById('avatar-form');
        const profilePicInput = document.getElementById('profile_picture');
        const profileAvatar = document.querySelector('.profile-avatar img');

        profilePicInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (profileAvatar) {
                        profileAvatar.src = e.target.result;
                    } else {
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = 'Profile Picture';
                        document.querySelector('.profile-avatar i').replaceWith(newImg);
                    }
                }
                reader.readAsDataURL(this.files[0]);
                avatarForm.submit();
            }
        });

        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.add('hidden'));
                
                btn.classList.add('active');
                document.getElementById(`${tabId}-tab`).classList.remove('hidden');
            });
        });

        // Password visibility toggle
        const passwordToggles = document.querySelectorAll('.password-toggle');
        
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const input = toggle.previousElementSibling;
                const icon = toggle.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });
    });
    </script>
</body>
</html> 