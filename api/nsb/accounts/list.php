<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));

    $sql = "SELECT a.id, a.account_number, a.customer_name, a.starting_balance,
                   a.current_balance, a.starting_date, a.status, a.branch_id,
                   a.remarks, a.created_at,
                   b.name AS branch_name, b.code AS branch_code
            FROM nsb_accounts a
            LEFT JOIN branches b ON b.id = a.branch_id
            WHERE a.deleted_at IS NULL";

    $params = [];
    if ($status !== '') {
        $sql .= " AND a.status = :status";
        $params['status'] = $status;
    }
    if ($search !== '') {
        $sql .= " AND (a.customer_name LIKE :s OR a.account_number LIKE :s)";
        $params['s'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY a.created_at DESC, a.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiResponse(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
