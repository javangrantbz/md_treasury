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

$dept = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
$dept->execute(['id' => $departmentId]);
if (!$dept->fetch()) {
    apiResponse(['success' => false, 'message' => 'Department not found.'], 404);
}

$cc = $pdo->prepare("SELECT id FROM cost_centers WHERE id = :id LIMIT 1");
$cc->execute(['id' => $costCenterId]);
if (!$cc->fetch()) {
    apiResponse(['success' => false, 'message' => 'Cost center not found.'], 404);
}

$pdo->prepare("
    INSERT IGNORE INTO department_cost_centers (department_id, cost_center_id)
    VALUES (:department_id, :cost_center_id)
")->execute(['department_id' => $departmentId, 'cost_center_id' => $costCenterId]);

AuditLog::log($pdo, 'assign', 'department', $departmentId, 'Cost center #' . $costCenterId . ' assigned to department #' . $departmentId . '.');
apiResponse(['success' => true, 'message' => 'Cost center assigned successfully.']);
