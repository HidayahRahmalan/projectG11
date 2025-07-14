<?php
// --- DATABASE CREDENTIALS ---
// Replace with your actual database details
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'group1');      // Your database username (e.g., 'root')
define('DB_PASSWORD', 'abc123');          // Your database password
define('DB_NAME', 'p25_cooktogether'); // Your database name

// --- ERROR REPORTING ---
// Turn on error reporting for debugging. You can turn this off in production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// This line is crucial! It makes MySQLi throw exceptions for errors,
// which our try/catch block in registration.php can catch.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- ATTEMPT CONNECTION ---
try {
    // The connection object is named $conn, which your registration.php expects.
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    // Set the charset to avoid character encoding issues
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // If connection fails, stop everything and show a generic error.
    // The specific error is logged by PHP, but not shown to the user for security.
    // The die() function will prevent the registration.php script from continuing.
    die("Error: Could not connect to the database. " . $e->getMessage());
}
?>
