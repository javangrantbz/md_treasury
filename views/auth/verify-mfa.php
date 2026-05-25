<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';

if (empty($_SESSION['pending_user_id'])) {
    redirect(url('/views/auth/government-login.php'));
}

$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verify Identity - Treasury Revenue System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo url('assets/css/app.css'); ?>" rel="stylesheet">
    <style>
        .otp-input { text-align: center; font-size: 1.6rem; letter-spacing: .35em; width: 100%; padding: .5rem; }
        .hint { font-size: .82rem; color: #6b7280; margin-top: .5rem; }
    </style>
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Verify Your Identity</h1>
    <p>Open your authenticator app and enter the 6-digit code.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo url('api/auth/verify-mfa.php'); ?>">
        <label for="otp_code">Authentication Code</label>
        <input type="text" id="otp_code" name="otp_code" class="otp-input"
               maxlength="6" inputmode="numeric" autocomplete="one-time-code"
               placeholder="000 000" required autofocus>
        <p class="hint">Codes refresh every 30 seconds. Enter the current code shown in your app.</p>
        <button type="submit" style="margin-top:1rem;width:100%">Verify</button>
    </form>
</div>
</body>
</html>
