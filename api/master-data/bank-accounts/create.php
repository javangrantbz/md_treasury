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

$bankName      = trim((string)($data['bank_name']      ?? ''));
$accountName   = trim((string)($data['account_name']   ?? ''));
$accountNumber = trim((string)($data['account_number'] ?? ''));
$currencyCode  = strtoupper(trim((string)($data['currency_code'] ?? 'BZD')));
$itemNumber    = trim((string)($data['item_number']    ?? ''));
$sofNumber     = trim((string)($data['sof_number']     ?? ''));
$status        = trim((string)($data['status']         ?? 'active'));
$branchName    = trim((string)($data['branch_name']    ?? ''));
$accountTypeId = ($data['account_type_id'] ?? '') !== '' ? (int)$data['account_type_id'] : null;

if ($bankName === '' || $accountName === '' || $accountNumber === '') {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Bank name, account name, and account number are required.'], 422);
}

if (!in_array($currencyCode, ['BZD', 'USD'], true)) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Currency must be BZD or USD.'], 422);
}

try {
    $dupCheck = $pdo->prepare("SELECT id FROM bank_accounts WHERE account_number = :n LIMIT 1");
    $dupCheck->execute(['n' => $accountNumber]);
    if ($dupCheck->fetch()) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Account number already exists.'], 409);
    }

    $masked = str_repeat('*', max(strlen($accountNumber) - 4, 0)) . substr($accountNumber, -4);
    $uuid   = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $stmt = $pdo->prepare("
        INSERT INTO bank_accounts
            (uuid, bank_name, account_name, account_number, account_masked,
             currency_code, item_number, sof_number, status, branch_name,
             account_type_id, created_by, updated_by)
        VALUES
            (:uuid, :bank_name, :account_name, :account_number, :account_masked,
             :currency_code, :item_number, :sof_number, :status, :branch_name,
             :account_type_id, :created_by, :updated_by)
    ");

    $stmt->execute([
        'uuid'            => $uuid,
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
        'created_by'      => $user['id'] ?? null,
        'updated_by'      => $user['id'] ?? null,
    ]);

    $newBankId = (int)$pdo->lastInsertId();
    try { AuditLog::log($pdo, 'create', 'bank_account', $newBankId, 'Bank account created.'); } catch (Throwable $e) {}
    ob_end_clean();
    apiResponse(['success' => true, 'message' => 'Bank account created successfully.', 'id' => $newBankId], 201);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
