<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $costCenterId = (int)($_GET['cost_center_id'] ?? 0);
    if ($costCenterId <= 0) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Invalid cost_center_id.'], 422);
    }

    $stmt = $pdo->prepare("
        SELECT ccba.id AS assignment_id,
               ba.id, ba.bank_name, ba.account_name, ba.account_masked,
               ba.currency_code, ba.item_number, ba.sof_number, ba.status,
               ba.branch_name, bat.name AS account_type_name
        FROM cost_center_bank_accounts ccba
        JOIN bank_accounts ba            ON ba.id  = ccba.bank_account_id
        LEFT JOIN bank_account_types bat ON bat.id = ba.account_type_id
        WHERE ccba.cost_center_id = :cost_center_id
        ORDER BY ba.bank_name ASC, ba.account_name ASC
    ");
    $stmt->execute(['cost_center_id' => $costCenterId]);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
