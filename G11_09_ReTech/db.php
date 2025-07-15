<?php
$servername = "localhost";
$username = "Querty";      
$password = "12345!";          
$dbname = "report"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
