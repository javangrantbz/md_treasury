<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Totp.php';

$pendingUserId = $_SESSION['pending_user_id'] ?? null;
if (!$pendingUserId) {
    redirect(url('/views/auth/government-login.php'));
}

// Generate a fresh secret and store it (unconfirmed)
$secret = Totp::generateSecret();

$stmt = $pdo->prepare("UPDATE users SET mfa_totp_secret = :secret, mfa_totp_confirmed_at = NULL WHERE id = :id");
$stmt->execute(['secret' => $secret, 'id' => $pendingUserId]);

$_SESSION['pending_totp_secret'] = $secret;

redirect(url('views/auth/mfa-setup.php'));
