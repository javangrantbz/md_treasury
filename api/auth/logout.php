<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/Auth.php';
$_logUser = Auth::user();
if ($_logUser) {
    try {
        AuditLog::log($pdo, 'logout', 'user', (int)$_logUser['id'], 'User logged out.', (int)$_logUser['id']);
    } catch (Throwable $e) {}
}
Auth::logout();
redirect(url('/views/auth/government-login.php'));
