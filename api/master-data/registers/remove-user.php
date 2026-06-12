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
$userId     = (int)($data['user_id']     ?? 0);

if ($registerId <= 0 || $userId <= 0) {
    apiResponse(['success' => false, 'message' => 'Register and user are required.'], 422);
}

$pdo->prepare("
    UPDATE register_users SET deleted_at = NOW() WHERE register_id = :register_id AND user_id = :user_id
")->execute(['register_id' => $registerId, 'user_id' => $userId]);

AuditLog::log($pdo, 'remove', 'register', $registerId, 'User #' . $userId . ' removed from register #' . $registerId . '.');
apiResponse(['success' => true, 'message' => 'User removed successfully.']);
