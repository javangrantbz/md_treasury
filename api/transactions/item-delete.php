<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requirePost();

$data = requestData();
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid item id.'
    ], 422);
}

$stmt = $pdo->prepare("SELECT id FROM transaction_items WHERE id = :id AND deleted_at IS NULL LIMIT 1");
$stmt->execute(['id' => $id]);

if (!$stmt->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Transaction item not found.'
    ], 404);
}

$pdo->prepare("UPDATE transaction_items SET deleted_at = NOW() WHERE id = :id")->execute(['id' => $id]);

AuditLog::log($pdo, 'delete', 'transaction_item', $id, 'Transaction item #' . $id . ' deleted.');
apiResponse([
    'success' => true,
    'message' => 'Transaction item deleted successfully.'
]);