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

$departmentId = (int)($data['department_id'] ?? 0);
$costCenterId = (int)($data['cost_center_id'] ?? 0);

if ($departmentId <= 0 || $costCenterId <= 0) {
    apiResponse(['success' => false, 'message' => 'Department and cost center are required.'], 422);
}

$pdo->prepare("
    UPDATE department_cost_centers SET deleted_at = NOW()
    WHERE department_id = :department_id AND cost_center_id = :cost_center_id
")->execute(['department_id' => $departmentId, 'cost_center_id' => $costCenterId]);

AuditLog::log($pdo, 'remove', 'department', $departmentId, 'Cost center #' . $costCenterId . ' removed from department #' . $departmentId . '.');
apiResponse(['success' => true, 'message' => 'Cost center removed from department.']);
