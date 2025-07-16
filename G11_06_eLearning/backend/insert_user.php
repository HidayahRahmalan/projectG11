<?php
include 'connection.php';

$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'student';
$status = $_POST['status'] ?? 'Active';

if (!$fullname || !$email || !$username || !$password) {
    echo "Missing fields";
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (FullName, Email, Username, PasswordHash, UserRole, users_Status) 
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$result = $stmt->execute([$fullname, $email, $username, $hashedPassword, $role, $status]);

if ($result) {
    echo "success";
} else {
    echo "error";
}
