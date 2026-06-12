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
            s.shift_id,
            s.started_at AS shift_start,
            s.ended_at   AS shift_end,
            COUNT(t.id)  AS transaction_count,
            COALESCE(SUM(t.total_amount), 0) AS total_amount,
            CONCAT(u.first_name, ' ', u.last_name) AS cashier_name
        FROM pos_shifts s
        LEFT JOIN pos_transactions t ON t.shift_id = s.shift_id AND t.status = 'completed' AND t.deleted_at IS NULL
        LEFT JOIN users u ON u.id = s.uid AND u.deleted_at IS NULL
        WHERE s.deleted_at IS NULL
    ";

    $params = [];

    if ($dateFrom !== '') {
        $sql .= " AND DATE(s.started_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $sql .= " AND DATE(s.started_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    $sql .= " GROUP BY s.shift_id, s.started_at, s.ended_at, u.first_name, u.last_name ORDER BY s.started_at DESC";

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
