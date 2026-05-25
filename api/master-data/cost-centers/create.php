<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$code = trim((string)($data['code'] ?? ''));
$name = trim((string)($data['name'] ?? ''));
$departmentId = (int)($data['department_id'] ?? 0);
$subTreasuryId = (int)($data['sub_treasury_id'] ?? 0);
$status = trim((string)($data['status'] ?? 'active'));
$description = trim((string)($data['description'] ?? ''));

if ($code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Code and name are required.'
    ], 422);
}

$dupCheck = $pdo->prepare("SELECT id FROM cost_centers WHERE code = :code LIMIT 1");
$dupCheck->execute(['code' => $code]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Cost center code already exists.'
    ], 409);
}

if ($departmentId > 0) {
    $deptCheck = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
    $deptCheck->execute(['id' => $departmentId]);

    if (!$deptCheck->fetch()) {
        apiResponse([
            'success' => false,
            'message' => 'Selected department does not exist.'
        ], 422);
    }
}

if ($subTreasuryId > 0) {
    $stCheck = $pdo->prepare("SELECT id FROM sub_treasuries WHERE id = :id LIMIT 1");
    $stCheck->execute(['id' => $subTreasuryId]);

    if (!$stCheck->fetch()) {
        apiResponse([
            'success' => false,
            'message' => 'Selected sub-treasury does not exist.'
        ], 422);
    }
}

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$stmt = $pdo->prepare("
    INSERT INTO cost_centers (
        uuid, code, name, department_id, sub_treasury_id, status, description, created_by, updated_by
    ) VALUES (
        :uuid, :code, :name, :department_id, :sub_treasury_id, :status, :description, :created_by, :updated_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'code' => $code,
    'name' => $name,
    'department_id' => $departmentId > 0 ? $departmentId : null,
    'sub_treasury_id' => $subTreasuryId > 0 ? $subTreasuryId : null,
    'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
    'description' => $description !== '' ? $description : null,
    'created_by' => $user['id'] ?? null,
    'updated_by' => $user['id'] ?? null,
]);

$newCcId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'cost_center', $newCcId, 'Cost center created.');
apiResponse([
    'success' => true,
    'message' => 'Cost center created successfully.',
    'id' => $newCcId
], 201);