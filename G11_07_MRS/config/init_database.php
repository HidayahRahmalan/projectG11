<?php
require_once "database.php";

// Create USERS table
$sql = "CREATE TABLE IF NOT EXISTS USERS (
    User_ID INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'Staff', 'User') NOT NULL DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
)";

if (!$conn->query($sql)) {
    die("Error creating USERS table: " . $conn->error);
}

// Create URGENCY_LEVEL table
$sql = "CREATE TABLE IF NOT EXISTS URGENCY_LEVEL (
    Urgency_ID INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    description TEXT,
    color_code VARCHAR(7) DEFAULT '#000000'
)";

if (!$conn->query($sql)) {
    die("Error creating URGENCY_LEVEL table: " . $conn->error);
}

// Insert default urgency levels if they don't exist
$urgency_levels = [
    ['High', 'Urgent issues requiring immediate attention', '#dc3545'],
    ['Medium', 'Important issues requiring attention within 24-48 hours', '#ffc107'],
    ['Low', 'Non-critical issues that can be addressed during regular maintenance', '#28a745']
];

foreach ($urgency_levels as $level) {
    $sql = "INSERT IGNORE INTO URGENCY_LEVEL (label, description, color_code) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $level[0], $level[1], $level[2]);
    $stmt->execute();
}

// Create CATEGORY table
$sql = "CREATE TABLE IF NOT EXISTS CATEGORY (
    Category_ID INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    die("Error creating CATEGORY table: " . $conn->error);
}

// Insert default categories if they don't exist
$categories = [
    ['Electrical', 'Electrical system related issues'],
    ['Plumbing', 'Water and plumbing related issues'],
    ['HVAC', 'Heating, ventilation, and air conditioning issues'],
    ['Structural', 'Building structure related issues'],
    ['IT/Network', 'IT infrastructure and network related issues'],
    ['Safety', 'Safety and security related issues'],
    ['General', 'General maintenance issues']
];

foreach ($categories as $category) {
    $sql = "INSERT IGNORE INTO CATEGORY (name, description) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $category[0], $category[1]);
    $stmt->execute();
}

// Create REPORT table
$sql = "CREATE TABLE IF NOT EXISTS REPORT (
    Report_ID VARCHAR(10) PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    Description TEXT NOT NULL,
    Location VARCHAR(255) NOT NULL,
    User_ID INT NOT NULL,
    Category_ID INT NOT NULL,
    Urgency_ID INT NOT NULL,
    report_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES USERS(User_ID),
    FOREIGN KEY (Category_ID) REFERENCES CATEGORY(Category_ID),
    FOREIGN KEY (Urgency_ID) REFERENCES URGENCY_LEVEL(Urgency_ID)
)";

if (!$conn->query($sql)) {
    die("Error creating REPORT table: " . $conn->error);
}

// Create STATUS_LOG table
$sql = "CREATE TABLE IF NOT EXISTS STATUS_LOG (
    Status_ID INT AUTO_INCREMENT PRIMARY KEY,
    Report_ID VARCHAR(10) NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    updated_by INT NOT NULL,
    updated_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (Report_ID) REFERENCES REPORT(Report_ID) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES USERS(User_ID)
)";

if (!$conn->query($sql)) {
    die("Error creating STATUS_LOG table: " . $conn->error);
}

// Create MEDIA table
$sql = "CREATE TABLE IF NOT EXISTS MEDIA (
    Media_ID INT AUTO_INCREMENT PRIMARY KEY,
    Report_ID VARCHAR(10) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    FOREIGN KEY (Report_ID) REFERENCES REPORT(Report_ID) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES USERS(User_ID)
)";

if (!$conn->query($sql)) {
    die("Error creating MEDIA table: " . $conn->error);
}

// Create COMMENTS table
$sql = "CREATE TABLE IF NOT EXISTS COMMENTS (
    Comment_ID INT AUTO_INCREMENT PRIMARY KEY,
    Report_ID VARCHAR(10) NOT NULL,
    User_ID INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Report_ID) REFERENCES REPORT(Report_ID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID) REFERENCES USERS(User_ID)
)";

if (!$conn->query($sql)) {
    die("Error creating COMMENTS table: " . $conn->error);
}

// Create remember_tokens table
$sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(User_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token)
)";

if (!$conn->query($sql)) {
    die("Error creating remember_tokens table: " . $conn->error);
}

// Create password_resets table
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(User_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token)
)";

if (!$conn->query($sql)) {
    die("Error creating password_resets table: " . $conn->error);
}

// Create default admin user if it doesn't exist
$admin_name = "Administrator";
$admin_email = "admin@system.com";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);
$admin_role = "Admin";

$sql = "INSERT IGNORE INTO USERS (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $admin_name, $admin_email, $admin_password, $admin_role);
$stmt->execute();

echo "Database initialization completed successfully!\n";
echo "Default admin credentials:\n";
echo "Email: admin@system.com\n";
echo "Password: admin123\n";
echo "\nPlease change these credentials after first login.";

$conn->close();
?> 