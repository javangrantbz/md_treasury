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

$costCenterId = (int)($data['cost_center_id'] ?? 0);
$activityCode = trim((string)($data['activity_code'] ?? ''));
$activityName = trim((string)($data['activity_name'] ?? ''));
$revenueCode = trim((string)($data['revenue_code'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$defaultAmount = trim((string)($data['default_amount'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($costCenterId <= 0 || $activityCode === '' || $activityName === '') {
    apiResponse([
        'success' => false,
        'message' => 'Cost center, activity code, and activity name are required.'
    ], 422);
}

$ccCheck = $pdo->prepare("SELECT id FROM cost_centers WHERE id = :id LIMIT 1");
$ccCheck->execute(['id' => $costCenterId]);

if (!$ccCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Selected cost center does not exist.'
    ], 422);
}

$dupCheck = $pdo->prepare("SELECT id FROM cost_center_activities WHERE activity_code = :code LIMIT 1");
$dupCheck->execute(['code' => $activityCode]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Activity code already exists.'
    ], 409);
}

if ($defaultAmount !== '' && !is_numeric($defaultAmount)) {
    apiResponse([
        'success' => false,
        'message' => 'Default amount must be numeric.'
    ], 422);
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
    INSERT INTO cost_center_activities (
        uuid, cost_center_id, activity_code, activity_name,
        revenue_code, description, default_amount, is_active,
        created_by, updated_by
    ) VALUES (
        :uuid, :cost_center_id, :activity_code, :activity_name,
        :revenue_code, :description, :default_amount, :is_active,
        :created_by, :updated_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'cost_center_id' => $costCenterId,
    'activity_code' => $activityCode,
    'activity_name' => $activityName,
    'revenue_code' => $revenueCode !== '' ? $revenueCode : null,
    'description' => $description !== '' ? $description : null,
    'default_amount' => $defaultAmount !== '' ? $defaultAmount : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'created_by' => $user['id'] ?? null,
    'updated_by' => $user['id'] ?? null,
]);

$newActId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'cost_center_activity', $newActId, 'Activity created.');
apiResponse([
    'success' => true,
    'message' => 'Activity created successfully.',
    'id' => $newActId
], 201);