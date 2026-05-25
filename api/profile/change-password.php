<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::requireAuth();
requirePost();

$authUser = Auth::user();
$userId   = (int)$authUser['id'];
$data     = requestData();

$current  = (string)($data['current_password']  ?? '');
$new      = (string)($data['new_password']       ?? '');
$confirm  = (string)($data['confirm_password']   ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    jsonResponse(['success' => false, 'message' => 'All password fields are required.'], 422);
}
if (strlen($new) < 8) {
    jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters.'], 422);
}
if ($new !== $confirm) {
    jsonResponse(['success' => false, 'message' => 'New passwords do not match.'], 422);
}

$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $userId]);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Current password is incorrect.'], 422);
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = :hash, updated_by = :uid WHERE id = :id")
    ->execute(['hash' => $hash, 'uid' => $userId, 'id' => $userId]);

AuditLog::log($pdo, 'update', 'user', $userId, 'Password changed.');
jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
