<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
Auth::requireAuth();

apiResponse(['success' => true, 'data' => [], 'message' => 'Placeholder for Card List API']);
