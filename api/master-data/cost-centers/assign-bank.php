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
$user = Auth::user();

$costCenterId  = (int)($data['cost_center_id']  ?? 0);
$bankAccountId = (int)($data['bank_account_id'] ?? 0);

if ($costCenterId <= 0 || $bankAccountId <= 0) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Cost center and bank account are required.'], 422);
}

try {
    $cc = $pdo->prepare("SELECT id FROM cost_centers WHERE id = :id LIMIT 1");
    $cc->execute(['id' => $costCenterId]);
    if (!$cc->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Cost center not found.'], 404);
    }

    $ba = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = :id LIMIT 1");
    $ba->execute(['id' => $bankAccountId]);
    if (!$ba->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Bank account not found.'], 404);
    }

    $dup = $pdo->prepare("SELECT id FROM cost_center_bank_accounts WHERE cost_center_id = :cc AND bank_account_id = :ba LIMIT 1");
    $dup->execute(['cc' => $costCenterId, 'ba' => $bankAccountId]);
    if ($dup->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'This bank account is already assigned to the cost center.'], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO cost_center_bank_accounts (cost_center_id, bank_account_id, created_by) VALUES (:cc, :ba, :created_by)");
    $stmt->execute(['cc' => $costCenterId, 'ba' => $bankAccountId, 'created_by' => $user['id'] ?? null]);

    $newAssignId = (int)$pdo->lastInsertId();
    try { AuditLog::log($pdo, 'assign', 'cost_center', $costCenterId, 'Bank account #' . $bankAccountId . ' assigned to cost center #' . $costCenterId . '.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse(['success' => true, 'message' => 'Bank account assigned successfully.', 'id' => $newAssignId], 201);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
