<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$departmentId = (int)($_GET['department_id'] ?? 0);
$subTreasuryId = (int)($_GET['sub_treasury_id'] ?? 0);
$registerId = (int)($_GET['register_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$expenditureTypeId = (int)($_GET['expenditure_type_id'] ?? 0);

$sql = "
    SELECT
        ee.id,
        ee.uuid,
        ee.expense_number,
        ee.supplier_id,
        ee.expenditure_type_id,
        ee.department_id,
        ee.sub_treasury_id,
        ee.register_id,
        ee.branch_id,
        ee.invoice_number,
        ee.invoice_date,
        ee.total_amount,
        ee.total_gst,
        ee.currency_code,
        ee.status,
        ee.notes,
        ee.created_at,
        s.supplier_name,
        et.expenditure_name,
        d.name AS department_name,
        st.sub_treasury_name,
        r.register_name,
        b.name AS branch_name
    FROM expense_entries ee
    INNER JOIN suppliers s ON s.id = ee.supplier_id
    INNER JOIN expenditure_types et ON et.id = ee.expenditure_type_id
    INNER JOIN departments d ON d.id = ee.department_id
    LEFT JOIN sub_treasuries st ON st.id = ee.sub_treasury_id
    LEFT JOIN registers r ON r.id = ee.register_id
    LEFT JOIN branches b ON b.id = ee.branch_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (
        ee.expense_number LIKE :search
        OR ee.invoice_number LIKE :search
        OR s.supplier_name LIKE :search
        OR et.expenditure_name LIKE :search
        OR d.name LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql .= " AND ee.status = :status";
    $params['status'] = $status;
}

if ($departmentId > 0) {
    $sql .= " AND ee.department_id = :department_id";
    $params['department_id'] = $departmentId;
}

if ($subTreasuryId > 0) {
    $sql .= " AND ee.sub_treasury_id = :sub_treasury_id";
    $params['sub_treasury_id'] = $subTreasuryId;
}

if ($registerId > 0) {
    $sql .= " AND ee.register_id = :register_id";
    $params['register_id'] = $registerId;
}

if ($supplierId > 0) {
    $sql .= " AND ee.supplier_id = :supplier_id";
    $params['supplier_id'] = $supplierId;
}

if ($expenditureTypeId > 0) {
    $sql .= " AND ee.expenditure_type_id = :expenditure_type_id";
    $params['expenditure_type_id'] = $expenditureTypeId;
}

$sql .= " ORDER BY ee.created_at DESC, ee.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

apiResponse([
    'success' => true,
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);