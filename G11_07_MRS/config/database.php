<?php
// Database configuration
$db_host = "localhost";
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

// Set character set
$conn->set_charset("latin1_swedish_ci");

?> 
