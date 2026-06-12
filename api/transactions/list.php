<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database-pos.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$sql = "
    SELECT
        t.id,
        t.receipt_number,
        t.transaction_date,
        t.status,
        d.name AS department_name,
        COALESCE(c.customer_name, CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name
    FROM transactions t
    LEFT JOIN departments d ON d.id = t.department_id AND d.deleted_at IS NULL
    LEFT JOIN customers c ON c.id = t.customer_id AND c.deleted_at IS NULL
    WHERE t.deleted_at IS NULL
    ORDER BY t.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

apiResponse([
    'success' => true,
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);