<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$search   = trim((string)($_GET['search']    ?? ''));
$status   = trim((string)($_GET['status']    ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to']   ?? ''));

$sql = "
    SELECT
        t.id,
        t.uuid,
        t.shift_id,
        t.uid,
        t.customer_id,
        t.customer_name,
        t.dept_id,
        t.dept_name,
        t.total_amount,
        t.items,
        t.status,
        t.completed_at,
        t.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS cashier_name
    FROM transactions t
    LEFT JOIN users u ON u.id = t.uid AND u.deleted_at IS NULL
    WHERE t.deleted_at IS NULL
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

// Parse items JSON and compute summary fields
foreach ($rows as &$row) {
    $items = [];
    if (!empty($row['items'])) {
        $decoded = json_decode($row['items'], true);
        if (is_array($decoded)) {
            $items = $decoded;
        }
    }
    unset($row['items']); // don't send raw JSON to frontend

    $methods = [];
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

    $row['item_count']           = count($items);
    $row['payment_methods']      = $methods;
    $row['activities_summary']   = $activities;
}
unset($row);

apiResponse([
    'success' => true,
    'data'    => $rows,
]);
