<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::requireAuth();
requireGet();

try {
    $userId = (int)Auth::user()['id'];

    $status = trim((string)($_GET['status'] ?? ''));
    $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

    $where = 'WHERE t.user_id = :user_id';
    $params = ['user_id' => $userId];

    if ($status !== '' && in_array($status, $allowedStatuses, true)) {
        $where   .= ' AND t.status = :status';
        $params['status'] = $status;
    }

    $tickets = $pdo->prepare("
        SELECT
            t.id,
            t.category,
            t.priority,
            t.subject,
            t.description,
            t.status,
            t.resolution_notes,
            t.resolved_at,
            t.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS resolved_by_name
        FROM support_tickets t
        LEFT JOIN users u ON u.id = t.resolved_by
        $where
        ORDER BY t.created_at DESC
    ");
    $tickets->execute($params);
    $rows = $tickets->fetchAll();

    // Summary counts (always across all statuses for the logged-in user)
    $counts = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM support_tickets
        WHERE user_id = :user_id
        GROUP BY status
    ");
    $counts->execute(['user_id' => $userId]);
    $summary = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0, 'total' => 0];
    foreach ($counts->fetchAll() as $row) {
        $summary[$row['status']] = (int)$row['cnt'];
        $summary['total'] += (int)$row['cnt'];
    }

    ob_end_clean();
    jsonResponse(['success' => true, 'tickets' => $rows, 'summary' => $summary]);

} catch (Throwable $e) {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
