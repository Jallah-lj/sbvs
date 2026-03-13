-- Student Registration Module Schema (reference)
-- Date: 2026-03-10

-- 1) Students
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50) NOT NULL UNIQUE,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  gender ENUM('Male','Female') NOT NULL,
  dob DATE NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(191) NULL,
  address VARCHAR(255) NULL,
  branch_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2) Courses
CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_name VARCHAR(150) NOT NULL,
  duration VARCHAR(100) NULL,
  fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  branch_id INT NOT NULL
);

-- 3) Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  batch_id INT NOT NULL,
  enrollment_date DATE NOT NULL,
  status ENUM('Active','Completed','Dropped') DEFAULT 'Active'
);

-- 4) Payments
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NULL,
  total_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  balance DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(50) NOT NULL,
  receipt_number VARCHAR(50) NULL,
  recorded_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Compatibility notes for current VSMS codebase:
-- Existing implementation uses:
--   students.user_id + users.name/email
--   courses.name + courses.fees
--   payments.amount + payments.receipt_no + payments.enrollment_id + payments.branch_id
-- If needed, you can add optional compatibility columns instead of replacing existing fields.
