<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid expense id.'], 422);
}

$entryStmt = $pdo->prepare("
    SELECT
        ee.*,
        s.supplier_name,
        et.expenditure_name,
        d.name AS department_name,
        st.sub_treasury_name,
        r.register_name,
        b.name AS branch_name,
        u1.first_name AS created_by_first_name,
        u1.last_name AS created_by_last_name,
        u2.first_name AS submitted_by_first_name,
        u2.last_name AS submitted_by_last_name,
        u3.first_name AS approved_by_first_name,
        u3.last_name AS approved_by_last_name,
        u4.first_name AS paid_by_first_name,
        u4.last_name AS paid_by_last_name
    FROM expense_entries ee
    INNER JOIN suppliers s ON s.id = ee.supplier_id
    INNER JOIN expenditure_types et ON et.id = ee.expenditure_type_id
    INNER JOIN departments d ON d.id = ee.department_id
    LEFT JOIN sub_treasuries st ON st.id = ee.sub_treasury_id
    LEFT JOIN registers r ON r.id = ee.register_id
    LEFT JOIN branches b ON b.id = ee.branch_id
    LEFT JOIN users u1 ON u1.id = ee.created_by
    LEFT JOIN users u2 ON u2.id = ee.submitted_by
    LEFT JOIN users u3 ON u3.id = ee.approved_by
    LEFT JOIN users u4 ON u4.id = ee.paid_by
    WHERE ee.id = :id
    LIMIT 1
");
$entryStmt->execute(['id' => $id]);
$entry = $entryStmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    apiResponse(['success' => false, 'message' => 'Expense entry not found.'], 404);
}

$historyStmt = $pdo->prepare("
    SELECT
        esh.id,
        esh.previous_status,
        esh.new_status,
        esh.change_reason,
        esh.created_at,
        u.first_name,
        u.last_name,
        u.username
    FROM expense_status_history esh
    INNER JOIN users u ON u.id = esh.changed_by
    WHERE esh.expense_entry_id = :expense_entry_id
    ORDER BY esh.created_at ASC, esh.id ASC
");
$historyStmt->execute(['expense_entry_id' => $id]);

apiResponse([
    'success' => true,
    'data' => [
        'entry' => $entry,
        'status_history' => $historyStmt->fetchAll(PDO::FETCH_ASSOC)
    ]
]);