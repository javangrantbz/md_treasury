<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();

$transactionId = (int)($data['transaction_id'] ?? 0);
$costCenterActivityId = (int)($data['cost_center_activity_id'] ?? 0);
$description = trim((string)($data['description'] ?? ''));
$quantity = (float)($data['quantity'] ?? 1);
$unitAmount = (float)($data['unit_amount'] ?? 0);
$currencyCode = strtoupper(trim((string)($data['currency_code'] ?? 'BZD')));
$sortOrder = (int)($data['sort_order'] ?? 1);

if ($transactionId <= 0 || $description === '') {
    apiResponse([
        'success' => false,
        'message' => 'Transaction and description are required.'
    ], 422);
}

if ($quantity <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Quantity must be greater than zero.'
    ], 422);
}

if ($unitAmount < 0) {
    apiResponse([
        'success' => false,
        'message' => 'Unit amount cannot be negative.'
    ], 422);
}

$stmt = $pdo->prepare("SELECT id FROM transactions WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $transactionId]);
if (!$stmt->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Transaction not found.'
    ], 404);
}

if ($costCenterActivityId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM cost_center_activities WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $costCenterActivityId]);
    if (!$stmt->fetch()) {
        apiResponse([
            'success' => false,
            'message' => 'Cost center activity not found.'
        ], 422);
    }
}

$lineTotal = $quantity * $unitAmount;

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$stmt = $pdo->prepare("
    INSERT INTO transaction_items (
        uuid,
        transaction_id,
        cost_center_activity_id,
        description,
        quantity,
        unit_amount,
        line_total,
        currency_code,
        sort_order
    ) VALUES (
        :uuid,
        :transaction_id,
        :cost_center_activity_id,
        :description,
        :quantity,
        :unit_amount,
        :line_total,
        :currency_code,
        :sort_order
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'transaction_id' => $transactionId,
    'cost_center_activity_id' => $costCenterActivityId > 0 ? $costCenterActivityId : null,
    'description' => $description,
    'quantity' => number_format($quantity, 2, '.', ''),
    'unit_amount' => number_format($unitAmount, 2, '.', ''),
    'line_total' => number_format($lineTotal, 2, '.', ''),
    'currency_code' => $currencyCode !== '' ? $currencyCode : 'BZD',
    'sort_order' => $sortOrder > 0 ? $sortOrder : 1,
]);

$newItemId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'transaction_item', $newItemId, 'Item added to transaction #' . $transactionId . ': ' . $description);
apiResponse([
    'success' => true,
    'message' => 'Transaction item added successfully.',
    'id' => $newItemId
], 201);