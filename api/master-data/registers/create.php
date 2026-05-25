<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$departmentId   = (int)($data['department_id']    ?? 0);
$subTreasuryId  = ($data['sub_treasury_id'] ?? '') !== '' ? (int)$data['sub_treasury_id'] : null;
$assignedUserId = ($data['assigned_user_id'] ?? '') !== '' ? (int)$data['assigned_user_id'] : null;
$registerName   = trim((string)($data['register_name'] ?? ''));
$description    = trim((string)($data['description']   ?? ''));
$isActive       = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($departmentId <= 0 || $registerName === '') {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Department and register name are required.'], 422);
}

try {
    $dept = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
    $dept->execute(['id' => $departmentId]);
    if (!$dept->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Selected department does not exist.'], 422);
    }

    if ($subTreasuryId !== null) {
        $st = $pdo->prepare("SELECT id, department_id FROM sub_treasuries WHERE id = :id LIMIT 1");
        $st->execute(['id' => $subTreasuryId]);
        $stRow = $st->fetch(PDO::FETCH_ASSOC);

        if (!$stRow) {
            ob_end_clean();
            apiResponse(['success' => false, 'message' => 'Selected sub-treasury does not exist.'], 422);
        }

        if ((int)$stRow['department_id'] !== $departmentId) {
            ob_end_clean();
            apiResponse(['success' => false, 'message' => 'Sub-treasury does not belong to the selected department.'], 422);
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

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO registers (
            uuid, department_id, sub_treasury_id, assigned_user_id, register_code, register_name,
            description, is_active, created_by, updated_by
        ) VALUES (
            :uuid, :department_id, :sub_treasury_id, :assigned_user_id, :register_code, :register_name,
            :description, :is_active, :created_by, :updated_by
        )
    ");

    $stmt->execute([
        'uuid'             => $uuid,
        'department_id'    => $departmentId,
        'sub_treasury_id'  => $subTreasuryId,
        'assigned_user_id' => $assignedUserId,
        'register_code'    => 'TEMP-' . time(),
        'register_name'    => $registerName,
        'description'      => $description !== '' ? $description : null,
        'is_active'        => $isActive === 1 ? 1 : 0,
        'created_by'       => $user['id'] ?? null,
        'updated_by'       => $user['id'] ?? null,
    ]);

    $newId        = (int)$pdo->lastInsertId();
    $registerCode = 'REG-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);

    $pdo->prepare("UPDATE registers SET register_code = :code WHERE id = :id")
        ->execute(['code' => $registerCode, 'id' => $newId]);

    $pdo->commit();
    try { AuditLog::log($pdo, 'create', 'register', $newId, 'Register ' . $registerCode . ' created.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse([
        'success'       => true,
        'message'       => 'Register created successfully.',
        'id'            => $newId,
        'register_code' => $registerCode,
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
