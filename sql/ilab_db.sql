-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS ilab_system;

-- Select the database
USE ilab_system;

-- Create the admin_users table
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL, -- store hashed passwords securely
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin account
-- Username: admin
-- Password: admin1234 (hashed)
-- Email: admin@gmail.com
-- Name: Administrator
INSERT INTO admin_users (name, email, username, password)
VALUES (
  'Administrator',
  'admin@gmail.com',
  'admin',
  '$2y$10$eBzvS6VYX7IkKb1o6t.TCuWfBSP6DZYbRExbhAvlK4pJDsyOn1v1G'
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(100),
  course VARCHAR(100),
  year_level INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS faculty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(100),
  department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lab_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('student', 'faculty') NOT NULL,
  user_id INT NOT NULL,
  pc_number INT NOT NULL,
  time_in DATETIME DEFAULT CURRENT_TIMESTAMP,
  time_out DATETIME NULL,
  status ENUM('active', 'ended') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_number INT NOT NULL,
  issue_description TEXT,
  status ENUM('pending', 'resolved') DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS student_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  request_type VARCHAR(100),
  description TEXT,
  status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_text TEXT,
  activity_type VARCHAR(50),
  icon_class VARCHAR(50), -- like 'fas fa-user-check'
  color_class VARCHAR(50), -- like 'text-green-600'
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
