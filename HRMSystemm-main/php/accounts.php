<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function isAdmin(): bool {
    return !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'Administrator';
}

function canViewEmployees(): bool {
    $role = $_SESSION['user_role'] ?? '';
    return !empty($_SESSION['user_id']) &&
        ($role === 'Administrator' || stripos($role, 'HR') !== false || strcasecmp($role, 'Human Resources') === 0);
}

function ensureEmployeeSchema(PDO $pdo): void {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'contact_no'");
    $check->execute();
    if (!$check->fetchColumn()) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN contact_no VARCHAR(11) NULL AFTER email");
    }

    $column = $pdo->query("SHOW COLUMNS FROM employees LIKE 'status'")->fetch();
    if ($column && strpos($column['Type'], "'inactive'") === false) {
        $pdo->exec("ALTER TABLE employees MODIFY status ENUM('pending','approved','inactive','rejected') NOT NULL DEFAULT 'pending'");
    }
}

ensureEmployeeSchema($pdo);

function normalizedHiringPosition(string $position): string {
    $value = strtolower(trim($position));
    if (strpos($value, 'barista') !== false) return 'barista';
    if (strpos($value, 'cashier') !== false) return 'cashier';
    return $value;
}

function hiringSlotError(PDO $pdo, string $position, int $excludeId = 0): ?string {
    $normalized = normalizedHiringPosition($position);
    if (!in_array($normalized, ['barista', 'cashier'], true)) {
        return 'Only Barista and Cashier positions are allowed for employee records.';
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE status = 'approved' AND LOWER(position) LIKE ? AND id <> ?");
    $stmt->execute(['%' . $normalized . '%', $excludeId]);
    if ((int)$stmt->fetchColumn() >= 4) {
        return ucfirst($normalized) . ' is already full. Set an existing employee to Not Active/Resigned before activating another one.';
    }
    return null;
}

// List all employee accounts (Admin view)
if ($action === 'list') {
    if (!canViewEmployees()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'HR or Administrator access is required.']);
        exit;
    }
    $stmt = $pdo->query("SELECT id, full_name, email, contact_no, username, position, status, created_at
                         FROM employees ORDER BY created_at DESC");
    echo json_encode(['success' => true, 'accounts' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Administrator access is required.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($id < 1 || !$full_name || !$email || !$contact_no || !$position || !$status) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required employee fields.']);
        exit;
    }
    if (!in_array($status, ['approved', 'inactive'], true)) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid employee status.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (!preg_match('/^\d{11}$/', $contact_no)) {
        echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits.']);
        exit;
    }
    if (stripos($position, 'administrator') !== false || stripos($position, 'HR') !== false) {
        echo json_encode(['success' => false, 'message' => 'Employee records cannot be changed to Administrator or HR positions here.']);
        exit;
    }
    if ($status === 'approved') {
        $slotError = hiringSlotError($pdo, $position, $id);
        if ($slotError) {
            echo json_encode(['success' => false, 'message' => $slotError]);
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already belongs to another employee.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, email = ?, contact_no = ?, position = ?, status = ? WHERE id = ? AND position <> 'Administrator' AND position NOT LIKE '%HR%'");
    $stmt->execute([$full_name, $email, $contact_no, $position, $status, $id]);
    echo json_encode(['success' => true, 'message' => 'Employee record updated successfully.']);
    exit;
}

// Approve or reject a pending account (Admin action)
if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Administrator access is required.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $requestedDecision = $_POST['decision'] ?? '';
    if (!in_array($requestedDecision, ['approve', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid approval decision.']);
        exit;
    }
    $decision = $requestedDecision === 'reject' ? 'rejected' : 'approved';

    if ($id < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee account.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ? AND status = 'pending'");
    $stmt->execute([$decision, $id]);

    if ($stmt->rowCount() !== 1) {
        echo json_encode(['success' => false, 'message' => 'Only pending employee accounts can be updated.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Account has been ' . $decision . '.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
