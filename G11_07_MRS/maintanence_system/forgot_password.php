<?php
session_start();
if (isset($_SESSION['id'])) {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    
    // Check if email exists
    $sql = "SELECT User_ID, name FROM USERS WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $token_sql = "INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)";
                if ($token_stmt = $conn->prepare($token_sql)) {
                    $token_stmt->bind_param("iss", $user["User_ID"], $token, $expires);
                    if ($token_stmt->execute()) {
                        // Send reset email
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . 
                                    dirname($_SERVER['PHP_SELF']) . 
                                    "/reset_password.php?token=" . $token;
                        
                        $to = $email;
                        $subject = "Password Reset Request";
                        $message = "Hi " . $user["name"] . ",\n\n";
                        $message .= "You have requested to reset your password. Click the link below to reset it:\n\n";
                        $message .= $reset_link . "\n\n";
                        $message .= "This link will expire in 1 hour.\n\n";
                        $message .= "If you did not request this reset, please ignore this email.\n\n";
                        $message .= "Best regards,\nMaintenance System Team";
                        
                        $headers = "From: noreply@maintenance-system.com";
                        
                        if (mail($to, $subject, $message, $headers)) {
                            $success = "Password reset instructions have been sent to your email.";
                        } else {
                            $error = "Error sending reset email. Please try again later.";
                        }
                    } else {
                        $error = "Error generating reset token. Please try again.";
                    }
                }
            } else {
                $error = "No account found with that email address.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Maintenance System</title>
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
        .forgot-container {
            width: 100%;
            max-width: 450px;
            padding: 0 20px;
        }
        .form-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 32px;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-description {
            text-align: center;
            margin-bottom: 30px;
            color: #666;
            font-size: 14px;
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
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-login a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        .back-to-login a:hover {
            color: #0066cc;
        }
        .alert {
            margin-bottom: 20px;
            font-family: 'DM Sans', sans-serif;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h1 class="form-title">Forgot Password</h1>
        <p class="form-description">Enter your email address and we'll send you instructions to reset your password.</p>
        
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php } ?>
        
        <?php if (!empty($success)) { ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php } ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-reset">Send Reset Link</button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 