<?php

$host = "localhost";
$user = "cooksphere";
$pass = "Abc123";
$dbname = "cooksphere";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>
