<?php

declare(strict_types=1);
require_once __DIR__ . '/../../includes/Auth.php';

$login = post('login', '');
$password = post('password', '');

if ($login === '' || $password === '') {
    $_SESSION['flash_error'] = 'Username/email and password are required.';
    redirect(url('/views/auth/government-login.php'));
}

$result = Auth::attemptLogin($pdo, $login, $password);

if (!$result['success']) {
    $_SESSION['flash_error'] = $result['message'];
    redirect(url('/views/auth/government-login.php'));
}

redirect($result['redirect']);
