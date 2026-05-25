<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$search   = trim((string)($_GET['search']    ?? ''));
$status   = trim((string)($_GET['status']    ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to']   ?? ''));

try {
    $sql = "
        SELECT
            t.id,
            t.uuid,
            t.shift_id,
            t.uid,
            t.customer_id,
            t.customer_name,
            t.dept_id,
            COALESCE(t.dept_name, d.name) AS dept_name,
            t.total_amount,
            t.items,
            t.status,
            t.completed_at,
            t.created_at
        FROM transactions t
        LEFT JOIN departments d ON d.id = t.dept_id
        WHERE 1=1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            t.customer_name LIKE :search
            OR t.dept_name  LIKE :search
            OR t.shift_id   LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    if ($status !== '') {
        $sql .= " AND t.status = :status";
        $params['status'] = $status;
    }

    if ($dateFrom !== '') {
        $sql .= " AND DATE(t.completed_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $sql .= " AND DATE(t.completed_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    $sql .= " ORDER BY t.completed_at DESC, t.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $items = [];
        if (!empty($row['items'])) {
            $decoded = json_decode($row['items'], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        unset($row['items']);

        $methods    = [];
        $activities = [];
        foreach ($items as $item) {
            $m = $item['payment_method'] ?? null;
            if ($m && !in_array($m, $methods, true)) {
                $methods[] = $m;
            }
            $a = $item['activity_name'] ?? null;
            if ($a && !in_array($a, $activities, true)) {
                $activities[] = $a;
            }
        }

        $row['item_count']         = count($items);
        $row['payment_methods']    = $methods;
        $row['activities_summary'] = $activities;
    }
    unset($row);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $rows]);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
