CREATE DATABASE IF NOT EXISTS capstone_db;
USE capstone_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, 
    role ENUM('admin', 'student', 'staff') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Plain text password for easy testing
INSERT INTO users (username, password, role) 
VALUES ('testuser', 'password123', 'student');