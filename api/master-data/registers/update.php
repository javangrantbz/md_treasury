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

$id             = (int)($data['id']             ?? 0);
$departmentId   = (int)($data['department_id']   ?? 0);
$subTreasuryId  = ($data['sub_treasury_id'] ?? '') !== '' ? (int)$data['sub_treasury_id'] : null;
$assignedUserId = ($data['assigned_user_id'] ?? '') !== '' ? (int)$data['assigned_user_id'] : null;
$registerName   = trim((string)($data['register_name'] ?? ''));
$description    = trim((string)($data['description']   ?? ''));
$isActive       = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($id <= 0 || $departmentId <= 0 || $registerName === '') {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Department and register name are required.'], 422);
}

try {
    $exists = $pdo->prepare("SELECT id FROM registers WHERE id = :id LIMIT 1");
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Register not found.'], 404);
    }

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

    $stmt = $pdo->prepare("
        UPDATE registers
        SET
            department_id    = :department_id,
            sub_treasury_id  = :sub_treasury_id,
            assigned_user_id = :assigned_user_id,
            register_name    = :register_name,
            description      = :description,
            is_active        = :is_active,
            updated_by       = :updated_by
        WHERE id = :id
    ");

    $stmt->execute([
        'id'              => $id,
        'department_id'   => $departmentId,
        'sub_treasury_id' => $subTreasuryId,
        'assigned_user_id'=> $assignedUserId,
        'register_name'   => $registerName,
        'description'     => $description !== '' ? $description : null,
        'is_active'       => $isActive === 1 ? 1 : 0,
        'updated_by'      => $user['id'] ?? null,
    ]);

    try { AuditLog::log($pdo, 'update', 'register', $id, 'Register #' . $id . ' updated.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse(['success' => true, 'message' => 'Register updated successfully.']);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
