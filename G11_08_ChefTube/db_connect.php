<?php
/**
 * ChefTube Database Connection
 * PDO MySQL Connection for XAMPP
 */

// Database configuration
$db_host = 'localhost';
$db_name = 'p25_cheftube';
$db_user = 'larry';
$db_pass = 'larry123';
$db_port = '3306';

try {
    // Create PDO connection
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    // Set timezone (optional - adjust to your timezone)
    $pdo->exec("SET time_zone = '+08:00'"); // Malaysia timezone
    
    // Connection success message (remove in production)
    // echo "Database connected successfully!<br>";
    
} catch (PDOException $e) {
    // Development error handling - shows detailed errors
    die("Connection failed: " . $e->getMessage());
    
    // Production error handling (uncomment for live site)
    // error_log("Database connection error: " . $e->getMessage());
    // die("Database connection failed. Please try again later.");
}

/**
 * Function to check if connection is alive
 * @return bool
 */
function isConnected() {
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Function to close connection (optional - PDO closes automatically)
 */
function closeConnection() {
    global $pdo;
    $pdo = null;
}

// Usage examples (remove these comments in production):
/*
// How to use this file in other PHP files:

// Include the connection
require_once 'db_connect.php';

// Example: Get all videos
$stmt = $pdo->prepare("SELECT * FROM video ORDER BY date_uploaded DESC");
$stmt->execute();
$videos = $stmt->fetchAll();

// Example: Get video by ID (with prepared statement)
$video_id = 'V1';
$stmt = $pdo->prepare("SELECT * FROM video WHERE vid_id = ?");
$stmt->execute([$video_id]);
$video = $stmt->fetch();

// Example: Insert new user
$stmt = $pdo->prepare("INSERT INTO user (user_id, username, password, date_joined, pfp, email) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $username, $hashed_password, $date_joined, $pfp, $email]);

// Example: Update video views
$stmt = $pdo->prepare("UPDATE video SET views = views + 1 WHERE vid_id = ?");
$stmt->execute([$video_id]);
*/
?>
