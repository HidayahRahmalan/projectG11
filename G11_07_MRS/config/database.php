<?php
// Database configuration
$db_host = "bitp3353.utem.edu.my";
$db_user = "mrs";
$db_password = "12345";
$db_name = "p25_maintenance_db";

// Create connection without database selection
$conn = new mysqli($db_host, $db_user, $db_password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_name);

// Set character set
$conn->set_charset("utf8mb4");

// Create remember_tokens table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(User_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token)
)";

if (!$conn->query($sql)) {
    error_log("Error creating remember_tokens table: " . $conn->error);
}

// Create password_resets table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(User_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token)
)";

if (!$conn->query($sql)) {
    error_log("Error creating password_resets table: " . $conn->error);
}

// Create comments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS COMMENTS (
    Comment_ID INT AUTO_INCREMENT PRIMARY KEY,
    Report_ID VARCHAR(10) NOT NULL,
    User_ID INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Report_ID) REFERENCES REPORT(Report_ID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID) REFERENCES USERS(User_ID) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    error_log("Error creating COMMENTS table: " . $conn->error);
}
?> 
