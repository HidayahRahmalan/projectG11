<?php
$servername = "localhost";
$username = "facilityfix";
$password = "facility123";
$dbname = "p25_multimedia";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} /*else {
    echo "Database connection successful!";
} */
?>