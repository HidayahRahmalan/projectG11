<?php
session_start();
include 'dbconnection.php';

// Retrieve form data
$role = $_POST['Role'];
$email = $_POST['Email'];
$password = $_POST['Password'];

// Find the data of user
$sql = "SELECT user_id, name, password FROM users WHERE email = ? AND role = ?";
$stmt_select = $conn->prepare($sql);
$stmt_select->bind_param("ss", $email, $role);
$stmt_select->execute();
$stmt_select->store_result();

// Check if a matching row was found
if ($stmt_select->num_rows == 0) {
    $_SESSION['login_error'] = "Invalid Email or Password.";
    header("Location: index.php");
    exit();
} else {
    $stmt_select->bind_result($user_id, $name, $stored_password);
    $stmt_select->fetch();

    if (password_verify($password, $stored_password)) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['name'] = $name;

        if ($role == 'Staff') {
            header("Location: staff/home.php");
            exit();
        } else {
            header("Location: admin/dashboard.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Invalid Email or Password.";
        header("Location: index.php");
        exit();
    }
}
$stmt_select->close();
$conn->close();