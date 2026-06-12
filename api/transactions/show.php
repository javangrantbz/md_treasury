<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid transaction id.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.uuid,
        t.receipt_number,
        t.department_id,
        t.sub_treasury_id,
        t.register_id,
        t.cost_center_id,
        t.bank_account_id,
        t.customer_id,
        t.transaction_date,
        t.narration,
        t.status,
        t.created_at,
        d.name AS department_name,
        st.sub_treasury_name,
        r.register_name,
        cc.name AS cost_center_name,
        COALESCE(
            c.customer_name,
            TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')))
        ) AS customer_name
    FROM transactions t
    LEFT JOIN departments d ON d.id = t.department_id AND d.deleted_at IS NULL
    LEFT JOIN sub_treasuries st ON st.id = t.sub_treasury_id AND st.deleted_at IS NULL
    LEFT JOIN registers r ON r.id = t.register_id AND r.deleted_at IS NULL
    LEFT JOIN cost_centers cc ON cc.id = t.cost_center_id AND cc.deleted_at IS NULL
    LEFT JOIN customers c ON c.id = t.customer_id AND c.deleted_at IS NULL
    WHERE t.id = :id AND t.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    apiResponse([
        'success' => false,
        'message' => 'Transaction not found.'
    ], 404);
}

$statusHistory = [];

try {
    $historyStmt = $pdo->prepare("
        SELECT
            tsh.id,
            tsh.previous_status,
            tsh.new_status,
            tsh.created_at,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS changed_by_name
        FROM transaction_status_history tsh
        LEFT JOIN users u ON u.id = tsh.changed_by
        WHERE tsh.transaction_id = :transaction_id
        ORDER BY tsh.created_at ASC, tsh.id ASC
    ");
    $historyStmt->execute(['transaction_id' => $id]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $statusHistory = [];
}

apiResponse([
    'success' => true,
    'data' => [
        'transaction' => $transaction,
        'status_history' => $statusHistory
    ]
]);