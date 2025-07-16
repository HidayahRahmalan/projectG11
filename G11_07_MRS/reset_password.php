<?php
session_start();
if (isset($_SESSION['id'])) {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

$error = "";
$success = "";

// Verify token
if (isset($_GET["token"])) {
    $token = $_GET["token"];
    
    // Check if token exists and is valid
    $sql = "SELECT pr.*, u.email 
            FROM password_resets pr 
            JOIN USERS u ON pr.user_id = u.User_ID 
            WHERE pr.token = ? AND pr.expires > NOW() 
            ORDER BY pr.created_at DESC 
            LIMIT 1";
            
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $reset = $result->fetch_assoc();
                
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    $password = $_POST["password"];
                    $confirm_password = $_POST["confirm_password"];
                    
                    if (strlen($password) < 8) {
                        $error = "Password must be at least 8 characters long.";
                    } elseif ($password != $confirm_password) {
                        $error = "Passwords do not match.";
                    } else {
                        // Update password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $update_sql = "UPDATE USERS SET password = ? WHERE User_ID = ?";
                        
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $hashed_password, $reset["user_id"]);
                            if ($update_stmt->execute()) {
                                // Delete used token
                                $delete_sql = "DELETE FROM password_resets WHERE token = ?";
                                if ($delete_stmt = $conn->prepare($delete_sql)) {
                                    $delete_stmt->bind_param("s", $token);
                                    $delete_stmt->execute();
                                }
                                
                                $success = "Password has been reset successfully. You can now <a href='index.php'>login</a> with your new password.";
                            } else {
                                $error = "Error updating password. Please try again.";
                            }
                        }
                    }
                }
            } else {
                $error = "Invalid or expired reset token.";
            }
        }
        $stmt->close();
    }
} else {
    $error = "Reset token not provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Maintenance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            background: #fff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Sans', sans-serif;
        }
        .reset-container {
            width: 100%;
            max-width: 450px;
            padding: 0 20px;
        }
        .form-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 32px;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-label {
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .form-control {
            font-family: 'DM Sans', sans-serif;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-reset {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            background: #0066cc;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            width: 100%;
            margin-top: 20px;
            text-transform: uppercase;
        }
        .btn-reset:hover {
            background: #0052a3;
        }
        .alert {
            margin-bottom: 20px;
            font-family: 'DM Sans', sans-serif;
        }
        .alert a {
            color: inherit;
            text-decoration: underline;
        }
        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1 class="form-title">Reset Password</h1>
        
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php } ?>
        
        <?php if (!empty($success)) { ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php } else if (empty($error) || strpos($error, "Password") !== false) { ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?token=" . $token); ?>" method="post">
                <div class="mb-4">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="password-requirements">Password must be at least 8 characters long</div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-reset">Reset Password</button>
            </form>
        <?php } ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 