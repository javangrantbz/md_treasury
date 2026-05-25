<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();
$user = Auth::user();

$supplierId = (int)($data['supplier_id'] ?? 0);
$expenditureTypeId = (int)($data['expenditure_type_id'] ?? 0);
$departmentId = (int)($data['department_id'] ?? 0);
$subTreasuryId = (int)($data['sub_treasury_id'] ?? 0);
$registerId = (int)($data['register_id'] ?? 0);
$branchId = (int)($data['branch_id'] ?? 0);
$invoiceNumber = trim((string)($data['invoice_number'] ?? ''));
$invoiceDate = trim((string)($data['invoice_date'] ?? ''));
$totalAmount = trim((string)($data['total_amount'] ?? ''));
$totalGst = trim((string)($data['total_gst'] ?? '0'));
$currencyCode = strtoupper(trim((string)($data['currency_code'] ?? 'BZD')));
$status = trim((string)($data['status'] ?? 'draft'));
$notes = trim((string)($data['notes'] ?? ''));

if ($supplierId <= 0 || $expenditureTypeId <= 0 || $departmentId <= 0 || $totalAmount === '') {
    apiResponse([
        'success' => false,
        'message' => 'Supplier, expenditure type, department, and total amount are required.'
    ], 422);
}

if (!is_numeric($totalAmount) || (float)$totalAmount < 0) {
    apiResponse(['success' => false, 'message' => 'Total amount must be a valid non-negative number.'], 422);
}

if ($totalGst !== '' && (!is_numeric($totalGst) || (float)$totalGst < 0)) {
    apiResponse(['success' => false, 'message' => 'Total GST must be a valid non-negative number.'], 422);
}

$allowedCurrencies = ['BZD', 'USD'];
if (!in_array($currencyCode, $allowedCurrencies, true)) {
    apiResponse(['success' => false, 'message' => 'Currency must be BZD or USD.'], 422);
}

$allowedStatuses = ['draft', 'submitted'];
if (!in_array($status, $allowedStatuses, true)) {
    apiResponse(['success' => false, 'message' => 'Invalid initial status.'], 422);
}

if ($invoiceDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $invoiceDate);
    if (!$d || $d->format('Y-m-d') !== $invoiceDate) {
        apiResponse(['success' => false, 'message' => 'Invoice date must be in YYYY-MM-DD format.'], 422);
    }
}

$stmt = $pdo->prepare("SELECT id, is_active FROM suppliers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $supplierId]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$supplier) {
    apiResponse(['success' => false, 'message' => 'Selected supplier does not exist.'], 422);
}
if ((int)$supplier['is_active'] !== 1) {
    apiResponse(['success' => false, 'message' => 'Selected supplier is inactive.'], 422);
}

$stmt = $pdo->prepare("SELECT id, is_active FROM expenditure_types WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $expenditureTypeId]);
$expType = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$expType) {
    apiResponse(['success' => false, 'message' => 'Selected expenditure type does not exist.'], 422);
}
if ((int)$expType['is_active'] !== 1) {
    apiResponse(['success' => false, 'message' => 'Selected expenditure type is inactive.'], 422);
}

$stmt = $pdo->prepare("SELECT id, branch_id FROM departments WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $departmentId]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$department) {
    apiResponse(['success' => false, 'message' => 'Selected department does not exist.'], 422);
}

if ($branchId > 0 && (int)($department['branch_id'] ?? 0) !== $branchId) {
    apiResponse(['success' => false, 'message' => 'Department does not belong to selected branch.'], 422);
}

if ($subTreasuryId > 0) {
    $stmt = $pdo->prepare("SELECT id, department_id FROM sub_treasuries WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $subTreasuryId]);
    $st = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$st) {
        apiResponse(['success' => false, 'message' => 'Selected sub-treasury does not exist.'], 422);
    }
    if ((int)$st['department_id'] !== $departmentId) {
        apiResponse(['success' => false, 'message' => 'Sub-treasury does not belong to selected department.'], 422);
    }
}

