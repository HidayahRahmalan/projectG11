<?php
// Initialize the session
session_start();

// If the user is already logged in, redirect them to the home page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: indexCT.php");
    exit;
}

require_once "connection.php";

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate email and password inputs
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Proceed if there are no validation errors
    if (empty($email_err) && empty($password_err)) {
        
        // Prepare SQL statement to fetch user data
        $sql = "SELECT user_id, name, password, role FROM user WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);

            if ($stmt->execute()) {
                $stmt->store_result();

                // Check if exactly one account with that email exists
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($user_id, $name, $hashed_password, $role);
                    
                    if ($stmt->fetch()) {
                        // Verify the password from the form against the hashed password
                        if (password_verify($password, $hashed_password)) {
                            
                            // --- PASSWORD IS CORRECT ---
                            // Session was already started. Now, store user data in session variables.
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["name"] = $name;
                            $_SESSION["role"] = $role; // This is the most important part for permissions

                            // CHANGED: Redirect all users to the homepage. 
                            // The navbar will dynamically show the correct links based on their role.
                            header("location: home.php");
                            exit;

                        } else {
                            // Generic error for wrong password
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else {
                    // Generic error for user not found
                    $login_err = "Invalid email or password.";
                }
            } else {
                // Generic error for database execution issues
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
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
    <title>Login - CookTogether</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
      /* You can move these specific styles to your main style.css if you want */
      .form-error { color: #dc3545; font-size: 0.9rem; margin-top: 5px; display: block; }
      .login-error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #f5c6cb; text-align: center; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
          <a href="indexCT.php" class="logo">🍳 CookTogether</a>
          <div class="nav-links">
            <a class="nav-link" href="indexCT.php">Home</a>
            <a class="nav-link" href="about.php">About Us</a>
            <a class="nav-link" href="login.php">Login</a>
          </div>
        </div>
      </nav>

    <div class="container">
        <div class="auth-section">
            <h1 class="auth-title">Welcome Back</h1>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                
                <?php 
                if(!empty($login_err)){
                    echo '<div class="login-error">' . $login_err . '</div>';
                }        
                ?>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email...">
                    <span class="form-error"><?php echo $email_err; ?></span>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password...">
                    <span class="form-error"><?php echo $password_err; ?></span>
                </div>
                
                <button type="submit" class="submit-btn">Login</button>
            </form>
            
            <div class="auth-switch">
                Don't have an account? <a href="register_viewer.php">Register</a>
            </div>
        </div>
    </div>
</body>
</html>