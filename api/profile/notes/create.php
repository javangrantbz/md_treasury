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
    $content = trim((string)($data['content'] ?? ''));

    if ($content === '') {
        throw new Exception('Note content cannot be empty.');
    }

    $stmt = $pdo->prepare("INSERT INTO user_notes (user_id, content) VALUES (:user_id, :content)");
    $stmt->execute(['user_id' => $userId, 'content' => $content]);

    apiResponse(['success' => true, 'message' => 'Note added.', 'id' => $pdo->lastInsertId()]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
