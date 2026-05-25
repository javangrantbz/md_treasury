<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Totp.php';

$pendingUserId = $_SESSION['pending_user_id'] ?? null;
$secret        = $_SESSION['pending_totp_secret'] ?? null;

if (!$pendingUserId || !$secret) {
    redirect(url('/views/auth/government-login.php'));
}

$code = post('otp_code', '');

if ($code === '') {
    $_SESSION['flash_error'] = 'Please enter the 6-digit code from your authenticator app.';
    redirect(url('views/auth/mfa-setup.php'));
}

if (!Totp::verify($secret, $code)) {
    $_SESSION['flash_error'] = 'Code did not match. Make sure you scanned the correct QR code and try again.';
    redirect(url('views/auth/mfa-setup.php'));
}

// Mark TOTP as confirmed
$stmt = $pdo->prepare("UPDATE users SET mfa_totp_confirmed_at = NOW() WHERE id = :id");
$stmt->execute(['id' => $pendingUserId]);

unset($_SESSION['pending_totp_secret']);

Auth::completeLogin($pdo, (int) $pendingUserId);

redirect(url('views/portal/index.php'));
