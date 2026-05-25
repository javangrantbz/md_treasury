<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requirePost();

try {
    $userId = Auth::userId();
    $data = requestData();
    $noteId = (int)($data['id'] ?? 0);

    if ($noteId <= 0) throw new Exception('Invalid note ID.');

    $stmt = $pdo->prepare("DELETE FROM user_notes WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $noteId, 'user_id' => $userId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Note not found or already deleted.');
    }

    apiResponse(['success' => true, 'message' => 'Note deleted.']);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
