<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$transactionId = (int)($_GET['transaction_id'] ?? 0);

if ($transactionId <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid transaction id.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT
        ti.id,
        ti.uuid,
        ti.transaction_id,
        ti.cost_center_activity_id,
        ti.description,
        ti.quantity,
        ti.unit_amount,
        ti.line_total,
        ti.currency_code,
        ti.sort_order,
        ti.created_at,
        cca.activity_code,
        cca.activity_name
    FROM transaction_items ti
    LEFT JOIN cost_center_activities cca ON cca.id = ti.cost_center_activity_id AND cca.deleted_at IS NULL
    WHERE ti.transaction_id = :transaction_id AND ti.deleted_at IS NULL
    ORDER BY ti.sort_order ASC, ti.id ASC
");
$stmt->execute(['transaction_id' => $transactionId]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0.00;
foreach ($items as $item) {
    $total += (float)$item['line_total'];
}

apiResponse([
    'success' => true,
    'data' => [
        'items' => $items,
        'total' => number_format($total, 2, '.', '')
    ]
]);