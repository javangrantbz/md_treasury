<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();

$id = (int)($data['id'] ?? 0);
$costCenterActivityId = (int)($data['cost_center_activity_id'] ?? 0);
$description = trim((string)($data['description'] ?? ''));
$quantity = (float)($data['quantity'] ?? 1);
$unitAmount = (float)($data['unit_amount'] ?? 0);
$currencyCode = strtoupper(trim((string)($data['currency_code'] ?? 'BZD')));
$sortOrder = (int)($data['sort_order'] ?? 1);

if ($id <= 0 || $description === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
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

$stmt = $pdo->prepare("SELECT id FROM transaction_items WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
if (!$stmt->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Transaction item not found.'
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

$stmt = $pdo->prepare("
    UPDATE transaction_items
    SET
        cost_center_activity_id = :cost_center_activity_id,
        description = :description,
        quantity = :quantity,
        unit_amount = :unit_amount,
        line_total = :line_total,
        currency_code = :currency_code,
        sort_order = :sort_order
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
    'cost_center_activity_id' => $costCenterActivityId > 0 ? $costCenterActivityId : null,
    'description' => $description,
    'quantity' => number_format($quantity, 2, '.', ''),
    'unit_amount' => number_format($unitAmount, 2, '.', ''),
    'line_total' => number_format($lineTotal, 2, '.', ''),
    'currency_code' => $currencyCode !== '' ? $currencyCode : 'BZD',
    'sort_order' => $sortOrder > 0 ? $sortOrder : 1,
]);

AuditLog::log($pdo, 'update', 'transaction_item', $id, 'Transaction item #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Transaction item updated successfully.'
]);