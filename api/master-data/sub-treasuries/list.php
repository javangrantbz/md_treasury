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
    $search       = trim((string)($_GET['search']      ?? ''));
    $departmentId = (int)($_GET['department_id']       ?? 0);
    $isActive     = trim((string)($_GET['is_active']   ?? ''));
    $sql = "SELECT st.id, st.uuid, st.department_id, st.sub_treasury_code, st.sub_treasury_name,
                   st.district, st.address_line, st.contact_phone, st.contact_email,
                   st.is_active, st.created_at, d.name AS department_name
            FROM sub_treasuries st
            INNER JOIN departments d ON d.id = st.department_id
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (st.sub_treasury_code LIKE :s1 OR st.sub_treasury_name LIKE :s2 OR d.name LIKE :s3)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like];
    }
    if ($departmentId > 0) { $sql .= " AND st.department_id = :department_id"; $params['department_id'] = $departmentId; }
    if ($isActive !== '')  { $sql .= " AND st.is_active = :is_active";          $params['is_active']     = (int)$isActive; }
    $sql .= " ORDER BY st.sub_treasury_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
