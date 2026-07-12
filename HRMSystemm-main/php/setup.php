<?php
// One-time setup: creates the database, employees table, and seeds
// an Admin account and an approved HR account so the flow is testable.
// Run once in the browser: http://localhost/HRMSYSTEM/php/setup.php

$host = 'localhost';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS quadra_hrms");
$pdo->exec("USE quadra_hrms");

$pdo->exec("CREATE TABLE IF NOT EXISTS employees (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    contact_no VARCHAR(11) NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    position   VARCHAR(50)  NOT NULL,
    status     ENUM('pending','approved','inactive','rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Add PIN support for installations created before this feature was added.
$pinColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'quadra_hrms' AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'pin'");
$pinColumn->execute();
if (!$pinColumn->fetchColumn()) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN pin VARCHAR(255) NULL AFTER password");
}

$contactColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'quadra_hrms' AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'contact_no'");
$contactColumn->execute();
if (!$contactColumn->fetchColumn()) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN contact_no VARCHAR(11) NULL AFTER email");
}

$statusColumn = $pdo->query("SHOW COLUMNS FROM employees LIKE 'status'")->fetch();
if ($statusColumn && strpos($statusColumn['Type'], "'inactive'") === false) {
    $pdo->exec("ALTER TABLE employees MODIFY status ENUM('pending','approved','inactive','rejected') DEFAULT 'pending'");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(30) NOT NULL UNIQUE,
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
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    hr_remarks TEXT NULL,
    interview_date DATE NULL,
    interview_time VARCHAR(50) NULL,
    interview_location VARCHAR(255) NULL,
    email_sent_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_applicant_status (status)
)");

// Add resume storage path for existing applicants tables.
$resumePathColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'quadra_hrms' AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'resume_path'");
$resumePathColumn->execute();
if (!$resumePathColumn->fetchColumn()) {
    $pdo->exec("ALTER TABLE applicants ADD COLUMN resume_path VARCHAR(255) NULL AFTER resume_name");
}

foreach ([
    'employee_id' => "VARCHAR(30) NULL AFTER application_code",
    'interview_stage' => "VARCHAR(20) NULL AFTER status",
    'interview_result' => "VARCHAR(20) NULL AFTER interview_stage"
] as $column => $definition) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'quadra_hrms' AND TABLE_NAME = 'applicants' AND COLUMN_NAME = ?");
    $check->execute([$column]);
    if (!$check->fetchColumn()) $pdo->exec("ALTER TABLE applicants ADD COLUMN {$column} {$definition}");
}

// Keep applications created with the older workflow visible in the new interview flow.
$pdo->exec("UPDATE applicants SET status = 'initial_interview_pending' WHERE status = 'hr_qualified'");
$pdo->exec("UPDATE applicants SET status = 'rejected' WHERE status IN ('hr_rejected', 'admin_rejected')");
$pdo->exec("UPDATE applicants SET status = 'hired' WHERE status = 'admin_approved'");

seed($pdo, 'System Administrator', 'admin@quadra.com', 'admin', 'admin123', 'Administrator', '123456');
seed($pdo, 'HR Manager',           'hr@quadra.com',    'hr',    'hr12345',  'HR Manager', '654321');

echo "Setup complete.<br>";
echo "Admin PIN: 123456<br>";
echo "HR PIN: 654321";

function seed($pdo, $name, $email, $username, $pw, $pos, $pin) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE employees SET pin = ? WHERE (email = ? OR username = ?) AND (pin IS NULL OR pin = '')");
        $stmt->execute([password_hash($pin, PASSWORD_DEFAULT), $email, $username]);
        return;
    }

    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO employees (full_name, email, username, password, pin, position, status)
                           VALUES (?, ?, ?, ?, ?, ?, 'approved')");
    $stmt->execute([$name, $email, $username, $hash, password_hash($pin, PASSWORD_DEFAULT), $pos]);
}
