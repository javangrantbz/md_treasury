<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.users.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$id = (int)($data['id'] ?? 0);
$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$password = (string)($data['password'] ?? '');
$confirmPassword = (string)($data['confirm_password'] ?? '');
$userType = trim((string)($data['user_type'] ?? 'internal'));
$authSource = trim((string)($data['auth_source'] ?? 'local'));
$mfaEnabled = isset($data['mfa_enabled']) ? (int)$data['mfa_enabled'] : 1;
$status = trim((string)($data['status'] ?? 'active'));

$branchId = (int)($data['branch_id'] ?? 0);
$departmentId = (int)($data['department_id'] ?? 0);
$subTreasuryId = (int)($data['sub_treasury_id'] ?? 0);
$registerId = (int)($data['register_id'] ?? 0);
$roleId = (int)($data['role_id'] ?? 0);

if ($id <= 0 || $firstName === '' || $lastName === '' || $username === '' || $email === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$oldUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$oldUserStmt->execute(['id' => $id]);
$oldData = $oldUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$oldData) {
    apiResponse(['success' => false, 'message' => 'User not found.'], 404);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse(['success' => false, 'message' => 'Email is invalid.'], 422);
}

if ($password !== '' && $password !== $confirmPassword) {
    apiResponse(['success' => false, 'message' => 'Password confirmation does not match.'], 422);
}

if ($password !== '' && strlen($password) < 6) {
    apiResponse(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
}

$allowedStatus = ['active', 'inactive', 'locked', 'pending'];
if (!in_array($status, $allowedStatus, true)) {
    apiResponse(['success' => false, 'message' => 'Invalid user status.'], 422);
}

$allowedUserTypes = ['internal', 'external'];
if (!in_array($userType, $allowedUserTypes, true)) {
    apiResponse(['success' => false, 'message' => 'Invalid user type.'], 422);
}

$allowedAuthSources = ['local', 'sso', 'microsoft'];
if (!in_array($authSource, $allowedAuthSources, true)) {
    apiResponse(['success' => false, 'message' => 'Invalid auth source.'], 422);
}

$dupUser = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1");
$dupUser->execute([
    'username' => $username,
    'id' => $id,
]);
if ($dupUser->fetch()) {
    apiResponse(['success' => false, 'message' => 'Username already exists.'], 409);
}

$dupEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
$dupEmail->execute([
    'email' => $email,
    'id' => $id,
]);
if ($dupEmail->fetch()) {
    apiResponse(['success' => false, 'message' => 'Email already exists.'], 409);
}

if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $branchId]);
    if (!$stmt->fetch()) {
        apiResponse(['success' => false, 'message' => 'Selected branch does not exist.'], 422);
    }
}

if ($departmentId > 0) {
    $stmt = $pdo->prepare("SELECT id, branch_id FROM departments WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $departmentId]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dept) {
        apiResponse(['success' => false, 'message' => 'Selected department does not exist.'], 422);
    }
    if ($branchId > 0 && (int)($dept['branch_id'] ?? 0) !== $branchId) {
        apiResponse(['success' => false, 'message' => 'Department does not belong to selected branch.'], 422);
    }
}

if ($subTreasuryId > 0) {
    $stmt = $pdo->prepare("SELECT id, department_id FROM sub_treasuries WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $subTreasuryId]);
    $st = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$st) {
        apiResponse(['success' => false, 'message' => 'Selected sub-treasury does not exist.'], 422);
    }
    if ($departmentId > 0 && (int)$st['department_id'] !== $departmentId) {
        apiResponse(['success' => false, 'message' => 'Sub-treasury does not belong to selected department.'], 422);
    }
}

if ($registerId > 0) {
    $stmt = $pdo->prepare("SELECT id, department_id, sub_treasury_id FROM registers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $registerId]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reg) {
        apiResponse(['success' => false, 'message' => 'Selected register does not exist.'], 422);
    }
    if ($departmentId > 0 && (int)$reg['department_id'] !== $departmentId) {
        apiResponse(['success' => false, 'message' => 'Register does not belong to selected department.'], 422);
    }
    if ($subTreasuryId > 0 && (int)$reg['sub_treasury_id'] !== $subTreasuryId) {
        apiResponse(['success' => false, 'message' => 'Register does not belong to selected sub-treasury.'], 422);
    }
}

if ($roleId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute(['id' => $roleId]);
    if (!$stmt->fetch()) {
        apiResponse(['success' => false, 'message' => 'Selected role does not exist.'], 422);
    }
}

$fields = [
    'user_type' => $userType,
    'auth_source' => $authSource,
    'username' => $username,
    'email' => $email,
    'phone' => $phone !== '' ? $phone : null,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'mfa_enabled' => $mfaEnabled === 1 ? 1 : 0,
    'status' => $status,
    'branch_id' => $branchId > 0 ? $branchId : null,
    'department_id' => $departmentId > 0 ? $departmentId : null,
    'sub_treasury_id' => $subTreasuryId > 0 ? $subTreasuryId : null,
    'register_id' => $registerId > 0 ? $registerId : null,
    'updated_by' => $user['id'] ?? null,
    'id' => $id,
];

$sql = "
    UPDATE users
    SET
        user_type = :user_type,
        auth_source = :auth_source,
        username = :username,
        email = :email,
        phone = :phone,
        first_name = :first_name,
        last_name = :last_name,
        mfa_enabled = :mfa_enabled,
        status = :status,
        branch_id = :branch_id,
        department_id = :department_id,
        sub_treasury_id = :sub_treasury_id,
        register_id = :register_id,
        updated_by = :updated_by
";

if ($password !== '') {
    $sql .= ", password_hash = :password_hash";
    $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

$sql .= " WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute($fields);

$pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id")->execute(['user_id' => $id]);

if ($roleId > 0) {
    $roleStmt = $pdo->prepare("
        INSERT INTO user_roles (user_id, role_id, assigned_by)
        VALUES (:user_id, :role_id, :assigned_by)
    ");
    $roleStmt->execute([
        'user_id' => $id,
        'role_id' => $roleId,
        'assigned_by' => $user['id'] ?? null,
    ]);
}

$newDataStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$newDataStmt->execute(['id' => $id]);
$newData = $newDataStmt->fetch(PDO::FETCH_ASSOC);

AuditLog::log($pdo, 'update', 'user', $id, "User updated: $username ($email)", null, $oldData, $newData);

apiResponse([
    'success' => true,
    'message' => 'User updated successfully.'
]);
