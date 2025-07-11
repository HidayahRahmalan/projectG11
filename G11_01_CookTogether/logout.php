<?php
// Initialize the session.
// This is necessary to access and destroy the session.
session_start();
 
// Unset all of the session variables.
// This empties the $_SESSION array, removing all stored data.
$_SESSION = array();
 
// Destroy the session itself.
// This cleans up the session on the server.
session_destroy();
 
// Redirect the user to the login page.
// The user is now logged out and should be sent to a public page.
header("location: login.php");
exit; // It's a good practice to call exit() after a header redirect.
?>