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

    $sql = "SELECT ba.id, ba.uuid, ba.bank_name, ba.account_name, ba.account_number,
                   ba.account_masked, ba.currency_code, ba.item_number, ba.sof_number,
                   ba.status, ba.branch_name, ba.account_type_id, ba.created_at,
                   bat.name AS account_type_name
            FROM bank_accounts ba
            LEFT JOIN bank_account_types bat ON bat.id = ba.account_type_id
            WHERE ba.deleted_at IS NULL";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (ba.bank_name    LIKE :s1
                    OR ba.account_name  LIKE :s2
                    OR ba.account_number LIKE :s3
                    OR ba.account_masked LIKE :s4
                    OR ba.currency_code  LIKE :s5
                    OR ba.item_number    LIKE :s6
                    OR ba.sof_number     LIKE :s7
                    OR bat.name          LIKE :s8)";
        $like = '%' . $search . '%';
        $params += ['s1'=>$like,'s2'=>$like,'s3'=>$like,'s4'=>$like,
                    's5'=>$like,'s6'=>$like,'s7'=>$like,'s8'=>$like];
    }

    if ($status !== '') {
        $sql .= " AND ba.status = :status";
        $params['status'] = $status;
    }

    $sql .= " ORDER BY ba.bank_name ASC, ba.account_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    ob_end_clean();
    apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Throwable $e) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
