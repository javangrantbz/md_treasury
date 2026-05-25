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
$userId = (int)($data['user_id'] ?? 0);

if ($userId <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid user id.'], 422);
}

$exists = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
$exists->execute(['id' => $userId]);

if (!$exists->fetch()) {
    apiResponse(['success' => false, 'message' => 'User not found.'], 404);
}

$pdo->prepare("
    UPDATE users
    SET register_id = NULL
    WHERE id = :id
")->execute(['id' => $userId]);

AuditLog::log($pdo, 'remove', 'register', null, 'User #' . $userId . ' removed from register.');
apiResponse([
    'success' => true,
    'message' => 'User removed from register successfully.'
]);