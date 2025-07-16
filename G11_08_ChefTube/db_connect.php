<?php
/**
 * ChefTube Database Connection
 * MySQLi Connection for XAMPP
 */

// Database configuration
$db_host = 'localhost';
$db_name = 'p25_cheftube';
$db_user = 'larry';
$db_pass = 'larry123';
$db_port = '3306'; 

// Create mysqli connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {

    die("Connection failed: " . $conn->connect_error);

   
}



?>
