-- Create the database
CREATE DATABASE IF NOT EXISTS ilab_system;
USE ilab_system;

-- Drop existing tables (CAUTION: dev use only)
DROP TABLE IF EXISTS attendance_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS pc_reservations;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS student_logs;
DROP TABLE IF EXISTS lab_activities;
DROP TABLE IF EXISTS maintenance_requests;
DROP TABLE IF EXISTS lab_sessions;
DROP TABLE IF EXISTS faculty;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS pcs;
DROP TABLE IF EXISTS password_reset_logs;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS admin_users;

-- Admin Users Table
CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login Logs
CREATE TABLE login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  user_agent TEXT,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- Password Reset Logs
CREATE TABLE password_reset_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  reset_token VARCHAR(255),
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45)
);

-- PCs Table
CREATE TABLE pcs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_name VARCHAR(100) NOT NULL,
  status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available'
);

-- Students Table
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(150) NOT NULL,
  usn_or_lrn VARCHAR(50) NOT NULL UNIQUE,
  birthday DATE NOT NULL,
  year_level VARCHAR(50) NOT NULL,
  strand VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  contact VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Faculty Table
CREATE TABLE faculty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  faculty_id VARCHAR(50) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Lab Sessions Table
CREATE TABLE lab_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('student', 'faculty') NOT NULL,
  user_id INT NOT NULL,
  pc_id INT NOT NULL,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  logout_time DATETIME DEFAULT NULL,
  status ENUM('active', 'inactive') DEFAULT 'active',
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE
);

-- Maintenance Requests Table
CREATE TABLE maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_id INT NOT NULL,
  issue_description TEXT,
  requested_by VARCHAR(150),
  status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE
);

-- Lab Activities Table
CREATE TABLE lab_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user VARCHAR(100),
  action ENUM('login', 'logout', 'maintenance') NOT NULL,
  pc_no INT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Student Logs Table
CREATE TABLE student_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  pc_id INT,
  login_time DATETIME,
  logout_time DATETIME,
  duration_minutes INT,
  status ENUM('logged_in', 'logged_out'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE SET NULL
);

-- User Settings Table
CREATE TABLE user_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  setting_key VARCHAR(100),
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- PC Reservations Table
CREATE TABLE pc_reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  pc_id INT NOT NULL,
  reservation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME DEFAULT NULL,
  status ENUM('reserved', 'cancelled') DEFAULT 'reserved',
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE
);

-- ✅ Attendance Logs Table (optional: for time-in/out without PC)
CREATE TABLE attendance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  time_in DATETIME,
  time_out DATETIME,
  duration_minutes INT DEFAULT NULL,
  date DATE DEFAULT CURRENT_DATE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ✅ Notifications Table
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ✅ Feedback Table (optional)
CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message TEXT NOT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
