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
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));

    $sql    = "SELECT id, code, name, district, address_line, status, created_at FROM branches WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (code LIKE :s1 OR name LIKE :s2 OR district LIKE :s3 OR address_line LIKE :s4)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like];
    }
    if ($status !== '') { $sql .= " AND status = :status"; $params['status'] = $status; }

    $sql .= " ORDER BY name ASC";
    $stmt  = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
