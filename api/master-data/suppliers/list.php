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
    $sql = "SELECT id, uuid, supplier_name, contact_name, tax_id, email, phone,
                   address_line_1, address_line_2, district, country, notes, is_active, created_at
            FROM suppliers WHERE deleted_at IS NULL";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (supplier_name LIKE :s1 OR contact_name LIKE :s2 OR tax_id LIKE :s3 OR email LIKE :s4 OR phone LIKE :s5)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like,'s5'=>$like];
    }
    if ($isActive !== '') { $sql .= " AND is_active = :is_active"; $params['is_active'] = (int)$isActive; }
    $sql .= " ORDER BY supplier_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
