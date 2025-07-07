<?php
$host = "localhost";   
$user = "mmdb1";     
$pass = "mmdb1";     
$dbname = "p25_complain";   

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set charset
$conn->set_charset("utf8");

?>
