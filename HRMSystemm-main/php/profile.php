<?php
require_once 'config.php';

header('Content-Type: application/json');

function ensureEmployeeContactColumn(PDO $pdo): void {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'contact_no'");
    $check->execute();
    if (!$check->fetchColumn()) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN contact_no VARCHAR(11) NULL AFTER email");
    }
}

ensureEmployeeContactColumn($pdo);

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Account does not exist. Please sign in again.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, email, contact_no, position, status, created_at FROM employees WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account does not exist.']);
    exit;
}

if ($user['status'] !== 'approved') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This employee account is not active. Please contact HR.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contactNo = trim($_POST['contact_no'] ?? '');

        if (!$fullName || !$email || !$contactNo) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required profile fields.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }
        if (!preg_match('/^\d{11}$/', $contactNo)) {
            echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits.']);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id <> ? LIMIT 1");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already belongs to another account.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, email = ?, contact_no = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $contactNo, $userId]);
        $_SESSION['user_name'] = $fullName;
        echo json_encode(['success' => true, 'message' => 'Your profile information was updated successfully.']);
        exit;
    }

    if ($action === 'regenerate_pin') {
        $pin = (string)random_int(100000, 999999);
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
        $subject = 'Your New Quadra Cafe Employee Account PIN';
        $message = "Dear {$user['full_name']},\n\nYour new Quadra Cafe employee account PIN has been generated.\n\nAccess PIN: {$pin}\n\nPlease remember this PIN and keep it private. You will use it to sign in to your employee account.\n\nRegards,\nQuadra Cafe HR Team";

        try {
            require_once 'send_email.php';
            sendQuadraEmail($user['email'], $user['full_name'], $subject, $message);
        } catch (Throwable $error) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'The new PIN was not saved because the email could not be sent: ' . $error->getMessage()]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees SET pin = ? WHERE id = ?");
        $stmt->execute([$pinHash, $userId]);
        echo json_encode(['success' => true, 'message' => 'A new PIN was generated and emailed to ' . $user['email'] . '.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid profile action.']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);