if ($registerId > 0) {
    $stmt = $pdo->prepare("SELECT id, department_id, sub_treasury_id, is_active FROM registers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $registerId]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reg) {
        apiResponse(['success' => false, 'message' => 'Selected register does not exist.'], 422);
    }
    if ((int)$reg['is_active'] !== 1) {
        apiResponse(['success' => false, 'message' => 'Selected register is inactive.'], 422);
    }
    if ((int)$reg['department_id'] !== $departmentId) {
        apiResponse(['success' => false, 'message' => 'Register does not belong to selected department.'], 422);
    }
    if ($subTreasuryId > 0 && (int)$reg['sub_treasury_id'] !== $subTreasuryId) {
        apiResponse(['success' => false, 'message' => 'Register does not belong to selected sub-treasury.'], 422);
    }
}

$year = date('Y');
$prefix = "EXP-{$year}-";

$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM expense_entries
    WHERE expense_number LIKE :prefix
");
$countStmt->execute(['prefix' => $prefix . '%']);
$total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$expenseNumber = $prefix . str_pad((string)($total + 1), 6, '0', STR_PAD_LEFT);

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$createdBy = (int)($user['id'] ?? 0);
if ($createdBy <= 0) {
    apiResponse(['success' => false, 'message' => 'Authenticated user not found.'], 401);
}

$submittedBy = null;
$submittedAt = null;

if ($status === 'submitted') {
    $submittedBy = $createdBy;
    $submittedAt = date('Y-m-d H:i:s');
}

$stmt = $pdo->prepare("
    INSERT INTO expense_entries (
        uuid,
        expense_number,
        supplier_id,
        expenditure_type_id,
        department_id,
        sub_treasury_id,
        register_id,
        branch_id,
        invoice_number,
        invoice_date,
        total_amount,
        total_gst,
        currency_code,
        status,
        notes,
        created_by,
        submitted_by,
        submitted_at
    ) VALUES (
        :uuid,
        :expense_number,
        :supplier_id,
        :expenditure_type_id,
        :department_id,
        :sub_treasury_id,
        :register_id,
        :branch_id,
        :invoice_number,
        :invoice_date,
        :total_amount,
        :total_gst,
        :currency_code,
        :status,
        :notes,
        :created_by,
        :submitted_by,
        :submitted_at
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'expense_number' => $expenseNumber,
    'supplier_id' => $supplierId,
    'expenditure_type_id' => $expenditureTypeId,
    'department_id' => $departmentId,
    'sub_treasury_id' => $subTreasuryId > 0 ? $subTreasuryId : null,
    'register_id' => $registerId > 0 ? $registerId : null,
    'branch_id' => $branchId > 0 ? $branchId : null,
    'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
    'invoice_date' => $invoiceDate !== '' ? $invoiceDate : null,
    'total_amount' => number_format((float)$totalAmount, 2, '.', ''),
    'total_gst' => number_format((float)$totalGst, 2, '.', ''),
    'currency_code' => $currencyCode,
    'status' => $status,
    'notes' => $notes !== '' ? $notes : null,
    'created_by' => $createdBy,
    'submitted_by' => $submittedBy,
    'submitted_at' => $submittedAt
]);

$newId = (int)$pdo->lastInsertId();

$historyStmt = $pdo->prepare("
    INSERT INTO expense_status_history (
        expense_entry_id,
        previous_status,
        new_status,
        changed_by,
        change_reason
    ) VALUES (
        :expense_entry_id,
        :previous_status,
        :new_status,
        :changed_by,
        :change_reason
    )
");
$historyStmt->execute([
    'expense_entry_id' => $newId,
    'previous_status' => null,
    'new_status' => $status,
    'changed_by' => $createdBy,
    'change_reason' => $status === 'submitted' ? 'Created and submitted.' : 'Created as draft.'
]);

AuditLog::log($pdo, 'create', 'expense', $newId, 'Expense ' . $expenseNumber . ' created.');
apiResponse([
    'success' => true,
    'message' => 'Expense entry created successfully.',
    'id' => $newId,
    'expense_number' => $expenseNumber
], 201);