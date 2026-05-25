<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header('Location: ' . url('views/portal/index.php'));
} else {
    header('Location: ' . url('views/auth/government-login.php'));
}
exit;
