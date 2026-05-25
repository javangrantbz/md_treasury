<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.bank_accounts.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$id            = (int)($data['id']             ?? 0);
$bankName      = trim((string)($data['bank_name']      ?? ''));
$accountName   = trim((string)($data['account_name']   ?? ''));
$accountNumber = trim((string)($data['account_number'] ?? ''));
$currencyCode  = strtoupper(trim((string)($data['currency_code'] ?? 'BZD')));
$itemNumber    = trim((string)($data['item_number']    ?? ''));
$sofNumber     = trim((string)($data['sof_number']     ?? ''));
$status        = trim((string)($data['status']         ?? 'active'));
$branchName    = trim((string)($data['branch_name']    ?? ''));
$accountTypeId = ($data['account_type_id'] ?? '') !== '' ? (int)$data['account_type_id'] : null;

if ($id <= 0 || $bankName === '' || $accountName === '' || $accountNumber === '') {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Invalid request.'], 422);
}

if (!in_array($currencyCode, ['BZD', 'USD'], true)) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Currency must be BZD or USD.'], 422);
}

try {
    $exists = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = :id LIMIT 1");
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Bank account not found.'], 404);
    }

    $dupCheck = $pdo->prepare("SELECT id FROM bank_accounts WHERE account_number = :n AND id <> :id LIMIT 1");
    $dupCheck->execute(['n' => $accountNumber, 'id' => $id]);
    if ($dupCheck->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Another bank account already uses that account number.'], 409);
    }

    $masked = str_repeat('*', max(strlen($accountNumber) - 4, 0)) . substr($accountNumber, -4);

    $stmt = $pdo->prepare("
        UPDATE bank_accounts
        SET bank_name       = :bank_name,
            account_name    = :account_name,
            account_number  = :account_number,
            account_masked  = :account_masked,
            currency_code   = :currency_code,
            item_number     = :item_number,
            sof_number      = :sof_number,
            status          = :status,
            branch_name     = :branch_name,
            account_type_id = :account_type_id,
            updated_by      = :updated_by
        WHERE id = :id
    ");

    $stmt->execute([
        'id'              => $id,
        'bank_name'       => $bankName,
        'account_name'    => $accountName,
        'account_number'  => $accountNumber,
        'account_masked'  => $masked,
        'currency_code'   => $currencyCode,
        'item_number'     => $itemNumber !== '' ? $itemNumber : null,
        'sof_number'      => $sofNumber  !== '' ? $sofNumber  : null,
        'status'          => in_array($status, ['active','inactive'], true) ? $status : 'active',
        'branch_name'     => $branchName !== '' ? $branchName : null,
        'account_type_id' => $accountTypeId,
        'updated_by'      => $user['id'] ?? null,
    ]);

    try { AuditLog::log($pdo, 'update', 'bank_account', $id, 'Bank account #' . $id . ' updated.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse(['success' => true, 'message' => 'Bank account updated successfully.']);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
