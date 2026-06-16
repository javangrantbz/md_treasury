<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

try {
    $totals = $pdo->query("
        SELECT
            COUNT(*)                                                   AS total_accounts,
            COALESCE(SUM(CASE WHEN status = 'active'  THEN 1 ELSE 0 END), 0) AS active_accounts,
            COALESCE(SUM(CASE WHEN status = 'dormant' THEN 1 ELSE 0 END), 0) AS dormant_accounts,
            COALESCE(SUM(CASE WHEN status = 'closed'  THEN 1 ELSE 0 END), 0) AS closed_accounts,
            COALESCE(SUM(current_balance), 0)                         AS total_balance,
            COALESCE(SUM(starting_balance), 0)                        AS total_starting_balance
        FROM nsb_accounts
        WHERE deleted_at IS NULL
    ")->fetch(PDO::FETCH_ASSOC);

    $thisMonth = date('Y-m-01');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nsb_accounts WHERE deleted_at IS NULL AND created_at >= :m");
    $stmt->execute(['m' => $thisMonth]);
    $newThisMonth = (int)$stmt->fetchColumn();

    $recent = $pdo->query("
        SELECT a.account_number, a.customer_name, a.current_balance, a.starting_date, a.status, a.created_at,
               b.name AS branch_name
        FROM nsb_accounts a
        LEFT JOIN branches b ON b.id = a.branch_id
        WHERE a.deleted_at IS NULL
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    apiResponse(['success' => true, 'data' => [
        'total_accounts'         => (int)$totals['total_accounts'],
        'active_accounts'        => (int)$totals['active_accounts'],
        'dormant_accounts'       => (int)$totals['dormant_accounts'],
        'closed_accounts'        => (int)$totals['closed_accounts'],
        'total_balance'          => (float)$totals['total_balance'],
        'total_starting_balance' => (float)$totals['total_starting_balance'],
        'new_this_month'         => $newThisMonth,
        'recent_accounts'        => $recent,
    ]]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
