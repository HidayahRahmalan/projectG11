<?php
$password = "abc123";
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>