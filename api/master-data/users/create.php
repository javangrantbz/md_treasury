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

$firstName       = trim((string)($data['first_name']      ?? ''));
$lastName        = trim((string)($data['last_name']       ?? ''));
$username        = trim((string)($data['username']        ?? ''));
$email           = trim((string)($data['email']           ?? ''));
$phone           = trim((string)($data['phone']           ?? ''));
$password        = (string)($data['password']             ?? '');
$confirmPassword = (string)($data['confirm_password']     ?? '');
$userType        = trim((string)($data['user_type']       ?? 'internal'));
$authSource      = trim((string)($data['auth_source']     ?? ''));
$status          = trim((string)($data['status']          ?? 'active'));
$mfaEnabled      = isset($data['mfa_enabled']) ? (int)$data['mfa_enabled'] : 0;
$roleId          = (int)($data['role_id']                 ?? 0);
$departmentId    = ($data['department_id']    ?? '') !== '' ? (int)$data['department_id']    : null;
$branchId        = ($data['branch_id']        ?? '') !== '' ? (int)$data['branch_id']        : null;
$subTreasuryId   = ($data['sub_treasury_id']  ?? '') !== '' ? (int)$data['sub_treasury_id']  : null;
$registerId      = ($data['register_id']      ?? '') !== '' ? (int)$data['register_id']      : null;

$isMicrosoft = $authSource === 'microsoft';

if ($email === '') {
    apiResponse(['success' => false, 'message' => 'Email is required.'], 422);
}

if ($isMicrosoft) {
    // Name and username will be populated on first login via Entra sync
    if ($username === '') {
        $username = strstr($email, '@', true) ?: $email;
    }
    // Generate a random unusable password — Microsoft users never use it
    $password = bin2hex(random_bytes(32));
} else {
    if ($firstName === '' || $lastName === '' || $username === '' || $password === '') {
        apiResponse(['success' => false, 'message' => 'Required fields missing.'], 422);
    }
    if ($password !== $confirmPassword) {
        apiResponse(['success' => false, 'message' => 'Passwords do not match.'], 422);
    }
}

$allowedAuthSources = ['local', 'microsoft'];
if (!in_array($authSource, $allowedAuthSources, true)) {
    apiResponse(['success' => false, 'message' => 'Invalid auth source.'], 422);
}

$allowedUserTypes = ['internal', 'external'];
if (!in_array($userType, $allowedUserTypes, true)) {
    $userType = 'internal';
}

if ($roleId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute(['id' => $roleId]);
    if (!$stmt->fetch()) {
        apiResponse(['success' => false, 'message' => 'Selected role does not exist.'], 422);
    }
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Generate UUID v4
$uuidBytes = random_bytes(16);
$uuidBytes[6] = chr(ord($uuidBytes[6]) & 0x0f | 0x40);
$uuidBytes[8] = chr(ord($uuidBytes[8]) & 0x3f | 0x80);
$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($uuidBytes), 4));

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (
            uuid,
            username, email, password_hash,
            first_name, last_name, phone,
            user_type, auth_source, status, mfa_enabled,
            department_id, branch_id, sub_treasury_id, register_id,
            created_by, updated_by
        ) VALUES (
            :uuid,
            :username, :email, :password_hash,
            :first_name, :last_name, :phone,
            :user_type, :auth_source, :status, :mfa_enabled,
            :department_id, :branch_id, :sub_treasury_id, :register_id,
            :created_by, :updated_by
        )
    ");

    $stmt->execute([
        'uuid'           => $uuid,
        'username'       => $username,
        'email'          => $email,
        'password_hash'  => $passwordHash,
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'phone'          => $phone !== '' ? $phone : null,
        'user_type'      => $userType,
        'auth_source'    => $authSource,
        'status'         => $status,
        'mfa_enabled'    => $mfaEnabled,
        'department_id'  => $departmentId,
        'branch_id'      => $branchId,
        'sub_treasury_id'=> $subTreasuryId,
        'register_id'    => $registerId,
        'created_by'     => $user['id'] ?? null,
        'updated_by'     => $user['id'] ?? null,
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    if ($roleId > 0) {
        $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :assigned_by)")
            ->execute(['user_id' => $newUserId, 'role_id' => $roleId, 'assigned_by' => $user['id'] ?? null]);
    }

    AuditLog::log($pdo, 'create', 'user', $newUserId, 'User created: ' . $username . ' (' . $firstName . ' ' . $lastName . ').');
    apiResponse(['success' => true, 'message' => 'User created successfully.', 'id' => $newUserId], 201);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // Duplicate entry — check which field
        if (strpos($e->getMessage(), 'username') !== false) {
            apiResponse(['success' => false, 'message' => 'Username is already taken.'], 422);
        }
        if (strpos($e->getMessage(), 'email') !== false) {
            apiResponse(['success' => false, 'message' => 'Email address is already registered.'], 422);
        }
        apiResponse(['success' => false, 'message' => 'A duplicate value was detected. Check username or email.'], 422);
    }
    apiResponse(['success' => false, 'message' => 'Failed to create user. Please try again.'], 500);
}
