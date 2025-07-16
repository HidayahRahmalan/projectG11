<?php
session_start();
require_once "config/database.php";

// Initialize login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

$lockout_time = 15 * 60; // 15 minutes
$login_err = "";

// Lockout logic
if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_time']) < $lockout_time) {
    $remaining_time = $lockout_time - (time() - $_SESSION['last_attempt_time']);
    $login_err = "Too many failed attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $remember = isset($_POST["remember"]) ? $_POST["remember"] : "";

    $sql = "SELECT * FROM USERS WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row["password"])) {
                    $_SESSION['login_attempts'] = 0;

                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $row["User_ID"];
                    $_SESSION["name"] = $row["name"];
                    $_SESSION["profile_picture"] = $row["profile_picture"];
                    $_SESSION["role"] = $row["role"];

                    // Remember me
                    if ($remember == "on") {
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                        $token_sql = "INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)";
                        if ($token_stmt = $conn->prepare($token_sql)) {
                            $token_stmt->bind_param("iss", $row["User_ID"], $token, $expires);
                            $token_stmt->execute();
                            setcookie("remember_token", $token, strtotime('+30 days'), "/", "", true, true);
                        }
                    }

                    // Redirect by role
                    if ($_SESSION["role"] === "Admin") {
                        header("location: admin_dashboard.php");
                    } elseif ($_SESSION["role"] === "Staff") {
                        header("location: staff_dashboard.php");
                    } else {
                        header("location: home.php");
                    }
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    $login_err = "Invalid password.";
                }
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $login_err = "Invalid email.";
            }
        }
        $stmt->close();
    }
}

// Auto-login using cookie
if (!isset($_SESSION["loggedin"]) && isset($_COOKIE["remember_token"])) {
    $token = $_COOKIE["remember_token"];
    $token_sql = "SELECT u.* FROM USERS u 
                  JOIN remember_tokens rt ON u.User_ID = rt.user_id 
                  WHERE rt.token = ? AND rt.expires > NOW()";

    if ($token_stmt = $conn->prepare($token_sql)) {
        $token_stmt->bind_param("s", $token);
        $token_stmt->execute();
        $token_result = $token_stmt->get_result();

        if ($token_result->num_rows == 1) {
            $user = $token_result->fetch_assoc();
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $user["User_ID"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["profile_picture"] = $user["profile_picture"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "Admin") {
                header("location: admin_dashboard.php");
            } elseif ($user["role"] === "Staff") {
                header("location: staff_dashboard.php");
            } else {
                header("location: home.php");
            }
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Maintenance System</title>
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
        .login-container {
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
        .register-link {
            text-align: center;
            margin-bottom: 40px;
            font-size: 14px;
        }
        .register-link a {
            color: #000;
            text-decoration: none;
        }
        .form-label {
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-login {
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
        .btn-login:hover {
            background: #0052a3;
        }
        .alert {
            font-family: 'DM Sans', sans-serif;
        }
        .form-check {
            margin-top: 15px;
        }
        .form-check-label {
            font-size: 14px;
        }
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        .forgot-password a {
            color: #666;
            font-size: 14px;
            text-decoration: none;
        }
        .forgot-password a:hover {
            color: #0066cc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="form-title">Login</h1>
        <p class="register-link">Not registered yet? <a href="signup.php">Register</a></p>

        <?php if (!empty($login_err)) { ?>
            <div class="alert alert-danger"><?php echo $login_err; ?></div>
        <?php } ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            <button type="submit" class="btn btn-login">Login</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
