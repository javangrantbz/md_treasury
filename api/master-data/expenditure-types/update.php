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

$id = (int)($data['id'] ?? 0);
$code = trim((string)($data['expenditure_code'] ?? ''));
$name = trim((string)($data['expenditure_name'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($id <= 0 || $code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$exists = $pdo->prepare("
    SELECT id
    FROM expenditure_types
    WHERE id = :id
    LIMIT 1
");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Expenditure type not found.'
    ], 404);
}

$dup = $pdo->prepare("
    SELECT id
    FROM expenditure_types
    WHERE expenditure_code = :code
      AND id <> :id
    LIMIT 1
");
$dup->execute([
    'code' => $code,
    'id' => $id
]);

if ($dup->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Another expenditure type already uses that code.'
    ], 409);
}

$stmt = $pdo->prepare("
    UPDATE expenditure_types
    SET
        expenditure_code = :expenditure_code,
        expenditure_name = :expenditure_name,
        description = :description,
        is_active = :is_active,
        updated_by = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
    'expenditure_code' => $code,
    'expenditure_name' => $name,
    'description' => $description !== '' ? $description : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'updated_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'expenditure_type', $id, 'Expenditure type #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Expenditure type updated successfully.'
]);