-- Create database if not exists
CREATE DATABASE IF NOT EXISTS maintenance_system;
USE maintenance_system;

-- Users table
CREATE TABLE IF NOT EXISTS USERS (
    User_ID INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'staff', 'technician', 'customer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Maintenance Reports table
CREATE TABLE IF NOT EXISTS MAINTENANCE_REPORTS (
    Report_ID INT PRIMARY KEY AUTO_INCREMENT,
    User_ID INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES USERS(User_ID),
    FOREIGN KEY (assigned_to) REFERENCES USERS(User_ID)
);

-- Comments table
CREATE TABLE IF NOT EXISTS COMMENTS (
    Comment_ID INT PRIMARY KEY AUTO_INCREMENT,
    Report_ID INT NOT NULL,
    User_ID INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Report_ID) REFERENCES MAINTENANCE_REPORTS(Report_ID),
    FOREIGN KEY (User_ID) REFERENCES USERS(User_ID)
);

-- Insert default admin user if not exists
INSERT INTO USERS (username, password, email, name, role)
SELECT 'admin', '$2y$10$8K1p/a0dR1x0M1m0Z1p0O.1p0O1p0O1p0O1p0O1p0O1p0O1p0O1p0O', 'admin@example.com', 'System Administrator', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE role = 'admin');

-- Create indexes for better performance
CREATE INDEX idx_users_role ON USERS(role);
CREATE INDEX idx_reports_status ON MAINTENANCE_REPORTS(status);
CREATE INDEX idx_reports_priority ON MAINTENANCE_REPORTS(priority);
CREATE INDEX idx_reports_assigned_to ON MAINTENANCE_REPORTS(assigned_to);
CREATE INDEX idx_comments_report ON COMMENTS(Report_ID); 