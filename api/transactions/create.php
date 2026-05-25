<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();
$user = Auth::user();

$departmentId    = (int)($data['department_id'] ?? 0);
$subTreasuryId   = (int)($data['sub_treasury_id'] ?? 0);
$registerId      = (int)($data['register_id'] ?? 0);
$costCenterId    = (int)($data['cost_center_id'] ?? 0);
$bankAccountId   = (int)($data['bank_account_id'] ?? 0);
$customerId      = (int)($data['customer_id'] ?? 0);
$transactionDate = trim((string)($data['transaction_date'] ?? ''));
$narration       = trim((string)($data['narration'] ?? ''));
$status          = trim((string)($data['status'] ?? 'draft'));

if ($departmentId <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Department is required.'
    ], 422);
}

if ($transactionDate === '') {
    apiResponse([
        'success' => false,
        'message' => 'Transaction date is required.'
    ], 422);
}

$dateCheck = DateTime::createFromFormat('Y-m-d', $transactionDate);
if (!$dateCheck || $dateCheck->format('Y-m-d') !== $transactionDate) {
    apiResponse([
        'success' => false,
        'message' => 'Transaction date must be in YYYY-MM-DD format.'
    ], 422);
}

$allowedStatuses = ['draft', 'submitted'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'draft';
}

