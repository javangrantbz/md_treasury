<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requirePost();

try {
    $customerName  = trim((string)($_POST['customer_name']  ?? ''));
    $nic           = trim((string)($_POST['nic']            ?? ''));
    $accountNumber = trim((string)($_POST['account_number'] ?? ''));
    $cardType      = trim((string)($_POST['card_type']      ?? 'debit'));
    $branchId      = (int)($_POST['branch_id']              ?? 0);
    $remarks       = trim((string)($_POST['remarks']        ?? ''));
    $createdBy     = Auth::userId();

    if ($customerName === '')  throw new Exception('Customer Name is required.');
    if ($nic === '')           throw new Exception('NIC is required.');
    if ($accountNumber === '') throw new Exception('Account Number is required.');
    if ($branchId <= 0)        throw new Exception('Valid Branch is required.');

    $sql = "INSERT INTO nsb_applications (customer_name, nic, account_number, card_type, branch_id, remarks, created_by)
            VALUES (:customer_name, :nic, :account_number, :card_type, :branch_id, :remarks, :created_by)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'customer_name'  => $customerName,
        'nic'            => $nic,
        'account_number' => $accountNumber,
        'card_type'      => $cardType,
        'branch_id'      => $branchId,
        'remarks'        => $remarks,
        'created_by'     => $createdBy
    ]);

    apiResponse(['success' => true, 'message' => 'Application submitted successfully.', 'id' => $pdo->lastInsertId()]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
