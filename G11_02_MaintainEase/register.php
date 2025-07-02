<?php
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "INSERT INTO users (name, role, email, password)
            VALUES ('$name', '$role', '$email', '$password')";

    if ($conn->query($sql) === TRUE) {
        $message = "✅ Registered successfully. <a href='login.php'>Login here</a>";
    } else {
        $message = "❌ Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Maintenance Report System</title>
  <style>
    * {
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f4f9ff;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    .register-container {
      background: #fff;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      width: 400px;
    }

    h2 {
      text-align: center;
      color: #333;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select {
      width: 100%;
      padding: 0.8rem;
      margin: 10px 0;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    button {
      width: 100%;
      padding: 0.8rem;
      background: #28a745;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    button:hover {
      background: #218838;
    }

    .login-link {
      text-align: center;
      margin-top: 15px;
    }

    .login-link a {
      color: #007BFF;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    .message {
      text-align: center;
      margin-bottom: 10px;
      color: #d9534f;
    }

    .message a {
      color: #28a745;
    }
  </style>
</head>
<body>
  

<div class="register-container">
  <h2>Register</h2>
  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>
  <form method="POST" action="">
    <input type="text" name="name" placeholder="Full Name" required>
    <select name="role" required>
      <option value="">-- Select Role --</option>
      <option value="staff">Staff</option>
      <option value="admin">Admin</option>
    </select>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register</button>
  </form>
  <div class="login-link">
    Already have an account? <a href="login.php">Login here</a>
  </div>
</div>

</body>
</html>
