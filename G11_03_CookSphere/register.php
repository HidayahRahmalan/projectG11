<?php
// register.php
session_start();

// DB configuration
$host = 'localhost';
$db = 'p25_cooksphere';
$user = 'cooksphere';
$pass = 'Abc123';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get and sanitize inputs
$fullname = trim($_POST['fullname']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$role = $_POST['role'];

$fullname = $conn->real_escape_string($fullname);
$email = $conn->real_escape_string($email);
$role = $conn->real_escape_string($role);
$password = $conn->real_escape_string($password);

// Validate role
$allowed_roles = ['chef', 'student'];
if (!in_array($role, $allowed_roles)) {
    echo "<script>alert('Invalid role.'); window.history.back();</script>";
    exit();
}

// Check if email exists
$check = $conn->query("SELECT * FROM user WHERE Email = '$email'");
if ($check && $check->num_rows > 0) {
    echo "<script>alert('Email already exists!'); window.history.back();</script>";
    exit();
}

// Hash password
//$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$sql = "INSERT INTO user (FullName, Email, Password, Role) VALUES ('$fullname', '$email', '$password', '$role')";

if ($conn->query($sql) === TRUE) {
    echo "<script>alert('Registration successful! You can now log in.'); window.location.href='index.html';</script>";
} else {
    echo "<script>alert('Error during registration.'); window.history.back();</script>";
}

$conn->close();
?>
