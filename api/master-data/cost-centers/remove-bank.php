<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.cost_centers.manage');
requirePost();

$data = requestData();
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Invalid assignment id.'], 422);
}

try {
    $exists = $pdo->prepare("SELECT id FROM cost_center_bank_accounts WHERE id = :id LIMIT 1");
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $pdo->prepare("DELETE FROM cost_center_bank_accounts WHERE id = :id")->execute(['id' => $id]);

    try { AuditLog::log($pdo, 'remove', 'cost_center', null, 'Bank account assignment #' . $id . ' removed from cost center.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse(['success' => true, 'message' => 'Bank account removed successfully.']);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
