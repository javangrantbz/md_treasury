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
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid assignment id.'], 422);
}

$exists = $pdo->prepare("SELECT id FROM register_bank_accounts WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
}

$pdo->prepare("DELETE FROM register_bank_accounts WHERE id = :id")->execute(['id' => $id]);

AuditLog::log($pdo, 'remove', 'register', null, 'Bank account assignment #' . $id . ' removed from register.');
apiResponse([
    'success' => true,
    'message' => 'Bank account removed from register successfully.'
]);