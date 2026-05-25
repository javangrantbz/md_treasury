<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
require_once __DIR__ . '/../../../includes/Rbac.php';
Rbac::require($pdo, 'master_data.roles.manage');
requireGet();

try {
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? 'active'));

    $sql = "SELECT id, code, name, description, status, created_at FROM roles WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (code LIKE :s1 OR name LIKE :s2 OR description LIKE :s3)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like];
    }
    if ($status !== '') { $sql .= " AND status = :status"; $params['status'] = $status; }

    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
