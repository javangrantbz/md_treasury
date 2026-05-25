<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
Auth::requireAuth();
requireGet();

// Placeholder for NSB specific customer list
// Currently the view is pulling directly from master-data/users/list.php
apiResponse(['success' => true, 'data' => [], 'message' => 'NSB Customer API placeholder']);
