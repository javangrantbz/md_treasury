<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $userId = Auth::userId();
    $stmt = $pdo->prepare("SELECT id, content, created_at FROM user_notes WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute(['user_id' => $userId]);
    apiResponse(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
