<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to']   ?? ''));

try {
    $sql = "
        SELECT
            t.shift_id,
            MIN(t.completed_at) as shift_start,
            MAX(t.completed_at) as shift_end,
            COUNT(t.id) as transaction_count,
            SUM(t.total_amount) as total_amount,
            CONCAT(u.first_name, ' ', u.last_name) AS cashier_name
        FROM transactions t
        LEFT JOIN users u ON u.id = t.uid
        WHERE t.status = 'completed'
    ";

    $params = [];

    if ($dateFrom !== '') {
        $sql .= " AND DATE(t.completed_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $sql .= " AND DATE(t.completed_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    $sql .= " GROUP BY t.shift_id, u.first_name, u.last_name ORDER BY shift_end DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiResponse([
        'success' => true,
        'data'    => $rows,
    ]);
} catch (Throwable $e) {
    apiResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
