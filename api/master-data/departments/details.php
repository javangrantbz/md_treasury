<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid department id.'
    ], 422);
}

/**
 * Department header
 */
$deptStmt = $pdo->prepare("
    SELECT
        d.id,
        d.code,
        d.name,
        d.ministry_name,
        d.branch_id,
        d.status,
        d.short_name,
        d.description,
        d.created_at,
        b.name AS branch_name,
        b.code AS branch_code
    FROM departments d
    LEFT JOIN branches b ON b.id = d.branch_id
    WHERE d.id = :id
    LIMIT 1
");
$deptStmt->execute(['id' => $id]);
$department = $deptStmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    apiResponse([
        'success' => false,
        'message' => 'Department not found.'
    ], 404);
}

/**
 * Department bank accounts
 */
$bankStmt = $pdo->prepare("
    SELECT
        dba.id,
        dba.department_id,
        dba.bank_account_id,
        dba.is_default,
        dba.status,
        ba.id AS account_id,
        ba.uuid,
        ba.bank_name,
        ba.account_name,
        ba.account_number,
        ba.account_masked,
        ba.currency_code,
        ba.item_number,
        ba.sof_number,
        ba.branch_name,
        ba.account_type
    FROM department_bank_accounts dba
    INNER JOIN bank_accounts ba ON ba.id = dba.bank_account_id
    WHERE dba.department_id = :id
      AND dba.status = 'active'
    ORDER BY ba.bank_name ASC, ba.account_name ASC
");
$bankStmt->execute(['id' => $id]);
$bankAccounts = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Department cost centers
 */
$ccStmt = $pdo->prepare("
    SELECT
        cc.id,
        cc.uuid,
        cc.code,
        cc.name,
        cc.status,
        cc.description,
        cc.created_at,
        st.sub_treasury_name
    FROM department_cost_centers dcc
    INNER JOIN cost_centers cc ON cc.id = dcc.cost_center_id
    LEFT JOIN sub_treasuries st ON st.id = cc.sub_treasury_id
    WHERE dcc.department_id = :id
    ORDER BY cc.name ASC
");
$ccStmt->execute(['id' => $id]);
$costCenters = $ccStmt->fetchAll(PDO::FETCH_ASSOC);

apiResponse([
    'success' => true,
    'data' => [
        'department' => $department,
        'bank_accounts' => $bankAccounts,
        'cost_centers' => $costCenters,
    ]
]);