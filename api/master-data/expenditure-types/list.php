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

    $sql = "SELECT id, uuid, expenditure_code, expenditure_name, description, is_active, created_at
            FROM expenditure_types WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (expenditure_code LIKE :s1 OR expenditure_name LIKE :s2 OR description LIKE :s3)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like];
    }
    if ($isActive !== '') { $sql .= " AND is_active = :is_active"; $params['is_active'] = (int)$isActive; }

    $sql .= " ORDER BY expenditure_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
