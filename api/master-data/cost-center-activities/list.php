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
    $isActive = trim((string)($_GET['is_active'] ?? ''));

    $sql = "SELECT cca.id, cca.uuid, cca.cost_center_id, cca.activity_code, cca.activity_name,
                   cca.revenue_code, cca.description, cca.default_amount, cca.is_active, cca.created_at,
                   cc.code AS cost_center_code, cc.name AS cost_center_name
            FROM cost_center_activities cca
            INNER JOIN cost_centers cc ON cc.id = cca.cost_center_id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (cca.activity_code LIKE :s1 OR cca.activity_name LIKE :s2 OR cca.revenue_code LIKE :s3 OR cc.name LIKE :s4)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like];
    }
    if ($isActive !== '') { $sql .= " AND cca.is_active = :is_active"; $params['is_active'] = (int)$isActive; }

    $sql .= " ORDER BY cca.activity_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
