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
    $search         = trim((string)($_GET['search']         ?? ''));
    $branchId       = (int)($_GET['branch_id']              ?? 0);
    $departmentId   = (int)($_GET['department_id']          ?? 0);
    $subTreasuryId  = (int)($_GET['sub_treasury_id']        ?? 0);
    $registerId     = (int)($_GET['register_id']            ?? 0);
    $status         = trim((string)($_GET['status']         ?? 'active'));
    $userType       = trim((string)($_GET['user_type']      ?? 'internal'));
    $sql = "SELECT u.id, u.username, u.email, u.phone, u.first_name, u.last_name,
                   u.user_type, u.auth_source, u.mfa_enabled, u.status,
                   u.branch_id, u.department_id, u.sub_treasury_id,
                   b.name AS branch_name, d.name AS department_name,
                   st.sub_treasury_name,
                   rl.id AS role_id, rl.name AS role_name, rl.code AS role_code
            FROM users u
            LEFT JOIN branches b ON b.id = u.branch_id
            LEFT JOIN departments d ON d.id = u.department_id
            LEFT JOIN sub_treasuries st ON st.id = u.sub_treasury_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles rl ON rl.id = ur.role_id
            WHERE u.deleted_at IS NULL";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (u.username LIKE :s1 OR u.email LIKE :s2 OR u.first_name LIKE :s3 OR u.last_name LIKE :s4 OR u.phone LIKE :s5)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like,'s5'=>$like];
    }
    if ($branchId      > 0) { $sql .= " AND u.branch_id = :branch_id";             $params['branch_id']       = $branchId; }
    if ($departmentId  > 0) { $sql .= " AND u.department_id = :department_id";     $params['department_id']   = $departmentId; }
    if ($subTreasuryId > 0) { $sql .= " AND u.sub_treasury_id = :sub_treasury_id"; $params['sub_treasury_id'] = $subTreasuryId; }
    if ($registerId    > 0) { $sql .= " AND u.id IN (SELECT user_id FROM register_users WHERE register_id = :register_id)"; $params['register_id'] = $registerId; }
    if ($status   !== '')      { $sql .= " AND u.status = :status";                   $params['status']          = $status; }
    if ($userType !== '')      { $sql .= " AND u.user_type = :user_type";             $params['user_type']       = $userType; }
    $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
