<?php
$host = "localhost";
$user = "MaintainEase";
$pass = "1234";
$dbname = "p25_maintainease";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
