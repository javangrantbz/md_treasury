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
$reason = trim((string)($data['reason'] ?? 'Submitted for review.'));

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid expense id.'], 422);
}

$stmt = $pdo->prepare("SELECT id, status FROM expense_entries WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Expense entry not found.'], 404);
}

if ($row['status'] !== 'draft') {
    apiResponse(['success' => false, 'message' => 'Only draft entries can be submitted.'], 422);
}

$userId = (int)($user['id'] ?? 0);

$pdo->prepare("
    UPDATE expense_entries
    SET
        status = 'submitted',
        submitted_by = :submitted_by,
        submitted_at = NOW()
    WHERE id = :id
")->execute([
    'submitted_by' => $userId,
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
    'previous_status' => 'draft',
    'new_status' => 'submitted',
    'changed_by' => $userId,
    'change_reason' => $reason !== '' ? $reason : null
]);

AuditLog::log($pdo, 'submit', 'expense', $id, 'Expense #' . $id . ' submitted.');
apiResponse([
    'success' => true,
    'message' => 'Expense entry submitted successfully.'
]);