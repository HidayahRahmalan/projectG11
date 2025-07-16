<?php
// FILE: auth/auth_instructor.php

// Start the session to be able to read the $_SESSION variables.
session_start();

// If the user is not logged in OR their role is not 'Instructor',
// send them back to the login page and stop the script.
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Instructor') {
    header("Location: ../auth/login.html");
    exit();
}
?>