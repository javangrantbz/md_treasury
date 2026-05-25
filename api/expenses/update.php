<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();
$user = Auth::user();

$id = (int)($data['id'] ?? 0);
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
$notes = trim((string)($data['notes'] ?? ''));
$submitNow = isset($data['submit_now']) ? (int)$data['submit_now'] : 0;

if ($id <= 0 || $supplierId <= 0 || $expenditureTypeId <= 0 || $departmentId <= 0 || $totalAmount === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$stmt = $pdo->prepare("SELECT * FROM expense_entries WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    apiResponse(['success' => false, 'message' => 'Expense entry not found.'], 404);
}

if (!in_array($current['status'], ['draft', 'submitted'], true)) {
    apiResponse(['success' => false, 'message' => 'Only draft or submitted entries can be updated here.'], 422);
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

$updatedBy = (int)($user['id'] ?? 0);
if ($updatedBy <= 0) {
    apiResponse(['success' => false, 'message' => 'Authenticated user not found.'], 401);
}

$newStatus = $current['status'];
$submittedBy = $current['submitted_by'];
$submittedAt = $current['submitted_at'];

if ($submitNow === 1 && $current['status'] === 'draft') {
    $newStatus = 'submitted';
    $submittedBy = $updatedBy;
    $submittedAt = date('Y-m-d H:i:s');
}

$stmt = $pdo->prepare("
    UPDATE expense_entries
    SET
        supplier_id = :supplier_id,
        expenditure_type_id = :expenditure_type_id,
        department_id = :department_id,
        sub_treasury_id = :sub_treasury_id,
        register_id = :register_id,
        branch_id = :branch_id,
        invoice_number = :invoice_number,
        invoice_date = :invoice_date,
        total_amount = :total_amount,
        total_gst = :total_gst,
        currency_code = :currency_code,
        status = :status,
        notes = :notes,
        submitted_by = :submitted_by,
        submitted_at = :submitted_at
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
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
    'status' => $newStatus,
    'notes' => $notes !== '' ? $notes : null,
    'submitted_by' => $submittedBy,
    'submitted_at' => $submittedAt
]);

if ($newStatus !== $current['status']) {
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
        'expense_entry_id' => $id,
        'previous_status' => $current['status'],
        'new_status' => $newStatus,
        'changed_by' => $updatedBy,
        'change_reason' => 'Updated entry and submitted.'
    ]);
}

AuditLog::log($pdo, 'update', 'expense', $id, 'Expense #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Expense entry updated successfully.'
]);