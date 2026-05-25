<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/Auth.php';

if (!Auth::check()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

jsonResponse([
    'success' => true,
    'user' => Auth::user(),
]);
