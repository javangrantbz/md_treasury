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
    $search        = trim((string)($_GET['search']       ?? ''));
    $departmentId  = (int)($_GET['department_id']        ?? 0);
    $subTreasuryId = (int)($_GET['sub_treasury_id']      ?? 0);
    $status        = trim((string)($_GET['status']       ?? ''));

    $sql = "SELECT cc.id, cc.uuid, cc.code, cc.name, cc.department_id, cc.sub_treasury_id,
                   cc.status, cc.description, cc.created_at,
                   d.name AS department_name, st.sub_treasury_name,
                   COUNT(DISTINCT ccba.bank_account_id) AS bank_count
            FROM cost_centers cc
            LEFT JOIN departments d                  ON d.id  = cc.department_id
            LEFT JOIN sub_treasuries st              ON st.id = cc.sub_treasury_id
            LEFT JOIN cost_center_bank_accounts ccba ON ccba.cost_center_id = cc.id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (cc.code LIKE :s1 OR cc.name LIKE :s2 OR d.name LIKE :s3 OR st.sub_treasury_name LIKE :s4)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like];
    }
    if ($departmentId  > 0) { $sql .= " AND cc.department_id = :department_id";     $params['department_id']   = $departmentId; }
    if ($subTreasuryId > 0) { $sql .= " AND cc.sub_treasury_id = :sub_treasury_id"; $params['sub_treasury_id'] = $subTreasuryId; }
    if ($status !== '')     { $sql .= " AND cc.status = :status";                   $params['status']          = $status; }

    $sql .= " GROUP BY cc.id ORDER BY cc.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
