<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Invalid bank account id.'], 422);
}

try {
    $stmt = $pdo->prepare("
        SELECT ba.id, ba.uuid, ba.bank_name, ba.account_name, ba.account_number,
               ba.account_masked, ba.currency_code, ba.item_number, ba.sof_number,
               ba.status, ba.branch_name, ba.account_type_id, ba.created_at,
               bat.name AS account_type_name
        FROM bank_accounts ba
        LEFT JOIN bank_account_types bat ON bat.id = ba.account_type_id
        WHERE ba.id = :id AND ba.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Bank account not found.'], 404);
    }

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $row]);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
