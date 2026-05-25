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

$name         = trim((string)($data['name']         ?? ''));
$ministryName = trim((string)($data['ministry_name'] ?? ''));
$shortName    = trim((string)($data['short_name']    ?? ''));
$description  = trim((string)($data['description']   ?? ''));
$status       = trim((string)($data['status']        ?? 'active'));
$bankIds      = array_filter(array_map('intval', (array)($data['bank_account_ids'] ?? [])));
$ccIds        = array_filter(array_map('intval', (array)($data['cost_center_ids']  ?? [])));

if (empty($name)) {
    apiResponse(['success' => false, 'message' => 'Department name is required.'], 422);
}

$check = $pdo->prepare("SELECT 1 FROM departments WHERE name = :name LIMIT 1");
$check->execute(['name' => $name]);
if ($check->fetch()) {
    apiResponse(['success' => false, 'message' => 'Department name already exists.'], 409);
}

// Auto-generate unique code
do {
    $code = 'DEPT-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $codeCheck = $pdo->prepare("SELECT 1 FROM departments WHERE code = :code LIMIT 1");
    $codeCheck->execute(['code' => $code]);
} while ($codeCheck->fetch());

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO departments (code, name, ministry_name, short_name, description, status, created_by, updated_by)
        VALUES (:code, :name, :ministry_name, :short_name, :description, :status, :created_by, :updated_by)
    ");
    $stmt->execute([
        'code'         => $code,
        'name'         => $name,
        'ministry_name'=> $ministryName !== '' ? $ministryName : null,
        'short_name'   => $shortName    !== '' ? $shortName    : null,
        'description'  => $description  !== '' ? $description  : null,
        'status'       => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
        'created_by'   => $user['id'] ?? null,
        'updated_by'   => $user['id'] ?? null,
    ]);
    $deptId = (int)$pdo->lastInsertId();

    // Assign bank accounts
    if (!empty($bankIds)) {
        $baInsert = $pdo->prepare("
            INSERT IGNORE INTO department_bank_accounts (department_id, bank_account_id, is_default, status, created_by)
            VALUES (:dept_id, :ba_id, 0, 'active', :created_by)
        ");
        foreach ($bankIds as $baId) {
            $baInsert->execute(['dept_id' => $deptId, 'ba_id' => $baId, 'created_by' => $user['id'] ?? null]);
        }
    }

    // Assign cost centers via junction table
    if (!empty($ccIds)) {
        $ccInsert = $pdo->prepare("
            INSERT IGNORE INTO department_cost_centers (department_id, cost_center_id)
            VALUES (:dept_id, :cc_id)
        ");
        foreach ($ccIds as $ccId) {
            $ccInsert->execute(['dept_id' => $deptId, 'cc_id' => $ccId]);
        }
    }

    $pdo->commit();
    try { AuditLog::log($pdo, 'create', 'department', $deptId, 'Department created.'); } catch (Throwable $e) {}
    apiResponse(['success' => true, 'message' => 'Department created successfully.', 'id' => $deptId], 201);

} catch (Throwable $e) {
    $pdo->rollBack();
    apiResponse(['success' => false, 'message' => 'Failed to create department: ' . $e->getMessage()], 500);
}
