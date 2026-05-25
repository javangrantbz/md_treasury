<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    // Total applications
    $totalApps = $pdo->query("SELECT COUNT(*) FROM nsb_applications")->fetchColumn();
    
    // Total Delivered/Printed (Issued)
    $totalIssued = $pdo->query("SELECT COUNT(*) FROM nsb_applications WHERE status IN ('printed', 'delivered')")->fetchColumn();
    
    // Pending/Processing
    $activeReqs = $pdo->query("SELECT COUNT(*) FROM nsb_applications WHERE status IN ('pending', 'processing', 'approved')")->fetchColumn();
    
    // Issues this month
    $thisMonth = date('Y-m-01');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nsb_applications WHERE created_at >= :this_month");
    $stmt->execute(['this_month' => $thisMonth]);
    $monthIssued = $stmt->fetchColumn();
    
    // By Card Type
    $typeStmt = $pdo->query("SELECT card_type, COUNT(*) as count FROM nsb_applications GROUP BY card_type");
    $byType = $typeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent activity (last 5)
    $recentStmt = $pdo->query("SELECT a.customer_name, a.status, a.created_at, b.name as branch_name 
                               FROM nsb_applications a 
                               JOIN branches b ON a.branch_id = b.id 
                               ORDER BY a.created_at DESC LIMIT 5");
    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    apiResponse(['success' => true, 'data' => [
        'total_applications' => $totalApps,
        'total_issued' => $totalIssued,
        'active_requests' => $activeReqs,
        'this_month_count' => $monthIssued,
        'by_type' => $byType,
        'recent_activity' => $recent
    ]]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
