-- =========================
-- iLab System Database Setup (Finalized - July 2025)
-- =========================

-- Make sure we're on MySQL 8+
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Create / select database
CREATE DATABASE IF NOT EXISTS ilab_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE ilab_system;

-- 2) Drop existing tables (development reset)
SET FOREIGN_KEY_CHECKS = 0;

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
  students, 
  pcs, 
  password_reset_logs, 
  login_logs, 
  admin_users, 
  reports, 
  user_logs,
  student_requests,
  lab_settings,
  labs,
  password_resets;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- ADMIN USERS
-- =========================

CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
  reset_token VARCHAR(255),
  reset_expires DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default super admin
-- Password is 'superadmin' (bcrypt hashed)
INSERT INTO admin_users (username, email, password, role) VALUES (
  'admin',
  'admin@gmail.com',
  '$2y$10$6JKYM.efMd4D8L6GGJH0R.7no8dSkM/00n6ZfS0b3D9gjBWkDMZpy',
  'super_admin'
);
SELECT * FROM `admin_users` WHERE 1;


-- =========================
-- ADMIN PASSWORD RESETS
-- =========================

CREATE TABLE admin_password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- =========================
-- LOGIN LOGS (admins)
-- =========================
CREATE TABLE login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  user_agent TEXT,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_login_admin_time (admin_id, login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PASSWORD RESET LOGS (admins)
-- =========================
CREATE TABLE password_reset_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  reset_token VARCHAR(255),
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  INDEX idx_reset_email_time (email, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- LABS
-- =========================
CREATE TABLE labs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lab_name VARCHAR(100) NOT NULL UNIQUE,
  room_code VARCHAR(20) NOT NULL UNIQUE,
  capacity INT NOT NULL CHECK (capacity > 0),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PCS
-- =========================
-- Recreate PCS table
CREATE TABLE pcs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_number INT NOT NULL, -- Sequential number within each lab
  pc_name VARCHAR(100) NOT NULL,
  status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available',
  lab_id INT NOT NULL,
  FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
  UNIQUE KEY uk_lab_pcnum (lab_id, pc_number),
  UNIQUE KEY uk_pcname_lab (pc_name, lab_id),
  INDEX idx_pc_lab (lab_id),
  INDEX idx_pc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- STUDENTS
-- =========================
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_students_name (fullname),
  INDEX idx_students_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- LAB SESSIONS (students/admins using PCs)
-- =========================
CREATE TABLE lab_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('student','admin') NOT NULL DEFAULT 'student',
  user_id INT NOT NULL,
  pc_id INT NOT NULL,
  reservation_id INT DEFAULT NULL,  -- 🔄 NEW COLUMN
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  logout_time DATETIME DEFAULT NULL,
  status ENUM('active', 'inactive') DEFAULT 'active',
  
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE,
  FOREIGN KEY (reservation_id) REFERENCES pc_reservations(id) ON DELETE SET NULL, -- 🔄 NEW CONSTRAINT

  INDEX idx_session_status (status),
  INDEX idx_session_user (user_type, user_id, status),
  INDEX idx_session_pc (pc_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MAINTENANCE REQUESTS
-- =========================
CREATE TABLE maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pc_id INT NOT NULL,
  student_id INT DEFAULT NULL,
  issue TEXT NOT NULL,
  status ENUM('pending', 'in_progress', 'completed', 'rejected', 'resolved') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  INDEX idx_maint_pc_status (pc_id, status),
  INDEX idx_maint_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- LAB ACTIVITIES (generic log)
-- =========================
CREATE TABLE lab_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user VARCHAR(100),
  action ENUM('login', 'logout', 'maintenance') NOT NULL,
  pc_no INT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_labact_user_time (user, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- STUDENT LOGS (historical usage)
-- =========================
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
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE SET NULL,
  INDEX idx_slogs_student (student_id),
  INDEX idx_slogs_pc (pc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- USER SETTINGS (per student)
-- =========================
CREATE TABLE user_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  setting_key VARCHAR(100),
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uk_student_setting (student_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PC RESERVATIONS
-- =========================
CREATE TABLE pc_reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  pc_id INT NOT NULL,
  reservation_date DATE NOT NULL,
  time_start TIME NOT NULL,
  time_end TIME NOT NULL,
  reservation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME DEFAULT NULL,
  status ENUM('pending','reserved','approved','cancelled','rejected','expired','completed') DEFAULT 'pending',
  reason TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE CASCADE,
  CHECK (time_start < time_end),
  INDEX idx_resv_pc_date_time (pc_id, reservation_date, time_start, time_end),
  INDEX idx_resv_student_date (student_id, reservation_date),
  INDEX idx_resv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- ATTENDANCE LOGS
-- =========================
CREATE TABLE attendance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  time_in DATETIME,
  time_out DATETIME,
  duration_minutes INT DEFAULT NULL,
  date DATE DEFAULT CURRENT_DATE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_att_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- NOTIFICATIONS
-- =========================
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('student', 'admin') NOT NULL,
  recipient_id INT NOT NULL,
  message TEXT NOT NULL,
  read_at DATETIME DEFAULT NULL,
  is_read BOOLEAN GENERATED ALWAYS AS (read_at IS NOT NULL) STORED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipient (recipient_type, recipient_id),
  INDEX idx_unread (recipient_type, recipient_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- FEEDBACK
-- =========================
CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message TEXT NOT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_feedback_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- USER LOGS (generic)
-- =========================
CREATE TABLE user_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action ENUM('login', 'logout') NOT NULL,
  pc_no VARCHAR(10),
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_userlogs_user (user_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- STUDENT REQUESTS
-- =========================
CREATE TABLE student_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_studreq_student_status (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- REPORTS (UPDATED)
-- =========================
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT,
  pc_id INT,  -- New column to reference the reported PC
  report_type VARCHAR(100) NOT NULL,
  details TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  FOREIGN KEY (pc_id) REFERENCES pcs(id) ON DELETE SET NULL,

  INDEX idx_reports_type (report_type),
  INDEX idx_reports_student (student_id),
  INDEX idx_reports_pc (pc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- LAB SETTINGS
-- =========================
CREATE TABLE lab_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lab_name VARCHAR(255) NOT NULL DEFAULT 'iLab Computer Center',
  default_session_length VARCHAR(50) NOT NULL DEFAULT '1 hour',
  max_reservations INT NOT NULL DEFAULT 3,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PASSWORD RESETS (students)
-- =========================
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_pwreset_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================
-- SEED LABS
-- =========================
INSERT INTO labs (lab_name, room_code, capacity) VALUES 
  ('Superlab', 'SL-101', 40),
  ('ComputerLab', 'CL-102', 40),
  ('InternetLab', 'IL-103', 40),
  ('MacLab', 'ML-104', 20);

-- =========================
-- SEED PCS PER LAB
-- =========================
-- (40 PCs for 1/2/3, 20 PCs for 4)
-- Superlab (40 PCs)
INSERT INTO pcs (pc_number, pc_name, lab_id)
SELECT 
  n,
  CONCAT('Superlab-PC-', LPAD(n, 2, '0')),
  1
FROM (
  SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
  UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
  UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35
  UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL SELECT 40
) AS superlab;

-- ComputerLab (40 PCs)
INSERT INTO pcs (pc_number, pc_name, lab_id)
SELECT 
  n,
  CONCAT('ComputerLab-PC-', LPAD(n, 2, '0')),
  2
FROM (
  SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
  UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
  UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35
  UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL SELECT 40
) AS clab;

-- InternetLab (40 PCs)
INSERT INTO pcs (pc_number, pc_name, lab_id)
SELECT 
  n,
  CONCAT('InternetLab-PC-', LPAD(n, 2, '0')),
  3
FROM (
  SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
  UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
  UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35
  UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL SELECT 40
) AS ilab;

-- MacLab (20 PCs)
INSERT INTO pcs (pc_number, pc_name, lab_id)
SELECT 
  n,
  CONCAT('MacLab-PC-', LPAD(n, 2, '0')),
  4
FROM (
  SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
) AS maclab;


-- =========================
-- VIEWS (Optional but useful)
-- =========================

-- Real-time PC status view
DROP VIEW IF EXISTS v_pc_current_status;
CREATE VIEW v_pc_current_status AS
SELECT
  pcs.id AS pc_id,
  pcs.pc_name,
  labs.lab_name,
  CASE
    WHEN pcs.status = 'maintenance'
         OR EXISTS (
            SELECT 1 FROM maintenance_requests mr
            WHERE mr.pc_id = pcs.id
              AND mr.status IN ('pending', 'in_progress')
         )
      THEN 'maintenance'
    WHEN EXISTS (
      SELECT 1 FROM pc_reservations r
      WHERE r.pc_id = pcs.id
        AND r.status IN ('reserved','approved')
        AND r.reservation_date = CURDATE()
        AND CURTIME() BETWEEN r.time_start AND r.time_end
    )
      THEN 'reserved'
    WHEN EXISTS (
      SELECT 1 FROM lab_sessions ls
      WHERE ls.pc_id = pcs.id AND ls.status = 'active'
    )
      THEN 'in_use'
    ELSE 'available'
  END AS computed_status
FROM pcs
JOIN labs ON labs.id = pcs.lab_id
ORDER BY labs.lab_name, pcs.pc_name;

-- Active student sessions quick lookup
DROP VIEW IF EXISTS v_student_active_sessions;
CREATE VIEW v_student_active_sessions AS
SELECT 
  ls.id AS session_id,
  s.id AS student_id,
  s.fullname,
  pcs.pc_name,
  ls.login_time,
  TIMESTAMPDIFF(MINUTE, ls.login_time, NOW()) AS minutes_used
FROM lab_sessions ls
JOIN students s ON s.id = ls.user_id AND ls.user_type = 'student'
JOIN pcs ON pcs.id = ls.pc_id
WHERE ls.status = 'active';
