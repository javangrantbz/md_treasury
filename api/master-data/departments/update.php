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

$id           = (int)($data['id']            ?? 0);
$code         = trim((string)($data['code']          ?? ''));
$name         = trim((string)($data['name']          ?? ''));
$ministryName = trim((string)($data['ministry_name'] ?? ''));
$branchId     = (int)($data['branch_id']     ?? 0);
$status       = trim((string)($data['status']        ?? 'active'));
$shortName    = trim((string)($data['short_name']    ?? ''));
$description  = trim((string)($data['description']   ?? ''));
$bankIds      = array_filter(array_map('intval', (array)($data['bank_account_ids'] ?? [])));
$ccIds        = array_filter(array_map('intval', (array)($data['cost_center_ids']  ?? [])));

if ($id <= 0 || $code === '' || $name === '') {
    apiResponse(['success' => false, 'message' => 'Department id, code, and name are required.'], 422);
}

$exists = $pdo->prepare("SELECT id FROM departments WHERE id = :id AND deleted_at IS NULL LIMIT 1");
$exists->execute(['id' => $id]);
if (!$exists->fetch()) {
    apiResponse(['success' => false, 'message' => 'Department not found.'], 404);
}

$check = $pdo->prepare("SELECT id FROM departments WHERE code = :code AND id <> :id AND deleted_at IS NULL LIMIT 1");
$check->execute(['code' => $code, 'id' => $id]);
if ($check->fetch()) {
    apiResponse(['success' => false, 'message' => 'Another department already uses that code.'], 409);
}

if ($branchId > 0) {
    $branchCheck = $pdo->prepare("SELECT id FROM branches WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $branchCheck->execute(['id' => $branchId]);
    if (!$branchCheck->fetch()) {
        apiResponse(['success' => false, 'message' => 'Selected branch does not exist.'], 422);
    }
}

$pdo->beginTransaction();
try {
    $pdo->prepare("
        UPDATE departments
        SET code=:code, name=:name, ministry_name=:ministry_name, branch_id=:branch_id,
            status=:status, short_name=:short_name, description=:description, updated_by=:updated_by
        WHERE id = :id
    ")->execute([
        'id'           => $id,
        'code'         => $code,
        'name'         => $name,
        'ministry_name'=> $ministryName !== '' ? $ministryName : null,
        'branch_id'    => $branchId > 0 ? $branchId : null,
        'status'       => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
        'short_name'   => $shortName    !== '' ? $shortName    : null,
        'description'  => $description  !== '' ? $description  : null,
        'updated_by'   => $user['id'] ?? null,
    ]);

    // Sync bank accounts: soft-delete all then re-insert selected
    $pdo->prepare("UPDATE department_bank_accounts SET deleted_at = NOW() WHERE department_id = :id AND deleted_at IS NULL")->execute(['id' => $id]);
    if (!empty($bankIds)) {
        $baInsert = $pdo->prepare("
            INSERT IGNORE INTO department_bank_accounts (department_id, bank_account_id, is_default, status, created_by)
            VALUES (:dept_id, :ba_id, 0, 'active', :created_by)
        ");
        foreach ($bankIds as $baId) {
            $baInsert->execute(['dept_id' => $id, 'ba_id' => $baId, 'created_by' => $user['id'] ?? null]);
        }
    }

    // Sync cost centers via junction table
    $pdo->prepare("UPDATE department_cost_centers SET deleted_at = NOW() WHERE department_id = :id AND deleted_at IS NULL")->execute(['id' => $id]);
    if (!empty($ccIds)) {
        $ccInsert = $pdo->prepare("
            INSERT IGNORE INTO department_cost_centers (department_id, cost_center_id)
            VALUES (:dept_id, :cc_id)
        ");
        foreach ($ccIds as $ccId) {
            $ccInsert->execute(['dept_id' => $id, 'cc_id' => $ccId]);
        }
    }

    $pdo->commit();
    try { AuditLog::log($pdo, 'update', 'department', $id, 'Department #' . $id . ' updated.'); } catch (Throwable $e) {}
    apiResponse(['success' => true, 'message' => 'Department updated successfully.']);

} catch (Throwable $e) {
    $pdo->rollBack();
    apiResponse(['success' => false, 'message' => 'Failed to update department: ' . $e->getMessage()], 500);
}
