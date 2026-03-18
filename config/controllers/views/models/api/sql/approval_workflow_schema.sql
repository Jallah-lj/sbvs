-- Approval Workflow & Change Request System Schema
-- Date: 2026-03-10
-- Purpose: Enable Super Admin locking mechanism with Admin change request workflow

-- 1) Change Requests Table - Track all requested modifications
CREATE TABLE IF NOT EXISTS change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_type ENUM('student', 'enrollment', 'payment', 'course') NOT NULL,
  record_id INT NOT NULL,
  requested_by INT NOT NULL,
  requested_by_role VARCHAR(50) NOT NULL,
  field_name VARCHAR(100) NOT NULL,
  old_value LONGTEXT NULL,
  new_value LONGTEXT NULL,
  request_message TEXT NULL,
  status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  reviewed_by INT NULL,
  review_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL,
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2) Add tracking columns to existing tables
-- These will be added via auto-migration checks in API functions

-- For students table:
-- ALTER TABLE students ADD COLUMN locked_by INT NULL AFTER created_at;
-- ALTER TABLE students ADD COLUMN locked_by_role VARCHAR(50) NULL AFTER locked_by;
-- ALTER TABLE students ADD COLUMN last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER locked_by_role;

-- For enrollments table:
-- ALTER TABLE enrollments ADD COLUMN locked_by INT NULL AFTER status;
-- ALTER TABLE enrollments ADD COLUMN locked_by_role VARCHAR(50) NULL AFTER locked_by;
-- ALTER TABLE enrollments ADD COLUMN last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER locked_by_role;

-- For payments table:
-- ALTER TABLE payments ADD COLUMN locked_by INT NULL AFTER created_at;
-- ALTER TABLE payments ADD COLUMN locked_by_role VARCHAR(50) NULL AFTER locked_by;
-- ALTER TABLE payments ADD COLUMN last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER locked_by_role;

-- For courses table:
-- ALTER TABLE courses ADD COLUMN locked_by INT NULL AFTER branch_id;
-- ALTER TABLE courses ADD COLUMN locked_by_role VARCHAR(50) NULL AFTER locked_by;
-- ALTER TABLE courses ADD COLUMN last_edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER locked_by_role;
