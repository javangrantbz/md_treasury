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
$reason = trim((string)($data['reason'] ?? 'Marked as paid.'));

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid expense id.'], 422);
}

$stmt = $pdo->prepare("SELECT id, status FROM expense_entries WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Expense not found.'], 404);
}

if ($row['status'] !== 'approved') {
    apiResponse(['success' => false, 'message' => 'Only approved entries can be paid.'], 422);
}

$userId = (int)($user['id'] ?? 0);

$pdo->prepare("
    UPDATE expense_entries
    SET
        status = 'paid',
        paid_by = :paid_by,
        paid_at = NOW()
    WHERE id = :id
")->execute([
    'paid_by' => $userId,
    'id' => $id
]);

$pdo->prepare("
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
")->execute([
    'expense_entry_id' => $id,
    'previous_status' => 'approved',
    'new_status' => 'paid',
    'changed_by' => $userId,
    'change_reason' => $reason
]);

AuditLog::log($pdo, 'pay', 'expense', $id, 'Expense #' . $id . ' marked as paid.');
apiResponse([
    'success' => true,
    'message' => 'Expense marked as paid.'
]);