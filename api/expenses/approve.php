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
$reason = trim((string)($data['reason'] ?? 'Approved.'));

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid expense id.'], 422);
}

$stmt = $pdo->prepare("SELECT id, status, created_by, submitted_by FROM expense_entries WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Expense not found.'], 404);
}

if ($row['status'] !== 'submitted') {
    apiResponse(['success' => false, 'message' => 'Only submitted entries can be approved.'], 422);
}

$userId = (int)($user['id'] ?? 0);

// Four-Eyes Principle: Approver cannot be the Creator or Submitter
if ($userId === (int)$row['created_by'] || $userId === (int)($row['submitted_by'] ?? 0)) {
    apiResponse([
        'success' => false,
        'message' => 'Security Violation: Dual control required. You cannot approve an expense you created or submitted.'
    ], 403);
}

$pdo->prepare("
    UPDATE expense_entries
    SET
        status = 'approved',
        approved_by = :approved_by,
        approved_at = NOW()
    WHERE id = :id
")->execute([
    'approved_by' => $userId,
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
    'previous_status' => 'submitted',
    'new_status' => 'approved',
    'changed_by' => $userId,
    'change_reason' => $reason
]);

AuditLog::log($pdo, 'approve', 'expense', $id, 'Expense #' . $id . ' approved.');
apiResponse([
    'success' => true,
    'message' => 'Expense approved successfully.'
]);