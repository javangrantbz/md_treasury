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
    $content = trim((string)($data['content'] ?? ''));

    if ($noteId <= 0) throw new Exception('Invalid note ID.');
    if ($content === '') throw new Exception('Note content cannot be empty.');

    $stmt = $pdo->prepare("UPDATE user_notes SET content = :content WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['content' => $content, 'id' => $noteId, 'user_id' => $userId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Note not found or no changes made.');
    }

    apiResponse(['success' => true, 'message' => 'Note updated.']);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
