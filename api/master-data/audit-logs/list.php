<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';
Auth::requireAuth();
Rbac::require($pdo, 'master_data.audit_logs.view');
requireGet();
try {
    $search     = trim((string)($_GET['search']      ?? ''));
    $action     = trim((string)($_GET['action']      ?? ''));
    $entityType = trim((string)($_GET['entity_type'] ?? ''));
    $userId     = (int)($_GET['user_id']             ?? 0);
    $dateFrom   = trim((string)($_GET['date_from']   ?? ''));
    $dateTo     = trim((string)($_GET['date_to']     ?? ''));

    $sql = "SELECT al.id, al.event_action AS action, al.event_type, al.entity_type, al.entity_id,
                   al.description, al.old_values, al.new_values, 
                   al.ip_address, al.user_agent, al.ua_browser, al.ua_os, al.ua_device,
                   al.created_at,
                   u.id AS user_id,
                   CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name,
                   u.username
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (al.description LIKE :search 
                   OR al.entity_type LIKE :search 
                   OR al.event_action LIKE :search 
                   OR al.ua_browser LIKE :search 
                   OR al.ua_os LIKE :search 
                   OR al.ua_device LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    if ($action !== '') {
        $sql .= " AND al.event_action = :action";
        $params['action'] = $action;
    }
    if ($entityType !== '') {
        $sql .= " AND al.entity_type = :entity_type";
        $params['entity_type'] = $entityType;
    }
    if ($userId > 0) {
        $sql .= " AND al.user_id = :user_id";
        $params['user_id'] = $userId;
    }
    if ($dateFrom !== '') {
        $sql .= " AND DATE(al.created_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= " AND DATE(al.created_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    $sql .= " ORDER BY al.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch distinct actions and entity types for filter dropdowns
    $actionsStmt = $pdo->query("SELECT DISTINCT event_action FROM audit_logs ORDER BY event_action ASC");
    $typesStmt   = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC");

    ob_end_clean();
    apiResponse([
        'success'      => true,
        'data'         => $rows,
        'actions'      => $actionsStmt->fetchAll(PDO::FETCH_COLUMN),
        'entity_types' => $typesStmt->fetchAll(PDO::FETCH_COLUMN),
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
