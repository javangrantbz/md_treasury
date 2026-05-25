<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/Auth.php';

$code = post('otp_code', '');

if ($code === '') {
    $_SESSION['flash_error'] = 'Verification code is required.';
    redirect(url('/views/auth/verify-mfa.php'));
}

$result = Auth::verifyMfa($pdo, $code);

if (!$result['success']) {
    $_SESSION['flash_error'] = $result['message'];
    redirect(url('/views/auth/verify-mfa.php'));
}

redirect($result['redirect']);
