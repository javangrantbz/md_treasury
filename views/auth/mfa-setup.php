<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Totp.php';

$pendingUserId = $_SESSION['pending_user_id'] ?? null;
if (!$pendingUserId) {
    redirect(url('/views/auth/government-login.php'));
}

// Load pending secret; if none, generate one first
$secret = $_SESSION['pending_totp_secret'] ?? null;
if (!$secret) {
    redirect(url('api/auth/mfa-enroll.php'));
}

$stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $pendingUserId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
$label  = $u['email'] ?? $u['username'] ?? 'user';
$issuer = 'Treasury Revenue System';

$otpauthUri = Totp::otpauthUri($secret, $label, $issuer);

$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Set Up Authenticator - Treasury Revenue System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo url('assets/css/app.css'); ?>" rel="stylesheet">
    <style>
        .step-list { list-style: none; padding: 0; margin: 1.25rem 0; counter-reset: step; }
        .step-list li { counter-increment: step; padding: .6rem 0 .6rem 2.5rem; position: relative; border-bottom: 1px solid #f0f0f0; }
        .step-list li::before { content: counter(step); position: absolute; left: 0; top: .6rem; width: 1.6rem; height: 1.6rem; background: #1a56db; color: #fff; border-radius: 50%; font-size: .8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        #qrcode { display: flex; justify-content: center; margin: 1rem 0; }
        #qrcode canvas { border: 4px solid #e5e7eb; border-radius: 8px; }
        .secret-key { font-family: monospace; font-size: .95rem; letter-spacing: .2em; background: #f9fafb; border: 1px solid #d1d5db; padding: .5rem 1rem; border-radius: 6px; word-break: break-all; margin: .4rem 0 1rem; display: block; }
        .note { font-size: .8rem; color: #6b7280; margin: .25rem 0; }
        .regen-link { font-size: .82rem; color: #6b7280; margin-top: 1.5rem; text-align: center; }
    </style>
</head>
<body class="auth-page">
<div class="auth-card" style="max-width:460px">
    <h1>Set Up Two-Factor Authentication</h1>
    <p class="note" style="font-size:.9rem;color:#374151">
        Use an authenticator app — Google Authenticator, Authy, or Microsoft Authenticator.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <ol class="step-list">
        <li>Open your authenticator app and tap <strong>+</strong> / <em>Add account</em>.</li>
        <li>
            <strong>Scan this QR code:</strong>
            <div id="qrcode"></div>
            <p class="note">Can't scan? Enter this key manually in your app:</p>
            <code class="secret-key"><?php echo h($secret); ?></code>
        </li>
        <li>
            Enter the <strong>6-digit code</strong> your app shows to confirm:
            <form method="post" action="<?php echo url('api/auth/mfa-confirm.php'); ?>" style="margin-top:.75rem">
                <input type="text" name="otp_code" maxlength="6" inputmode="numeric"
                       autocomplete="one-time-code" placeholder="000 000"
                       required autofocus
                       style="text-align:center;font-size:1.4rem;letter-spacing:.3em;width:100%;padding:.5rem">
                <button type="submit" style="margin-top:.75rem;width:100%">Confirm &amp; Activate</button>
            </form>
        </li>
    </ol>

    <p class="regen-link">
        QR code not working? <a href="<?php echo url('api/auth/mfa-enroll.php'); ?>">Generate a new one</a>
    </p>
</div>

<script src="<?php echo url('assets/js/qrcode.min.js'); ?>"></script>
<script>
(function () {
    var uri = <?php echo json_encode($otpauthUri); ?>;
    var el  = document.getElementById('qrcode');

    if (typeof QRCode !== 'undefined') {
        new QRCode(el, { text: uri, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    } else {
        // Fallback: show a deep-link button (works on mobile)
        el.innerHTML = '<a href="' + encodeURI(uri) + '" style="display:inline-block;padding:.5rem 1rem;background:#1a56db;color:#fff;border-radius:6px;text-decoration:none">Open in Authenticator App</a>'
                     + '<p style="font-size:.8rem;color:#c00;margin-top:.5rem">QR code could not load — use the manual key above or tap the button on your phone.</p>';
    }
})();
</script>
</body>
</html>
