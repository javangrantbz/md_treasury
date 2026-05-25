<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_center_activities.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$id = (int)($data['id'] ?? 0);
$costCenterId = (int)($data['cost_center_id'] ?? 0);
$activityCode = trim((string)($data['activity_code'] ?? ''));
$activityName = trim((string)($data['activity_name'] ?? ''));
$revenueCode = trim((string)($data['revenue_code'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$defaultAmount = trim((string)($data['default_amount'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($id <= 0 || $costCenterId <= 0 || $activityCode === '' || $activityName === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$exists = $pdo->prepare("SELECT id FROM cost_center_activities WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Activity not found.'
    ], 404);
}

$ccCheck = $pdo->prepare("SELECT id FROM cost_centers WHERE id = :id LIMIT 1");
$ccCheck->execute(['id' => $costCenterId]);

if (!$ccCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Selected cost center does not exist.'
    ], 422);
}

$dupCheck = $pdo->prepare("
    SELECT id
    FROM cost_center_activities
    WHERE activity_code = :code
      AND id <> :id
    LIMIT 1
");
$dupCheck->execute([
    'code' => $activityCode,
    'id' => $id,
]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Another activity already uses that code.'
    ], 409);
}

if ($defaultAmount !== '' && !is_numeric($defaultAmount)) {
    apiResponse([
        'success' => false,
        'message' => 'Default amount must be numeric.'
    ], 422);
}

$stmt = $pdo->prepare("
    UPDATE cost_center_activities
    SET
        cost_center_id = :cost_center_id,
        activity_code = :activity_code,
        activity_name = :activity_name,
        revenue_code = :revenue_code,
        description = :description,
        default_amount = :default_amount,
        is_active = :is_active,
        updated_by = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
    'cost_center_id' => $costCenterId,
    'activity_code' => $activityCode,
    'activity_name' => $activityName,
    'revenue_code' => $revenueCode !== '' ? $revenueCode : null,
    'description' => $description !== '' ? $description : null,
    'default_amount' => $defaultAmount !== '' ? $defaultAmount : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'updated_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'cost_center_activity', $id, 'Activity #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Activity updated successfully.'
]);