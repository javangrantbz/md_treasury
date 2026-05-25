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

$firstName = trim((string)($data['first_name'] ?? ''));
$lastName  = trim((string)($data['last_name']  ?? ''));
$phone     = trim((string)($data['phone']       ?? ''));

if ($firstName === '' || $lastName === '') {
    jsonResponse(['success' => false, 'message' => 'First name and last name are required.'], 422);
}

$stmt = $pdo->prepare("
    UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone, updated_by = :updated_by
    WHERE id = :id
");
$stmt->execute([
    'first_name'  => $firstName,
    'last_name'   => $lastName,
    'phone'       => $phone !== '' ? $phone : null,
    'updated_by'  => $userId,
    'id'          => $userId,
]);

// Refresh session with updated name
$_SESSION['user']['first_name'] = $firstName;
$_SESSION['user']['last_name']  = $lastName;
$_SESSION['user']['phone']      = $phone !== '' ? $phone : null;

AuditLog::log($pdo, 'update', 'user', $userId, 'Profile updated.');
jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
