<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Invalid user id.'], 422);
}

try {
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.email,
            u.phone,
            u.first_name,
            u.last_name,
            u.user_type,
            u.auth_source,
            u.mfa_enabled,
            u.status,
            u.branch_id,
            u.department_id,
            u.sub_treasury_id,
            u.profile_image_path,
            rl.id   AS role_id,
            rl.name AS role_name,
            rl.code AS role_code
        FROM users u
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN roles rl ON rl.id = ur.role_id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'User not found.'], 404);
    }

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}