/*
|--------------------------------------------------------------------------
| Validate department
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, branch_id, status
    FROM departments
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(['id' => $departmentId]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    apiResponse([
        'success' => false,
        'message' => 'Selected department does not exist.'
    ], 422);
}

if (($department['status'] ?? '') !== 'active') {
    apiResponse([
        'success' => false,
        'message' => 'Selected department is inactive.'
    ], 422);
}

$branchId = (int)($department['branch_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Validate sub-treasury
|--------------------------------------------------------------------------
*/
if ($subTreasuryId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, department_id, is_active
        FROM sub_treasuries
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $subTreasuryId]);
    $subTreasury = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subTreasury) {
        apiResponse([
            'success' => false,
            'message' => 'Selected sub-treasury does not exist.'
        ], 422);
    }

    if ((int)$subTreasury['department_id'] !== $departmentId) {
        apiResponse([
            'success' => false,
            'message' => 'Selected sub-treasury does not belong to the selected department.'
        ], 422);
    }

    if ((int)($subTreasury['is_active'] ?? 0) !== 1) {
        apiResponse([
            'success' => false,
            'message' => 'Selected sub-treasury is inactive.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| Validate register
|--------------------------------------------------------------------------
*/
if ($registerId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, department_id, sub_treasury_id, is_active
        FROM registers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $registerId]);
    $register = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$register) {
        apiResponse([
            'success' => false,
            'message' => 'Selected register does not exist.'
        ], 422);
    }

    if ((int)$register['department_id'] !== $departmentId) {
        apiResponse([
            'success' => false,
            'message' => 'Selected register does not belong to the selected department.'
        ], 422);
    }

    if ($subTreasuryId > 0 && (int)$register['sub_treasury_id'] !== $subTreasuryId) {
        apiResponse([
            'success' => false,
            'message' => 'Selected register does not belong to the selected sub-treasury.'
        ], 422);
    }

    if ((int)($register['is_active'] ?? 0) !== 1) {
        apiResponse([
            'success' => false,
            'message' => 'Selected register is inactive.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| Validate cost center
|--------------------------------------------------------------------------
*/
if ($costCenterId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, department_id, sub_treasury_id, status
        FROM cost_centers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $costCenterId]);
    $costCenter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$costCenter) {
        apiResponse([
            'success' => false,
            'message' => 'Selected cost center does not exist.'
        ], 422);
    }

    if ((int)$costCenter['department_id'] !== $departmentId) {
        apiResponse([
            'success' => false,
            'message' => 'Selected cost center does not belong to the selected department.'
        ], 422);
    }

    if ($subTreasuryId > 0 && !empty($costCenter['sub_treasury_id']) && (int)$costCenter['sub_treasury_id'] !== $subTreasuryId) {
        apiResponse([
            'success' => false,
            'message' => 'Selected cost center does not belong to the selected sub-treasury.'
        ], 422);
    }

    if (($costCenter['status'] ?? '') !== 'active') {
        apiResponse([
            'success' => false,
            'message' => 'Selected cost center is inactive.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| Validate bank account
|--------------------------------------------------------------------------
*/
if ($bankAccountId > 0) {
    if ($registerId <= 0) {
        apiResponse([
            'success' => false,
            'message' => 'A register must be selected before choosing a bank account.'
        ], 422);
    }

    $stmt = $pdo->prepare("
        SELECT
            rba.id,
            rba.register_id,
            rba.bank_account_id,
            rba.is_active,
            ba.status
        FROM register_bank_accounts rba
        INNER JOIN bank_accounts ba ON ba.id = rba.bank_account_id
        WHERE rba.register_id = :register_id
          AND rba.bank_account_id = :bank_account_id
        LIMIT 1
    ");
    $stmt->execute([
        'register_id' => $registerId,
        'bank_account_id' => $bankAccountId
    ]);
    $registerBank = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registerBank) {
        apiResponse([
            'success' => false,
            'message' => 'Selected bank account is not assigned to the selected register.'
        ], 422);
    }

    if ((int)($registerBank['is_active'] ?? 0) !== 1) {
        apiResponse([
            'success' => false,
            'message' => 'Selected register bank account mapping is inactive.'
        ], 422);
    }

    if (($registerBank['status'] ?? '') !== 'active') {
        apiResponse([
            'success' => false,
            'message' => 'Selected bank account is inactive.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| Validate customer
|--------------------------------------------------------------------------
*/
if ($customerId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        apiResponse([
            'success' => false,
            'message' => 'Selected customer does not exist.'
        ], 422);
    }

    if (($customer['status'] ?? '') !== 'active') {
        apiResponse([
            'success' => false,
            'message' => 'Selected customer is inactive.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| Generate IDs
|--------------------------------------------------------------------------
*/
$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$year = date('Y');
$prefix = "RC-{$year}-";

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM transactions
    WHERE receipt_number LIKE :prefix
");
$stmt->execute(['prefix' => $prefix . '%']);
$total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$receiptNumber = $prefix . str_pad((string)($total + 1), 6, '0', STR_PAD_LEFT);

$createdBy = (int)($user['id'] ?? 0);
if ($createdBy <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Authenticated user not found.'
    ], 401);
}

/*
|--------------------------------------------------------------------------
| Insert transaction
|--------------------------------------------------------------------------
|
| Adjust the column list below only if your transactions table uses
| slightly different column names than these.
|
*/
$stmt = $pdo->prepare("
    INSERT INTO transactions (
        uuid,
        receipt_number,
        department_id,
        sub_treasury_id,
        register_id,
        cost_center_id,
        bank_account_id,
        customer_id,
        transaction_date,
        narration,
        status,
        created_by
    ) VALUES (
        :uuid,
        :receipt_number,
        :department_id,
        :sub_treasury_id,
        :register_id,
        :cost_center_id,
        :bank_account_id,
        :customer_id,
        :transaction_date,
        :narration,
        :status,
        :created_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'receipt_number' => $receiptNumber,
    'department_id' => $departmentId,
    'sub_treasury_id' => $subTreasuryId > 0 ? $subTreasuryId : null,
    'register_id' => $registerId > 0 ? $registerId : null,
    'cost_center_id' => $costCenterId > 0 ? $costCenterId : null,
    'bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
    'customer_id' => $customerId > 0 ? $customerId : null,
    'transaction_date' => $transactionDate,
    'narration' => $narration !== '' ? $narration : null,
    'status' => $status,
    'created_by' => $createdBy
]);

$transactionId = (int)$pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| Optional status history
|--------------------------------------------------------------------------
|
| Leave this block in only if your table exists.
|
*/
try {
    $stmt = $pdo->prepare("
        INSERT INTO transaction_status_history (
            transaction_id,
            previous_status,
            new_status,
            changed_by,
            change_reason
        ) VALUES (
            :transaction_id,
            :previous_status,
            :new_status,
            :changed_by,
            :change_reason
        )
    ");

    $stmt->execute([
        'transaction_id' => $transactionId,
        'previous_status' => null,
        'new_status' => $status,
        'changed_by' => $createdBy,
        'change_reason' => $status === 'submitted' ? 'Created and submitted.' : 'Created as draft.'
    ]);
} catch (Throwable $e) {
    // Ignore if you have not created transaction_status_history yet.
}

AuditLog::log($pdo, 'create', 'transaction', $transactionId, 'Transaction ' . $receiptNumber . ' created.');
if (wantsJson()) {
    apiResponse([
        'success' => true,
        'message' => 'Transaction created successfully.',
        'id' => $transactionId,
        'receipt_number' => $receiptNumber
    ], 201);
}

flash('flash_success', 'Transaction created successfully.');
redirect(url('views/cashiering/transactions/details.php?id=' . $transactionId));