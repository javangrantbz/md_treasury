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
    apiResponse(['success' => false, 'message' => 'Invalid register id.'], 422);
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*,
               d.name AS department_name,
               st.sub_treasury_name
        FROM registers r
        INNER JOIN departments d     ON d.id  = r.department_id
        LEFT  JOIN sub_treasuries st ON st.id = r.sub_treasury_id
        WHERE r.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_end_clean();
        apiResponse(['success' => false, 'message' => 'Register not found.'], 404);
    }

    $uStmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.username
        FROM register_users ru
        JOIN users u ON u.id = ru.user_id
        WHERE ru.register_id = :id
        ORDER BY u.first_name, u.last_name
    ");
    $uStmt->execute(['id' => $id]);
    $row['users'] = $uStmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $row]);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
