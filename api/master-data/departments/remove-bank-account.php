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

$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid assignment id.'
    ], 422);
}

$exists = $pdo->prepare("
    SELECT id
    FROM department_bank_accounts
    WHERE id = :id AND deleted_at IS NULL
    LIMIT 1
");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Assignment not found.'
    ], 404);
}

$stmt = $pdo->prepare("UPDATE department_bank_accounts SET deleted_at = NOW() WHERE id = :id");
$stmt->execute(['id' => $id]);

AuditLog::log($pdo, 'remove', 'department', null, 'Bank account assignment #' . $id . ' removed from department.');
apiResponse([
    'success' => true,
    'message' => 'Bank account removed from department successfully.'
]);