<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
requirePost();

$data = requestData();

$registerId = (int)($data['register_id'] ?? 0);
$userId = (int)($data['user_id'] ?? 0);

if ($registerId <= 0 || $userId <= 0) {
    apiResponse(['success' => false, 'message' => 'Register and user are required.'], 422);
}

$reg = $pdo->prepare("SELECT id, department_id, sub_treasury_id FROM registers WHERE id = :id LIMIT 1");
$reg->execute(['id' => $registerId]);
$regRow = $reg->fetch(PDO::FETCH_ASSOC);

if (!$regRow) {
    apiResponse(['success' => false, 'message' => 'Register not found.'], 404);
}

$userStmt = $pdo->prepare("
    SELECT id, department_id, sub_treasury_id, status
    FROM users
    WHERE id = :id
    LIMIT 1
");
$userStmt->execute(['id' => $userId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    apiResponse(['success' => false, 'message' => 'User not found.'], 404);
}

if ($userRow['status'] !== 'active') {
    apiResponse(['success' => false, 'message' => 'Only active users can be assigned.'], 422);
}

if (!empty($userRow['department_id']) && (int)$userRow['department_id'] !== (int)$regRow['department_id']) {
    apiResponse(['success' => false, 'message' => 'User does not belong to the register department.'], 422);
}

$pdo->prepare("
    UPDATE users
    SET register_id = :register_id
    WHERE id = :id
")->execute([
    'register_id' => $registerId,
    'id' => $userId
]);

AuditLog::log($pdo, 'assign', 'register', $registerId, 'User #' . $userId . ' assigned to register #' . $registerId . '.');
apiResponse([
    'success' => true,
    'message' => 'User assigned to register successfully.'
]);