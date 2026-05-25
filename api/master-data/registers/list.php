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
    $search        = trim((string)($_GET['search']      ?? ''));
    $departmentId  = (int)($_GET['department_id']       ?? 0);
    $subTreasuryId = (int)($_GET['sub_treasury_id']     ?? 0);
    $isActive      = trim((string)($_GET['is_active']   ?? ''));
    $sql = "SELECT r.id, r.uuid, r.department_id, r.sub_treasury_id, r.assigned_user_id,
                   r.register_code, r.register_name, r.description, r.is_active, r.created_at,
                   d.name AS department_name, st.sub_treasury_name,
                   CONCAT(u.first_name, ' ', u.last_name) AS assigned_user_name
            FROM registers r
            INNER JOIN departments d    ON d.id  = r.department_id
            LEFT  JOIN sub_treasuries st ON st.id = r.sub_treasury_id
            LEFT  JOIN users u          ON u.id  = r.assigned_user_id
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (r.register_code LIKE :s1 OR r.register_name LIKE :s2 OR d.name LIKE :s3 OR st.sub_treasury_name LIKE :s4)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like];
    }
    if ($departmentId  > 0) { $sql .= " AND r.department_id = :department_id";     $params['department_id']   = $departmentId; }
    if ($subTreasuryId > 0) { $sql .= " AND r.sub_treasury_id = :sub_treasury_id"; $params['sub_treasury_id'] = $subTreasuryId; }
    if ($isActive !== '')   { $sql .= " AND r.is_active = :is_active";             $params['is_active']       = (int)$isActive; }
    $sql .= " ORDER BY r.register_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
