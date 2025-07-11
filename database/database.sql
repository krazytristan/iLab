-- =========================
-- iLab System Database Setup
-- =========================

CREATE DATABASE IF NOT EXISTS ilab_system;
USE ilab_system;

-- DROP ALL TABLES (for DEV reset)
DROP TABLE IF EXISTS 
  attendance_logs, 
  notifications, 
  feedback, 
  pc_reservations, 
  user_settings, 
  student_logs, 
  lab_activities, 
  maintenance_requests, 
  lab_sessions, 
  faculty, 
  students, 
  pcs, 
  password_reset_logs, 
  login_logs, 
  admin_users, 
  reports, 
  user_logs,
  student_requests,
  lab_settings,
  labs;

-- ========== ADMIN USERS ==========
CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  reset_token VARCHAR(255),
  reset_expires DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========== LOGIN LOGS ==========
CREATE TABLE login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  user_agent TEXT,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- ========== PASSWORD RESET LOGS ==========
CREATE TABLE password_reset_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  reset_token VARCHAR(255),
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45)
);

-- ========== LABS ==========
CREATE TABLE labs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lab_name VARCHAR(100) NOT NULL UNIQUE,
  room_code VARCHAR(20) NOT NULL UNIQUE,
  capacity INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========== PCS ==========
CREATE TABLE pcs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_name VARCHAR(100) NOT NULL,
  status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available',
  lab_id INT,
  FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
);

-- ========== STUDENTS ==========
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

-- ========== FACULTY ==========
CREATE TABLE faculty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  faculty_id VARCHAR(50) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========== LAB SESSIONS ==========
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

-- ========== MAINTENANCE REQUESTS ==========
CREATE TABLE maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_id INT NOT NULL,
  issue TEXT NOT NULL,
  status ENUM('pending', 'resolved') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE
);

-- ========== LAB ACTIVITIES ==========
CREATE TABLE lab_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user VARCHAR(100),
  action ENUM('login', 'logout', 'maintenance') NOT NULL,
  pc_no INT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ========== STUDENT LOGS ==========
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

-- ========== USER SETTINGS ==========
CREATE TABLE user_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  setting_key VARCHAR(100),
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ========== PC RESERVATIONS ==========
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

-- ========== ATTENDANCE LOGS ==========
CREATE TABLE attendance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  time_in DATETIME,
  time_out DATETIME,
  duration_minutes INT DEFAULT NULL,
  date DATE DEFAULT CURRENT_DATE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ========== NOTIFICATIONS ==========
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ========== FEEDBACK ==========
CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message TEXT NOT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ========== USER LOGS ==========
CREATE TABLE user_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action ENUM('login', 'logout') NOT NULL,
  pc_no VARCHAR(10),
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ========== STUDENT REQUESTS ==========
CREATE TABLE student_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ========== REPORTS ==========
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  report_type VARCHAR(100) NOT NULL,
  details TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- ========== LAB SETTINGS ==========
CREATE TABLE lab_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lab_name VARCHAR(255) NOT NULL DEFAULT 'iLab Computer Center',
  default_session_length VARCHAR(50) NOT NULL DEFAULT '1 hour',
  max_reservations INT NOT NULL DEFAULT 3,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========== Insert Default Lab Settings ==========
INSERT INTO lab_settings (lab_name, default_session_length, max_reservations)
VALUES ('iLab Computer Center', '1 hour', 3);

-- ========== Insert Lab Records ==========
INSERT INTO labs (lab_name, room_code, capacity) VALUES 
  ('Superlab', 'SL-101', 40),
  ('ComputerLab', 'CL-102', 40),
  ('InternetLab', 'IL-103', 40),
  ('MacLab', 'ML-104', 20);

-- ========== OPTIONAL: Generate Sample PCs ==========
-- Uncomment and run this part if you want to auto-create PC entries linked to labs

-- Superlab PCs
-- INSERT INTO pcs (pc_name, lab_id) VALUES 
--   ('Superlab-PC-01', 1), ('Superlab-PC-02', 1), ..., ('Superlab-PC-40', 1);

-- ComputerLab PCs
-- INSERT INTO pcs (pc_name, lab_id) VALUES 
--   ('ComputerLab-PC-01', 2), ..., ('ComputerLab-PC-40', 2);

-- InternetLab PCs
-- INSERT INTO pcs (pc_name, lab_id) VALUES 
--   ('InternetLab-PC-01', 3), ..., ('InternetLab-PC-40', 3);

-- MacLab PCs
-- INSERT INTO pcs (pc_name, lab_id) VALUES 
--   ('MacLab-PC-01', 4), ..., ('MacLab-PC-20', 4);
