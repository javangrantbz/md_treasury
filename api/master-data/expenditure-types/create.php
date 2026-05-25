<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.expenditure_types.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$code = trim((string)($data['expenditure_code'] ?? ''));
$name = trim((string)($data['expenditure_name'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Expenditure code and name are required.'
    ], 422);
}

$dup = $pdo->prepare("
    SELECT id
    FROM expenditure_types
    WHERE expenditure_code = :code
    LIMIT 1
");
$dup->execute(['code' => $code]);

if ($dup->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Expenditure code already exists.'
    ], 409);
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
    INSERT INTO expenditure_types (
        uuid,
        expenditure_code,
        expenditure_name,
        description,
        is_active,
        created_by,
        updated_by
    ) VALUES (
        :uuid,
        :expenditure_code,
        :expenditure_name,
        :description,
        :is_active,
        :created_by,
        :updated_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'expenditure_code' => $code,
    'expenditure_name' => $name,
    'description' => $description !== '' ? $description : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'created_by' => $user['id'] ?? null,
    'updated_by' => $user['id'] ?? null,
]);

$newExpTypeId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'expenditure_type', $newExpTypeId, 'Expenditure type created.');
apiResponse([
    'success' => true,
    'message' => 'Expenditure type created successfully.',
    'id' => $newExpTypeId
], 201);