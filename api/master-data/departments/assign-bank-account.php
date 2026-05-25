<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.departments.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$departmentId = (int)($data['department_id'] ?? 0);
$bankAccountId = (int)($data['bank_account_id'] ?? 0);
$isDefault = isset($data['is_default']) ? (int)$data['is_default'] : 0;
$status = trim((string)($data['status'] ?? 'active'));

if ($departmentId <= 0 || $bankAccountId <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Department and bank account are required.'
    ], 422);
}

$deptCheck = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
$deptCheck->execute(['id' => $departmentId]);
if (!$deptCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Department not found.'
    ], 404);
}

$bankCheck = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = :id LIMIT 1");
$bankCheck->execute(['id' => $bankAccountId]);
if (!$bankCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Bank account not found.'
    ], 404);
}

$dupCheck = $pdo->prepare("
    SELECT id
    FROM department_bank_accounts
    WHERE department_id = :department_id
      AND bank_account_id = :bank_account_id
    LIMIT 1
");
$dupCheck->execute([
    'department_id' => $departmentId,
    'bank_account_id' => $bankAccountId,
]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'This bank account is already assigned to the department.'
    ], 409);
}

if ($isDefault === 1) {
    $unsetDefault = $pdo->prepare("
        UPDATE department_bank_accounts
        SET is_default = 0
        WHERE department_id = :department_id
    ");
    $unsetDefault->execute(['department_id' => $departmentId]);
}

$stmt = $pdo->prepare("
    INSERT INTO department_bank_accounts (
        department_id,
        bank_account_id,
        is_default,
        status,
        created_by
    ) VALUES (
        :department_id,
        :bank_account_id,
        :is_default,
        :status,
        :created_by
    )
");

$stmt->execute([
    'department_id' => $departmentId,
    'bank_account_id' => $bankAccountId,
    'is_default' => $isDefault === 1 ? 1 : 0,
    'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
    'created_by' => $user['id'] ?? null,
]);

$newAssignId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'assign', 'department', $departmentId, 'Bank account #' . $bankAccountId . ' assigned to department #' . $departmentId . '.');
apiResponse([
    'success' => true,
    'message' => 'Bank account assigned successfully.',
    'id' => $newAssignId
], 201);