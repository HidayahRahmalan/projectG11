<?php
/**
 * ChefTube Configuration File
 * 
 * This file contains all the configuration settings for the ChefTube application.
 * Make sure to update these settings according to your environment.
 */

// Prevent direct access
if (!defined('CHEFTUBE_APP')) {
    die('Direct access not permitted');
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'cheftube');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// APPLICATION SETTINGS
// =============================================================================
define('APP_NAME', 'ChefTube');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, production
define('APP_DEBUG', true);
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');

// Base URLs and Paths
define('BASE_URL', 'http://localhost/cheftube/');
define('UPLOAD_PATH', 'cc/');
define('WEBSITE_PATH', 'website/');

// =============================================================================
// SECURITY SETTINGS
// =============================================================================
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes

// CSRF Protection
define('CSRF_TOKEN_NAME', '_token');
define('CSRF_TOKEN_LENGTH', 32);

// =============================================================================
// FILE UPLOAD SETTINGS
// =============================================================================
// Video settings
define('MAX_VIDEO_SIZE', 1024 * 1024 * 1024); // 1GB
define('ALLOWED_VIDEO_TYPES', ['video/mp4']);
define('VIDEO_UPLOAD_PATH', 'video/');

// Image settings
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('THUMBNAIL_UPLOAD_PATH', 'thumbnail/');
define('PFP_UPLOAD_PATH', 'pfp/');

// =============================================================================
// CONTENT SETTINGS
// =============================================================================
define('MAX_TITLE_LENGTH', 100);
define('MAX_DESCRIPTION_LENGTH', 1000);
define('MAX_USERNAME_LENGTH', 50);
define('MIN_USERNAME_LENGTH', 3);

// =============================================================================
// EMAIL SETTINGS (for future features)
// =============================================================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@cheftube.com');
define('FROM_NAME', 'ChefTube');

// =============================================================================
// PAGINATION SETTINGS
// =============================================================================
define('VIDEOS_PER_PAGE', 20);
define('COMMENTS_PER_PAGE', 10);
define('SEARCH_RESULTS_PER_PAGE', 15);

// =============================================================================
// LOGGING SETTINGS
// =============================================================================
define('LOG_ERRORS', true);
define('LOG_FILE', 'logs/error.log');
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR

// =============================================================================
// CACHE SETTINGS
// =============================================================================
define('CACHE_ENABLED', false);
define('CACHE_LIFETIME', 3600); // 1 hour

// =============================================================================
// API SETTINGS (for future features)
// =============================================================================
define('API_ENABLED', false);
define('API_RATE_LIMIT', 100); // requests per hour
define('API_VERSION', 'v1');

// =============================================================================
// SOCIAL MEDIA SETTINGS (for future features)
// =============================================================================
define('FACEBOOK_APP_ID', '');
define('GOOGLE_CLIENT_ID', '');
define('TWITTER_API_KEY', '');

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get application configuration value
 */
function get_config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if we're in development mode
 */
function is_development() {
    return APP_ENV === 'development';
}

/**
 * Check if we're in production mode
 */
function is_production() {
    return APP_ENV === 'production';
}

/**
 * Get base URL
 */
function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Get upload URL
 */
function upload_url($path = '') {
    return BASE_URL . UPLOAD_PATH . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset_url($path = '') {
    return BASE_URL . WEBSITE_PATH . ltrim($path, '/');
}

/**
 * Log error message
 */
function log_error($message, $level = 'ERROR') {
    if (!LOG_ERRORS) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION[CSRF_TOKEN_NAME] = $token;
    return $token;
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Format file size
 */
function format_file_size($bytes) {
    if ($bytes === 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types, $max_size) {
    $errors = [];
    
    if (!isset($file) || $file['error'] !== 0) {
        $errors[] = "File upload failed.";
        return $errors;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "File type not allowed.";
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "File size exceeds maximum limit of " . format_file_size($max_size) . ".";
    }
    
    return $errors;
}

/**
 * Generate unique filename
 */
function generate_unique_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid()), 0, 8);
    
    return $prefix . $timestamp . '_' . $random . '.' . $extension;
}

// =============================================================================
// INITIALIZATION
// =============================================================================

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
if (is_development()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Upload configuration
ini_set('upload_max_filesize', format_file_size(MAX_VIDEO_SIZE));
ini_set('post_max_size', format_file_size(MAX_VIDEO_SIZE));
ini_set('max_execution_time', 300); // 5 minutes
ini_set('max_input_time', 300);

// =============================================================================
// CONSTANTS FOR EASY ACCESS
// =============================================================================
define('CHEFTUBE_INITIALIZED', true);
?>