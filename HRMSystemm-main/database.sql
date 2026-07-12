CREATE DATABASE IF NOT EXISTS quadra_hrms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quadra_hrms;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  contact_no VARCHAR(11) NULL,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  pin VARCHAR(255) NULL,
  position VARCHAR(50) NOT NULL,
  status ENUM('pending', 'approved', 'inactive', 'rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employees_email (email),
  UNIQUE KEY uq_employees_username (username),
  KEY idx_employees_status (status),
  KEY idx_employees_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applicants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_code VARCHAR(30) NOT NULL,
  employee_id VARCHAR(30) NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  birthdate DATE NOT NULL,
  gender VARCHAR(30) NOT NULL,
  civil_status VARCHAR(30) NOT NULL,
  email VARCHAR(100) NOT NULL,
  contact_no VARCHAR(30) NOT NULL,
  address TEXT NOT NULL,
  position VARCHAR(80) NOT NULL,
  employment_type VARCHAR(40) NOT NULL,
  availability ENUM('available','not-available') NOT NULL DEFAULT 'not-available',
  years_experience VARCHAR(30) NOT NULL,
  preferred_shift VARCHAR(40) NOT NULL,
  education VARCHAR(100) NOT NULL,
  school VARCHAR(150) NOT NULL,
  course VARCHAR(150) NOT NULL,
  year_graduated VARCHAR(10) NOT NULL,
  high_school VARCHAR(150) NOT NULL,
  high_school_year VARCHAR(10) NOT NULL,
  honors VARCHAR(255) NULL,
  resume_name VARCHAR(255) NULL,
  resume_path VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  interview_stage VARCHAR(20) NULL,
  interview_result VARCHAR(20) NULL,
  hr_remarks TEXT NULL,
  interview_date DATE NULL,
  interview_time VARCHAR(50) NULL,
  interview_location VARCHAR(255) NULL,
  email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_applicants_application_code (application_code),
  KEY idx_applicants_status (status),
  KEY idx_applicants_email (email),
  KEY idx_applicants_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO employees (full_name, email, username, password, pin, position, status)
VALUES
  (
    'System Administrator',
    'admin@quadra.com',
    'admin',
    '$2y$10$PpNfwtudL0Aae.o61tY4Luq9hjZ38kZIMEw49q0Ckf0uMtzdVeVES',
    '$2y$10$stTeEDNCFp8zfnmk6FnCyuBRUNDsDTIE/WAg0hOfxf/ClUIdq9eT6',
    'Administrator',
    'approved'
  ),
  (
    'HR Manager',
    'hr@quadra.com',
    'hr',
    '$2y$10$rBpfHWhyYciakNk6obima.ILw24.Ny5qTW5T94LnmbCABPqQDcfni',
    '$2y$10$PGDoS/hvXDGY6fMrkGFyf.LDrnHbcMWWrHNgvY.7krC0Dyj83Eal6',
    'HR Manager',
    'approved'
  )
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password = VALUES(password),
  pin = VALUES(pin),
  position = VALUES(position),
  status = VALUES(status);
