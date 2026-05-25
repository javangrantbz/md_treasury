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

$id = (int)($data['id'] ?? 0);
$code = trim((string)($data['code'] ?? ''));
$name = trim((string)($data['name'] ?? ''));
$departmentId = (int)($data['department_id'] ?? 0);
$subTreasuryId = (int)($data['sub_treasury_id'] ?? 0);
$status = trim((string)($data['status'] ?? 'active'));
$description = trim((string)($data['description'] ?? ''));

if ($id <= 0 || $code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$exists = $pdo->prepare("SELECT id FROM cost_centers WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Cost center not found.'
    ], 404);
}

$dupCheck = $pdo->prepare("
    SELECT id
    FROM cost_centers
    WHERE code = :code
      AND id <> :id
    LIMIT 1
");
$dupCheck->execute([
    'code' => $code,
    'id' => $id,
]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Another cost center already uses that code.'
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

$stmt = $pdo->prepare("
    UPDATE cost_centers
    SET
        code = :code,
        name = :name,
        department_id = :department_id,
        sub_treasury_id = :sub_treasury_id,
        status = :status,
        description = :description,
        updated_by = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
    'code' => $code,
    'name' => $name,
    'department_id' => $departmentId > 0 ? $departmentId : null,
    'sub_treasury_id' => $subTreasuryId > 0 ? $subTreasuryId : null,
    'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
    'description' => $description !== '' ? $description : null,
    'updated_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'cost_center', $id, 'Cost center #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Cost center updated successfully.'
]);