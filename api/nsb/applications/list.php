<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $status = trim((string)($_GET['status'] ?? ''));
    $sql = "SELECT a.*, b.name as branch_name, b.code as branch_code, 
                   u.username as created_by_username
            FROM nsb_applications a
            JOIN branches b ON a.branch_id = b.id
            JOIN users u ON a.created_by = u.id
            WHERE 1=1";
    
    $params = [];
    if ($status !== '') {
        $sql .= " AND a.status = :status";
        $params['status'] = $status;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiResponse(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
