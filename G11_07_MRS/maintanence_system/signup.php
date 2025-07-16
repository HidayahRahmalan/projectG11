<?php
session_start();
// Only allow access if not logged in or if role is customer
if (isset($_SESSION['id']) && $_SESSION['role'] !== 'customer') {
    header('Location: home.php');
    exit();
}
require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    
    // Validate email
    if ($stmt = $conn->prepare("SELECT User_ID FROM USERS WHERE email = ?")) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $signup_err = "This email is already taken.";
        }
        $stmt->close();
    }
    
    // Validate password
    if (strlen($password) < 6) {
        $signup_err = "Password must have at least 6 characters.";
    }
    
    // If no errors, proceed with registration
    if (empty($signup_err)) {
        $sql = "INSERT INTO USERS (name, email, password, role) VALUES (?, ?, ?, 'customer')";
        if ($stmt = $conn->prepare($sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                header("location: login.php");
                exit();
            } else {
                $signup_err = "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Maintenance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Google Fonts -->
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
        .signup-container {
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
        .login-link {
            text-align: center;
            margin-bottom: 40px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
        }
        .login-link a {
            color: #000;
            text-decoration: none;
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
        .btn-signup {
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
        .btn-signup:hover {
            background: #0052a3;
        }
        .alert {
            font-family: 'DM Sans', sans-serif;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <h1 class="form-title">Create new Account</h1>
        <p class="login-link">Already Registered? <a href="login.php">Login</a></p>
        
        <?php if (!empty($signup_err)) { ?>
            <div class="alert alert-danger"><?php echo $signup_err; ?></div>
        <?php } ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-signup">sign up</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 