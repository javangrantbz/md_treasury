<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $search   = trim((string)($_GET['search']    ?? ''));
    $status   = trim((string)($_GET['status']    ?? ''));
    $branchId = (int)($_GET['branch_id']         ?? 0);

    $sql = "SELECT d.id, d.code, d.name, d.ministry_name, d.branch_id, d.status,
                   d.short_name, d.description, d.created_at, b.name AS branch_name,
                   COUNT(DISTINCT dba.id)  AS bank_account_count,
                   COUNT(DISTINCT dcc.id)  AS cost_center_count
            FROM departments d
            LEFT JOIN branches b                ON b.id   = d.branch_id AND b.deleted_at IS NULL
            LEFT JOIN department_bank_accounts dba ON dba.department_id = d.id AND dba.deleted_at IS NULL
            LEFT JOIN department_cost_centers  dcc ON dcc.department_id = d.id AND dcc.deleted_at IS NULL
            WHERE d.deleted_at IS NULL";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (d.code LIKE :s1 OR d.name LIKE :s2 OR d.ministry_name LIKE :s3)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like];
    }
    if ($status   !== '') { $sql .= " AND d.status = :status";       $params['status']    = $status; }
    if ($branchId  > 0)  { $sql .= " AND d.branch_id = :branch_id"; $params['branch_id'] = $branchId; }

    $sql .= " GROUP BY d.id, b.name ORDER BY d.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
