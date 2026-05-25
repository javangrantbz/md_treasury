<?php
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/MicrosoftAuth.php';
require_once __DIR__ . '/../../config/database.php';

// Microsoft returns ?error=... when the user cancels or an error occurs
if (!empty($_GET['error'])) {
    $_SESSION['flash_error'] = 'Microsoft sign-in was cancelled or failed. Please try again.';
    redirect(url('views/auth/government-login.php'));
}

$code  = get('code', '');
$state = get('state', '');

if (empty($code)) {
    $_SESSION['flash_error'] = 'Invalid authentication response from Microsoft.';
    redirect(url('views/auth/government-login.php'));
}

$redirectUri = abs_url('views/auth/microsoft-callback.php');
$result = MicrosoftAuth::handleCallback($pdo, $code, $state, $redirectUri);

if ($result['success']) {
    redirect($result['redirect']);
}

$_SESSION['flash_error'] = $result['message'];
redirect(url('views/auth/government-login.php'));
