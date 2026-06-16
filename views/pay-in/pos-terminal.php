<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$userId = (int)$_SESSION['user']['id'];

function pos_get_active_shift_context(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM pos_shifts
        WHERE uid = :uid AND status = 1
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    return $shift ?: null;
}

// ===== POST: API handler =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = [];
    $action = isset($input['action']) ? $input['action'] : 'load_activities';

    // Helper: resolve register + department for current user
    function pos_get_user_context($pdo, $userId) {
        $stmt = $pdo->prepare("
            SELECT r.id AS register_id, r.department_id
            FROM registers r
            INNER JOIN register_users ru ON ru.register_id = r.id
            WHERE ru.user_id = :uid AND r.is_active = 1
              AND r.deleted_at IS NULL AND ru.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $deptId = $row ? (int)$row['department_id'] : null;
        $regId  = $row ? (int)$row['register_id']   : null;
        if (!$deptId && !empty($_SESSION['user']['department_id'])) {
            $deptId = (int)$_SESSION['user']['department_id'];
        }
        return ['department_id' => $deptId, 'register_id' => $regId];
    }

    // ===== END SHIFT =====
    if ($action === 'end_shift') {
        try {
            $s = $pdo->prepare("SELECT * FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
            $s->execute(['uid' => $userId]);
            $sh = $s->fetch(PDO::FETCH_ASSOC);
            if (!$sh) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No active shift found.']);
                exit;
            }
            $curShift = $sh['shift_id'];

            $s = $pdo->prepare("SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount),0) AS total FROM pos_transactions WHERE shift_id = :sid");
            $s->execute(['sid' => $curShift]);
            $txRow = $s->fetch(PDO::FETCH_ASSOC);

            $s = $pdo->prepare("SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM pos_cart_items WHERE shift_id = :sid AND status = 'completed' GROUP BY payment_method ORDER BY total DESC");
            $s->execute(['sid' => $curShift]);
            $breakdown = $s->fetchAll(PDO::FETCH_ASSOC);

            $cashCollected = 0;
            foreach ($breakdown as $b) {
                if ($b['payment_method'] === 'cash') { $cashCollected = (float)$b['total']; break; }
            }

            $endedAt = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE pos_shifts SET status = 0, ended_at = :eat WHERE uid = :uid AND status = 1");
            $stmt->execute(['eat' => $endedAt, 'uid' => $userId]);

            ob_end_clean();
            echo json_encode([
                'success'           => true,
                'shift_id'          => $curShift,
                'started_at'        => isset($sh['started_at']) ? $sh['started_at'] : '',
                'ended_at'          => $endedAt,
                'starting_cash'     => (float)$sh['starting_cash'],
                'transaction_count' => (int)$txRow['tx_count'],
                'total_amount'      => (float)$txRow['total'],
                'cash_collected'    => $cashCollected,
                'expected_drawer'   => (float)$sh['starting_cash'] + $cashCollected,
                'method_breakdown'  => $breakdown,
            ]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ===== VERIFY PIN + FETCH PROTECTED DATA =====
    if ($action === 'verify_pin') {
        // Change SUPERVISOR_PIN constant in config to customise
        $correctPin = defined('SUPERVISOR_PIN') ? SUPERVISOR_PIN : '1234';
        $pin     = isset($input['pin'])     ? trim($input['pin'])     : '';
        $context = isset($input['context']) ? trim($input['context']) : '';
        if ($pin !== $correctPin) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
            exit;
        }
        if ($context === 'totals') {
            try {
                $sh = null;
                $s  = $pdo->prepare("SELECT shift_id, starting_cash FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
                $s->execute(['uid' => $userId]);
                $sh = $s->fetch(PDO::FETCH_ASSOC);
                if (!$sh) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'No active shift.']); exit; }
                $curShift  = $sh['shift_id'];
                $startCash = (float)$sh['starting_cash'];

                $s = $pdo->prepare("SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount),0) AS total FROM pos_transactions WHERE shift_id = :sid");
                $s->execute(['sid' => $curShift]);
                $txRow = $s->fetch(PDO::FETCH_ASSOC);

                $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS cash FROM pos_cart_items WHERE shift_id = :sid AND status = 'completed' AND payment_method = 'cash'");
                $s->execute(['sid' => $curShift]);
                $cashRow = $s->fetch(PDO::FETCH_ASSOC);

                ob_end_clean();
                echo json_encode([
                    'success'           => true,
                    'shift_id'          => $curShift,
                    'transaction_count' => (int)$txRow['tx_count'],
                    'total_amount'      => (float)$txRow['total'],
                    'cash_collected'    => (float)$cashRow['cash'],
                    'starting_cash'     => $startCash,
                    'expected_drawer'   => $startCash + (float)$cashRow['cash'],
                ]);
            } catch (Throwable $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
        ob_end_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // ===== SEARCH CUSTOMERS =====
    if ($action === 'search_customers') {
        $q = isset($input['query']) ? trim($input['query']) : '';
        if (strlen($q) < 1) { ob_end_clean(); echo json_encode(['success'=>true,'customers'=>[]]); exit; }
        $like = '%'.$q.'%';
        $stmt = $pdo->prepare("
            SELECT id, uuid, customer_type, first_name, last_name, organization_name,
                   email, phone, customer_name, tax_id,
                   address_line_1, address_line_2, district, country, status, created_at
            FROM customers
            WHERE (customer_name LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3
                   OR organization_name LIKE :q4 OR email LIKE :q5 OR phone LIKE :q6
                   OR tax_id LIKE :q7)
              AND status = 'active' AND deleted_at IS NULL
            ORDER BY customer_name ASC LIMIT 20
        ");
        $stmt->execute(['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like,'q5'=>$like,'q6'=>$like,'q7'=>$like]);
        ob_end_clean();
        echo json_encode(['success'=>true,'customers'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ===== CREATE CUSTOMER =====
    if ($action === 'create_customer') {
        $fn = isset($input['first_name']) ? trim($input['first_name']) : '';
        $ln = isset($input['last_name'])  ? trim($input['last_name'])  : '';
        $ph = isset($input['phone'])      ? trim($input['phone'])      : '';
        $em = isset($input['email'])      ? trim($input['email'])      : '';
        if (empty($fn)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'First name is required.']); exit; }
        try {
            $uuid  = bin2hex(random_bytes(16));
            $cname = trim($fn.' '.$ln);
            $stmt  = $pdo->prepare("
                INSERT INTO customers (uuid, customer_type, first_name, last_name, customer_name, email, phone, status, created_by, created_at, updated_at)
                VALUES (:uuid,'individual',:fn,:ln,:cn,:em,:ph,'active',:uid,NOW(),NOW())
            ");
            $stmt->execute(['uuid'=>$uuid,'fn'=>$fn,'ln'=>$ln,'cn'=>$cname,'em'=>$em,'ph'=>$ph,'uid'=>$userId]);
            $newId = $pdo->lastInsertId();
            ob_end_clean();
            echo json_encode(['success'=>true,'customer'=>['id'=>$newId,'uuid'=>$uuid,'first_name'=>$fn,'last_name'=>$ln,'customer_name'=>$cname,'email'=>$em,'phone'=>$ph,'status'=>'active']]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'Failed to create customer: '.$e->getMessage()]);
        }
        exit;
    }

    // ===== SEARCH DEPARTMENTS =====
    if ($action === 'search_departments') {
        $q = isset($input['query']) ? trim($input['query']) : '';
        if (strlen($q) < 1) { ob_end_clean(); echo json_encode(['success'=>true,'departments'=>[]]); exit; }
        $like = '%'.$q.'%';
        $stmt = $pdo->prepare("
            SELECT id, code, name, short_name, ministry_name
            FROM departments
            WHERE (name LIKE :q1 OR code LIKE :q2 OR short_name LIKE :q3)
              AND deleted_at IS NULL
            ORDER BY name ASC LIMIT 20
            ORDER BY name ASC LIMIT 20
        ");
        $stmt->execute(['q1'=>$like,'q2'=>$like,'q3'=>$like]);
        ob_end_clean();
        echo json_encode(['success'=>true,'departments'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ===== LOAD BANK ACCOUNTS =====
    if ($action === 'load_bank_accounts') {
        try {
            $ctx    = pos_get_user_context($pdo, $userId);
            $deptId = $ctx['department_id'];
            $activeShift = pos_get_active_shift_context($pdo, $userId);
            $accounts = [];
            if (!empty($activeShift['bank_account_id'])) {
                $stmt = $pdo->prepare("
                    SELECT
                        0 AS link_id,
                        :department_id AS department_id,
                        ba.id AS bank_account_id,
                        1 AS is_default,
                        'active' AS link_status,
                        ba.id AS bank_id,
                        ba.bank_name,
                        ba.account_name,
                        ba.account_number,
                        ba.currency_code,
                        ba.account_masked,
                        ba.account_type
                    FROM bank_accounts ba
                    WHERE ba.id = :bank_account_id
                      AND ba.status = 'active'
                      AND ba.deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([
                    'department_id' => $deptId,
                    'bank_account_id' => (int)$activeShift['bank_account_id'],
                ]);
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($deptId) {
                $stmt = $pdo->prepare("
                    SELECT dba.id AS link_id, dba.department_id, dba.bank_account_id,
                           dba.is_default, dba.status AS link_status,
                           ba.id AS bank_id, ba.bank_name, ba.account_name,
                           ba.account_number, ba.currency_code, ba.account_masked, ba.account_type
                    FROM department_bank_accounts dba
                    INNER JOIN bank_accounts ba ON ba.id = dba.bank_account_id
                    WHERE dba.department_id = :dept_id AND ba.status = 'active'
                      AND dba.deleted_at IS NULL AND ba.deleted_at IS NULL
                    ORDER BY dba.is_default DESC, ba.bank_name ASC
                ");
                $stmt->execute(['dept_id' => $deptId]);
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            if (empty($accounts)) {
                $stmt = $pdo->prepare("
                    SELECT 0 AS link_id, NULL AS department_id, id AS bank_account_id,
                           0 AS is_default, 'active' AS link_status,
                           id AS bank_id, bank_name, account_name,
                           account_number, currency_code, account_masked, account_type
                    FROM bank_accounts WHERE status = 'active' AND deleted_at IS NULL
                    ORDER BY bank_name ASC
                ");
                $stmt->execute();
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            ob_end_clean();
            echo json_encode(['success'=>true,'bank_accounts'=>$accounts]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ===== ADD TO CART =====
    if ($action === 'add_to_cart') {
        $shiftId    = isset($input['shift_id'])        ? trim($input['shift_id'])          : '';
        $actId      = isset($input['activity_id'])     ? (int)$input['activity_id']         : 0;
        $actName    = isset($input['activity_name'])   ? trim($input['activity_name'])      : '';
        $actCode    = isset($input['activity_code'])   ? trim($input['activity_code'])      : '';
        $amount     = isset($input['amount'])          ? (float)$input['amount']            : 0;
        $method     = isset($input['payment_method'])  ? trim($input['payment_method'])     : '';
        $details    = isset($input['payment_details']) ? $input['payment_details']          : [];
        $beneficiary = isset($input['beneficiary_name']) ? trim($input['beneficiary_name']) : '';
        $custId     = isset($input['customer_id'])     ? (int)$input['customer_id']         : null;
        $custName   = isset($input['customer_name'])   ? trim($input['customer_name'])      : null;
        $deptId     = isset($input['dept_id'])         ? (int)$input['dept_id']             : null;
        $deptName   = isset($input['dept_name'])       ? trim($input['dept_name'])          : null;

        if (!$shiftId || !$actId || $amount <= 0 || !$method) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'Missing required cart fields.']);
            exit;
        }
        if (!$custId && !$deptId) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'A customer or department must be selected before charging a service.']);
            exit;
        }
        if (!$deptId) {
            $ctx = pos_get_user_context($pdo, $userId);
            $deptId = $ctx['department_id'];
        }
        try {
            $existingStmt = $pdo->prepare("
                SELECT customer_id, customer_name, dept_id, dept_name
                FROM pos_cart_items
                WHERE shift_id = :sid AND uid = :uid AND status = 'pending' AND deleted_at IS NULL
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $existingStmt->execute(['sid'=>$shiftId,'uid'=>$userId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $sameCustomer = (int)($existing['customer_id'] ?: 0) === (int)($custId ?: 0)
                    && trim((string)($existing['customer_name'] ?: '')) === trim((string)($custName ?: ''));
                $sameDept = (int)($existing['dept_id'] ?: 0) === (int)($deptId ?: 0)
                    && trim((string)($existing['dept_name'] ?: '')) === trim((string)($deptName ?: ''));
                if (!$sameCustomer || !$sameDept) {
                    ob_end_clean();
                    echo json_encode(['success'=>false,'message'=>'Open receipt already belongs to a different payer. Print or clear the current receipt first.']);
                    exit;
                }
            }

            $uuid = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO pos_cart_items
                  (uuid, shift_id, uid, customer_id, customer_name, dept_id, dept_name,
                   activity_id, activity_name, activity_code, amount, payment_method, payment_details, beneficiary_name)
                VALUES (:uuid,:sid,:uid,:cid,:cn,:did,:dn,:aid,:an,:ac,:amt,:pm,:pd,:bn)
            ");
            $stmt->execute([
                'uuid'=>$uuid,'sid'=>$shiftId,'uid'=>$userId,
                'cid'=>$custId ?: null,'cn'=>$custName,'did'=>$deptId ?: null,'dn'=>$deptName,
                'aid'=>$actId,'an'=>$actName,'ac'=>$actCode,'amt'=>$amount,
                'pm'=>$method,'pd'=>json_encode($details),'bn'=>$beneficiary,
            ]);
            ob_end_clean();
            echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'uuid'=>$uuid]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'Failed to save: '.$e->getMessage()]);
        }
        exit;
    }

    // ===== LOAD CART =====
    if ($action === 'load_cart') {
        $shiftId = isset($input['shift_id']) ? trim($input['shift_id']) : '';
        $stmt = $pdo->prepare("SELECT * FROM pos_cart_items WHERE shift_id = :sid AND uid = :uid AND status = 'pending' AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute(['sid'=>$shiftId,'uid'=>$userId]);
        ob_end_clean();
        echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ===== DELETE CART ITEM =====
    if ($action === 'delete_cart_item') {
        $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;
        if (!$itemId) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Missing item ID.']); exit; }
        $stmt = $pdo->prepare("UPDATE pos_cart_items SET deleted_at = NOW() WHERE id = :id AND uid = :uid AND status = 'pending' AND deleted_at IS NULL");
        $stmt->execute(['id'=>$itemId,'uid'=>$userId]);
        ob_end_clean();
        echo json_encode(['success'=>true]);
        exit;
    }

    // ===== COMPLETE TRANSACTION =====
    if ($action === 'complete_transaction') {
        $shiftId = isset($input['shift_id']) ? trim($input['shift_id']) : '';
        $stmt = $pdo->prepare("SELECT * FROM pos_cart_items WHERE shift_id = :sid AND uid = :uid AND status = 'pending' AND deleted_at IS NULL ORDER BY created_at ASC");
        $stmt->execute(['sid'=>$shiftId,'uid'=>$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'No items in cart.']); exit; }
        $total = 0;
        foreach ($items as $it) { $total += (float)$it['amount']; }
        try {
            $pdo->beginTransaction();
            $txUuid = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO pos_transactions (uuid, shift_id, uid, customer_id, customer_name, dept_id, dept_name, total_amount, items)
                VALUES (:uuid,:sid,:uid,:cid,:cn,:did,:dn,:total,:items)
            ");
            $stmt->execute([
                'uuid'=>$txUuid,'sid'=>$shiftId,'uid'=>$userId,
                'cid'=>$items[0]['customer_id'],'cn'=>$items[0]['customer_name'],
                'did'=>$items[0]['dept_id'],'dn'=>$items[0]['dept_name'],
                'total'=>$total,'items'=>json_encode($items),
            ]);
            $txId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE pos_cart_items SET status = 'completed' WHERE shift_id = :sid AND uid = :uid AND status = 'pending' AND deleted_at IS NULL");
            $stmt->execute(['sid'=>$shiftId,'uid'=>$userId]);
            $pdo->commit();
            ob_end_clean();
            echo json_encode(['success'=>true,'transaction_id'=>$txUuid,'transaction_db_id'=>$txId,'total'=>$total]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'Failed: '.$e->getMessage()]);
        }
        exit;
    }

    // ===== TOGGLE FAVORITE =====
    if ($action === 'toggle_favorite') {
        $actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
        if (!$actId) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Missing activity_id']); exit; }
        $stmt = $pdo->prepare("SELECT id FROM pos_service_favorites WHERE uid = :uid AND activity_id = :aid");
        $stmt->execute(['uid'=>$userId,'aid'=>$actId]);
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM pos_service_favorites WHERE uid = :uid AND activity_id = :aid")->execute(['uid'=>$userId,'aid'=>$actId]);
            ob_end_clean();
            echo json_encode(['success'=>true,'favorited'=>false]);
        } else {
            $pdo->prepare("INSERT INTO pos_service_favorites (uid, activity_id) VALUES (:uid, :aid)")->execute(['uid'=>$userId,'aid'=>$actId]);
            ob_end_clean();
            echo json_encode(['success'=>true,'favorited'=>true]);
        }
        exit;
    }

    // ===== CHECKOUT (atomic: insert items + transaction in one DB tx) =====
    if ($action === 'checkout') {
        $shiftId     = isset($input['shift_id'])         ? trim($input['shift_id'])         : '';
        $items       = isset($input['items'])             ? $input['items']                  : [];
        $payMethod   = isset($input['payment_method'])    ? trim($input['payment_method'])   : '';
        $payDetails  = isset($input['payment_details'])   ? $input['payment_details']        : [];
        $beneficiary = isset($input['beneficiary_name'])  ? trim($input['beneficiary_name']) : '';
        $custId      = isset($input['customer_id'])       ? (int)$input['customer_id']       : null;
        $custName    = isset($input['customer_name'])     ? trim($input['customer_name'])    : null;
        $deptId      = isset($input['dept_id'])           ? (int)$input['dept_id']           : null;
        $deptName    = isset($input['dept_name'])         ? trim($input['dept_name'])        : null;

        if (empty($items) || !$payMethod || !$shiftId) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing required checkout data.']);
            exit;
        }

        $total = 0;
        foreach ($items as $it) { $total += (float)(isset($it['amount']) ? $it['amount'] : 0); }

        try {
            $pdo->beginTransaction();
            $pdJson = json_encode($payDetails);
            $stmtC  = $pdo->prepare("
                INSERT INTO pos_cart_items
                  (uuid, shift_id, uid, customer_id, customer_name, dept_id, dept_name,
                   activity_id, activity_name, activity_code, amount, payment_method,
                   payment_details, beneficiary_name, status)
                VALUES (:uuid,:sid,:uid,:cid,:cn,:did,:dn,:aid,:an,:ac,:amt,:pm,:pd,:bn,'completed')
            ");
            foreach ($items as $it) {
                $stmtC->execute([
                    'uuid' => bin2hex(random_bytes(16)),
                    'sid'  => $shiftId,
                    'uid'  => $userId,
                    'cid'  => ($custId ?: null),
                    'cn'   => $custName,
                    'did'  => ($deptId ?: null),
                    'dn'   => $deptName,
                    'aid'  => (int)(isset($it['activity_id']) ? $it['activity_id'] : 0),
                    'an'   => isset($it['activity_name']) ? trim($it['activity_name']) : '',
                    'ac'   => isset($it['activity_code']) ? trim($it['activity_code']) : '',
                    'amt'  => (float)(isset($it['amount']) ? $it['amount'] : 0),
                    'pm'   => $payMethod,
                    'pd'   => $pdJson,
                    'bn'   => $beneficiary,
                ]);
            }
            $txUuid = bin2hex(random_bytes(16));
            $pdo->prepare("
                INSERT INTO pos_transactions
                  (uuid, shift_id, uid, customer_id, customer_name, dept_id, dept_name, total_amount, items)
                VALUES (:uuid,:sid,:uid,:cid,:cn,:did,:dn,:total,:items)
            ")->execute([
                'uuid'  => $txUuid,
                'sid'   => $shiftId,
                'uid'   => $userId,
                'cid'   => ($custId ?: null),
                'cn'    => $custName,
                'did'   => ($deptId ?: null),
                'dn'    => $deptName,
                'total' => $total,
                'items' => json_encode($items),
            ]);
            $pdo->commit();
            ob_end_clean();
            echo json_encode(['success' => true, 'transaction_id' => $txUuid, 'total' => $total]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== DAILY CASH SALES REPORT (current cashier, today) =====
    if ($action === 'daily_sales_report') {
        try {
            $today = date('Y-m-d');

            // All of today's completed CASH items for this cashier.
            $s = $pdo->prepare("
                SELECT ci.id, ci.shift_id, ci.activity_name, ci.activity_code,
                       ci.amount, ci.beneficiary_name, ci.customer_name,
                       ci.created_at, ci.pay_in_id
                FROM pos_cart_items ci
                WHERE ci.uid = :uid
                  AND ci.payment_method = 'cash'
                  AND ci.status = 'completed'
                  AND ci.deleted_at IS NULL
                  AND DATE(ci.created_at) = :today
                ORDER BY ci.created_at ASC
            ");
            $s->execute(['uid' => $userId, 'today' => $today]);
            $items = $s->fetchAll(PDO::FETCH_ASSOC);

            $totalCash   = 0.0;  // all cash collected today
            $settledCash = 0.0;  // cash already attached to a pay-in
            $pendingCash = 0.0;  // cash not yet paid in
            $pendingCount = 0;
            foreach ($items as &$it) {
                $amt = (float)$it['amount'];
                $totalCash += $amt;
                if (!empty($it['pay_in_id'])) {
                    $settledCash += $amt;
                } else {
                    $pendingCash += $amt;
                    $pendingCount++;
                }
                $it['amount'] = $amt;
            }
            unset($it);

            ob_end_clean();
            echo json_encode([
                'success'       => true,
                'date'          => $today,
                'items'         => $items,
                'total_cash'    => round($totalCash, 2),
                'settled_cash'  => round($settledCash, 2),
                'pending_cash'  => round($pendingCash, 2),
                'pending_count' => $pendingCount,
                'total_count'   => count($items),
            ]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to load daily sales: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== GENERATE PAY-IN FROM TODAY'S CASH SALES =====
    if ($action === 'generate_sales_payin') {
        try {
            $today = date('Y-m-d');

            // Eligible = today's completed cash items for this cashier not yet paid in.
            $s = $pdo->prepare("
                SELECT id, amount
                FROM pos_cart_items
                WHERE uid = :uid
                  AND payment_method = 'cash'
                  AND status = 'completed'
                  AND pay_in_id IS NULL
                  AND deleted_at IS NULL
                  AND DATE(created_at) = :today
                ORDER BY id ASC
            ");
            $s->execute(['uid' => $userId, 'today' => $today]);
            $eligible = $s->fetchAll(PDO::FETCH_ASSOC);

            if (empty($eligible)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No un-settled cash sales for today. Nothing to pay in.']);
                exit;
            }

            $cashTotal = 0.0;
            $ids = [];
            foreach ($eligible as $row) {
                $cashTotal += (float)$row['amount'];
                $ids[] = (int)$row['id'];
            }
            $cashTotal = round($cashTotal, 2);

            // Resolve department context from active shift.
            $deptId   = null;
            $deptName = null;
            $sh = $pdo->prepare("SELECT department_id FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
            $sh->execute(['uid' => $userId]);
            $shRow = $sh->fetch(PDO::FETCH_ASSOC);
            if ($shRow && !empty($shRow['department_id'])) {
                $deptId = (int)$shRow['department_id'];
                $ds = $pdo->prepare("SELECT name FROM departments WHERE id = :id LIMIT 1");
                $ds->execute(['id' => $deptId]);
                $dRow = $ds->fetch(PDO::FETCH_ASSOC);
                if ($dRow) $deptName = $dRow['name'];
            }

            $pdo->beginTransaction();

            // Generate sequential pay-in id for the date.
            $datePrefix = date('Ymd', strtotime($today));
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM pay_ins WHERE pay_in_id LIKE :p");
            $cntStmt->execute(['p' => 'PI-' . $datePrefix . '-%']);
            $seq     = (int)$cntStmt->fetchColumn() + 1;
            $payInId = 'PI-' . $datePrefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            $note = 'Auto-generated from POS cash sales (' . count($ids) . ' transaction'
                  . (count($ids) === 1 ? '' : 's') . ') on ' . date('d M Y') . '. '
                  . 'Denominations to be counted at deposit.';

            $pdo->prepare("
                INSERT INTO pay_ins
                    (pay_in_id, department_id, department_name, cashier_uid, pay_in_date,
                     total_cash, total_cheques, total_amount, notes, bank_slip_path, status, source)
                VALUES (:pid,:did,:dn,:uid,:dt,:tc,0,:tot,:notes,NULL,'submitted','pos_sales')
            ")->execute([
                'pid'   => $payInId,
                'did'   => $deptId,
                'dn'    => $deptName,
                'uid'   => $userId,
                'dt'    => $today,
                'tc'    => $cashTotal,
                'tot'   => $cashTotal,
                'notes' => $note,
            ]);

            // Cash row with no denomination breakdown (counted at deposit).
            $pdo->prepare("
                INSERT INTO pay_in_cash
                    (pay_in_id,d_100,d_50,d_20,d_10,d_5,d_2,d_1,c_50,c_25,c_10,c_5,c_1,cash_total)
                VALUES (:pid,0,0,0,0,0,0,0,0,0,0,0,0,:ct)
            ")->execute(['pid' => $payInId, 'ct' => $cashTotal]);

            // Mark the settled items so they aren't paid in twice.
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE pos_cart_items SET pay_in_id = ? WHERE id IN ($placeholders)");
            $upd->execute(array_merge([$payInId], $ids));

            $pdo->commit();

            ob_end_clean();
            echo json_encode([
                'success'      => true,
                'pay_in_id'    => $payInId,
                'total_cash'   => $cashTotal,
                'item_count'   => count($ids),
                'view_url'     => url('views/pay-in/pay-in-view.php') . '?id=' . urlencode($payInId),
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to generate pay-in: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== RECENT TRANSACTIONS (current cashier, today, all methods) =====
    if ($action === 'recent_transactions') {
        try {
            $today = date('Y-m-d');
            $s = $pdo->prepare("
                SELECT uuid, total_amount, items, customer_name, dept_name, status, created_at
                FROM pos_transactions
                WHERE uid = :uid AND DATE(created_at) = :today
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $s->execute(['uid' => $userId, 'today' => $today]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);

            $out = [];
            $grand = 0.0;
            foreach ($rows as $r) {
                $items = [];
                if (!empty($r['items'])) {
                    $d = json_decode($r['items'], true);
                    if (is_array($d)) $items = $d;
                }
                $methods = [];
                foreach ($items as $it) {
                    $m = isset($it['payment_method']) ? $it['payment_method'] : '';
                    if ($m !== '' && !in_array($m, $methods, true)) $methods[] = $m;
                }
                $grand += (float)$r['total_amount'];
                $out[] = [
                    'uuid'          => $r['uuid'],
                    'total_amount'  => (float)$r['total_amount'],
                    'customer_name' => $r['customer_name'],
                    'dept_name'     => $r['dept_name'],
                    'status'        => $r['status'],
                    'created_at'    => $r['created_at'],
                    'item_count'    => count($items),
                    'methods'       => $methods,
                ];
            }

            ob_end_clean();
            echo json_encode([
                'success'      => true,
                'date'         => $today,
                'transactions' => $out,
                'count'        => count($out),
                'grand_total'  => round($grand, 2),
            ]);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to load recent transactions: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== LOAD ACTIVITIES (default) =====
    try {
        $ctx    = pos_get_user_context($pdo, $userId);
        $deptId = $ctx['department_id'];
        $activeShift = pos_get_active_shift_context($pdo, $userId);

        $costCenters = [];
        if (!empty($activeShift['cost_center_id'])) {
            $stmt = $pdo->prepare("
                SELECT cc.id, cc.code, cc.name, cc.uuid
                FROM cost_centers cc
                WHERE cc.id = :cost_center_id
                  AND cc.deleted_at IS NULL
                  AND cc.status = 'active'
                LIMIT 1
            ");
            $stmt->execute(['cost_center_id' => (int)$activeShift['cost_center_id']]);
            $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($deptId) {
            $stmt = $pdo->prepare("
                SELECT cc.id, cc.code, cc.name, cc.uuid
                FROM department_cost_centers dcc
                INNER JOIN cost_centers cc ON cc.id = dcc.cost_center_id
                WHERE dcc.department_id = :did AND cc.status = 'active'
                ORDER BY cc.name ASC
            ");
            $stmt->execute(['did' => $deptId]);
            $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $activities = [];
        if (!empty($costCenters)) {
            $ccIds        = array_column($costCenters, 'id');
            $placeholders = implode(',', array_fill(0, count($ccIds), '?'));
            $stmt = $pdo->prepare("
                SELECT cca.id, cca.uuid, cca.cost_center_id, cca.activity_code, cca.activity_name,
                       cca.revenue_code, cca.gl_account, cca.description, cca.default_amount, cca.is_active,
                       cc.name AS cost_center_name, cc.code AS cost_center_code, cc.fund AS fund,
                       d.name AS department_name, d.short_name AS department_short_name
                FROM cost_center_activities cca
                INNER JOIN cost_centers cc ON cc.id = cca.cost_center_id
                LEFT JOIN departments d ON d.id = cc.department_id
                WHERE cca.cost_center_id IN ($placeholders) AND cca.is_active = 1
                ORDER BY cc.name ASC, cca.activity_name ASC
            ");
            $stmt->execute($ccIds);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $favStmt = $pdo->prepare("SELECT activity_id FROM pos_service_favorites WHERE uid = :uid");
        $favStmt->execute(['uid' => $userId]);
        $favIds = $favStmt->fetchAll(PDO::FETCH_COLUMN);

        ob_end_clean();
        echo json_encode(['success'=>true,'activities'=>$activities,'cost_centers'=>$costCenters,'favorites'=>$favIds]);
    } catch (Throwable $e) {
        ob_end_clean();
        echo json_encode(['success'=>false,'message'=>'Failed to load activities: '.$e->getMessage()]);
    }
    exit;
}

// ===== GET: Render page =====
// Never let the browser/proxy serve a stale POS terminal (inline JS lives in
// this page, so a cached copy = old behaviour).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Check for active shift — redirect to shift-start if none
$stmt = $pdo->prepare("SELECT * FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
$stmt->execute(['uid' => $userId]);
$activeShift = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activeShift) {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pos-shift-start.php'));
    exit;
}

$sessionUser = $_SESSION['user'];
$userName    = trim(($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? ''));
if (!$userName) $userName = $sessionUser['username'] ?? 'Cashier';

$shiftId    = $activeShift['shift_id'];
$shiftStart = isset($activeShift['started_at']) ? $activeShift['started_at'] : '';

// Fetch department/branch name
$branchName = 'Treasury';
if (!empty($activeShift['department_id'])) {
    $s = $pdo->prepare("SELECT name, short_name FROM departments WHERE id = :id LIMIT 1");
    $s->execute(['id' => $activeShift['department_id']]);
    $dept = $s->fetch(PDO::FETCH_ASSOC);
    if ($dept) $branchName = $dept['short_name'] ?: $dept['name'];
}

// Fetch register/terminal name
$terminalName = '';
if (!empty($activeShift['register_id'])) {
    $s = $pdo->prepare("SELECT register_code, register_name FROM registers WHERE id = :id LIMIT 1");
    $s->execute(['id' => $activeShift['register_id']]);
    $reg = $s->fetch(PDO::FETCH_ASSOC);
    if ($reg) $terminalName = $reg['register_name'] ?: $reg['register_code'];
}

$shiftCostCenterName = '';
if (!empty($activeShift['cost_center_id'])) {
    $s = $pdo->prepare("SELECT code, name FROM cost_centers WHERE id = :id LIMIT 1");
    $s->execute(['id' => $activeShift['cost_center_id']]);
    $cc = $s->fetch(PDO::FETCH_ASSOC);
    if ($cc) {
        $shiftCostCenterName = trim(($cc['code'] ? $cc['code'] . ' - ' : '') . ($cc['name'] ?? ''));
    }
}

$shiftBankName = '';
if (!empty($activeShift['bank_account_id'])) {
    $s = $pdo->prepare("SELECT bank_name, account_name, account_masked FROM bank_accounts WHERE id = :id LIMIT 1");
    $s->execute(['id' => $activeShift['bank_account_id']]);
    $bank = $s->fetch(PDO::FETCH_ASSOC);
    if ($bank) {
        $shiftBankName = trim(($bank['bank_name'] ?? '') . ' - ' . ($bank['account_name'] ?? ''));
        if (!empty($bank['account_masked'])) {
            $shiftBankName .= ' (' . $bank['account_masked'] . ')';
        }
    }
}

// Fetch role
$roleName = '';
$s = $pdo->prepare("
    SELECT r.name FROM roles r
    INNER JOIN user_roles ur ON ur.role_id = r.id
    WHERE ur.user_id = :uid LIMIT 1
");
$s->execute(['uid' => $userId]);
$roleRow = $s->fetch(PDO::FETCH_ASSOC);
if ($roleRow) $roleName = $roleRow['name'];

// Environment badge (define APP_ENV constant in config/env.php to customise)
$appEnv = 'PRODUCTION';
if (!empty($_SERVER['SERVER_NAME'])) {
    $sn = $_SERVER['SERVER_NAME'];
    if (strpos($sn, 'localhost') !== false || $sn === '127.0.0.1' || strpos($sn, '.local') !== false) {
        $appEnv = 'DEVELOPMENT';
    }
}
if (defined('APP_ENV')) $appEnv = strtoupper(APP_ENV);
$envBgMap = ['PRODUCTION'=>'#dc2626','TEST'=>'#ea580c','TRAINING'=>'#2563eb','SANDBOX'=>'#7c3aed','DEVELOPMENT'=>'#ca8a04'];
$envBg    = isset($envBgMap[$appEnv]) ? $envBgMap[$appEnv] : '#6b7280';

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS Terminal — Treasury Revenue System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: { colors: {
  brand:    { 50:'#f0f7eb',100:'#dcefd0',200:'#b9dfa1',300:'#8fc96a',400:'#6db344',500:'#50832d',600:'#467328',700:'#3b6222',800:'#30501c',900:'#253f16' },
  treasury: { 50:'#eef6ff',100:'#d9ebff',200:'#bcdbff',300:'#8ac2ff',400:'#56a3ff',500:'#2b7fff',600:'#1a62f5',700:'#174de2',800:'#193fba',900:'#1a3a91' },
}}}}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;overflow-y:auto;overflow-x:hidden}
.hdr-btn:hover{background:rgba(255,255,255,.14)!important;color:#fff!important;}
.svc-card{transition:all .15s;border:1.5px solid #e2e8f0;cursor:pointer;}
.svc-card:hover{border-color:#6db344;background:#f8fdf5;transform:translateY(-1px);box-shadow:0 4px 12px -2px rgba(59,98,34,.14);}
.svc-card.flash{border-color:#467328;background:#f0f7eb;}
.co-pay-btn{transition:all .12s;border:1.5px solid #e2e8f0;}
.co-pay-btn.selected{border-color:#334155!important;background:#f1f5f9;color:#0f172a;font-weight:600;}

/* ---- Required-field buzz ---- */
@keyframes pos-shake {
  0%,100% { transform: translateX(0); }
  15% { transform: translateX(-6px); }
  30% { transform: translateX(5px); }
  45% { transform: translateX(-4px); }
  60% { transform: translateX(3px); }
  75% { transform: translateX(-2px); }
}
.pos-shake { animation: pos-shake .4s ease-in-out; }
.pos-required-error {
  border-color:#dc2626 !important;
  background:#fef2f2 !important;
  box-shadow:0 0 0 3px rgba(220,38,38,.15) !important;
}

/* ---- Bolder, higher-contrast modal & panel borders ---- */
/* Darken the dull green/gray border colors used on cards and modals. */
[class~="border-[#dbe5d2]"],
[class~="border-[#e6eee0]"],
[class~="border-[#eef3ea]"],
[class~="border-[#d4e3cc]"],
[class~="border-gray-100"],
[class~="border-gray-200"] { border-color:#8fae7e !important; }
/* Thicken the frame on rounded panels, cards, inputs and primary buttons
   (small pill/chip badges keep their thin 1px border). */
[class~="border"][class~="rounded-xl"],
[class~="border"][class~="rounded-2xl"],
[class~="border"][class~="rounded-[24px]"],
[class~="border"][class~="rounded-[22px]"],
[class~="border"][class~="rounded-[20px]"] { border-width:2px !important; }
/* Give every modal panel a clear frame, even when it had no border class. */
.fixed.inset-0.z-50 .shadow-2xl { border:2px solid #6f9d5e !important; }

/* ---- Receipt print styles ---- */
@media print {
  body > *:not(#receipt-print-root) { display:none !important; }
  #receipt-print-root { display:block !important; position:static !important; background:#fff !important; }
  #receipt-print-root .no-print { display:none !important; }
  #rct-paper {
    box-shadow:none !important; border:none !important;
    max-width:100% !important; margin:0 !important;
    font-size:11pt;
  }
  @page { size:A5 portrait; margin:12mm; }
}
</style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50">

<style>
body{font-family:'Inter',sans-serif;margin:0;overflow-y:auto;overflow-x:hidden;background:#f8fafc}
.svc-card{transition:all .15s;border:1px solid #e2e8f0;cursor:pointer;}
.svc-card:hover{border-color:#1e4620;background:#f8fdf5;transform:translateY(-1px);box-shadow:0 4px 12px -2px rgba(30,70,32,.15);}
.svc-card.flash{border-color:#1e4620;background:#f0f7eb;}

/* Two-layer header & utility bar */
.hdr-main{background:#3f6f33;color:#f7faec;}
.hdr-util{background:#f1f5f9;border-bottom:1px solid #e2e8f0;color:#475569;}
.hdr-btn:hover{background:rgba(255,255,255,.15);color:#fff;}

/* Metadata dividers */
.meta-item{border-right:1px solid rgba(255,255,255,.15);padding:0 12px;}
.meta-item:last-child{border:none;}

/* Slightly darker neutral borders (cards, panels, tables, inputs) */
*,::before,::after{border-color:#d1d5db;}
.border-gray-200{border-color:#d1d5db!important;}
.border-slate-200{border-color:#cbd5e1!important;}
.border-\[\#dbe5d2\]{border-color:#c2d2b4!important;}
.border-\[\#e6eee0\]{border-color:#d3dec8!important;}
.border-\[\#eef3ea\]{border-color:#dbe6d2!important;}

/* Cart panel */
.treasury-panel{background:#fff;border-left:1px solid #e2e8f0;}
.co-pay-btn{transition:all .12s;border:1.5px solid #e2e8f0;}
.co-pay-btn.selected{border-color:#1e4620!important;background:#f0fdf4;color:#14532d;font-weight:600;}
.page-pay-btn{transition:all .12s;border:1.5px solid #e2e8f0;}
.page-pay-btn.selected{border-color:#1e4620!important;background:#f0fdf4;color:#14532d;font-weight:700;box-shadow:0 0 0 1px rgba(30,70,32,.08);}
.hdr-act:hover{background:rgba(255,255,255,.16)!important;color:#fff!important;}
@media print{
  body>*:not(#receipt-print-root):not(#shift-report-modal):not(#instruction-print-root){display:none!important;}
  #receipt-print-root,#shift-report-modal,#instruction-print-root{display:block!important;position:static!important;background:#fff!important;}
  .no-print{display:none!important;}
  #rct-paper{box-shadow:none!important;border:none!important;max-width:100%!important;margin:0!important;font-size:11pt;}
  #bdi-paper{box-shadow:none!important;border:none!important;max-width:100%!important;margin:0!important;font-size:11pt;}
  .sr-paper{box-shadow:none!important;border:none!important;border-radius:0!important;max-width:100%!important;margin:0!important;font-size:11pt;}
  @page{size:auto;margin:10mm;}
  #rct-paper[data-print-format="thermal"]{width:80mm!important;max-width:80mm!important;font-size:9pt!important;}
  #rct-paper[data-print-format="half-letter"]{width:5.5in!important;max-width:5.5in!important;font-size:10pt!important;}
  #rct-paper[data-print-format="a5"]{width:148mm!important;max-width:148mm!important;font-size:10.5pt!important;}
  #rct-paper[data-print-format="letter"]{width:8in!important;max-width:8in!important;font-size:11pt!important;}
}
#rct-paper[data-print-format="thermal"]{max-width:24rem;}
#rct-paper[data-print-format="half-letter"]{max-width:34rem;}
#rct-paper[data-print-format="a5"]{max-width:38rem;}
#rct-paper[data-print-format="letter"]{max-width:50rem;}
</style>

<!-- ===== HEADER LAYER 1: IDENTITY ===== -->
<header class="shrink-0" style="background:linear-gradient(135deg,#4b7a3a 0%,#5a8a47 100%);box-shadow:0 1px 0 rgba(255,255,255,.08),0 2px 10px rgba(0,0,0,.18);">
  <?php if ($appEnv === 'DEVELOPMENT'): ?>
  <div style="height:24px;background:<?= $envBg ?>;display:flex;align-items:center;padding:0 18px;font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#fff7d6;border-bottom:1px solid rgba(0,0,0,.12);">
    Developing
  </div>
  <?php endif; ?>
  <div style="display:flex;align-items:center;height:62px;padding:0 18px;gap:0;">

    <!-- Back to Cashiering -->
    <a href="<?= url('views/cashiering/dashboard.php') ?>"
       style="display:flex;align-items:center;gap:4px;padding-right:14px;margin-right:2px;border-right:1px solid rgba(255,255,255,.16);color:rgba(247,250,236,.7);text-decoration:none;font-size:11px;font-weight:700;letter-spacing:.05em;flex-shrink:0;transition:color .15s;"
       onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='rgba(247,250,236,.7)'">
      <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Cashiering
    </a>

    <!-- Seal + Title -->
    <div style="display:flex;align-items:center;gap:12px;padding:0 16px;border-right:1px solid rgba(255,255,255,.18);flex-shrink:0;">
      <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms"
           style="width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 1px 3px rgba(0,0,0,.24));">
      <div>
        <div style="font-size:8.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:rgba(247,250,236,.72);line-height:1;margin-bottom:3px;">Government of Belize &middot; Ministry of Finance</div>
        <div style="font-size:18px;font-weight:900;color:#fdfef8;letter-spacing:-.02em;line-height:1.1;">Treasury Revenue System</div>
      </div>
    </div>

    <!-- Branch -->
    <div style="display:flex;align-items:baseline;gap:6px;padding:0 18px;border-right:1px solid rgba(255,255,255,.16);flex-shrink:0;">
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(247,250,236,.7);">Branch</span>
      <span style="font-size:14px;font-weight:800;color:#f7faec;"><?= htmlspecialchars($branchName) ?></span>
    </div>

    <?php if ($terminalName): ?>
    <!-- Terminal -->
    <div style="display:flex;align-items:baseline;gap:6px;padding:0 18px;border-right:1px solid rgba(255,255,255,.16);flex-shrink:0;">
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(247,250,236,.7);">Terminal</span>
      <span style="font-size:14px;font-weight:800;color:#f7faec;"><?= htmlspecialchars($terminalName) ?></span>
    </div>
    <?php endif; ?>

    <!-- Shift # -->
    <div style="display:flex;align-items:baseline;gap:6px;padding:0 18px;flex-shrink:0;">
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(247,250,236,.7);">Shift</span>
      <span style="font-size:13px;font-weight:800;font-family:monospace;color:#eef6de;"><?= htmlspecialchars($shiftId) ?></span>
    </div>

    <div style="flex:1;"></div>

    <!-- New Pay-In shortcut -->
    <a href="<?= url('views/pay-in/pay-in-new.php') ?>" target="_blank"
       style="display:flex;align-items:center;gap:6px;margin-right:12px;padding:7px 13px;border-radius:8px;background:rgba(247,250,236,.12);border:1px solid rgba(247,250,236,.24);color:#f7faec;text-decoration:none;font-size:11px;font-weight:800;letter-spacing:.05em;flex-shrink:0;transition:background .15s,color .15s;"
       onmouseover="this.style.background='rgba(247,250,236,.2)';this.style.color='#ffffff'" onmouseout="this.style.background='rgba(247,250,236,.12)';this.style.color='#f7faec'">
      <svg style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Pay-In
      <svg style="width:9px;height:9px;opacity:.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
    </a>

    <!-- Cashier -->
    <div style="display:flex;align-items:center;gap:12px;padding-left:16px;border-left:1px solid rgba(255,255,255,.18);flex-shrink:0;">
      <div style="width:30px;height:30px;border-radius:50%;background:rgba(247,250,236,.15);border:1.5px solid rgba(247,250,236,.24);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg style="width:15px;height:15px;color:rgba(247,250,236,.86);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div>
        <div style="font-size:13px;font-weight:800;color:#ffffff;line-height:1.2;"><?= htmlspecialchars($userName) ?></div>
        <?php if ($roleName): ?>
        <div style="font-size:10.5px;color:rgba(247,250,236,.72);line-height:1.1;"><?= htmlspecialchars($roleName) ?></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</header>

<!-- ===== HEADER LAYER 2: OPERATIONAL STATUS & CONTROLS ===== -->
<div class="shrink-0" style="background:#355b2a;border-bottom:1px solid rgba(24,44,16,.2);">
  <div style="display:flex;align-items:center;height:40px;padding:0 20px;">

    <!-- Left: Live status -->
    <div style="display:flex;align-items:center;flex:1;overflow:hidden;">

      <!-- Session status -->
      <div style="display:flex;align-items:center;gap:7px;padding-right:16px;border-right:1px solid rgba(247,250,236,.16);flex-shrink:0;">
        <span id="ss-dot" style="width:8px;height:8px;border-radius:50%;background:#b7f08e;box-shadow:0 0 0 2px rgba(183,240,142,.22);flex-shrink:0;"></span>
        <span id="ss-label" style="font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#d9f3b9;">ACTIVE</span>
      </div>

      <?php if ($shiftStart): ?>
      <!-- Since -->
      <div style="display:flex;align-items:baseline;gap:5px;padding:0 16px;border-right:1px solid rgba(247,250,236,.16);flex-shrink:0;">
        <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(247,250,236,.72);">Since</span>
        <span style="font-size:12px;font-weight:700;color:#f1f8e7;"><?= date('h:i A', strtotime($shiftStart)) ?></span>
      </div>
      <?php endif; ?>

      <!-- Live clock -->
      <div style="padding-left:16px;">
        <span style="font-size:11px;font-weight:700;color:rgba(247,250,236,.78);font-family:monospace;" id="hdr-sync-time">--:--:--</span>
      </div>

    </div>

    <!-- Right: Action buttons -->
    <div style="display:flex;align-items:center;gap:3px;padding-left:14px;border-left:1px solid rgba(255,255,255,.09);flex-shrink:0;">
      <button type="button" id="btn-recent-tx" onclick="headerOpenRecentTransactions()" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Recent</button>
      <button type="button" id="btn-day-sales" onclick="headerOpenDaySales()" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.3);color:#bbf7d0;cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">My Sales</button>
      <button type="button" id="btn-totals" onclick="headerOpenTotals()" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Totals</button>
      <button id="btn-lock" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Lock</button>
      <button id="btn-supervisor" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Supervisor</button>
      <div style="width:1px;height:13px;background:rgba(255,255,255,.1);margin:0 2px;"></div>
      <button type="button" id="btn-end-shift" onclick="headerOpenEndShift()" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(220,38,38,.18);border:1px solid rgba(220,38,38,.32);color:#fca5a5;cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">End Shift</button>
    </div>

  </div>
<!-- MAIN CONTENT -->
<div class="flex flex-1 overflow-hidden bg-[#f6f8f1]">
  <div class="flex-1 overflow-hidden p-3 lg:p-4">
    <div class="h-full grid grid-cols-1 xl:grid-cols-[290px,minmax(0,1fr)] gap-3">
      <div class="bg-white border border-[#dbe5d2] rounded-[24px] flex flex-col overflow-hidden min-h-0" id="payer-column">
        <div class="px-4 py-4 border-b border-[#e6eee0] bg-[#fbfcf8]">
          <div class="flex items-center gap-2">
            <button id="payer-mode-customer" class="flex-1 py-2 rounded-xl bg-[#1e4620] text-white text-xs font-bold uppercase tracking-wide">Customer</button>
            <button id="payer-mode-department" class="flex-1 py-2 rounded-xl bg-white border border-gray-200 text-gray-600 text-xs font-bold uppercase tracking-wide">Department</button>
          </div>
          <div id="payer-search-controls">
            <div class="mt-3">
              <input type="text" id="payer-search-input" placeholder="Search payer..." class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white">
            </div>
            <button id="btn-inline-add-cust" class="mt-3 w-full py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-bold">+ New Customer</button>
            <div id="payer-search-empty" class="hidden mt-3 text-xs text-gray-400 text-center">No payer results.</div>
          </div>
          <button id="btn-change-payer-search" class="hidden mt-3 w-full py-2.5 rounded-xl bg-white border border-gray-200 text-slate-600 text-sm font-bold">Change Payer</button>
        </div>
        <div id="payer-results-wrap" class="flex-1 overflow-y-auto p-3 min-h-0">
          <div id="payer-results" class="space-y-2"></div>
        </div>
        <div class="px-4 py-4 border-t border-[#e6eee0] bg-[#fbfcf8]">
          <div class="flex items-center justify-between gap-3 mb-2">
            <div class="text-[10px] font-black uppercase tracking-[0.22em] text-[#62725f]">Selected Payer</div>
            <button id="btn-clear-payer" class="text-xs py-1 px-2 border border-red-200 rounded bg-red-50 text-red-600 font-semibold" style="display:none;">Clear</button>
          </div>
          <div id="payer-placeholder" class="text-sm text-gray-400 italic mb-3">No payer selected</div>
          <div id="payer-display" class="text-sm font-semibold text-gray-900 mb-3" style="display:none;"></div>
          <div id="payer-info-panel" class="rounded-2xl border overflow-hidden" style="display:none;">
            <div id="payer-info-header" class="px-3 py-2 flex items-center justify-between">
              <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Payer Details</div>
              <div id="payer-verify-badge"></div>
            </div>
            <div id="payer-info-body" class="px-3 py-3 grid grid-cols-2 gap-2"></div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-3 min-h-0 overflow-hidden">
      <div id="receipt-card" class="hidden bg-white border border-[#dbe5d2] rounded-[24px] flex flex-col overflow-hidden shrink-0">
        <div class="px-4 py-3 border-b border-[#e6eee0] bg-[#fbfcf8]">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div id="receipt-header-block" class="min-w-0">
              <div class="text-[10px] font-black uppercase tracking-[0.22em] text-[#62725f]">Current Receipt</div>
              <div class="text-sm font-semibold text-slate-900 mt-1" id="receipt-workspace-payer">No payer selected</div>
            </div>
            <div id="receipt-summary-actions" class="flex flex-wrap items-center gap-2 lg:justify-end">
              <button type="button" id="btn-view-receipt-items" class="px-3 py-2 rounded-xl bg-white border border-[#dbe5d2] text-xs font-semibold text-slate-700 hover:border-[#1e4620] hover:bg-[#f6faf2] transition-all inline-flex items-center gap-1.5 cursor-pointer" title="View charged items and payment details">
                <span id="cart-count">0 items</span>
                <svg class="w-3 h-3 text-[#62725f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </button>
              <div class="px-3 py-2 rounded-xl bg-white border border-[#dbe5d2] text-xs font-semibold text-slate-700">Total Charged: <span id="cart-total">$0.00</span></div>
            </div>
          </div>
        </div>
          <div id="inline-service-entry" class="hidden mx-4 my-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
            <div class="flex items-center gap-2 mb-4">
              <div id="inline-wizard-step-details-badge" class="px-3 py-1 rounded-full bg-[#1e4620] text-white text-[10px] font-black uppercase tracking-[0.18em]">1. Amount</div>
              <div class="h-px flex-1 bg-emerald-200"></div>
              <div id="inline-wizard-step-payment-badge" class="px-3 py-1 rounded-full bg-white border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-[0.18em]">2. Beneficiary</div>
              <div class="h-px flex-1 bg-emerald-200"></div>
              <div id="inline-wizard-step-instructions-badge" class="px-3 py-1 rounded-full bg-white border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-[0.18em]">3. Payment</div>
              <div class="h-px flex-1 bg-emerald-200"></div>
              <div id="inline-wizard-step-success-badge" class="px-3 py-1 rounded-full bg-white border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-[0.18em]">4. Finalize</div>
            </div>
            <div id="inline-service-step-details" class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Selected Service</div>
                <div id="inline-service-name" class="text-base font-bold text-slate-900 mt-2">--</div>
                <div id="inline-service-code" class="hidden text-xs font-mono text-slate-500 mt-1"></div>
                <div id="inline-service-cost-center" class="hidden text-xs text-emerald-800 font-semibold mt-1"></div>
              </div>
              <div class="w-full lg:w-72 shrink-0">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2">Amount</label>
                <div class="flex items-stretch w-full border border-emerald-200 rounded-xl overflow-hidden bg-white">
                  <span class="px-3 py-3 bg-emerald-50 border-r border-emerald-200 text-xs font-bold text-emerald-800">BZD $</span>
                  <input type="number" id="inline-service-amount" step="0.01" min="0.01" placeholder="0.00" class="min-w-0 flex-1 px-3 py-3 text-lg font-semibold outline-none bg-white">
                </div>
                <div class="flex gap-2 mt-3">
                  <button id="btn-inline-service-cancel" class="flex-1 py-2.5 rounded-xl bg-white border border-gray-200 text-slate-600 text-sm font-semibold">Cancel</button>
                  <button id="btn-inline-service-continue" class="flex-1 py-2.5 rounded-xl bg-[#1e4620] text-white text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed" disabled>Continue To Payment</button>
                </div>
              </div>
            </div>
            <div id="inline-service-step-payment" class="hidden space-y-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Beneficiary &amp; Payment Method</div>
                <div class="text-sm text-slate-600 mt-1">Enter the beneficiary, then choose how this charge will be paid.</div>
              </div>
              <button id="btn-inline-wizard-back" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-slate-600">Back To Amount</button>
            </div>
              <div class="rounded-2xl border border-emerald-200 bg-white overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1.25fr)]">
                  <div class="p-4 lg:pr-5 lg:border-r lg:border-emerald-200">
                    <div class="js-inline-payment-summary text-sm text-slate-700">No service selected yet.</div>
                  </div>
                  <div class="p-4 lg:pl-5 space-y-4">
                    <div>
                      <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Beneficiary Name *</div>
                        <button id="btn-beneficiary-toggle" type="button" class="px-2 py-1 rounded-lg border border-gray-200 bg-white text-[10px] font-bold uppercase tracking-widest text-slate-600">Edit</button>
                      </div>
                      <input type="text" id="page-beneficiary" placeholder="Name on receipt" readonly class="w-full px-3 py-2.5 border rounded-xl text-sm outline-none bg-gray-50">
                    </div>
                    <div>
                      <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Payment Method *</div>
                      <div id="page-pay-methods" class="grid grid-cols-2 gap-1">
                        <button data-method="cash" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">Cash</button>
                        <button data-method="check" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">Check</button>
                        <button data-method="bank_deposit" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">Bank Deposit</button>
                        <button data-method="pos_terminal" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">POS Terminal</button>
                        <button data-method="online_transfer" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">Transfer</button>
                        <button data-method="e_invoicing" class="page-pay-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-semibold cursor-pointer transition-all text-center leading-tight">E-Invoice</button>
                      </div>
                      <button id="btn-switch-payment-method" class="hidden mt-2 w-full py-2.5 rounded-xl border border-gray-200 bg-white text-slate-600 text-sm font-semibold">Switch Payment Method</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div id="inline-service-step-instructions" class="hidden space-y-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Payment</div>
                <div class="text-sm text-slate-600 mt-1">Enter the payment details, then add this service to the receipt.</div>
              </div>
              <button id="btn-inline-instructions-back" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-slate-600">Back</button>
            </div>
              <div class="rounded-2xl border border-emerald-200 bg-white overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1.25fr)]">
                  <div class="p-4 lg:pr-5 lg:border-r lg:border-emerald-200">
                    <div class="js-inline-payment-summary text-sm text-slate-700">No service selected yet.</div>
                  </div>
                  <div class="p-4 lg:pl-5">
                    <div id="page-pay-details" class="hidden space-y-3">
                      <div id="page-pd-cash" class="hidden space-y-3">
                        <div>
                          <label class="text-[11px] font-medium text-gray-500 uppercase">Amount Tendered *</label>
                          <input type="number" id="page-cash-tendered" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none">
                        </div>
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center justify-between">
                          <span class="text-sm font-semibold text-emerald-900">Change Due</span>
                          <span id="page-cash-change" class="text-lg font-black text-emerald-900">BZD $0.00</span>
                        </div>
                      </div>
                      <div id="page-pd-check" class="hidden space-y-3">
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Check Number *</label><input type="text" id="page-check-number" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Name *</label><input type="text" id="page-check-bank" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Account Holder</label><input type="text" id="page-check-holder" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                      </div>
                      <div id="page-pd-bank-deposit" class="hidden space-y-3">
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="page-bd-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm bg-gray-50"><option value="">-- Select --</option></select></div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                          Bank deposit payment generates customer instructions. The system will prepare the bank account number, reference, purpose of payment, and amount due for print or email delivery.
                        </div>
                        <button id="btn-generate-reference" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-brand-600 text-white text-xs font-bold uppercase tracking-wide shadow disabled:opacity-30 disabled:cursor-not-allowed" disabled>Generate Payment Reference</button>
                      </div>
                      <div id="page-pd-online-transfer" class="hidden space-y-3">
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="page-ot-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm bg-gray-50"><option value="">-- Select --</option></select></div>
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Reference Number *</label><input type="text" id="page-ot-ref" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Sender Name *</label><input type="text" id="page-ot-sender" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Amount *</label><input type="number" id="page-ot-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div id="page-pay-error" class="mx-4 mt-3 text-red-600 text-sm bg-red-50 rounded-xl px-4 py-3 hidden"></div>
                <div id="inline-charge-footer" class="px-4 py-4 border-t border-emerald-200 bg-white">
                  <div class="flex flex-col sm:flex-row gap-2">
                    <button id="btn-charge-inline" class="flex-1 py-3 rounded-2xl bg-brand-600 text-white text-sm font-bold uppercase tracking-wider shadow disabled:opacity-30 disabled:cursor-not-allowed" disabled>
                      Charge &amp; Add to Receipt
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div id="inline-service-step-success" class="hidden space-y-4">
              <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-[#1e4620] text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                  </div>
                  <div class="min-w-0">
                    <div class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Service Added To Receipt</div>
                    <div id="inline-success-message" class="text-sm text-slate-600 mt-0.5">Start a new transaction or finalize and print the receipt.</div>
                  </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 mt-4">
                  <button id="btn-inline-new-transaction" class="flex-1 py-3 rounded-2xl bg-white border border-emerald-300 text-emerald-800 text-sm font-bold uppercase tracking-wide">New Transaction</button>
                  <button id="btn-checkout" class="flex-1 py-3 rounded-2xl bg-[#1e4620] text-white text-sm font-bold uppercase tracking-wide shadow disabled:opacity-50">Finalize Transaction and Print Receipt</button>
                </div>
              </div>
            </div>
          </div>
      </div>

      <div class="bg-white border border-[#dbe5d2] rounded-[24px] flex-1 flex flex-col overflow-hidden min-h-0">
        <div id="pos-step-services" class="flex-1 flex flex-col min-h-0">
          <div class="px-4 py-3 border-b border-[#eef3ea] bg-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
              <div class="text-[10px] font-black uppercase tracking-[0.22em] text-[#62725f]">Services</div>
              <input type="text" id="search-services" placeholder="Search services..." class="w-full max-w-md px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm">
            </div>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-5 min-h-0" id="services-container">
            <div id="services-loading" class="text-center text-gray-400 py-10 text-sm">Loading services...</div>
            <div id="services-empty" class="hidden text-center text-gray-400 py-10 text-sm">No services available.</div>
            <div id="favorites-section" class="hidden">
              <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Favorite Services</div>
              <div class="flex gap-3 pb-1" id="favorites-grid"></div>
            </div>
            <div>
              <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Available Services</div>
              <div class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3" id="services-grid"></div>
            </div>
          </div>
        </div>

        <div id="pos-step-payment" class="hidden flex-1 flex flex-col min-h-0 bg-[#fcfdf9]">
          <div class="px-4 py-3 border-b border-[#e6eee0] bg-white flex items-center justify-between gap-3">
            <div>
              <div class="text-[10px] font-black uppercase tracking-[0.22em] text-[#62725f]">Payment</div>
              <div class="text-sm text-slate-500 mt-1">Complete this service line, then continue or print the receipt.</div>
            </div>
            <button id="btn-back-to-services" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-slate-600">Back To Services</button>
          </div>
          <div class="flex-1 overflow-y-auto p-4 min-h-0">
            <div class="grid grid-cols-1 xl:grid-cols-[320px,minmax(0,1fr)] gap-4 min-h-full">
              <div class="space-y-4">
                <div id="active-service-card" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-400">
                  No service selected yet.
                </div>
                <div class="rounded-2xl border border-[#dbe5d2] bg-white px-4 py-4">
                  <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Receipt Preview</div>
                  <div class="text-sm text-gray-700" id="receipt-preview-payer">No payer selected</div>
                  <div class="text-xs text-gray-400 mt-1" id="receipt-preview-beneficiary">Beneficiary: --</div>
                  <div id="cart-empty" class="text-center text-gray-400 py-6 text-sm italic">No items added yet.</div>
                  <div id="cart-list" class="space-y-2 mt-3" style="display:none;"></div>
                </div>
              </div>

              <div id="payment-form-panel" class="hidden"></div>
            </div>
          </div>
        </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- ===== MODALS ===== -->

<div id="receipt-locked-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('receipt-locked-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">
      <div class="px-5 py-4 border-b flex items-start justify-between gap-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">Current Receipt Must Be Completed First</h3>
          <div class="text-sm text-slate-500 mt-1">This receipt already has paid items for another payer. Print or clear it before changing payer.</div>
        </div>
        <button onclick="document.getElementById('receipt-locked-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="flex-1 overflow-y-auto p-5 space-y-5">
        <div class="rounded-2xl border border-[#dbe5d2] bg-[#fbfcf8] px-4 py-4">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Current Receipt Payer</div>
          <div id="receipt-locked-payer" class="text-sm font-semibold text-slate-900">--</div>
        </div>
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Paid Items</div>
          <div id="receipt-locked-items" class="space-y-2"></div>
        </div>
      </div>
      <div class="border-t px-5 py-4 bg-gray-50 flex gap-2">
        <button id="btn-close-receipt-locked" class="flex-1 py-2.5 rounded-xl bg-white border border-gray-200 text-slate-700 text-sm font-semibold">Close</button>
        <button id="btn-print-receipt-locked" class="flex-1 py-2.5 rounded-xl bg-[#1e4620] text-white text-sm font-bold">Print Receipt</button>
      </div>
    </div>
  </div>
</div>

<!-- Current Receipt Items -->
<div id="receipt-items-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('receipt-items-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">
      <div class="px-5 py-4 border-b flex items-start justify-between gap-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">Charged Items</h3>
          <div class="text-sm text-slate-500 mt-1" id="receipt-items-subtitle">Items on the current receipt and their payment details.</div>
        </div>
        <button onclick="document.getElementById('receipt-items-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="px-5 py-3 bg-[#fbfcf8] border-b border-[#e6eee0] flex items-center justify-between gap-3">
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Payer</div>
          <div id="receipt-items-payer" class="text-sm font-semibold text-slate-900">--</div>
        </div>
        <div class="text-right">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Charged</div>
          <div id="receipt-items-total" class="text-lg font-black text-[#1e4620]">BZD $0.00</div>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto p-5 space-y-3" id="receipt-items-list"></div>
      <div class="border-t px-5 py-4 bg-gray-50 flex gap-2">
        <button id="btn-close-receipt-items" class="flex-1 py-2.5 rounded-xl bg-white border border-gray-200 text-slate-700 text-sm font-semibold">Close</button>
        <button id="btn-finalize-from-items" class="flex-1 py-2.5 rounded-xl bg-[#1e4620] text-white text-sm font-bold">Finalize &amp; Print Receipt</button>
      </div>
    </div>
  </div>
</div>

<!-- Amount Entry -->
<div id="amt-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('amt-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-base font-bold">Enter Amount</h3>
        <button onclick="document.getElementById('amt-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="p-5 space-y-4">
        <!-- Service info block -->
        <div class="bg-gray-50 rounded-xl p-4 space-y-1.5">
          <div class="text-sm font-bold text-gray-900" id="amt-service-name"></div>
          <div id="amt-service-code" class="text-[10px] font-mono text-gray-400 hidden"></div>
          <div id="amt-cost-center" class="text-xs text-brand-700 font-medium hidden"></div>
          <div id="amt-revenue-code" class="hidden"><span class="text-[10px] bg-blue-50 text-blue-600 rounded px-1.5 py-0.5 font-mono"></span></div>
          <div id="amt-description" class="text-xs text-gray-500 leading-relaxed hidden"></div>
        </div>
        <!-- Amount input -->
        <div>
          <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1.5">Amount</label>
          <div id="amt-input-wrap" class="flex items-stretch w-full border rounded-xl overflow-hidden focus-within:border-brand-400 transition-colors bg-white">
            <span class="px-2.5 py-3 bg-gray-50 border-r text-xs font-bold text-gray-500 shrink-0 whitespace-nowrap">BZD $</span>
            <input type="number" id="amt-input" step="0.01" min="0.01" placeholder="0.00"
                   class="min-w-0 flex-1 w-full px-3 py-3 text-lg font-semibold text-center outline-none bg-white">
          </div>
        </div>
        <div class="flex gap-2">
          <button onclick="document.getElementById('amt-modal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm cursor-pointer">Cancel</button>
          <button id="btn-amt-add" class="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold cursor-pointer">Continue</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Service Payment -->
<div id="service-payment-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('service-payment-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-start justify-center pt-6 px-4 pb-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[92vh] overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
        <h3 class="text-base font-bold">Charge Service</h3>
        <button onclick="document.getElementById('service-payment-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="shrink-0 px-5 py-3 border-b bg-slate-50">
        <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wider">
          <span id="sp-step-pill-payer" class="px-3 py-1 rounded-full bg-slate-900 text-white">1. Payer</span>
          <span id="sp-step-pill-service" class="px-3 py-1 rounded-full bg-slate-200 text-slate-600">2. Service</span>
          <span id="sp-step-pill-payment" class="px-3 py-1 rounded-full bg-slate-200 text-slate-600">3. Payment</span>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto grid grid-cols-1 lg:grid-cols-[340px,1fr] bg-[#fcfdf8]">
        <div class="bg-[#f4f8ef] p-5 space-y-5 border-r border-[#dde8d6]">
          <div>
            <div class="text-[10px] font-black uppercase tracking-[0.24em] text-[#6c7f62] mb-3">Receipt Context</div>
            <div class="rounded-2xl bg-white border border-[#dde8d6] p-4 shadow-sm">
              <div class="text-[10px] uppercase tracking-[0.18em] text-[#7b8f70] mb-2">Selected Payer</div>
              <div id="sp-payer-display" class="text-sm font-semibold text-slate-900"></div>
              <div id="sp-payer-summary" class="mt-3 text-xs text-slate-600 space-y-1"></div>
            </div>
          </div>
          <div>
            <div class="text-[10px] font-black uppercase tracking-[0.24em] text-[#6c7f62] mb-3">Service Details</div>
            <div id="sp-item-card" class="rounded-2xl bg-white border border-[#dde8d6] p-4 space-y-3 shadow-sm"></div>
          </div>
          <div class="rounded-2xl bg-[#e8f3e8] border border-[#cfe0c8] px-4 py-3">
            <div class="text-[10px] uppercase tracking-[0.16em] text-[#477045] mb-1">Receipt Total Impact</div>
            <div class="text-2xl font-black text-[#1e4620]" id="sp-total">$0.00</div>
            <div class="text-xs text-[#547352] mt-1">This paid line will be added to the current receipt draft.</div>
          </div>
        </div>
        <div class="px-5 py-5 space-y-5 bg-white">
          <div id="sp-step-payer" class="space-y-4">
            <div>
              <div class="text-sm font-bold text-slate-900">Select The Payer First</div>
              <div class="text-sm text-slate-500 mt-1">A service cannot be charged until a customer or department is attached to the receipt.</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <button id="btn-sp-select-customer" class="text-left rounded-2xl border border-slate-200 px-4 py-4 hover:border-brand-400 hover:bg-brand-50 transition-all">
                <div class="text-sm font-bold text-slate-900">Choose Customer</div>
                <div class="text-xs text-slate-500 mt-1">Search or create a customer record for this receipt.</div>
              </button>
              <button id="btn-sp-select-dept" class="text-left rounded-2xl border border-slate-200 px-4 py-4 hover:border-brand-400 hover:bg-brand-50 transition-all">
                <div class="text-sm font-bold text-slate-900">Choose Department</div>
                <div class="text-xs text-slate-500 mt-1">Use a government department as the payer on this receipt.</div>
              </button>
            </div>
            <div class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
              The selected payer name will appear on the official receipt before payment is accepted.
            </div>
          </div>

          <div id="sp-step-service" class="space-y-4 hidden">
            <div>
              <div class="text-sm font-bold text-slate-900">Confirm Service Details</div>
              <div class="text-sm text-slate-500 mt-1">Review the payer and service before moving to payment.</div>
            </div>
            <div id="sp-service-review" class="rounded-2xl border border-slate-200 bg-slate-50 p-4"></div>
            <div class="flex justify-end">
              <button id="btn-sp-continue-to-payment" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold uppercase tracking-wide">Continue To Payment</button>
            </div>
          </div>

          <div id="sp-step-payment" class="space-y-4 hidden">
            <div>
              <div class="text-sm font-bold text-slate-900">Collect Payment</div>
              <div class="text-sm text-slate-500 mt-1">Choose the payment method for this specific service line.</div>
            </div>

            <div>
              <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Payment Method *</div>
              <div class="grid grid-cols-2 xl:grid-cols-3 gap-2">
                <button data-method="cash" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>Cash</button>
                <button data-method="check" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Check</button>
                <button data-method="bank_deposit" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Bank Deposit</button>
                <button data-method="pos_terminal" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Terminal</button>
                <button data-method="online_transfer" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-cyan-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Transfer</button>
                <button data-method="e_invoicing" class="sp-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>E-Invoice</button>
              </div>
            </div>

            <div id="sp-pay-details" class="hidden space-y-3">
              <div class="border-t border-dashed"></div>
              <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Payment Details</div>
              <div id="sp-pd-check" class="hidden space-y-3">
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Check Number *</label><input type="text" id="sp-check-number" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Name *</label><input type="text" id="sp-check-bank" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Account Holder</label><input type="text" id="sp-check-holder" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
              </div>
              <div id="sp-pd-bank-deposit" class="hidden space-y-3">
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="sp-bd-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50"><option value="">— Select —</option></select></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Reference Number *</label><input type="text" id="sp-bd-ref" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Amount Deposited *</label><input type="number" id="sp-bd-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
              </div>
              <div id="sp-pd-online-transfer" class="hidden space-y-3">
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="sp-ot-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50"><option value="">— Select —</option></select></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Reference Number *</label><input type="text" id="sp-ot-ref" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Sender Name *</label><input type="text" id="sp-ot-sender" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
                <div><label class="text-[11px] font-medium text-gray-500 uppercase">Amount *</label><input type="number" id="sp-ot-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
              </div>
            </div>

            <div>
              <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Beneficiary Name</div>
              <input type="text" id="sp-beneficiary" placeholder="Name on receipt (optional)" class="w-full px-4 py-2.5 border rounded-xl text-sm outline-none focus:border-brand-400">
            </div>
          </div>

          <div id="sp-error" class="text-red-600 text-sm bg-red-50 rounded-xl px-4 py-3 hidden"></div>
        </div>
      </div>

      <div class="shrink-0 border-t px-5 py-4 bg-gray-50 rounded-b-2xl">
        <button id="btn-confirm-service-payment" class="w-full py-3.5 rounded-2xl bg-brand-600 text-white text-sm font-bold uppercase tracking-wider shadow cursor-pointer hover:bg-brand-700 active:scale-95 transition-all disabled:opacity-30 disabled:cursor-not-allowed" disabled>
          Charge &amp; Add to Receipt
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Customer Search -->
<div id="cust-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('cust-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-start justify-center pt-10 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl relative">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-base font-bold">Search Customer</h3>
        <div class="flex items-center gap-2">
          <button id="btn-open-add-cust" class="px-3 py-1.5 rounded-xl border border-green-300 text-green-700 text-[11px] font-bold uppercase hover:bg-green-600 hover:text-white transition-all cursor-pointer">+ New</button>
          <button onclick="document.getElementById('cust-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
      </div>
      <div class="p-4">
        <input type="text" id="cust-search-input" placeholder="Search by name, email, phone…" class="w-full px-4 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50">
        <div id="cust-results" class="mt-3 max-h-72 overflow-y-auto space-y-1"></div>
        <div id="cust-no-results" class="hidden mt-3 text-center py-4 text-sm text-gray-400">No customers found.</div>
      </div>
    </div>
  </div>
</div>

<!-- Add Customer -->
<div id="add-cust-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('add-cust-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-start justify-center pt-16 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
      <div class="px-5 py-4 border-b"><h3 class="text-base font-bold">New Customer</h3></div>
      <div class="p-5 space-y-3">
        <div><label class="text-[11px] font-medium text-gray-500 uppercase">First Name *</label><input type="text" id="nc-fn" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Last Name</label><input type="text" id="nc-ln" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Phone</label><input type="text" id="nc-ph" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
        <div><label class="text-[11px] font-medium text-gray-500 uppercase">Email</label><input type="email" id="nc-em" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
        <div id="add-cust-alert" class="text-red-500 text-xs hidden"></div>
        <div class="flex gap-2 pt-1">
          <button onclick="document.getElementById('add-cust-modal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 cursor-pointer">Cancel</button>
          <button id="btn-save-cust" class="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 cursor-pointer">Save</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Department Search -->
<div id="dept-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('dept-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-start justify-center pt-10 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-base font-bold">Search Department</h3>
        <button onclick="document.getElementById('dept-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="p-4">
        <input type="text" id="dept-search-input" placeholder="Search by name or code…" class="w-full px-4 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50">
        <div id="dept-results" class="mt-3 max-h-72 overflow-y-auto space-y-1"></div>
        <div id="dept-no-results" class="hidden mt-3 text-center py-4 text-sm text-gray-400">No departments found.</div>
      </div>
    </div>
  </div>
</div>


<!-- Checkout Modal -->
<div id="checkout-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('checkout-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-start justify-center pt-6 px-4 pb-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[92vh]">
      <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
        <h3 class="text-base font-bold">Print Receipt</h3>
        <button onclick="document.getElementById('checkout-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

        <!-- Items -->
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Items</div>
          <div id="co-items-list" class="space-y-2 bg-gray-50 rounded-xl p-3"></div>
          <div class="flex justify-between items-center pt-3 px-3">
            <span class="text-sm font-bold text-gray-700">Total</span>
            <span class="text-lg font-black text-gray-900" id="co-total">$0.00</span>
          </div>
        </div>

        <div class="border-t border-dashed"></div>

        <!-- Payer -->
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Payer</div>
          <div id="co-payer-display" class="text-sm font-semibold text-gray-700 bg-gray-50 rounded-xl px-4 py-3"></div>
        </div>

        <div class="border-t border-dashed"></div>

        <!-- Payment Method -->
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Payment Method *</div>
          <div class="grid grid-cols-3 gap-2">
            <button data-method="cash"            class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>Cash</button>
            <button data-method="check"           class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Check</button>
            <button data-method="bank_deposit"    class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Bank Deposit</button>
            <button data-method="pos_terminal"    class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Terminal</button>
            <button data-method="online_transfer" class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-cyan-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Transfer</button>
            <button data-method="e_invoicing"     class="co-pay-btn flex flex-col items-center gap-1 px-2 py-3 rounded-xl border text-gray-700 text-[11px] font-medium cursor-pointer transition-all"><svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>E-Invoice</button>
          </div>
        </div>

        <!-- Payment Details -->
        <div id="co-pay-details" class="hidden space-y-3">
          <div class="border-t border-dashed"></div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Payment Details</div>
          <div id="pd-check" class="hidden space-y-3">
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Check Number *</label><input type="text" id="pd-check-number" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Name *</label><input type="text" id="pd-check-bank" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Account Holder</label><input type="text" id="pd-check-holder" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
          </div>
          <div id="pd-bank-deposit" class="hidden space-y-3">
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="pd-bd-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50"><option value="">— Select —</option></select></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Reference Number *</label><input type="text" id="pd-bd-ref" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Amount Deposited *</label><input type="number" id="pd-bd-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
          </div>
          <div id="pd-online-transfer" class="hidden space-y-3">
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Bank Account *</label><select id="pd-ot-bank" class="w-full mt-1 px-3 py-2.5 border rounded-lg text-sm outline-none focus:border-brand-400 bg-gray-50"><option value="">— Select —</option></select></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Reference Number *</label><input type="text" id="pd-ot-ref" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Sender Name *</label><input type="text" id="pd-ot-sender" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
            <div><label class="text-[11px] font-medium text-gray-500 uppercase">Amount *</label><input type="number" id="pd-ot-amount" step="0.01" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400"></div>
          </div>
        </div>

        <div class="border-t border-dashed"></div>

        <!-- Beneficiary -->
        <div>
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Beneficiary Name</div>
          <input type="text" id="co-beneficiary" placeholder="Name on receipt (optional)" class="w-full px-4 py-2.5 border rounded-xl text-sm outline-none focus:border-brand-400">
        </div>

        <div id="co-error" class="text-red-600 text-sm bg-red-50 rounded-xl px-4 py-3 hidden"></div>
      </div>

      <div class="shrink-0 border-t px-5 py-4 bg-gray-50 rounded-b-2xl">
        <button id="btn-confirm-checkout" class="w-full py-3.5 rounded-2xl bg-brand-600 text-white text-sm font-bold uppercase tracking-wider shadow cursor-pointer hover:bg-brand-700 active:scale-95 transition-all disabled:opacity-30 disabled:cursor-not-allowed" disabled>
          Finalize &amp; Print
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt -->
<div id="receipt-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm no-print"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4 py-6" id="receipt-print-root">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[95vh]" id="rct-paper">

      <!-- Screen-only toolbar -->
      <div class="no-print shrink-0 flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50 rounded-t-2xl">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Official Receipt</span>
        <div class="flex items-center gap-2">
          <select id="receipt-print-format" class="px-2 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-semibold text-slate-600">
            <option value="thermal">Thermal 80mm</option>
            <option value="half-letter" selected>Half Letter</option>
            <option value="a5">A5</option>
            <option value="letter">Letter</option>
          </select>
          <button id="btn-print-receipt" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold cursor-pointer hover:bg-brand-700">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
          </button>
          <button id="btn-email-receipt" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-semibold cursor-pointer hover:bg-slate-900">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Email
          </button>
          <button id="btn-close-receipt" class="w-7 h-7 rounded-lg bg-gray-200 flex items-center justify-center cursor-pointer hover:bg-gray-300">
            <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>

      <!-- Receipt content -->
      <div class="flex-1 overflow-y-auto" id="receipt-body" style="font-family:'Inter',sans-serif;">

        <!-- ===== OFFICIAL HEADER ===== -->
        <div style="background:#1e4620;padding:20px 24px 16px;text-align:center;">
          <div style="display:flex;align-items:center;justify-content:center;gap:14px;">
            <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms"
                 style="width:52px;height:52px;object-fit:contain;filter:brightness(1.1);">
            <div style="text-align:left;">
              <div style="color:#a7d9a8;font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:2px;">Government of Belize</div>
              <div style="color:#ffffff;font-size:16px;font-weight:900;line-height:1.15;letter-spacing:.01em;">Treasury Revenue System</div>
              <div style="color:#86c98a;font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-top:2px;">Ministry of Finance &amp; Economic Development</div>
            </div>
          </div>
          <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,.15);">
            <div style="display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:4px 16px;">
              <span style="color:#fff;font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;">Official Receipt</span>
            </div>
          </div>
        </div>

        <!-- ===== RECEIPT META ===== -->
        <div style="background:#f8fdf5;border-bottom:2px solid #d1e8d2;padding:10px 24px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;">
            <div>
        <!-- ===== RECEIPT META ===== -->
        <div style="background:#f8fdf5;border-bottom:2px solid #d1e8d2;padding:10px 24px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;">
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Receipt No.</div>
              <div style="font-size:13px;font-weight:900;color:#1e4620;font-family:monospace;" id="rct-number">--</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Date &amp; Time</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-datetime">--</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Branch</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-branch">--</div>
            </div>
          </div>
        </div>

        <div style="padding:0 24px;">

          <div id="rct-payment-status-banner" style="display:none;margin:14px 0 0;padding:12px 14px;border-radius:8px;border:1px solid #f5c2c7;background:#fff5f5;color:#991b1b;">
            <div id="rct-payment-status-title" style="font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;">Pending Payment</div>
            <div id="rct-payment-status-text" style="font-size:10px;line-height:1.6;margin-top:4px;">Bank deposit instructions were issued. Payment is not yet received and must not be treated as cash collected.</div>
          </div>

          <!-- ===== PAYER ===== -->
          <div style="margin:14px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
            <div style="font-size:8px;font-weight:800;color:#1e4620;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
              <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Payer Information
            </div>
            <div id="rct-payer-block" style="display:grid;grid-template-columns:1fr 1fr;gap:5px 16px;font-size:11px;"></div>
          </div>

          <!-- ===== ITEMS ===== -->
          <div style="margin:12px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
            <div style="font-size:8px;font-weight:800;color:#1e4620;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
              <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Description of Services
            </div>
            <div style="display:grid;grid-template-columns:1.2fr 1fr 86px 70px;gap:4px;background:#1e4620;color:#fff;font-size:8px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:5px 8px;border-radius:4px 4px 0 0;">
              <div>Service</div><div>Beneficiary</div><div style="text-align:center;">Payment</div><div style="text-align:right;">Amount</div>
            </div>
            <div id="rct-items" style="border:1px solid #d1e8d2;border-top:none;border-radius:0 0 4px 4px;overflow:hidden;"></div>
          </div>

          <!-- ===== TOTALS ===== -->
          <div style="margin:10px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
            <div style="display:flex;justify-content:flex-end;margin-bottom:6px;">
              <div style="background:#1e4620;color:#fff;border-radius:6px;padding:8px 16px;text-align:right;min-width:200px;">
                <div style="font-size:8px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#a7d9a8;margin-bottom:3px;">Total Amount</div>
                <div style="font-size:20px;font-weight:900;letter-spacing:.01em;" id="rct-total">BZD $0.00</div>
              </div>
            </div>
            <div style="font-size:9px;color:#4a6e4b;font-style:italic;text-align:right;" id="rct-amount-words"></div>
          </div>

          <div id="rct-payment-status-note" style="display:none;margin:10px 0 0;padding:10px 12px;border-radius:8px;background:#f8fafc;border:1px dashed #cbd5e1;color:#475569;font-size:9px;line-height:1.7;">
            Revenue from bank deposit items is recognized only after the referenced deposit is confirmed by the bank. Cashbook entry date is the date payment is received, not the date these instructions were issued.
          </div>

          <!-- ===== FOOTER ===== -->
          <!-- ===== FOOTER ===== -->
          <div style="margin:12px 0 16px;text-align:center;">
            <div style="font-size:8px;color:#4a6e4b;line-height:1.6;margin-bottom:10px;">
              This is an official receipt issued by the Government of Belize, Treasury Department.<br>
              Please retain this receipt for your records and tax purposes.<br>
              For enquiries: <strong>treasury@belize.gov.bz</strong> &nbsp;|&nbsp; Tel: +501 822-2362
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin:10px 0 6px;">
              <!-- QR placeholder -->
              <div style="width:52px;height:52px;border:2px solid #cde3ce;border-radius:4px;display:flex;align-items:center;justify-content:center;background:#f8fdf5;">
                <svg style="width:32px;height:32px;color:#6b8f6c;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                  <line x1="14" y1="14" x2="14" y2="14"/><line x1="17" y1="14" x2="21" y2="14"/><line x1="14" y1="17" x2="14" y2="21"/><line x1="21" y1="17" x2="21" y2="21"/><line x1="17" y1="17" x2="17" y2="21"/>
                </svg>
              </div>
              <div style="font-size:8px;color:#6b8f6c;text-align:left;line-height:1.7;">
                Verify this receipt at:<br>
                <strong style="font-size:9px;color:#1e4620;">treasury.gov.bz/verify</strong><br>
                Ref: <span id="rct-verify-ref" style="font-family:monospace;">—</span>
              </div>
            </div>
            <div style="font-size:8px;color:#aaa;border-top:1px solid #e8f3e8;padding-top:8px;margin-top:6px;" id="rct-processed-by"></div>
          </div>

        </div><!-- /padding wrapper -->
      </div><!-- /receipt-body -->

      <!-- Screen-only footer actions -->
      <div class="no-print shrink-0 border-t px-5 py-3 bg-gray-50 rounded-b-2xl flex gap-2">
        <button id="btn-done-receipt" class="flex-1 py-2.5 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold cursor-pointer hover:bg-gray-300">New Transaction</button>
        <button id="btn-email-receipt-2" class="flex-1 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-semibold cursor-pointer hover:bg-slate-900">
          <svg class="w-4 h-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Email Receipt
        </button>
        <button id="btn-print-receipt-2" class="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold cursor-pointer hover:bg-brand-700">
          <svg class="w-4 h-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Print Receipt
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Bank Deposit Instructions -->
<div id="bank-instructions-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm no-print" onclick="document.getElementById('bank-instructions-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4 py-6" id="instruction-print-root">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[95vh]" id="bdi-paper">
      <div class="no-print shrink-0 flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50 rounded-t-2xl">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Bank Deposit Instructions</span>
        <div class="flex items-center gap-2">
          <button id="btn-print-bank-instructions" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold cursor-pointer hover:bg-brand-700">Print</button>
          <button id="btn-email-bank-instructions" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-semibold cursor-pointer hover:bg-slate-900">Email</button>
          <button id="btn-close-bank-instructions" class="w-7 h-7 rounded-lg bg-gray-200 flex items-center justify-center cursor-pointer hover:bg-gray-300">
            <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto px-6 py-6 space-y-5" style="font-family:'Inter',sans-serif;">
        <div class="rounded-2xl border border-[#dbe5d2] overflow-hidden">
          <div class="bg-[#1e4620] px-6 py-5 text-white">
            <div class="text-[10px] font-black uppercase tracking-[0.24em] text-[#a7d9a8]">Treasury Revenue System</div>
            <div class="text-2xl font-black mt-1">Bank Deposit Instructions</div>
            <div class="text-sm text-[#d8ead8] mt-2">Provide these details to the customer so the payment can be completed at the bank.</div>
          </div>
          <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Payer</div><div class="font-semibold text-slate-900" id="bdi-payer-name">--</div></div>
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Reference Number</div><div class="font-semibold text-slate-900 font-mono" id="bdi-reference">--</div></div>
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Bank Name</div><div class="font-semibold text-slate-900" id="bdi-bank-name">--</div></div>
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Bank Account Number</div><div class="font-semibold text-slate-900 font-mono" id="bdi-bank-account-number">--</div></div>
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Purpose of Payment</div><div class="font-semibold text-slate-900" id="bdi-purpose">--</div></div>
            <div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Amount to be Paid</div><div class="font-black text-[#1e4620] text-lg" id="bdi-amount">BZD $0.00</div></div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 space-y-4 no-print">
          <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Delivery Options</div>
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" id="bdi-delivery-print" class="rounded border-slate-300" checked>
            Print copy for customer
          </label>
          <label id="bdi-delivery-email-wrap" class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" id="bdi-delivery-email" class="rounded border-slate-300">
            Email copy to customer
          </label>
          <div id="bdi-email-optin-wrap" class="text-sm text-slate-600">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="bdi-email-optin" class="rounded border-slate-300">
              Would you like to receive an email copy of the instructions?
            </label>
          </div>
          <div id="bdi-email-input-wrap" class="hidden">
            <label class="text-[11px] font-medium text-gray-500 uppercase">Email Address</label>
            <input type="email" id="bdi-email-input" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand-400" placeholder="customer@example.com">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Transactions (today, all methods) -->
<div id="recent-tx-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('recent-tx-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 overflow-y-auto flex items-start justify-center px-4 py-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-auto" style="font-family:'Inter',sans-serif;">

      <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 rounded-t-2xl">
        <div>
          <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Recent Transactions &mdash; Today</div>
          <div class="text-[11px] text-gray-400" id="rtx-subtitle">All payment methods</div>
        </div>
        <button onclick="document.getElementById('recent-tx-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>

      <div class="px-5 py-4">
        <div id="rtx-loading" class="py-10 text-center text-sm text-gray-400">Loading…</div>
        <div id="rtx-error" class="hidden py-6 text-center text-sm text-red-500"></div>
        <div id="rtx-content" class="hidden">
          <div class="border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-100 text-gray-500 text-[10px] uppercase tracking-wide">
                  <th class="text-left px-3 py-2 font-bold">Time</th>
                  <th class="text-left px-3 py-2 font-bold">Payer</th>
                  <th class="text-center px-3 py-2 font-bold">Items</th>
                  <th class="text-left px-3 py-2 font-bold">Payment</th>
                  <th class="text-right px-3 py-2 font-bold">Amount</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody id="rtx-rows"></tbody>
            </table>
          </div>
          <div id="rtx-empty" class="hidden py-8 text-center text-sm text-gray-400">No transactions recorded today.</div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3 px-5 py-3 border-t bg-gray-50 rounded-b-2xl">
        <div class="text-[11px] text-gray-500" id="rtx-footer-note">Click a row to open its official receipt.</div>
        <div class="text-sm font-bold text-gray-700" id="rtx-grand">BZD $0.00</div>
      </div>

    </div>
  </div>
</div>

<!-- Daily Cash Sales / Generate Pay-In -->
<div id="day-sales-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('day-sales-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 overflow-y-auto flex items-start justify-center px-4 py-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-auto" style="font-family:'Inter',sans-serif;">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 rounded-t-2xl">
        <div>
          <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">My Cash Sales &mdash; Today</div>
          <div class="text-[11px] text-gray-400" id="ds-date-label">—</div>
        </div>
        <button onclick="document.getElementById('day-sales-modal').classList.add('hidden')" class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-200"><svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-3 gap-3 px-5 pt-4">
        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5">
          <div class="text-[9px] font-bold uppercase tracking-wide text-emerald-600">Cash Today</div>
          <div class="text-base font-black text-emerald-800" id="ds-total-cash">BZD $0.00</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5">
          <div class="text-[9px] font-bold uppercase tracking-wide text-gray-500">Already Paid-In</div>
          <div class="text-base font-black text-gray-700" id="ds-settled-cash">BZD $0.00</div>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
          <div class="text-[9px] font-bold uppercase tracking-wide text-amber-600">To Pay-In</div>
          <div class="text-base font-black text-amber-700" id="ds-pending-cash">BZD $0.00</div>
        </div>
      </div>

      <!-- Body -->
      <div class="px-5 py-4">
        <div id="ds-loading" class="py-10 text-center text-sm text-gray-400">Loading…</div>
        <div id="ds-error" class="hidden py-6 text-center text-sm text-red-500"></div>

        <div id="ds-content" class="hidden">
          <div class="border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-100 text-gray-500 text-[10px] uppercase tracking-wide">
                  <th class="text-left px-3 py-2 font-bold">Time</th>
                  <th class="text-left px-3 py-2 font-bold">Service</th>
                  <th class="text-left px-3 py-2 font-bold">Payer</th>
                  <th class="text-right px-3 py-2 font-bold">Amount</th>
                  <th class="text-center px-3 py-2 font-bold">Status</th>
                </tr>
              </thead>
              <tbody id="ds-rows"></tbody>
            </table>
          </div>
          <div id="ds-empty" class="hidden py-8 text-center text-sm text-gray-400">No cash sales recorded today.</div>
        </div>
      </div>

      <!-- Footer / action -->
      <div class="flex items-center justify-between gap-3 px-5 py-3 border-t bg-gray-50 rounded-b-2xl">
        <div class="text-[11px] text-gray-500" id="ds-footer-note">
          Generates a pay-in for the cash you have not yet settled today.
        </div>
        <button id="btn-generate-payin" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold cursor-pointer hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
          Generate Pay-In
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Pay-In Generated Confirmation -->
<div id="payin-done-modal" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3"><svg class="w-6 h-6 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      <h3 class="text-base font-bold mb-1">Pay-In Generated</h3>
      <p class="text-sm text-gray-500 mb-1" id="pd-summary">—</p>
      <p class="text-xs text-gray-400 mb-4 font-mono" id="pd-payin-id">—</p>
      <div class="flex gap-2">
        <button onclick="document.getElementById('payin-done-modal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm cursor-pointer">Close</button>
        <a id="pd-view-link" href="#" target="_blank" class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold cursor-pointer hover:bg-emerald-700 text-center">View Pay-In</a>
      </div>
    </div>
  </div>
</div>

<!-- End Shift Confirm -->
<div id="end-shift-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('end-shift-modal').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3"><svg class="w-6 h-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      <h3 class="text-base font-bold mb-1">End Shift?</h3>
      <p class="text-sm text-gray-500 mb-4">This will close your current shift. Any unsaved cart items will be lost.</p>
      <div class="flex gap-2">
        <button onclick="document.getElementById('end-shift-modal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm cursor-pointer">Cancel</button>
        <button id="btn-confirm-end-shift" class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-semibold cursor-pointer">End Shift</button>
      </div>
    </div>
  </div>
</div>

<!-- Shift Report Modal -->
<div id="shift-report-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm no-print"></div>
  <div class="absolute inset-0 overflow-y-auto flex items-start justify-center px-4 py-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-auto sr-paper" style="font-family:'Inter',sans-serif;">

      <!-- Screen toolbar -->
      <div class="no-print flex items-center justify-between px-5 py-3 border-b bg-gray-50 rounded-t-2xl shrink-0">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">End of Shift Report</span>
        <div class="flex gap-2">
          <button id="btn-print-shift-report" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold cursor-pointer hover:bg-brand-700">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
          </button>
          <button id="btn-shift-report-done" class="px-3 py-1.5 rounded-lg bg-gray-200 text-gray-700 text-xs font-semibold cursor-pointer hover:bg-gray-300">
            Done &amp; Exit
          </button>
        </div>
      </div>

      <!-- Report body -->
      <div style="padding:28px 36px 32px;">

        <!-- Official header -->
        <div style="text-align:center;padding-bottom:16px;margin-bottom:20px;border-bottom:2px solid #1e4620;">
          <div style="display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:10px;">
            <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms" style="width:46px;height:46px;object-fit:contain;">
            <div style="text-align:left;">
              <div style="font-size:8px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4a6e4b;margin-bottom:1px;">Government of Belize</div>
              <div style="font-size:16px;font-weight:900;color:#1e4620;line-height:1.15;letter-spacing:-.01em;">Treasury Revenue System</div>
              <div style="font-size:8px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:#6b8f6c;margin-top:1px;">Ministry of Finance &amp; Economic Development</div>
            </div>
          </div>
          <div style="display:inline-block;background:#1e4620;color:#fff;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;padding:4px 20px;border-radius:4px;">End of Shift Report</div>
        </div>

        <!-- Shift identity grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;background:#f8fdf5;border:1px solid #d1e8d2;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:11px;">
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Shift ID</span><span id="sr-shift-id" style="font-family:monospace;font-weight:700;color:#1e4620;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Date</span><span id="sr-date" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Cashier</span><span id="sr-cashier" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Branch</span><span id="sr-branch" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Terminal</span><span id="sr-terminal" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Duration</span><span id="sr-duration" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Shift Opened</span><span id="sr-opened" style="font-weight:600;color:#111;"></span></div>
          <div><span style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b8f6c;display:block;margin-bottom:1px;">Shift Closed</span><span id="sr-closed" style="font-weight:600;color:#111;"></span></div>
        </div>

        <!-- Transaction summary -->
        <div style="margin-bottom:20px;">
          <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#1e4620;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
            <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Transaction Summary
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:11px;">
            <thead>
              <tr style="background:#1e4620;color:#fff;">
                <th style="text-align:left;padding:7px 10px;font-size:8.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Payment Method</th>
                <th style="text-align:center;padding:7px 10px;font-size:8.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Transactions</th>
                <th style="text-align:right;padding:7px 10px;font-size:8.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Amount (BZD)</th>
              </tr>
            </thead>
            <tbody id="sr-breakdown"></tbody>
            <tfoot>
              <tr style="background:#f0f7eb;border-top:2px solid #1e4620;">
                <td style="padding:8px 10px;font-size:11px;font-weight:900;color:#1e4620;text-transform:uppercase;letter-spacing:.05em;">Total</td>
                <td style="padding:8px 10px;font-size:12px;font-weight:900;color:#1e4620;text-align:center;" id="sr-tx-count">—</td>
                <td style="padding:8px 10px;font-size:13px;font-weight:900;color:#1e4620;text-align:right;" id="sr-total">—</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Cash drawer reconciliation -->
        <div style="margin-bottom:28px;">
          <div style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#1e4620;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
            <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Cash Drawer Reconciliation
          </div>
          <div style="border:1px solid #d1e8d2;border-radius:8px;overflow:hidden;font-size:11px;">
            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#fff;">
              <span style="color:#4b5563;">Starting Cash Float</span>
              <span id="sr-starting-cash" style="font-weight:600;color:#111;">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#f8fdf5;border-top:1px solid #e8f3e8;">
              <span style="color:#4b5563;">Cash Collected This Shift</span>
              <span id="sr-cash-collected" style="font-weight:600;color:#15803d;">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:9px 14px;background:#1e4620;border-top:1px solid #174f1a;">
              <span style="color:#a7d9a8;font-weight:700;text-transform:uppercase;font-size:9px;letter-spacing:.07em;">Expected Drawer</span>
              <span id="sr-expected-drawer" style="font-weight:900;color:#fff;font-size:13px;">—</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#fff;border-top:1px solid #e8f3e8;">
              <span style="color:#4b5563;">Actual Cash Count</span>
              <span style="color:#9ca3af;font-style:italic;">_______________</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#fff;border-top:1px solid #e8f3e8;">
              <span style="color:#4b5563;">Over / (Short)</span>
              <span style="color:#9ca3af;font-style:italic;">_______________</span>
            </div>
          </div>
        </div>

        <!-- Signature block -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:24px;">
          <div style="font-size:10.5px;">
            <div style="border-top:1px solid #374151;padding-top:6px;margin-bottom:10px;">
              <div style="font-weight:700;color:#111;margin-bottom:2px;">Cashier Signature</div>
            </div>
            <div style="color:#4b5563;line-height:2;">Name: ________________________________</div>
            <div style="color:#4b5563;line-height:2;">Date: _________________________________</div>
          </div>
          <div style="font-size:10.5px;">
            <div style="border-top:1px solid #374151;padding-top:6px;margin-bottom:10px;">
              <div style="font-weight:700;color:#111;margin-bottom:2px;">Supervisor Signature</div>
            </div>
            <div style="color:#4b5563;line-height:2;">Name: ________________________________</div>
            <div style="color:#4b5563;line-height:2;">Date: _________________________________</div>
          </div>
        </div>

        <!-- Footer -->
        <div style="border-top:1px solid #e5e7eb;padding-top:10px;text-align:center;font-size:8px;color:#9ca3af;line-height:1.8;">
          This report was generated automatically by the Treasury Revenue System on <span id="sr-generated-at"></span>.<br>
          For enquiries: <strong>treasury@belize.gov.bz</strong> &nbsp;|&nbsp; Tel: +501 822-2362
        </div>

      </div>
    </div>
  </div>
</div>

<!-- PIN Entry Modal -->
<div id="pin-modal" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6">
      <div class="text-center mb-5">
        <div class="w-12 h-12 rounded-full bg-brand-100 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-brand-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <div class="text-sm font-bold text-gray-800">Supervisor PIN Required</div>
        <div class="text-xs text-gray-400 mt-0.5" id="pin-modal-subtitle">Enter PIN to view session totals</div>
      </div>
      <!-- PIN dots -->
      <div class="flex justify-center gap-3 mb-4">
        <span class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 transition-colors" id="pd-0"></span>
        <span class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 transition-colors" id="pd-1"></span>
        <span class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 transition-colors" id="pd-2"></span>
        <span class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 transition-colors" id="pd-3"></span>
      </div>
      <div id="pin-error" class="text-red-500 text-xs text-center mb-3 hidden">Incorrect PIN. Please try again.</div>
      <!-- Numpad -->
      <div class="grid grid-cols-3 gap-2 mb-3">
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="1">1</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="2">2</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="3">3</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="4">4</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="5">5</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="6">6</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="7">7</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="8">8</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="9">9</button>
        <button id="pin-clear" class="py-3.5 rounded-xl border border-gray-200 text-xs font-medium text-gray-500 hover:bg-gray-100 cursor-pointer transition-colors">Clear</button>
        <button class="pin-key py-3.5 rounded-xl border border-gray-200 text-lg font-semibold hover:bg-gray-100 cursor-pointer transition-colors" data-k="0">0</button>
        <button id="pin-back" class="py-3.5 rounded-xl border border-gray-200 text-base font-medium text-gray-500 hover:bg-gray-100 cursor-pointer transition-colors">&#9003;</button>
      </div>
      <button id="pin-cancel" class="w-full py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-medium cursor-pointer hover:bg-gray-200 transition-colors">Cancel</button>
    </div>
  </div>
</div>

<!-- Session Totals Modal -->
<div id="totals-modal" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTotals()"></div>
  <div class="absolute inset-0 flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
      <!-- Header -->
      <div class="px-5 py-4 flex items-center justify-between" style="background:#1e4620;">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-green-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          <span class="text-sm font-bold text-white">Session Totals</span>
          <span class="text-[9px] font-mono text-green-300 ml-1" id="tl-shift-id"></span>
        </div>
        <button onclick="closeTotals()" class="w-6 h-6 rounded-lg bg-white/10 flex items-center justify-center cursor-pointer hover:bg-white/25 transition-colors">
          <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div id="tl-loading" class="py-10 text-center text-sm text-gray-400">Loading&hellip;</div>
      <div id="tl-body" class="p-5 space-y-3 hidden">
        <!-- Top stats -->
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-green-50 rounded-xl p-3 border border-green-100 text-center">
            <div class="text-[9px] font-bold text-green-600 uppercase tracking-widest mb-1">Transactions</div>
            <div class="text-2xl font-black text-green-800" id="tl-tx-count">—</div>
          </div>
          <div class="bg-blue-50 rounded-xl p-3 border border-blue-100 text-center">
            <div class="text-[9px] font-bold text-blue-600 uppercase tracking-widest mb-1">Total Revenue</div>
            <div class="text-lg font-black text-blue-800 leading-tight" id="tl-total">—</div>
          </div>
        </div>
        <!-- Drawer breakdown -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-3 py-1.5 bg-gray-100 border-b border-gray-200">
            <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Cash Drawer</div>
          </div>
          <div class="px-4 py-3 space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-xs text-gray-500">Starting Cash Float</span>
              <span class="text-sm font-semibold text-gray-700" id="tl-starting-cash">—</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-xs text-gray-500">Cash Collected</span>
              <span class="text-sm font-bold text-green-700" id="tl-cash">—</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-dashed border-gray-300">
              <span class="text-xs font-bold text-gray-800 uppercase tracking-wide">Expected Drawer</span>
              <span class="text-base font-black text-gray-900" id="tl-drawer">—</span>
            </div>
          </div>
        </div>
        <div class="text-[9px] text-gray-400 text-center font-mono" id="tl-autohide"></div>
      </div>
      <div id="tl-error" class="p-5 text-sm text-red-500 text-center hidden">Failed to load totals.</div>
    </div>
  </div>
</div>

<script>
console.log('%cPOS terminal build: my-sales+recent+receipt (2026-06-15)', 'color:#16a34a;font-weight:bold');
var SELF_URL        = <?= json_encode(url('views/pay-in/pos-terminal.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var SHIFT_START_URL = <?= json_encode(url('views/pay-in/pos-shift-start.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var POS_RECEIPT_URL = <?= json_encode(url('views/pay-in/pos-receipt.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var SHIFT_ID        = <?= json_encode((string)$shiftId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var CASHIER_NAME         = <?= json_encode($userName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var BRANCH_NAME          = <?= json_encode($branchName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var TERMINAL_NAME        = <?= json_encode($terminalName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var SHIFT_COST_CENTER_ID = <?= !empty($activeShift['cost_center_id']) ? (int)$activeShift['cost_center_id'] : 'null' ?>;
var SHIFT_BANK_ACCOUNT_ID = <?= !empty($activeShift['bank_account_id']) ? (int)$activeShift['bank_account_id'] : 'null' ?>;
var SHIFT_COST_CENTER_NAME = <?= json_encode($shiftCostCenterName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var SHIFT_BANK_ACCOUNT_NAME = <?= json_encode($shiftBankName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var CUSTOMER_PROFILE_URL = <?= json_encode(url('views/cashiering/master-data/customers/details.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Live sync clock
(function() {
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function tickClock() {
    var el = document.getElementById('hdr-sync-time');
    if (!el) return;
    var now = new Date();
    el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
  }
  tickClock();
  setInterval(tickClock, 1000);
})();

// Lock button — placeholder (blur screen)
document.getElementById('btn-lock').addEventListener('click', function() {
  var overlay = document.createElement('div');
  overlay.id = 'lock-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:999;background:rgba(15,30,15,0.97);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;';
  overlay.innerHTML = '<svg style="width:48px;height:48px;color:#6db344;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>'
    + '<div style="color:#fff;font-size:1.1rem;font-weight:700;letter-spacing:.05em;">TERMINAL LOCKED</div>'
    + '<div style="color:#8fc96a;font-size:.75rem;">Click to unlock</div>';
  overlay.addEventListener('click', function() { overlay.remove(); });
  document.body.appendChild(overlay);
});

// Supervisor button — placeholder
document.getElementById('btn-supervisor').addEventListener('click', function() {
  alert('Supervisor override: feature coming soon.');
});

function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function apiPost(data) {
  return fetch(SELF_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  }).then(function(r) { return r.json(); });
}

function headerOpenTotals() {
  if (typeof openPinModal === 'function') {
    openPinModal('totals', 'Enter supervisor PIN to view session totals');
    return;
  }
  if (typeof _pinBuffer !== 'undefined') _pinBuffer = '';
  if (typeof _pinTarget !== 'undefined') _pinTarget = 'totals';
  if (typeof updatePinDots === 'function') updatePinDots();
  var err = document.getElementById('pin-error');
  var subtitle = document.getElementById('pin-modal-subtitle');
  var modal = document.getElementById('pin-modal');
  if (err) err.classList.add('hidden');
  if (subtitle) subtitle.textContent = 'Enter supervisor PIN to view session totals';
  if (modal) modal.classList.remove('hidden');
}

function headerOpenEndShift() {
  var modal = document.getElementById('end-shift-modal');
  if (modal) modal.classList.remove('hidden');
}

function headerOpenDaySales() {
  var modal = document.getElementById('day-sales-modal');
  if (modal) modal.classList.remove('hidden');
  if (typeof openDaySalesModal === 'function') {
    openDaySalesModal();
    return;
  }
  if (typeof loadDailySales === 'function') {
    loadDailySales();
  }
}

function headerOpenRecentTransactions() {
  var modal = document.getElementById('recent-tx-modal');
  if (modal) modal.classList.remove('hidden');
  if (typeof loadRecentTransactions === 'function') {
    loadRecentTransactions();
  }
}

// ---- State ----
var cartItems       = [];
var selectedPayer   = null;
var pendingActivity = null;
var selectedPayMethod = '';
var bankAccountsCache = null;

// ---- Cart helpers ----
function cartTotal() {
  var t = 0;
  for (var i = 0; i < cartItems.length; i++) { t += parseFloat(cartItems[i].amount); }
  return t;
}

function renderCart() {
  var emptyEl  = document.getElementById('cart-empty');
  var listEl   = document.getElementById('cart-list');
  var countEl  = document.getElementById('cart-count');
  var totalEl  = document.getElementById('cart-total');
  var checkBtn = document.getElementById('btn-checkout');
  var summaryActions = document.getElementById('receipt-summary-actions');
  var headerBlock = document.getElementById('receipt-header-block');

  countEl.textContent = cartItems.length + ' item' + (cartItems.length !== 1 ? 's' : '');
  totalEl.textContent = '$' + cartTotal().toFixed(2);
  checkBtn.disabled   = cartItems.length === 0;
  if (summaryActions) summaryActions.classList.toggle('hidden', !selectedPayer && !cartItems.length);
  if (headerBlock) headerBlock.classList.toggle('hidden', !selectedPayer && !cartItems.length);

  if (!cartItems.length) {
    emptyEl.style.display = '';
    listEl.style.display  = 'none';
    return;
  }
  emptyEl.style.display = 'none';
  listEl.style.display  = 'block';

  var html = '';
  for (var i = 0; i < cartItems.length; i++) {
    var it = cartItems[i];
    var revTags = '';
    if (it.fund)          revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-blue-50 text-blue-700 border border-blue-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">Fund</span> '+escHtml(it.fund)+'</span>';
    if (it.department_name) revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-purple-50 text-purple-700 border border-purple-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">Dept</span> '+escHtml(it.department_name)+'</span>';
    if (it.revenue_code)  revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-amber-50 text-amber-700 border border-amber-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">RC</span> '+escHtml(it.revenue_code)+'</span>';
    if (it.gl_account)    revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-green-50 text-green-700 border border-green-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">GL</span> '+escHtml(it.gl_account)+'</span>';

    html += '<div class="bg-white rounded-xl p-3 border border-gray-200">'
          + '<div class="flex items-start justify-between gap-2">'
          + '<div class="flex-1 min-w-0">'
          + '<div class="text-sm font-semibold text-gray-900 truncate">'+escHtml(it.activity_name)+'</div>'
          + (it.activity_code ? '<div class="text-[10px] text-gray-400 font-mono mb-1">'+escHtml(it.activity_code)+'</div>' : '')
          + (revTags ? '<div class="flex flex-wrap gap-1">'+revTags+'</div>' : '')
          + '</div>'
          + '<div class="text-right flex flex-col items-end gap-1 shrink-0">'
          + '<div class="text-sm font-bold text-gray-900">BZD $'+parseFloat(it.amount).toFixed(2)+'</div>'
          + '<button class="cart-del-btn text-red-400 hover:text-red-600 cursor-pointer" data-idx="'+i+'" title="Remove">'
          + '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6"/></svg>'
          + '</button>'
          + '</div></div></div>';
  }
  listEl.innerHTML = html;

  listEl.querySelectorAll('.cart-del-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      cartItems.splice(parseInt(this.dataset.idx), 1);
      renderCart();
    });
  });
}

// ---- Add to cart ----
function addToCart(activity, amount) {
  cartItems.push({
    activity_id:          activity.id,
    activity_name:        activity.activity_name,
    activity_code:        activity.activity_code || '',
    amount:               amount,
    cost_center_name:     activity.cost_center_name || '',
    revenue_code:         activity.revenue_code || '',
    gl_account:           activity.gl_account || '',
    fund:                 activity.fund || '',
    department_name:      activity.department_name || activity.department_short_name || '',
  });
  var card = document.querySelector('.svc-card[data-id="'+activity.id+'"]');
  if (card) {
    card.classList.add('flash');
    setTimeout(function() { card.classList.remove('flash'); }, 400);
  }
  renderCart();
}

// ---- Services ----
var allServices = [];
var favoriteIds = [];
var selectedInlineService = null;

apiPost({action: 'load_activities'})
.then(function(data) {
  document.getElementById('services-loading').style.display = 'none';
  if (!data.success || !data.activities || !data.activities.length) {
    document.getElementById('services-empty').classList.remove('hidden');
    return;
  }
  allServices = data.activities;
  favoriteIds = (data.favorites || []).map(Number);
  renderServices(allServices);
  renderFavorites();
}).catch(function() {
  document.getElementById('services-loading').textContent = 'Failed to load services.';
});

function isFav(id) { return favoriteIds.indexOf(Number(id)) !== -1; }

function renderFavorites() {
  var section = document.getElementById('favorites-section');
  var grid    = document.getElementById('favorites-grid');
  var favs = allServices.filter(function(a) { return isFav(a.id); });
  if (!favs.length) { section.classList.add('hidden'); return; }
  section.classList.remove('hidden');
  var html = '';
  for (var i = 0; i < favs.length; i++) {
    var a = favs[i];
    var amt = a.default_amount ? '$' + parseFloat(a.default_amount).toFixed(2) : '';
    html += '<div class="svc-card flex-shrink-0 w-32 bg-amber-50 border border-amber-200 rounded-xl p-2.5 cursor-pointer"'
          + ' data-id="'+a.id+'" data-amount="'+(a.default_amount||0)+'">'
          + '<p class="text-[11px] font-semibold text-gray-800 leading-tight mb-1">'+escHtml(a.activity_name)+'</p>'
          + (amt ? '<p class="text-[10px] font-semibold text-brand-600">'+amt+'</p>' : '')
          + '</div>';
  }
  grid.innerHTML = html;
  attachServiceClicks(grid);
}

function renderServices(list) {
  var grid    = document.getElementById('services-grid');
  var emptyEl = document.getElementById('services-empty');
  if (!list.length) { grid.style.display = 'none'; emptyEl.classList.remove('hidden'); return; }
  emptyEl.classList.add('hidden');
  grid.style.display = 'grid';
  var html = '';
  for (var i = 0; i < list.length; i++) {
    var a = list[i];
    var amt     = a.default_amount ? '$' + parseFloat(a.default_amount).toFixed(2) : '';
    var starred = isFav(a.id);
    html += '<div class="svc-card bg-white rounded-xl p-3 cursor-pointer relative" data-id="'+a.id+'" data-amount="'+(a.default_amount||0)+'">'
          + '<button class="fav-btn absolute top-2 right-2 w-5 h-5 flex items-center justify-center rounded cursor-pointer '+(starred?'text-amber-500':'text-gray-300 hover:text-amber-400')+'" data-aid="'+a.id+'" onclick="event.stopPropagation()">'
          + '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="'+(starred?'currentColor':'none')+'" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>'
          + '<p class="text-[12px] font-medium text-gray-800 leading-tight mb-1 pr-5">'+escHtml(a.activity_name)+'</p>'
          + '<p class="text-[10px] text-gray-400">'+escHtml(a.cost_center_name||'')+'</p>'
          + (amt ? '<p class="text-[11px] font-semibold text-brand-600 mt-1">'+amt+'</p>' : '')
          + '</div>';
  }
  grid.innerHTML = html;
  attachServiceClicks(grid);

  grid.querySelectorAll('.fav-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var aid = this.dataset.aid;
      apiPost({action: 'toggle_favorite', activity_id: aid}).then(function(d) {
        if (d.success) {
          if (d.favorited) { favoriteIds.push(Number(aid)); }
          else { favoriteIds = favoriteIds.filter(function(x) { return x !== Number(aid); }); }
          renderServices(allServices);
          renderFavorites();
        }
      });
    });
  });
}

var inlineWizardStep = 'details';
var paymentFormPanelOriginalParent = null;
var paymentFormPanelOriginalNext = null;

function mountInlinePaymentForm() {
  var form = document.getElementById('payment-form-panel');
  var host = document.getElementById('inline-payment-form-host');
  if (!form || !host) return;
  if (!paymentFormPanelOriginalParent) {
    paymentFormPanelOriginalParent = form.parentNode;
    paymentFormPanelOriginalNext = form.nextSibling;
  }
  if (form.parentNode !== host) host.appendChild(form);
  form.classList.remove('min-h-0');
}

function unmountInlinePaymentForm() {
  var form = document.getElementById('payment-form-panel');
  if (!form || !paymentFormPanelOriginalParent) return;
  if (form.parentNode === paymentFormPanelOriginalParent) return;
  if (paymentFormPanelOriginalNext && paymentFormPanelOriginalNext.parentNode === paymentFormPanelOriginalParent) {
    paymentFormPanelOriginalParent.insertBefore(form, paymentFormPanelOriginalNext);
  } else {
    paymentFormPanelOriginalParent.appendChild(form);
  }
}

function updateInlinePaymentSummary() {
  var nodes = document.querySelectorAll('.js-inline-payment-summary');
  if (!nodes.length) return;
  var html;
  if (!pendingCharge || !pendingCharge.activity) {
    html = 'No service selected yet.';
  } else {
    var activity = pendingCharge.activity;
    html = '<div>'
      + '<div class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 mb-2">Selected Charge</div>'
      + '<div class="font-bold text-slate-900">' + escHtml(activity.activity_name || '') + '</div>'
      + (activity.activity_code ? '<div class="text-xs font-mono text-slate-500 mt-1">' + escHtml(activity.activity_code) + '</div>' : '')
      + (activity.cost_center_name ? '<div class="text-xs text-emerald-800 font-semibold mt-1">' + escHtml(activity.cost_center_name) + '</div>' : '')
      + '<div class="text-lg font-black text-[#1e4620] mt-3">BZD $' + parseFloat(pendingCharge.amount || 0).toFixed(2) + '</div>'
      + '</div>';
  }
  for (var i = 0; i < nodes.length; i++) nodes[i].innerHTML = html;
}

function updateReceiptCardVisibility() {
  var card = document.getElementById('receipt-card');
  if (!card) return;
  var wizard = document.getElementById('inline-service-entry');
  var wizardOpen = !!wizard && !wizard.classList.contains('hidden');
  var hasReceipt = !!selectedPayer || (typeof cartItems !== 'undefined' && cartItems.length > 0);
  card.classList.toggle('hidden', !hasReceipt && !wizardOpen);
}

// Buzz any required input that is empty: red highlight + shake + focus.
function buzzRequiredField(input) {
  if (!input) return;
  input.classList.add('pos-required-error');
  input.classList.remove('pos-shake');
  // Force reflow so the animation can replay on repeated triggers.
  void input.offsetWidth;
  input.classList.add('pos-shake');
  setTimeout(function() { input.classList.remove('pos-shake'); }, 450);
  try { input.focus(); } catch (e) {}
}

// Returns true when the beneficiary is present; otherwise buzzes the field.
function ensureBeneficiaryFilled() {
  var input = document.getElementById('page-beneficiary');
  if (!input) return true;
  if (input.value.trim()) {
    input.classList.remove('pos-required-error');
    return true;
  }
  if (input.readOnly) setBeneficiaryEditMode(true);
  buzzRequiredField(input);
  return false;
}

function setBeneficiaryEditMode(editable) {
  var input = document.getElementById('page-beneficiary');
  var btn = document.getElementById('btn-beneficiary-toggle');
  if (!input || !btn) return;
  input.readOnly = !editable;
  input.classList.toggle('bg-gray-50', !editable);
  input.classList.toggle('bg-white', editable);
  btn.textContent = editable ? 'Save' : 'Edit';
  btn.className = 'px-2 py-1 rounded-lg border border-gray-200 bg-white text-[10px] font-bold uppercase tracking-widest text-slate-600';
  if (editable) {
    setTimeout(function() {
      input.focus();
      input.select();
    }, 0);
  }
}

function validateInlineWizardAmount() {
  var input = document.getElementById('inline-service-amount');
  var btn = document.getElementById('btn-inline-service-continue');
  var ok = !!input && !isNaN(parseFloat(input.value)) && parseFloat(input.value) > 0;
  if (btn) btn.disabled = !ok;
  return ok;
}

function setWizardBadge(id, active) {
  var el = document.getElementById(id);
  if (el) el.className = 'px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-[0.18em] ' + (active ? 'bg-[#1e4620] text-white' : 'bg-white border border-emerald-200 text-emerald-700');
}

function showAllPayMethods() {
  var switchBtn = document.getElementById('btn-switch-payment-method');
  if (switchBtn) switchBtn.classList.add('hidden');
  document.querySelectorAll('.page-pay-btn').forEach(function(btn) {
    btn.classList.remove('hidden');
    btn.classList.toggle('selected', btn.dataset.method === selectedPayMethod);
  });
}

function setInlineWizardStep(step) {
  if (step === 'payment') inlineWizardStep = 'payment';
  else if (step === 'instructions') inlineWizardStep = 'instructions';
  else if (step === 'success') inlineWizardStep = 'success';
  else inlineWizardStep = 'details';

  var details = document.getElementById('inline-service-step-details');
  var payment = document.getElementById('inline-service-step-payment');
  var instructions = document.getElementById('inline-service-step-instructions');
  var success = document.getElementById('inline-service-step-success');
  if (details) details.classList.toggle('hidden', inlineWizardStep !== 'details');
  if (payment) payment.classList.toggle('hidden', inlineWizardStep !== 'payment');
  if (instructions) instructions.classList.toggle('hidden', inlineWizardStep !== 'instructions');
  if (success) success.classList.toggle('hidden', inlineWizardStep !== 'success');

  setWizardBadge('inline-wizard-step-details-badge', inlineWizardStep === 'details');
  setWizardBadge('inline-wizard-step-payment-badge', inlineWizardStep === 'payment');
  setWizardBadge('inline-wizard-step-instructions-badge', inlineWizardStep === 'instructions');
  setWizardBadge('inline-wizard-step-success-badge', inlineWizardStep === 'success');

  if (inlineWizardStep === 'payment') {
    showAllPayMethods();
    updateInlinePaymentSummary();
  }
  if (inlineWizardStep === 'instructions') {
    mountInlinePaymentForm();
    updateInlinePaymentSummary();
    if (typeof updateInlineChargeButtonLabel === 'function') updateInlineChargeButtonLabel();
    if (typeof validateInlineCharge === 'function') validateInlineCharge();
  }
}

  function closeInlineServiceEntry() {
    selectedInlineService = null;
    pendingActivity = null;
    var panel = document.getElementById('inline-service-entry');
    var input = document.getElementById('inline-service-amount');
    if (panel) panel.classList.add('hidden');
    if (input) input.value = '';
    setInlineWizardStep('details');
    validateInlineWizardAmount();
    unmountInlinePaymentForm();
    updateReceiptCardVisibility();
  }

  function openInlineServiceEntry(activity, amount) {
  selectedInlineService = activity;
  pendingActivity = activity;
  var panel = document.getElementById('inline-service-entry');
  var amountInput = document.getElementById('inline-service-amount');
  if (!panel || !amountInput) return;

  document.getElementById('inline-service-name').textContent = activity.activity_name || '--';

  var codeEl = document.getElementById('inline-service-code');
  if (codeEl) {
    if (activity.activity_code) {
      codeEl.textContent = activity.activity_code;
      codeEl.classList.remove('hidden');
    } else {
      codeEl.classList.add('hidden');
      codeEl.textContent = '';
    }
  }

  var ccEl = document.getElementById('inline-service-cost-center');
  if (ccEl) {
    if (activity.cost_center_name) {
      ccEl.textContent = activity.cost_center_name;
      ccEl.classList.remove('hidden');
    } else {
      ccEl.classList.add('hidden');
      ccEl.textContent = '';
    }
  }

  amountInput.value = amount > 0 ? parseFloat(amount).toFixed(2) : '';
  panel.classList.remove('hidden');
  updateReceiptCardVisibility();
  setInlineWizardStep('details');
  validateInlineWizardAmount();
  setTimeout(function() {
    amountInput.focus();
    amountInput.select();
  }, 0);
}

function attachServiceClicks(container) {
  container.querySelectorAll('.svc-card').forEach(function(card) {
    card.addEventListener('click', function() {
      var id     = this.dataset.id;
      var amount = parseFloat(this.dataset.amount) || 0;
      var activity = null;
      for (var i = 0; i < allServices.length; i++) {
        if (String(allServices[i].id) === String(id)) { activity = allServices[i]; break; }
      }
      if (!activity) return;
      if (selectedInlineService && String(selectedInlineService.id) === String(activity.id)) {
        closeInlineServiceEntry();
        return;
      } else {
        openInlineServiceEntry(activity, amount);
      }
    });
  });
}

// ---- Search ----
document.getElementById('search-services').addEventListener('input', function() {
  var q = this.value.toLowerCase().trim();
  if (!q) { renderServices(allServices); return; }
  renderServices(allServices.filter(function(a) {
    return (a.activity_name||'').toLowerCase().indexOf(q) !== -1
        || (a.activity_code||'').toLowerCase().indexOf(q) !== -1
        || (a.cost_center_name||'').toLowerCase().indexOf(q) !== -1;
  }));
});

document.getElementById('btn-inline-service-cancel').addEventListener('click', function() {
  closeInlineServiceEntry();
});

document.getElementById('inline-service-amount').addEventListener('input', function() {
  validateInlineWizardAmount();
});

document.getElementById('btn-inline-service-continue').addEventListener('click', function() {
  var amountInput = document.getElementById('inline-service-amount');
  var amount = parseFloat(amountInput.value);
  var activity = pendingActivity || selectedInlineService;
  if (!activity || isNaN(amount) || amount <= 0) {
    if (amountInput) amountInput.focus();
    return;
  }
  addToCart(activity, amount);
});

document.getElementById('btn-beneficiary-toggle').addEventListener('click', function() {
  var input = document.getElementById('page-beneficiary');
  var editable = !!(input && input.readOnly);
  setBeneficiaryEditMode(editable);
});

document.getElementById('inline-service-amount').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    document.getElementById('btn-inline-service-continue').click();
  }
});

// ---- Amount Modal ----
function openAmtModal(activity) {
  document.getElementById('amt-service-name').textContent = activity.activity_name || '';
  var amtInput = document.getElementById('amt-input');
  var amtWrap = document.getElementById('amt-input-wrap');
  if (amtInput) {
    amtInput.type = 'number';
    amtInput.removeAttribute('hidden');
    amtInput.style.display = '';
    amtInput.style.visibility = '';
  }
  if (amtWrap) {
    amtWrap.removeAttribute('hidden');
    amtWrap.style.display = 'flex';
    amtWrap.style.visibility = '';
  }

  var codeEl = document.getElementById('amt-service-code');
  if (activity.activity_code) { codeEl.textContent = activity.activity_code; codeEl.classList.remove('hidden'); }
  else { codeEl.classList.add('hidden'); }

  var ccEl = document.getElementById('amt-cost-center');
  if (activity.cost_center_name) { ccEl.textContent = activity.cost_center_name; ccEl.classList.remove('hidden'); }
  else { ccEl.classList.add('hidden'); }

  var revEl = document.getElementById('amt-revenue-code');
  if (activity.revenue_code) {
    revEl.querySelector('span').textContent = 'Revenue: ' + activity.revenue_code;
    revEl.classList.remove('hidden');
  } else { revEl.classList.add('hidden'); }

  var descEl = document.getElementById('amt-description');
  if (activity.description) { descEl.textContent = activity.description; descEl.classList.remove('hidden'); }
  else { descEl.classList.add('hidden'); }

  document.getElementById('amt-input').value = '';
  document.getElementById('amt-modal').classList.remove('hidden');
  setTimeout(function() { document.getElementById('amt-input').focus(); }, 100);
}

document.getElementById('btn-amt-add').addEventListener('click', function() {
  var amt = parseFloat(document.getElementById('amt-input').value);
  if (isNaN(amt) || amt <= 0) { document.getElementById('amt-input').focus(); return; }
  document.getElementById('amt-modal').classList.add('hidden');
  if (pendingActivity) { addToCart(pendingActivity, amt); pendingActivity = null; }
});
document.getElementById('amt-input').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { document.getElementById('btn-amt-add').click(); }
});

// ---- Payer ----
function updatePayerDisplay() {
  var dispEl  = document.getElementById('payer-display');
  var phEl    = document.getElementById('payer-placeholder');
  var clearEl = document.getElementById('btn-clear-payer');
  var panel   = document.getElementById('payer-info-panel');
  var hdr     = document.getElementById('payer-info-header');
  var badge   = document.getElementById('payer-verify-badge');
  var body    = document.getElementById('payer-info-body');

  if (selectedPayer) {
    dispEl.textContent = (selectedPayer.type === 'customer' ? 'Customer: ' : 'Dept: ') + selectedPayer.name;
    dispEl.style.display  = '';
    phEl.style.display    = 'none';
    clearEl.style.display = '';

    // Compliance panel
    if (selectedPayer.type === 'customer') {
      var c = selectedPayer.data || {};
      var verified = (c.status === 'active');
      panel.style.display = '';
      panel.style.borderColor = verified ? '#bbf7d0' : '#fecaca';
      hdr.style.background    = verified ? '#f0fdf4' : '#fff1f2';

      badge.innerHTML = verified
        ? '<span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-green-700 bg-green-100 rounded-full px-2 py-0.5"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>Verified</span>'
        : '<span class="text-[9px] font-black uppercase tracking-wider text-red-600 bg-red-100 rounded-full px-2 py-0.5">Unverified</span>';

      var type = c.customer_type ? (c.customer_type.charAt(0).toUpperCase() + c.customer_type.slice(1)) : '—';
      var rows = [
        ['TIN',  c.tax_id  || '—'],
        ['Name', selectedPayer.name],
        ['Type', type],
        ['Phone', c.phone || '—'],
      ];
      if (c.email) rows.push(['Email', c.email]);
      if (c.address_line_1) {
        var addr = [c.address_line_1, c.district, c.country].filter(Boolean).join(', ');
        rows.push(['Address', addr]);
      }
      if (c.created_at) rows.push(['Member since', c.created_at.substring(0,10)]);

      body.innerHTML = rows.map(function(r) {
        return '<div class="text-[10px]"><span class="font-bold text-gray-400 uppercase tracking-wide">'
          + escHtml(r[0]) + '</span><div class="font-medium text-gray-800 truncate">' + escHtml(r[1]) + '</div></div>';
      }).join('');

    } else {
      // Department payer — simpler panel
      var d = selectedPayer.data || {};
      panel.style.display = '';
      panel.style.borderColor = '#bfdbfe';
      hdr.style.background    = '#eff6ff';
      badge.innerHTML = '<span class="text-[9px] font-black uppercase tracking-wider text-blue-700 bg-blue-100 rounded-full px-2 py-0.5">Government</span>';

      var dRows = [
        ['Name', selectedPayer.name],
        ['Code', d.code || '—'],
      ];
      if (d.ministry_name) dRows.push(['Ministry', d.ministry_name]);

      body.innerHTML = dRows.map(function(r) {
        return '<div class="text-[10px]"><span class="font-bold text-gray-400 uppercase tracking-wide">'
          + escHtml(r[0]) + '</span><div class="font-medium text-gray-800 truncate">' + escHtml(r[1]) + '</div></div>';
      }).join('');
    }

  } else {
    dispEl.style.display  = 'none';
    phEl.style.display    = '';
    clearEl.style.display = 'none';
    panel.style.display   = 'none';
  }
}

var clearPayerBtn = document.getElementById('btn-clear-payer');
if (clearPayerBtn) {
  clearPayerBtn.addEventListener('click', function() {
    selectedPayer = null;
    updatePayerDisplay();
    updateCoPayerDisplay();
    if (typeof renderCart === 'function') renderCart();
  });
}

// ---- Customer Modal ----
function openCustModal() {
  document.getElementById('cust-modal').classList.remove('hidden');
  document.getElementById('cust-search-input').value = '';
  document.getElementById('cust-results').innerHTML = '';
  document.getElementById('cust-no-results').classList.add('hidden');
  setTimeout(function() { document.getElementById('cust-search-input').focus(); }, 100);
}
if (document.getElementById('btn-select-customer')) {
  document.getElementById('btn-select-customer').addEventListener('click', openCustModal);
}

var custTimer = null;
document.getElementById('cust-search-input').addEventListener('input', function() {
  clearTimeout(custTimer);
  var q = this.value.trim();
  if (!q) { document.getElementById('cust-results').innerHTML = ''; return; }
  custTimer = setTimeout(function() {
    apiPost({action: 'search_customers', query: q}).then(function(d) {
      var res  = document.getElementById('cust-results');
      var noR  = document.getElementById('cust-no-results');
      if (!d.success || !d.customers || !d.customers.length) { res.innerHTML = ''; noR.classList.remove('hidden'); return; }
      noR.classList.add('hidden');
      var html = '';
      for (var i = 0; i < d.customers.length; i++) {
        var c = d.customers[i];
        var name = c.customer_name || (((c.first_name||'') + ' ' + (c.last_name||'')).trim()) || '—';
        var addr = [c.address_line_1, c.address_line_2, c.district, c.country].filter(Boolean).join(', ');
        var memberSince = c.created_at ? c.created_at.substring(0,10) : '';
        var statusBadge = c.status === 'active'
          ? '<span class="text-[10px] bg-green-100 text-green-700 rounded px-1.5 py-0.5 font-medium">Active</span>'
          : '<span class="text-[10px] bg-red-100 text-red-600 rounded px-1.5 py-0.5 font-medium">'+escHtml(c.status||'')+'</span>';
        html += '<div class="cust-card border border-gray-200 rounded-xl overflow-hidden hover:border-brand-300 hover:shadow-sm transition-all mb-2">'
          + '<div class="cust-res px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors" data-json=\''+JSON.stringify(c).replace(/'/g,"&apos;")+'\'>'
          + '<div class="flex items-start justify-between gap-2 mb-2">'
          + '<div class="font-semibold text-gray-900 text-sm">'+escHtml(name)+'</div>'
          + '<div class="flex items-center gap-1.5 shrink-0">' + statusBadge
          + (c.customer_type ? '<span class="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5 font-medium capitalize">'+escHtml(c.customer_type)+'</span>' : '')
          + '</div></div>'
          + '<div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500">'
          + (c.tax_id   ? '<div><span class="font-medium text-gray-400">ID #</span> '+escHtml(c.tax_id)+'</div>' : '<div></div>')
          + (c.phone    ? '<div><span class="font-medium text-gray-400">Phone</span> '+escHtml(c.phone)+'</div>' : '<div></div>')
          + (c.email    ? '<div class="col-span-2"><span class="font-medium text-gray-400">Email</span> '+escHtml(c.email)+'</div>' : '')
          + (addr       ? '<div class="col-span-2"><span class="font-medium text-gray-400">Address</span> '+escHtml(addr)+'</div>' : '')
          + (memberSince? '<div><span class="font-medium text-gray-400">Member since</span> '+escHtml(memberSince)+'</div>' : '')
          + '</div></div>'
          + '<div class="border-t border-gray-100 px-4 py-1.5 bg-gray-50 flex justify-end">'
          + '<a href="'+CUSTOMER_PROFILE_URL+'?id='+c.id+'" target="_blank" onclick="event.stopPropagation()" class="text-[11px] text-brand-600 hover:underline font-medium">View Profile &amp; History &#8599;</a>'
          + '</div></div>';
      }
      res.innerHTML = html;
      res.querySelectorAll('.cust-res').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var c = JSON.parse(this.dataset.json.replace(/&apos;/g,"'"));
          var name = c.customer_name || (((c.first_name||'') + ' ' + (c.last_name||'')).trim()) || '—';
          setSelectedPayer({type: 'customer', id: c.id, name: name, data: c});
          document.getElementById('cust-modal').classList.add('hidden');
        });
      });
    });
  }, 300);
});

document.getElementById('btn-open-add-cust').addEventListener('click', function() {
  document.getElementById('cust-modal').classList.add('hidden');
  document.getElementById('add-cust-modal').classList.remove('hidden');
  ['nc-fn','nc-ln','nc-ph','nc-em'].forEach(function(id) { document.getElementById(id).value = ''; });
  document.getElementById('add-cust-alert').classList.add('hidden');
});

document.getElementById('btn-save-cust').addEventListener('click', function() {
  var alertEl = document.getElementById('add-cust-alert');
  alertEl.classList.add('hidden');
  var fn = document.getElementById('nc-fn').value.trim();
  if (!fn) { alertEl.textContent = 'First name required.'; alertEl.classList.remove('hidden'); return; }
  var btn = this; btn.disabled = true; btn.textContent = 'Saving...';
  apiPost({action:'create_customer', first_name:fn, last_name:document.getElementById('nc-ln').value.trim(),
    phone:document.getElementById('nc-ph').value.trim(), email:document.getElementById('nc-em').value.trim()})
  .then(function(d) {
    if (d.success) {
      var c = d.customer;
      var name = c.customer_name || (((c.first_name||'') + ' ' + (c.last_name||'')).trim()) || '—';
      setSelectedPayer({type:'customer', id:c.id, name:name, data:c});
      document.getElementById('add-cust-modal').classList.add('hidden');
    } else { alertEl.textContent = d.message || 'Failed.'; alertEl.classList.remove('hidden'); }
  }).catch(function() { alertEl.textContent = 'Error saving.'; alertEl.classList.remove('hidden'); })
  .then(function() { btn.disabled = false; btn.textContent = 'Save'; });
});

// ---- Department Modal ----
function openDeptModal() {
  document.getElementById('dept-modal').classList.remove('hidden');
  document.getElementById('dept-search-input').value = '';
  document.getElementById('dept-results').innerHTML = '';
  document.getElementById('dept-no-results').classList.add('hidden');
  setTimeout(function() { document.getElementById('dept-search-input').focus(); }, 100);
}
if (document.getElementById('btn-select-dept')) {
  document.getElementById('btn-select-dept').addEventListener('click', openDeptModal);
}

var deptTimer = null;
document.getElementById('dept-search-input').addEventListener('input', function() {
  clearTimeout(deptTimer);
  var q = this.value.trim();
  if (!q) { document.getElementById('dept-results').innerHTML = ''; return; }
  deptTimer = setTimeout(function() {
    apiPost({action: 'search_departments', query: q}).then(function(d) {
      var res = document.getElementById('dept-results');
      var noR = document.getElementById('dept-no-results');
      if (!d.success || !d.departments || !d.departments.length) { res.innerHTML = ''; noR.classList.remove('hidden'); return; }
      noR.classList.add('hidden');
      var html = '';
      for (var i = 0; i < d.departments.length; i++) {
        var dept = d.departments[i];
        html += '<button class="dept-res w-full text-left px-3 py-2.5 rounded-lg hover:bg-gray-100 cursor-pointer" data-json=\''+JSON.stringify(dept).replace(/'/g,"&apos;")+'\'>'
              + '<div class="text-sm font-medium text-gray-900">'+escHtml(dept.name)+'</div>'
              + '<div class="text-xs text-gray-400">'+escHtml(dept.code||'')+(dept.ministry_name?' · '+escHtml(dept.ministry_name):'')+'</div></button>';
      }
      res.innerHTML = html;
      res.querySelectorAll('.dept-res').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var dept = JSON.parse(this.dataset.json.replace(/&apos;/g,"'"));
          setSelectedPayer({type:'department', id:dept.id, name:dept.name, data:dept});
          document.getElementById('dept-modal').classList.add('hidden');
        });
      });
    });
  }, 300);
});

// ---- Checkout Modal ----
function openCheckout() {
  if (!cartItems.length) return;
  var itemsHtml = '';
  for (var i = 0; i < cartItems.length; i++) {
    var it = cartItems[i];
    var coTags = '';
    if (it.fund)            coTags += '<span class="text-[9px] bg-blue-50 text-blue-700 border border-blue-100 rounded px-1 font-medium">'+escHtml(it.fund)+'</span>';
    if (it.department_name) coTags += '<span class="text-[9px] bg-purple-50 text-purple-700 border border-purple-100 rounded px-1 font-medium">'+escHtml(it.department_name)+'</span>';
    if (it.revenue_code)    coTags += '<span class="text-[9px] bg-amber-50 text-amber-700 border border-amber-100 rounded px-1 font-medium">RC: '+escHtml(it.revenue_code)+'</span>';
    if (it.gl_account)      coTags += '<span class="text-[9px] bg-green-50 text-green-700 border border-green-100 rounded px-1 font-medium">GL: '+escHtml(it.gl_account)+'</span>';
    itemsHtml += '<div class="flex items-start justify-between gap-2 pb-2 border-b border-gray-100 last:border-0 last:pb-0">'
               + '<div class="flex-1 min-w-0">'
               + '<div class="text-sm text-gray-800 font-medium">'+escHtml(it.activity_name)
               + (it.activity_code ? ' <span class="text-[10px] text-gray-400 font-mono">'+escHtml(it.activity_code)+'</span>' : '')+'</div>'
               + (coTags ? '<div class="flex flex-wrap gap-1 mt-1">'+coTags+'</div>' : '')
               + '</div>'
               + '<div class="text-sm font-bold shrink-0">BZD $'+parseFloat(it.amount).toFixed(2)+'</div></div>';
  }
  document.getElementById('co-items-list').innerHTML = itemsHtml;
  document.getElementById('co-total').textContent = '$' + cartTotal().toFixed(2);
  selectedPayMethod = '';
  document.querySelectorAll('.co-pay-btn').forEach(function(b) { b.classList.remove('selected'); });
  hideAllPayDetails();
  document.getElementById('co-pay-details').classList.add('hidden');
  document.getElementById('co-beneficiary').value = (selectedPayer && selectedPayer.type === 'customer') ? selectedPayer.name : '';
  document.getElementById('btn-confirm-checkout').disabled = true;
  document.getElementById('co-error').classList.add('hidden');
  updateCoPayerDisplay();
  document.getElementById('checkout-modal').classList.remove('hidden');
}

function updateCoPayerDisplay() {
  var el = document.getElementById('co-payer-display');
  if (!el) return;
  if (selectedPayer) {
    var typeLabel = selectedPayer.type === 'customer' ? 'Customer' : 'Department';
    el.innerHTML = '<span class="font-semibold text-gray-900">'+escHtml(selectedPayer.name)+'</span>'
      + ' <span class="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5 font-medium">'+typeLabel+'</span>'
      + ' <button id="co-change-payer" class="text-xs text-brand-600 hover:underline cursor-pointer ml-2">Change</button>';
    document.getElementById('co-change-payer').addEventListener('click', function() {
      document.getElementById('checkout-modal').classList.add('hidden');
    });
  } else {
    el.innerHTML = '<span class="text-gray-400 font-normal text-sm">None — </span>'
      + '<button id="co-cust-btn" class="text-brand-600 hover:underline text-xs font-semibold cursor-pointer">Customer</button>'
      + '<span class="text-gray-400 text-xs"> or </span>'
      + '<button id="co-dept-btn" class="text-brand-600 hover:underline text-xs font-semibold cursor-pointer">Department</button>';
    document.getElementById('co-cust-btn').addEventListener('click', function() {
      document.getElementById('checkout-modal').classList.add('hidden'); openCustModal();
    });
    document.getElementById('co-dept-btn').addEventListener('click', function() {
      document.getElementById('checkout-modal').classList.add('hidden'); openDeptModal();
    });
  }
}

document.getElementById('btn-checkout').addEventListener('click', openCheckout);

document.querySelectorAll('.co-pay-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    selectedPayMethod = this.dataset.method;
    document.querySelectorAll('.co-pay-btn').forEach(function(b) { b.classList.remove('selected'); });
    this.classList.add('selected');
    showPayDetails(selectedPayMethod);
    validateCheckout();
  });
});

function hideAllPayDetails() {
  ['pd-check','pd-bank-deposit','pd-online-transfer'].forEach(function(id) {
    document.getElementById(id).classList.add('hidden');
  });
}

function showPayDetails(method) {
  hideAllPayDetails();
  var wrap = document.getElementById('co-pay-details');
  if (method === 'check') {
    wrap.classList.remove('hidden');
    document.getElementById('pd-check').classList.remove('hidden');
  } else if (method === 'bank_deposit') {
    wrap.classList.remove('hidden');
    document.getElementById('pd-bank-deposit').classList.remove('hidden');
    loadBankAccounts(function(a) { populateBankSelect('pd-bd-bank', a); });
  } else if (method === 'online_transfer') {
    wrap.classList.remove('hidden');
    document.getElementById('pd-online-transfer').classList.remove('hidden');
    loadBankAccounts(function(a) { populateBankSelect('pd-ot-bank', a); });
  } else {
    wrap.classList.add('hidden');
  }
}

function loadBankAccounts(cb) {
  if (bankAccountsCache !== null) { cb(bankAccountsCache); return; }
  apiPost({action: 'load_bank_accounts'}).then(function(d) {
    bankAccountsCache = (d.success && d.bank_accounts) ? d.bank_accounts : [];
    cb(bankAccountsCache);
  }).catch(function() { bankAccountsCache = []; cb([]); });
}

function populateBankSelect(selId, accounts) {
  var sel = document.getElementById(selId);
  sel.innerHTML = '<option value="">— Select Account —</option>';
  for (var i = 0; i < accounts.length; i++) {
    var acct = accounts[i];
    var label = acct.bank_name + ' — ' + acct.account_name;
    if (acct.account_masked) label += ' (' + acct.account_masked + ')';
    if (acct.currency_code)  label += ' [' + acct.currency_code + ']';
    var opt = document.createElement('option');
    opt.value = acct.bank_id;
    opt.textContent = label;
    opt.dataset.bankName = acct.bank_name;
    opt.dataset.accountName = acct.account_name || '';
    opt.dataset.accountNumber = acct.account_number || '';
    opt.dataset.accountMasked = acct.account_masked || '';
    sel.appendChild(opt);
  }
  sel.addEventListener('change', validateCheckout);
}

function getSelectedBankAccountMeta(selId) {
  var sel = typeof selId === 'string' ? document.getElementById(selId) : selId;
  var opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
  return {
    bank_account_id: sel ? (sel.value || '') : '',
    bank_name: opt ? (opt.dataset.bankName || '') : '',
    account_name: opt ? (opt.dataset.accountName || '') : '',
    account_number: opt ? (opt.dataset.accountNumber || '') : '',
    account_masked: opt ? (opt.dataset.accountMasked || '') : ''
  };
}

function setSelectedPayerEmail(email) {
  if (!selectedPayer || selectedPayer.type !== 'customer') return;
  if (!selectedPayer.data) selectedPayer.data = {};
  selectedPayer.data.email = (email || '').trim();
  updatePayerDisplay();
  if (typeof updateServicePayerDisplay === 'function') updateServicePayerDisplay();
  if (typeof updateCoPayerDisplay === 'function') updateCoPayerDisplay();
  if (typeof renderCart === 'function') renderCart();
}

function generateBankDepositReference(seed) {
  var stamp = String(seed || Date.now()).replace(/[^A-Za-z0-9]/g, '').slice(-8);
  return 'BDI-' + String(SHIFT_ID || 'POS') + '-' + stamp;
}

var currentBankInstructionData = null;

function updateBankInstructionButtons() {
  var printCb = document.getElementById('bdi-delivery-print');
  var emailCb = document.getElementById('bdi-delivery-email');
  var emailInput = document.getElementById('bdi-email-input');
  var printBtn = document.getElementById('btn-print-bank-instructions');
  var emailBtn = document.getElementById('btn-email-bank-instructions');
  if (printBtn && printCb) printBtn.disabled = !printCb.checked;
  if (emailBtn) {
    emailBtn.disabled = !(emailCb && emailCb.checked && emailInput && emailInput.value.trim());
    emailBtn.classList.toggle('opacity-50', !!emailBtn.disabled);
    emailBtn.classList.toggle('cursor-not-allowed', !!emailBtn.disabled);
  }
}

function syncBankInstructionDeliveryState() {
  var emailWrap = document.getElementById('bdi-email-input-wrap');
  var emailOptInWrap = document.getElementById('bdi-email-optin-wrap');
  var emailOptIn = document.getElementById('bdi-email-optin');
  var emailOptionWrap = document.getElementById('bdi-delivery-email-wrap');
  var emailCb = document.getElementById('bdi-delivery-email');
  var printCb = document.getElementById('bdi-delivery-print');
  var emailInput = document.getElementById('bdi-email-input');
  if (!emailWrap || !emailOptInWrap || !emailOptIn || !emailOptionWrap || !emailCb || !printCb || !emailInput) return;

  var hasEmail = !!emailInput.value.trim();
  printCb.checked = true;

  if (hasEmail) {
    emailOptionWrap.classList.remove('hidden');
    emailWrap.classList.remove('hidden');
    emailOptInWrap.classList.add('hidden');
    emailCb.checked = true;
  } else {
    emailOptInWrap.classList.remove('hidden');
    emailOptionWrap.classList.toggle('hidden', !emailOptIn.checked);
    emailWrap.classList.toggle('hidden', !emailOptIn.checked);
    emailCb.checked = !!emailOptIn.checked;
  }

  updateBankInstructionButtons();
}

function closeBankDepositInstructionsModal() {
  document.getElementById('bank-instructions-modal').classList.add('hidden');
}

function openBankDepositInstructionsModal(data) {
  currentBankInstructionData = data || null;
  if (!currentBankInstructionData) return;

  document.getElementById('bdi-payer-name').textContent = currentBankInstructionData.payerName || '--';
  document.getElementById('bdi-reference').textContent = currentBankInstructionData.reference || '--';
  document.getElementById('bdi-bank-name').textContent = currentBankInstructionData.bankName || '--';
  document.getElementById('bdi-bank-account-number').textContent = currentBankInstructionData.accountNumber || currentBankInstructionData.accountMasked || '--';
  document.getElementById('bdi-purpose').textContent = currentBankInstructionData.purpose || '--';
  document.getElementById('bdi-amount').textContent = 'BZD $' + parseFloat(currentBankInstructionData.amount || 0).toFixed(2);

  var emailOptIn = document.getElementById('bdi-email-optin');
  var emailInput = document.getElementById('bdi-email-input');
  var printCb = document.getElementById('bdi-delivery-print');
  var emailCb = document.getElementById('bdi-delivery-email');
  if (printCb) printCb.checked = true;
  if (emailOptIn) emailOptIn.checked = !!(currentBankInstructionData.payerEmail || '');
  if (emailCb) emailCb.checked = !!(currentBankInstructionData.payerEmail || '');
  if (emailInput) emailInput.value = currentBankInstructionData.payerEmail || '';

  syncBankInstructionDeliveryState();
  document.getElementById('bank-instructions-modal').classList.remove('hidden');
}

function emailBankDepositInstructions() {
  if (!currentBankInstructionData) return;
  var emailInput = document.getElementById('bdi-email-input');
  var email = emailInput ? emailInput.value.trim() : '';
  if (!email) {
    if (emailInput) emailInput.focus();
    return;
  }
  setSelectedPayerEmail(email);
  currentBankInstructionData.payerEmail = email;
  var subject = 'Bank Deposit Instructions - ' + (currentBankInstructionData.reference || 'Reference');
  var body = [
    'Bank Deposit Instructions',
    '',
    'Payer: ' + (currentBankInstructionData.payerName || '--'),
    'Bank Name: ' + (currentBankInstructionData.bankName || '--'),
    'Bank Account Number: ' + (currentBankInstructionData.accountNumber || currentBankInstructionData.accountMasked || '--'),
    'Reference Number: ' + (currentBankInstructionData.reference || '--'),
    'Purpose of Payment: ' + (currentBankInstructionData.purpose || '--'),
    'Amount to be Paid: BZD $' + parseFloat(currentBankInstructionData.amount || 0).toFixed(2)
  ].join('\n');
  window.location.href = 'mailto:' + encodeURIComponent(email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

function validateCheckout() {
  var ok = !!selectedPayMethod && !!selectedPayer;
  if (ok && selectedPayMethod === 'check') {
    ok = !!(document.getElementById('pd-check-number').value.trim() && document.getElementById('pd-check-bank').value.trim());
  } else if (ok && selectedPayMethod === 'bank_deposit') {
    ok = !!document.getElementById('pd-bd-bank').value;
  } else if (ok && selectedPayMethod === 'online_transfer') {
    ok = !!(document.getElementById('pd-ot-bank').value && document.getElementById('pd-ot-ref').value.trim() && document.getElementById('pd-ot-sender').value.trim() && document.getElementById('pd-ot-amount').value);
  }
  document.getElementById('btn-confirm-checkout').disabled = !ok;
}

['pd-check-number','pd-check-bank','pd-check-holder','pd-bd-ref','pd-bd-amount','pd-ot-ref','pd-ot-sender','pd-ot-amount'].forEach(function(id) {
  document.getElementById(id).addEventListener('input', validateCheckout);
});

// ---- Confirm Checkout ----
document.getElementById('btn-confirm-checkout').addEventListener('click', function() {
  var btn   = this;
  var errEl = document.getElementById('co-error');
  errEl.classList.add('hidden');
  if (!selectedPayer)     { errEl.textContent = 'Please select a payer before confirming.'; errEl.classList.remove('hidden'); return; }
  if (!selectedPayMethod) { errEl.textContent = 'Please select a payment method.'; errEl.classList.remove('hidden'); return; }
  if (!cartItems.length)  { errEl.textContent = 'Cart is empty.'; errEl.classList.remove('hidden'); return; }

  var payDetails = {};
  if (selectedPayMethod === 'check') {
    payDetails = {check_number: document.getElementById('pd-check-number').value.trim(),
                  bank_name:    document.getElementById('pd-check-bank').value.trim(),
                  holder:       document.getElementById('pd-check-holder').value.trim()};
  } else if (selectedPayMethod === 'pos_terminal') {
    payDetails = {bank_account_id: SHIFT_BANK_ACCOUNT_ID || '', bank_name: SHIFT_BANK_ACCOUNT_NAME || ''};
  } else if (selectedPayMethod === 'bank_deposit') {
    var bdSel = document.getElementById('pd-bd-bank');
    payDetails = {bank_account_id: bdSel.value,
                  bank_name:       bdSel.options[bdSel.selectedIndex] ? bdSel.options[bdSel.selectedIndex].dataset.bankName : '',
                  reference:       document.getElementById('pd-bd-ref').value.trim(),
                  amount_deposited: parseFloat(document.getElementById('pd-bd-amount').value)};
  } else if (selectedPayMethod === 'online_transfer') {
    var otSel = document.getElementById('pd-ot-bank');
    payDetails = {bank_account_id: otSel.value,
                  bank_name:       otSel.options[otSel.selectedIndex] ? otSel.options[otSel.selectedIndex].dataset.bankName : '',
                  reference:       document.getElementById('pd-ot-ref').value.trim(),
                  sender_name:     document.getElementById('pd-ot-sender').value.trim(),
                  amount_sent:     parseFloat(document.getElementById('pd-ot-amount').value)};
  }

  var beneficiary = document.getElementById('co-beneficiary').value.trim();
  var custId   = (selectedPayer && selectedPayer.type === 'customer')   ? selectedPayer.id   : null;
  var custName = (selectedPayer && selectedPayer.type === 'customer')   ? selectedPayer.name : null;
  var deptId   = (selectedPayer && selectedPayer.type === 'department') ? selectedPayer.id   : null;
  var deptName = (selectedPayer && selectedPayer.type === 'department') ? selectedPayer.name : null;

  btn.disabled = true; btn.textContent = 'Processing…';

  var receiptItems = cartItems.slice();
  var receiptPayer = selectedPayer;
  var receiptMethod = selectedPayMethod;

  apiPost({action:'checkout', shift_id:SHIFT_ID, items:cartItems,
    payment_method:selectedPayMethod, payment_details:payDetails,
    beneficiary_name:beneficiary, customer_id:custId, customer_name:custName,
    dept_id:deptId, dept_name:deptName})
  .then(function(d) {
    if (d.success) {
      document.getElementById('checkout-modal').classList.add('hidden');
      showReceipt(d, receiptItems, receiptPayer, receiptMethod, payDetails);
      cartItems = []; selectedPayer = null;
      updatePayerDisplay(); renderCart();
    } else {
      errEl.textContent = d.message || 'Checkout failed.'; errEl.classList.remove('hidden');
    }
  }).catch(function(e) {
    errEl.textContent = 'Network error: ' + e.message; errEl.classList.remove('hidden');
  }).then(function() {
    btn.disabled = false; btn.textContent = 'Confirm & Charge'; validateCheckout();
  });
});

// ---- Receipt ----
function fmtMethod(m) {
  var map = {cash:'Cash', check:'Cheque', bank_deposit:'Bank Deposit', pos_terminal:'POS Terminal', online_transfer:'Online Transfer', e_invoicing:'E-Invoice'};
  return map[m] || m;
}

// Returns an array of {label, value} describing a single charged item's payment.
function posPaymentDetailLines(it) {
  var method = it.payment_method || '';
  var pd = parsePaymentDetails(it.payment_details);
  var lines = [{label: 'Method', value: fmtMethod(method)}];
  if (method === 'cash') {
    if (pd.amount_tendered != null && pd.amount_tendered !== '') lines.push({label: 'Tendered', value: 'BZD $' + parseFloat(pd.amount_tendered || 0).toFixed(2)});
    if (pd.change_due != null && pd.change_due !== '')           lines.push({label: 'Change', value: 'BZD $' + parseFloat(pd.change_due || 0).toFixed(2)});
  } else if (method === 'check') {
    if (pd.check_number) lines.push({label: 'Cheque No.', value: pd.check_number});
    if (pd.bank_name)    lines.push({label: 'Bank', value: pd.bank_name});
    if (pd.holder)       lines.push({label: 'Account Holder', value: pd.holder});
  } else if (method === 'bank_deposit') {
    if (pd.reference)      lines.push({label: 'Reference', value: pd.reference});
    if (pd.bank_name)      lines.push({label: 'Bank', value: pd.bank_name});
    if (pd.account_masked || pd.account_number) lines.push({label: 'Deposit Account', value: pd.account_masked || pd.account_number});
  } else if (method === 'online_transfer') {
    if (pd.reference)   lines.push({label: 'Reference', value: pd.reference});
    if (pd.bank_name)   lines.push({label: 'Bank', value: pd.bank_name});
    if (pd.sender_name) lines.push({label: 'Sender', value: pd.sender_name});
  } else if (method === 'pos_terminal') {
    if (pd.bank_name)   lines.push({label: 'Settlement Bank', value: pd.bank_name});
  }
  return lines;
}

// Short one-line payment summary (e.g. "Bank Deposit · Ref BDI-...") for compact rows.
function posPaymentSummary(it) {
  var method = it.payment_method || '';
  var pd = parsePaymentDetails(it.payment_details);
  var label = fmtMethod(method);
  if ((method === 'bank_deposit' || method === 'online_transfer') && pd.reference) {
    label += ' · Ref ' + pd.reference;
  } else if (method === 'check' && pd.check_number) {
    label += ' · Cheque ' + pd.check_number;
  }
  return label;
}

// Returns the bank-deposit reference for an item, if any (used to surface pending references).
function posBankDepositReference(it) {
  if ((it.payment_method || '') !== 'bank_deposit') return '';
  var pd = parsePaymentDetails(it.payment_details);
  return pd.reference || '';
}

function numberToWords(n) {
  var ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
              'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
              'Seventeen','Eighteen','Nineteen'];
  var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  function two(n) {
    if (n < 20) return ones[n];
    return tens[Math.floor(n/10)] + (n%10 !== 0 ? ' '+ones[n%10] : '');
  }
  function three(n) {
    if (n < 100) return two(n);
    return ones[Math.floor(n/100)] + ' Hundred' + (n%100 !== 0 ? ' '+two(n%100) : '');
  }
  function convert(n) {
    if (n === 0) return 'Zero';
    var r = '';
    if (n >= 1000000) { r += three(Math.floor(n/1000000)) + ' Million'; n = n%1000000; if (n>0) r+=' '; }
    if (n >= 1000)    { r += three(Math.floor(n/1000))    + ' Thousand'; n = n%1000;   if (n>0) r+=' '; }
    if (n > 0)        { r += three(n); }
    return r;
  }
  n = Math.abs(n);
  var dollars = Math.floor(n);
  var cents   = Math.round((n - dollars) * 100);
  var result  = convert(dollars) + ' Dollar' + (dollars !== 1 ? 's' : '');
  if (cents > 0) result += ' and ' + two(cents) + ' Cent' + (cents !== 1 ? 's' : '');
  return result + ' Only';
}

function setReceiptPrintFormat(format) {
  var paper = document.getElementById('rct-paper');
  if (!paper) return;
  paper.setAttribute('data-print-format', format || 'half-letter');
}

function receiptHasPendingBankDeposit(items) {
  for (var i = 0; i < (items || []).length; i++) {
    if ((items[i].payment_method || '') === 'bank_deposit') return true;
  }
  return false;
}

function showReceipt(tx, items, payer, method, payDetails) {
  var now = new Date();
  function pad2(n) { return n < 10 ? '0'+n : ''+n; }
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var dateStr = pad2(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
  var h12 = now.getHours() % 12 || 12;
  var ampm = now.getHours() >= 12 ? 'PM' : 'AM';
  var timeStr = pad2(h12) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds()) + ' ' + ampm;
  var hasPendingBankDeposit = receiptHasPendingBankDeposit(items);

  // --- Meta ---
  document.getElementById('rct-number').textContent    = tx.transaction_id || '—';
  document.getElementById('rct-datetime').textContent  = dateStr + '  ' + timeStr;
  document.getElementById('rct-branch').textContent    = BRANCH_NAME   || '—';
  document.getElementById('rct-terminal').textContent  = TERMINAL_NAME || '—';
  document.getElementById('rct-cashier').textContent   = CASHIER_NAME  || '—';
  document.getElementById('rct-shift').textContent     = SHIFT_ID      || '—';
  document.getElementById('rct-verify-ref').textContent = tx.transaction_id || '—';
  document.getElementById('rct-processed-by').textContent = 'Processed by: ' + CASHIER_NAME + '   ·   ' + dateStr + ' ' + timeStr;

  // --- Payer block ---
  var payerHtml = '';
  function payerField(label, value) {
    return '<div><div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;">'
      + escHtml(label) + '</div><div style="font-weight:600;color:#111827;">' + escHtml(value) + '</div></div>';
  }
  if (payer && payer.type === 'customer') {
    var c = payer.data || {};
    payerHtml += payerField('Name', payer.name);
    payerHtml += payerField('Type', c.customer_type ? (c.customer_type.charAt(0).toUpperCase() + c.customer_type.slice(1)) : 'Individual');
    if (c.tax_id) payerHtml += payerField('TIN / ID #', c.tax_id);
    if (c.phone)  payerHtml += payerField('Phone', c.phone);
    if (c.email)  payerHtml += payerField('Email', c.email);
    if (c.address_line_1) {
      var addrParts = [c.address_line_1, c.address_line_2, c.district, c.country];
      var addr = addrParts.filter(function(x) { return !!x; }).join(', ');
      payerHtml += payerField('Address', addr);
    }
  } else if (payer && payer.type === 'department') {
    var dp = payer.data || {};
    payerHtml += payerField('Name', payer.name);
    payerHtml += payerField('Type', 'Government Department');
    if (dp.code) payerHtml += payerField('Code', dp.code);
    if (dp.ministry_name) payerHtml += payerField('Ministry', dp.ministry_name);
  } else {
    payerHtml += payerField('Name', 'Walk-in / Cash Customer');
    payerHtml += payerField('Type', 'Individual');
  }
  document.getElementById('rct-payer-block').innerHTML = payerHtml;

  // --- Items table rows (per-item payment: Service | Beneficiary | Payment | Amount) ---
  var total = 0;
  for (var t = 0; t < items.length; t++) { total += parseFloat(items[t].amount || 0); }
  if (!total && tx && tx.total) total = parseFloat(tx.total || 0);

  // Order items by payment type: collected payments (cash first) before
  // bank-deposit items, which are grouped at the bottom for a tear-off slip.
  function methodOrder(m) {
    var order = {cash:0, check:1, pos_terminal:2, online_transfer:3, e_invoicing:4, bank_deposit:9};
    return (order[m] != null) ? order[m] : 5;
  }
  var orderedItems = items.slice().sort(function(a, b) {
    return methodOrder(a.payment_method || '') - methodOrder(b.payment_method || '');
  });

  var methodTotals = {};
  var bankDepositTotal = 0;
  var itemsHtml = '';
  var bankSectionStarted = false;
  for (var i = 0; i < orderedItems.length; i++) {
    var it = orderedItems[i];
    var pd = parsePaymentDetails(it.payment_details);
    var m  = it.payment_method || '';
    methodTotals[m] = (methodTotals[m] || 0) + parseFloat(it.amount || 0);
    var isBank = (m === 'bank_deposit');
    if (isBank) bankDepositTotal += parseFloat(it.amount || 0);

    // Insert the tear-off divider once, right before the first bank-deposit row,
    // when there were collected (non-bank) items above it.
    if (isBank && !bankSectionStarted) {
      bankSectionStarted = true;
      if (itemsHtml) {
        itemsHtml += '<div style="border-top:2px dashed #1e4620;background:#f8fdf5;text-align:center;padding:7px 8px 6px;">'
          + '<span style="display:inline-block;background:#fff;border:1.5px solid #1e4620;border-radius:999px;padding:2px 12px;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1e4620;">&#9986; Bank Deposit &mdash; Tear Off &amp; Present At Bank</span>'
          + '</div>';
      }
    }

    var ref = posBankDepositReference(it) || ((m === 'online_transfer' && pd.reference) ? pd.reference : '');
    var rowBg = isBank ? '#fbfcf8' : ((i % 2 === 0) ? '#fff' : '#f8fdf5');
    var refSize = isBank ? '8px' : '7px';
    itemsHtml += '<div style="display:grid;grid-template-columns:1.2fr 1fr 86px 70px;gap:4px;padding:6px 8px;border-bottom:1px solid #e8f3e8;background:' + rowBg + ';">'
      + '<div style="font-size:10px;color:#111827;">'
      +   '<div style="font-weight:600;">' + escHtml(it.activity_name || '') + '</div>'
      +   (it.activity_code    ? '<div style="font-size:8px;font-family:monospace;color:#6b7280;">' + escHtml(it.activity_code) + '</div>' : '')
      +   (it.cost_center_name ? '<div style="font-size:8px;color:#9ca3af;">' + escHtml(it.cost_center_name) + '</div>' : '')
      + '</div>'
      + '<div style="font-size:9px;color:#374151;padding-top:2px;">' + escHtml(it.beneficiary_name || '—') + '</div>'
      + '<div style="font-size:8px;color:#111827;text-align:center;padding-top:2px;">'
      +   '<div style="font-weight:700;">' + escHtml(fmtMethod(m)) + '</div>'
      +   (ref ? '<div style="font-size:' + refSize + ';font-weight:700;font-family:monospace;color:#1d4ed8;word-break:break-all;">' + escHtml(ref) + '</div>' : '')
      + '</div>'
      + '<div style="font-size:10px;font-weight:700;color:#111827;text-align:right;padding-top:3px;">$' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
      + '</div>';
  }
  // If the receipt is bank-deposit only, still label the tear-off section.
  if (bankSectionStarted && bankDepositTotal === total && total > 0) {
    itemsHtml = '<div style="background:#f8fdf5;text-align:center;padding:6px 8px;border-bottom:1px solid #e8f3e8;">'
      + '<span style="display:inline-block;background:#fff;border:1.5px solid #1e4620;border-radius:999px;padding:2px 12px;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1e4620;">&#9986; Bank Deposit &mdash; Present At Bank</span>'
      + '</div>' + itemsHtml;
  }
  if (!itemsHtml) itemsHtml = '<div style="padding:10px 8px;font-size:10px;color:#6b7280;text-align:center;">No items.</div>';
  document.getElementById('rct-items').innerHTML = itemsHtml;

  // --- Totals ---
  document.getElementById('rct-total').textContent       = 'BZD $' + total.toFixed(2);
  document.getElementById('rct-amount-words').textContent = numberToWords(total);

  // --- Payment block: method breakdown + bank deposit references ---
  var pmHtml = '';
  Object.keys(methodTotals).forEach(function(mk) {
    pmHtml += payerField(fmtMethod(mk), 'BZD $' + methodTotals[mk].toFixed(2));
  });
  for (var r = 0; r < items.length; r++) {
    var rRef = posBankDepositReference(items[r]);
    if (rRef) pmHtml += payerField('Deposit Reference', rRef);
  }
  if (!pmHtml) pmHtml = payerField('Method', '—');
  document.getElementById('rct-payment-block').innerHTML = pmHtml;

  // --- Pending bank-deposit banner / note ---
  var banner = document.getElementById('rct-payment-status-banner');
  var note   = document.getElementById('rct-payment-status-note');
  if (banner) banner.style.display = hasPendingBankDeposit ? '' : 'none';
  if (note)   note.style.display   = hasPendingBankDeposit ? '' : 'none';

  // --- Stash data for email delivery ---
  currentReceiptEmailData = {
    number: tx.transaction_id || '',
    dateTime: dateStr + '  ' + timeStr,
    payer: payer,
    items: orderedItems,
    total: total,
    methodTotals: methodTotals
  };

  // Show
  document.getElementById('receipt-modal').classList.remove('hidden');
}

var currentReceiptEmailData = null;

function emailReceipt() {
  var d = currentReceiptEmailData;
  if (!d) return;
  var email = (d.payer && d.payer.data && d.payer.data.email) ? String(d.payer.data.email).trim() : '';
  if (!email) {
    email = window.prompt('Enter the email address to send this receipt to:') || '';
    email = email.trim();
    if (!email) return;
    if (d.payer && d.payer.data) d.payer.data.email = email;
  }
  var lines = [
    'Government of Belize — Treasury Revenue System',
    'OFFICIAL RECEIPT',
    '',
    'Receipt No: ' + (d.number || '—'),
    'Date/Time: ' + (d.dateTime || '—'),
    'Branch: ' + (BRANCH_NAME || '—'),
    'Cashier: ' + (CASHIER_NAME || '—'),
    'Payer: ' + (d.payer ? d.payer.name : 'Walk-in / Cash Customer'),
    '',
    'ITEMS',
    '-----'
  ];
  var emailBankStarted = false;
  for (var i = 0; i < d.items.length; i++) {
    var it = d.items[i];
    if ((it.payment_method || '') === 'bank_deposit' && !emailBankStarted) {
      emailBankStarted = true;
      lines.push('- - - - - - - - - - -  TEAR OFF / PRESENT AT BANK  - - - - - - - - - - -');
      lines.push('');
    }
    lines.push((i + 1) + '. ' + (it.activity_name || '') + '  —  BZD $' + parseFloat(it.amount || 0).toFixed(2));
    if (it.beneficiary_name) lines.push('   Beneficiary: ' + it.beneficiary_name);
    var dl = posPaymentDetailLines(it);
    for (var j = 0; j < dl.length; j++) { lines.push('   ' + dl[j].label + ': ' + dl[j].value); }
    lines.push('');
  }
  lines.push('TOTAL: BZD $' + parseFloat(d.total || 0).toFixed(2));
  lines.push('');
  if (receiptHasPendingBankDeposit(d.items)) {
    lines.push('NOTE: This receipt includes bank deposit item(s). Those payments are pending');
    lines.push('and are only confirmed once the referenced deposit is received by Treasury.');
    lines.push('');
  }
  lines.push('Verify at treasury.gov.bz/verify  ·  Ref: ' + (d.number || '—'));
  var subject = 'Treasury Receipt ' + (d.number || '');
  window.location.href = 'mailto:' + encodeURIComponent(email)
    + '?subject=' + encodeURIComponent(subject)
    + '&body=' + encodeURIComponent(lines.join('\n'));
}

document.getElementById('btn-print-receipt').addEventListener('click', function() {
  setReceiptPrintFormat((document.getElementById('receipt-print-format') || {}).value || 'half-letter');
  window.print();
});
document.getElementById('btn-print-receipt-2').addEventListener('click', function() {
  setReceiptPrintFormat((document.getElementById('receipt-print-format') || {}).value || 'half-letter');
  window.print();
});
document.getElementById('receipt-print-format').addEventListener('change', function() {
  setReceiptPrintFormat(this.value);
});
setReceiptPrintFormat((document.getElementById('receipt-print-format') || {}).value || 'half-letter');
var emailReceiptBtn1 = document.getElementById('btn-email-receipt');
if (emailReceiptBtn1) emailReceiptBtn1.addEventListener('click', emailReceipt);
var emailReceiptBtn2 = document.getElementById('btn-email-receipt-2');
if (emailReceiptBtn2) emailReceiptBtn2.addEventListener('click', emailReceipt);
document.getElementById('btn-close-receipt').addEventListener('click', function() {
  document.getElementById('receipt-modal').classList.add('hidden');
});
document.getElementById('btn-done-receipt').addEventListener('click', function() {
  document.getElementById('receipt-modal').classList.add('hidden');
  selectedPayer = null; updatePayerDisplay(); updatePayerSearchState();
});

// ---- Treasury mixed-payment receipt flow ----
var pendingCharge = null;
var serviceWizardStep = 'payer';
var receiptSidebarOpen = false;

function parsePaymentDetails(raw) {
  if (!raw) return {};
  if (typeof raw === 'object') return raw;
  try { return JSON.parse(raw); } catch (e) { return {}; }
}

function samePayer(a, b) {
  if (!a && !b) return true;
  if (!a || !b) return false;
  return a.type === b.type && String(a.id || '') === String(b.id || '') && String(a.name || '') === String(b.name || '');
}

function updatePayerSearchState() {
  var controls = document.getElementById('payer-search-controls');
  var resultsWrap = document.getElementById('payer-results-wrap');
  var changeBtn = document.getElementById('btn-change-payer-search');
  if (controls) controls.style.display = selectedPayer ? 'none' : '';
  if (resultsWrap) resultsWrap.style.display = selectedPayer ? 'none' : '';
  if (changeBtn) changeBtn.style.display = selectedPayer ? '' : 'none';
}

function openReceiptLockedModal() {
  var modal = document.getElementById('receipt-locked-modal');
  var payerEl = document.getElementById('receipt-locked-payer');
  var itemsEl = document.getElementById('receipt-locked-items');
  if (!modal || !payerEl || !itemsEl) return;

  payerEl.textContent = selectedPayer ? selectedPayer.name : 'No payer selected';
  if (!cartItems.length) {
    itemsEl.innerHTML = '<div class="text-sm text-gray-400 italic">No paid items in the current receipt.</div>';
  } else {
    itemsEl.innerHTML = cartItems.map(function(it) {
      return '<div class="rounded-xl border border-gray-200 bg-white px-4 py-3">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div class="min-w-0 flex-1">'
        + '<div class="text-sm font-semibold text-slate-900">' + escHtml(it.activity_name || '') + '</div>'
        + '<div class="text-xs text-slate-500 mt-1">' + escHtml(fmtMethod(it.payment_method || '')) + '</div>'
        + '<div class="text-xs text-slate-400 mt-1">Beneficiary: ' + escHtml(it.beneficiary_name || '--') + '</div>'
        + '</div>'
        + '<div class="text-sm font-bold text-slate-900 shrink-0">BZD $' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
        + '</div>'
        + '</div>';
    }).join('');
  }

  modal.classList.remove('hidden');
}

function openReceiptItemsModal() {
  var modal = document.getElementById('receipt-items-modal');
  var listEl = document.getElementById('receipt-items-list');
  var payerEl = document.getElementById('receipt-items-payer');
  var totalEl = document.getElementById('receipt-items-total');
  var subEl = document.getElementById('receipt-items-subtitle');
  var finalizeBtn = document.getElementById('btn-finalize-from-items');
  if (!modal || !listEl) return;

  payerEl.textContent = selectedPayer ? selectedPayer.name : 'No payer selected';
  totalEl.textContent = 'BZD $' + cartTotal().toFixed(2);
  if (subEl) subEl.textContent = cartItems.length
    ? cartItems.length + ' item' + (cartItems.length !== 1 ? 's' : '') + ' on the current receipt and their payment details.'
    : 'No items have been charged yet.';
  if (finalizeBtn) finalizeBtn.disabled = !cartItems.length;

  if (!cartItems.length) {
    listEl.innerHTML = '<div class="text-sm text-gray-400 italic text-center py-6">No items have been charged yet.</div>';
    modal.classList.remove('hidden');
    return;
  }

  listEl.innerHTML = cartItems.map(function(it, idx) {
    var lines = posPaymentDetailLines(it);
    var detailHtml = lines.map(function(l) {
      var mono = (l.label === 'Reference' || l.label === 'Cheque No.') ? ' font-mono' : '';
      return '<div class="flex items-center justify-between gap-3 py-0.5">'
        + '<span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">' + escHtml(l.label) + '</span>'
        + '<span class="text-xs font-semibold text-slate-800' + mono + '">' + escHtml(l.value) + '</span>'
        + '</div>';
    }).join('');
    return '<div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">'
      + '<div class="flex items-start justify-between gap-3 px-4 py-3 bg-[#fbfcf8] border-b border-[#eef3ea]">'
      +   '<div class="min-w-0">'
      +     '<div class="text-[10px] font-bold uppercase tracking-widest text-[#62725f]">Item ' + (idx + 1) + '</div>'
      +     '<div class="text-sm font-bold text-slate-900 mt-0.5">' + escHtml(it.activity_name || '') + '</div>'
      +     (it.activity_code ? '<div class="text-[11px] font-mono text-slate-400 mt-0.5">' + escHtml(it.activity_code) + '</div>' : '')
      +     '<div class="text-[11px] text-slate-500 mt-1">Beneficiary: ' + escHtml(it.beneficiary_name || '--') + '</div>'
      +   '</div>'
      +   '<div class="text-base font-black text-[#1e4620] shrink-0">BZD $' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
      + '</div>'
      + '<div class="px-4 py-3 space-y-0.5">' + detailHtml + '</div>'
      + '</div>';
  }).join('');

  modal.classList.remove('hidden');
}

(function wireReceiptItemsModal() {
  var pill = document.getElementById('btn-view-receipt-items');
  if (pill) pill.addEventListener('click', openReceiptItemsModal);
  var closeBtn = document.getElementById('btn-close-receipt-items');
  if (closeBtn) closeBtn.addEventListener('click', function() {
    document.getElementById('receipt-items-modal').classList.add('hidden');
  });
  var finalizeBtn = document.getElementById('btn-finalize-from-items');
  if (finalizeBtn) finalizeBtn.addEventListener('click', function() {
    document.getElementById('receipt-items-modal').classList.add('hidden');
    var checkoutBtn = document.getElementById('btn-checkout');
    if (checkoutBtn && !checkoutBtn.disabled) checkoutBtn.click();
  });
})();

function setSelectedPayer(payer) {
  if (cartItems.length && !samePayer(selectedPayer, payer)) {
    openReceiptLockedModal();
    return false;
  }
  selectedPayer = payer || null;
  updatePayerDisplay();
  updateCoPayerDisplay();
  updatePayerSearchState();
  if (typeof renderCart === 'function') renderCart();
  if (typeof updateInlineServiceCard === 'function') updateInlineServiceCard();
  if (pendingCharge) {
    updateServicePayerDisplay();
    updateServiceReview();
    setServiceWizardStep('service');
  }
  return true;
}

function syncPayerFromItems() {
  if (!cartItems.length) {
    selectedPayer = null;
    updatePayerDisplay();
    updateCoPayerDisplay();
    updatePayerSearchState();
    return;
  }
  var first = cartItems[0];
  if (first.customer_id) {
    selectedPayer = {
      type: 'customer',
      id: first.customer_id,
      name: first.customer_name || 'Customer',
      data: selectedPayer && selectedPayer.type === 'customer' ? selectedPayer.data : {}
    };
  } else if (first.dept_id) {
    selectedPayer = {
      type: 'department',
      id: first.dept_id,
      name: first.dept_name || 'Department',
      data: selectedPayer && selectedPayer.type === 'department' ? selectedPayer.data : {}
    };
  } else {
    selectedPayer = null;
  }
  updatePayerDisplay();
  updateCoPayerDisplay();
  updatePayerSearchState();
}

function loadDraftItems() {
  return apiPost({action:'load_cart', shift_id:SHIFT_ID}).then(function(d) {
    cartItems = (d.success && d.items ? d.items : []).map(function(it) {
      it.amount = parseFloat(it.amount || 0);
      it.payment_details = parsePaymentDetails(it.payment_details);
      return it;
    });
    syncPayerFromItems();
    renderCart();
  });
}

function setReceiptSidebar(open) {
  receiptSidebarOpen = !!open;
  var sidebar = document.getElementById('receipt-sidebar');
  var backdrop = document.getElementById('receipt-sidebar-backdrop');
  if (!sidebar || !backdrop) return;
  sidebar.classList.toggle('translate-x-full', !receiptSidebarOpen);
  backdrop.classList.toggle('hidden', !receiptSidebarOpen);
}

renderCart = function() {
  var emptyEl  = document.getElementById('cart-empty');
  var listEl   = document.getElementById('cart-list');
  var countEl  = document.getElementById('cart-count');
  var totalEl  = document.getElementById('cart-total');
  var checkBtn = document.getElementById('btn-checkout');
  var badgeEl  = document.getElementById('receipt-sidebar-count');

  countEl.textContent = cartItems.length + ' item' + (cartItems.length !== 1 ? 's' : '');
  totalEl.textContent = '$' + cartTotal().toFixed(2);
  checkBtn.disabled   = cartItems.length === 0;
  if (badgeEl) badgeEl.textContent = String(cartItems.length);

  if (!cartItems.length) {
    emptyEl.style.display = '';
    listEl.style.display  = 'none';
    return;
  }
  emptyEl.style.display = 'none';
  listEl.style.display  = 'block';

  var html = '';
  for (var i = 0; i < cartItems.length; i++) {
    var it = cartItems[i];
    var revTags = '';
    if (it.fund) revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-blue-50 text-blue-700 border border-blue-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">Fund</span> '+escHtml(it.fund)+'</span>';
    if (it.department_name) revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-purple-50 text-purple-700 border border-purple-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">Dept</span> '+escHtml(it.department_name)+'</span>';
    if (it.revenue_code) revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-amber-50 text-amber-700 border border-amber-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">RC</span> '+escHtml(it.revenue_code)+'</span>';
    if (it.gl_account) revTags += '<span class="inline-flex items-center gap-0.5 text-[9px] bg-green-50 text-green-700 border border-green-100 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">GL</span> '+escHtml(it.gl_account)+'</span>';

    html += '<div class="bg-white rounded-xl p-3 border border-gray-200">'
          + '<div class="flex items-start justify-between gap-2">'
          + '<div class="flex-1 min-w-0">'
          + '<div class="text-sm font-semibold text-gray-900 truncate">'+escHtml(it.activity_name || '')+'</div>'
          + (it.activity_code ? '<div class="text-[10px] text-gray-400 font-mono mb-1">'+escHtml(it.activity_code)+'</div>' : '')
          + '<div class="flex flex-wrap gap-1 mb-1">'
          + '<span class="inline-flex items-center gap-0.5 text-[9px] bg-slate-100 text-slate-700 border border-slate-200 rounded px-1.5 py-0.5 font-medium"><span class="font-bold">Paid By</span> '+escHtml(fmtMethod(it.payment_method || ''))+'</span>'
          + '</div>'
          + (revTags ? '<div class="flex flex-wrap gap-1">'+revTags+'</div>' : '')
          + '</div>'
          + '<div class="text-right flex flex-col items-end gap-1 shrink-0">'
          + '<div class="text-sm font-bold text-gray-900">BZD $'+parseFloat(it.amount || 0).toFixed(2)+'</div>'
          + '<button class="cart-del-btn text-red-400 hover:text-red-600 cursor-pointer" data-item-id="'+escHtml(String(it.id || ''))+'" title="Remove">'
          + '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6"/></svg>'
          + '</button>'
          + '</div></div></div>';
  }
  listEl.innerHTML = html;

  listEl.querySelectorAll('.cart-del-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var itemId = this.dataset.itemId;
      apiPost({action:'delete_cart_item', item_id:itemId}).then(function() {
        return loadDraftItems();
      });
    });
  });
};

function showServicePaymentModal(activity, amount) {
  pendingCharge = {activity: activity, amount: amount};
  document.getElementById('sp-total').textContent = '$' + parseFloat(amount || 0).toFixed(2);
  document.getElementById('sp-beneficiary').value = (selectedPayer && selectedPayer.type === 'customer') ? (selectedPayer.name || '') : '';
  document.getElementById('sp-error').classList.add('hidden');
  document.querySelectorAll('.sp-pay-btn').forEach(function(b) { b.classList.remove('selected'); });
  selectedPayMethod = '';
  ['sp-check-number','sp-check-bank','sp-check-holder','sp-bd-ref','sp-bd-amount','sp-ot-ref','sp-ot-sender','sp-ot-amount'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = '';
  });
  ['sp-bd-bank','sp-ot-bank'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = '';
  });
  updateServicePayerDisplay();
  updateServiceReview();
  showServicePayDetails('');
  setServiceWizardStep(selectedPayer ? 'service' : 'payer');
  validateServicePayment();
  document.getElementById('service-payment-modal').classList.remove('hidden');
}

addToCart = function(activity, amount) {
  showServicePaymentModal(activity, amount);
};

function updateServicePayerDisplay() {
  var el = document.getElementById('sp-payer-display');
  var summaryEl = document.getElementById('sp-payer-summary');
  if (!selectedPayer) {
    el.innerHTML = '<span class="text-slate-500 font-normal text-sm">No payer selected yet</span>';
    summaryEl.innerHTML = '<div class="text-slate-500">Select a customer or department to continue.</div>';
    return;
  }
  el.innerHTML = '<span class="font-semibold text-slate-900">'+escHtml(selectedPayer.name)+'</span>'
    + ' <span class="inline-block mt-2 text-[10px] bg-[#edf3ea] text-[#466946] rounded px-1.5 py-0.5 font-medium border border-[#d4e3cc]">'+escHtml(selectedPayer.type === 'customer' ? 'Customer' : 'Department')+'</span>';
  var data = selectedPayer.data || {};
  var rows = [];
  if (selectedPayer.type === 'customer') {
    if (data.tax_id) rows.push('<div><span class="text-slate-400">ID:</span> ' + escHtml(data.tax_id) + '</div>');
    if (data.phone) rows.push('<div><span class="text-slate-400">Phone:</span> ' + escHtml(data.phone) + '</div>');
    if (data.email) rows.push('<div><span class="text-slate-400">Email:</span> ' + escHtml(data.email) + '</div>');
  } else {
    if (data.code) rows.push('<div><span class="text-slate-400">Code:</span> ' + escHtml(data.code) + '</div>');
    if (data.ministry_name) rows.push('<div><span class="text-slate-400">Ministry:</span> ' + escHtml(data.ministry_name) + '</div>');
  }
  summaryEl.innerHTML = rows.join('') || '<div class="text-slate-500">Selected for this receipt.</div>';
}

function updateServiceReview() {
  if (!pendingCharge) return;
  var activity = pendingCharge.activity;
  var amount = pendingCharge.amount;
  document.getElementById('sp-item-card').innerHTML =
    '<div class="flex items-start justify-between gap-3">'
    + '<div class="flex-1 min-w-0">'
    + '<div class="text-lg font-bold text-slate-900 leading-tight">'+escHtml(activity.activity_name || '')+'</div>'
    + (activity.activity_code ? '<div class="text-[11px] text-slate-500 font-mono mt-1">'+escHtml(activity.activity_code)+'</div>' : '')
    + (activity.cost_center_name ? '<div class="text-xs text-slate-600 mt-3">'+escHtml(activity.cost_center_name)+'</div>' : '')
    + '</div>'
    + '<div class="text-base font-black text-[#1e4620] shrink-0">BZD $'+parseFloat(amount || 0).toFixed(2)+'</div></div>';

  document.getElementById('sp-service-review').innerHTML =
    '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'
    + '<div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Payer</div><div class="font-semibold text-slate-900">'+escHtml(selectedPayer ? selectedPayer.name : 'Not selected')+'</div></div>'
    + '<div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Amount</div><div class="font-semibold text-slate-900">BZD $'+parseFloat(amount || 0).toFixed(2)+'</div></div>'
    + '<div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Service</div><div class="font-semibold text-slate-900">'+escHtml(activity.activity_name || '')+'</div></div>'
    + '<div><div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Reference</div><div class="font-semibold text-slate-900">'+escHtml(activity.activity_code || '—')+'</div></div>'
    + '</div>';
}

function setServiceWizardStep(step) {
  serviceWizardStep = step;
  var steps = ['payer','service','payment'];
  steps.forEach(function(name, index) {
    var section = document.getElementById('sp-step-' + name);
    var pill = document.getElementById('sp-step-pill-' + name);
    if (section) section.classList.toggle('hidden', name !== step);
    if (pill) {
      var current = name === step;
      var complete = steps.indexOf(step) > index;
      pill.className = 'px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider '
        + (current ? 'bg-slate-900 text-white'
        : complete ? 'bg-emerald-100 text-emerald-700'
        : 'bg-slate-200 text-slate-600');
    }
  });
}

function hideServicePayDetails() {
  ['sp-pd-check','sp-pd-bank-deposit','sp-pd-online-transfer'].forEach(function(id) {
    document.getElementById(id).classList.add('hidden');
  });
}

function showServicePayDetails(method) {
  hideServicePayDetails();
  var wrap = document.getElementById('sp-pay-details');
  if (method === 'check') {
    wrap.classList.remove('hidden');
    document.getElementById('sp-pd-check').classList.remove('hidden');
  } else if (method === 'bank_deposit') {
    wrap.classList.remove('hidden');
    document.getElementById('sp-pd-bank-deposit').classList.remove('hidden');
    loadBankAccounts(function(a) { populateBankSelect('sp-bd-bank', a); });
  } else if (method === 'online_transfer') {
    wrap.classList.remove('hidden');
    document.getElementById('sp-pd-online-transfer').classList.remove('hidden');
    loadBankAccounts(function(a) { populateBankSelect('sp-ot-bank', a); });
  } else {
    wrap.classList.add('hidden');
  }
}

function collectServicePaymentDetails(method) {
  if (method === 'check') {
    return {
      check_number: document.getElementById('sp-check-number').value.trim(),
      bank_name: document.getElementById('sp-check-bank').value.trim(),
      holder: document.getElementById('sp-check-holder').value.trim()
    };
  }
  if (method === 'bank_deposit') {
    var bdSel = document.getElementById('sp-bd-bank');
    return {
      bank_account_id: bdSel.value,
      bank_name: bdSel.options[bdSel.selectedIndex] ? bdSel.options[bdSel.selectedIndex].dataset.bankName : '',
      reference: document.getElementById('sp-bd-ref').value.trim(),
      amount_deposited: parseFloat(document.getElementById('sp-bd-amount').value || '0')
    };
  }
  if (method === 'online_transfer') {
    var otSel = document.getElementById('sp-ot-bank');
    return {
      bank_account_id: otSel.value,
      bank_name: otSel.options[otSel.selectedIndex] ? otSel.options[otSel.selectedIndex].dataset.bankName : '',
      reference: document.getElementById('sp-ot-ref').value.trim(),
      sender_name: document.getElementById('sp-ot-sender').value.trim(),
      amount_sent: parseFloat(document.getElementById('sp-ot-amount').value || '0')
    };
  }
  return {};
}

function validateServicePayment() {
  var ok = !!pendingCharge && !!selectedPayer && !!selectedPayMethod && serviceWizardStep === 'payment';
  if (ok && selectedPayMethod === 'check') {
    ok = !!(document.getElementById('sp-check-number').value.trim() && document.getElementById('sp-check-bank').value.trim());
  } else if (ok && selectedPayMethod === 'bank_deposit') {
    ok = !!(document.getElementById('sp-bd-bank').value && document.getElementById('sp-bd-ref').value.trim() && document.getElementById('sp-bd-amount').value);
  } else if (ok && selectedPayMethod === 'online_transfer') {
    ok = !!(document.getElementById('sp-ot-bank').value && document.getElementById('sp-ot-ref').value.trim() && document.getElementById('sp-ot-sender').value.trim() && document.getElementById('sp-ot-amount').value);
  }
  document.getElementById('btn-confirm-service-payment').disabled = !ok;
}

function replaceNode(id) {
  var node = document.getElementById(id);
  var clone = node.cloneNode(true);
  node.parentNode.replaceChild(clone, node);
  return clone;
}

replaceNode('btn-amt-add').addEventListener('click', function() {
  var amt = parseFloat(document.getElementById('amt-input').value);
  if (isNaN(amt) || amt <= 0) { document.getElementById('amt-input').focus(); return; }
  document.getElementById('amt-modal').classList.add('hidden');
  if (pendingActivity) { showServicePaymentModal(pendingActivity, amt); pendingActivity = null; }
});

replaceNode('btn-checkout').addEventListener('click', function() {
  openCheckout();
});

if (document.getElementById('btn-open-receipt-sidebar')) {
  document.getElementById('btn-open-receipt-sidebar').addEventListener('click', function() {
    setReceiptSidebar(true);
  });
}

if (document.getElementById('btn-close-receipt-sidebar')) {
  document.getElementById('btn-close-receipt-sidebar').addEventListener('click', function() {
    setReceiptSidebar(false);
  });
}

if (document.getElementById('receipt-sidebar-backdrop')) {
  document.getElementById('receipt-sidebar-backdrop').addEventListener('click', function() {
    setReceiptSidebar(false);
  });
}

replaceNode('btn-confirm-checkout').addEventListener('click', function() {
  var btn = this;
  var errEl = document.getElementById('co-error');
  errEl.classList.add('hidden');
  if (!cartItems.length) {
    errEl.textContent = 'Receipt has no paid items.';
    errEl.classList.remove('hidden');
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Finalizing...';
  var receiptItems = cartItems.slice();
  var receiptPayer = selectedPayer;
  apiPost({action:'complete_transaction', shift_id:SHIFT_ID})
    .then(function(d) {
      if (!d.success) {
        errEl.textContent = d.message || 'Failed to finalize receipt.';
        errEl.classList.remove('hidden');
        return;
      }
      document.getElementById('checkout-modal').classList.add('hidden');
      showReceipt(d, receiptItems, receiptPayer);
      cartItems = [];
      selectedPayer = null;
      updatePayerDisplay();
      updateCoPayerDisplay();
      renderCart();
    })
    .catch(function(e) {
      errEl.textContent = 'Network error: ' + e.message;
      errEl.classList.remove('hidden');
    })
    .then(function() {
      btn.disabled = !cartItems.length;
      btn.textContent = 'Finalize & Print';
    });
});

replaceNode('btn-clear-payer').addEventListener('click', function() {
  if (cartItems.length) {
    openReceiptLockedModal();
    return;
  }
  selectedPayer = null;
  updatePayerDisplay();
  updateCoPayerDisplay();
  updatePayerSearchState();
  if (pendingCharge) {
    updateServicePayerDisplay();
    updateServiceReview();
    setServiceWizardStep('payer');
  }
});

document.getElementById('btn-sp-select-customer').addEventListener('click', function() {
  openCustModal();
});

document.getElementById('btn-sp-select-dept').addEventListener('click', function() {
  openDeptModal();
});

document.getElementById('btn-sp-continue-to-payment').addEventListener('click', function() {
  if (!selectedPayer) {
    document.getElementById('sp-error').textContent = 'Select a customer or department first.';
    document.getElementById('sp-error').classList.remove('hidden');
    return;
  }
  document.getElementById('sp-error').classList.add('hidden');
  setServiceWizardStep('payment');
  validateServicePayment();
});

document.querySelectorAll('.sp-pay-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    selectedPayMethod = this.dataset.method;
    document.querySelectorAll('.sp-pay-btn').forEach(function(b) { b.classList.remove('selected'); });
    this.classList.add('selected');
    showServicePayDetails(selectedPayMethod);
    validateServicePayment();
  });
});

['sp-check-number','sp-check-bank','sp-check-holder','sp-bd-ref','sp-bd-amount','sp-ot-ref','sp-ot-sender','sp-ot-amount'].forEach(function(id) {
  document.getElementById(id).addEventListener('input', validateServicePayment);
});
['sp-bd-bank','sp-ot-bank'].forEach(function(id) {
  document.getElementById(id).addEventListener('change', validateServicePayment);
});

document.getElementById('btn-confirm-service-payment').addEventListener('click', function() {
  var btn = this;
  var errEl = document.getElementById('sp-error');
  errEl.classList.add('hidden');
  if (!pendingCharge || !selectedPayer) {
    errEl.textContent = 'Select a customer or department first.';
    errEl.classList.remove('hidden');
    return;
  }
  if (serviceWizardStep !== 'payment' || !selectedPayMethod) {
    errEl.textContent = 'Finish the wizard and complete the payment details.';
    errEl.classList.remove('hidden');
    return;
  }
  var payDetails = collectServicePaymentDetails(selectedPayMethod);
  var beneficiary = document.getElementById('sp-beneficiary').value.trim();
  btn.disabled = true;
  btn.textContent = 'Charging...';
  apiPost({
    action:'add_to_cart',
    shift_id:SHIFT_ID,
    activity_id:pendingCharge.activity.id,
    activity_name:pendingCharge.activity.activity_name,
    activity_code:pendingCharge.activity.activity_code || '',
    amount:pendingCharge.amount,
    payment_method:selectedPayMethod,
    payment_details:payDetails,
    beneficiary_name:beneficiary,
    customer_id:selectedPayer && selectedPayer.type === 'customer' ? selectedPayer.id : null,
    customer_name:selectedPayer && selectedPayer.type === 'customer' ? selectedPayer.name : null,
    dept_id:selectedPayer && selectedPayer.type === 'department' ? selectedPayer.id : null,
    dept_name:selectedPayer && selectedPayer.type === 'department' ? selectedPayer.name : null
  }).then(function(d) {
    if (!d.success) {
      errEl.textContent = d.message || 'Failed to add paid item.';
      errEl.classList.remove('hidden');
      return;
    }
    document.getElementById('service-payment-modal').classList.add('hidden');
    pendingCharge = null;
    serviceWizardStep = 'payer';
    return loadDraftItems();
  }).catch(function(e) {
    errEl.textContent = 'Network error: ' + e.message;
    errEl.classList.remove('hidden');
  }).then(function() {
    btn.disabled = false;
    btn.textContent = 'Charge & Add to Receipt';
  });
});

openCheckout = function() {
  if (!cartItems.length) return;
  var itemsHtml = '';
  for (var i = 0; i < cartItems.length; i++) {
    var it = cartItems[i];
    itemsHtml += '<div class="flex items-start justify-between gap-2 pb-2 border-b border-gray-100 last:border-0 last:pb-0">'
      + '<div class="flex-1 min-w-0">'
      + '<div class="text-sm text-gray-800 font-medium">'+escHtml(it.activity_name || '')
      + (it.activity_code ? ' <span class="text-[10px] text-gray-400 font-mono">'+escHtml(it.activity_code)+'</span>' : '')+'</div>'
      + '<div class="text-[11px] text-gray-500 mt-1">'+escHtml(fmtMethod(it.payment_method || ''))+'</div>'
      + '</div>'
      + '<div class="text-sm font-bold shrink-0">BZD $'+parseFloat(it.amount || 0).toFixed(2)+'</div></div>';
  }
  document.getElementById('co-items-list').innerHTML = itemsHtml;
  document.getElementById('co-total').textContent = '$' + cartTotal().toFixed(2);
  document.getElementById('co-error').classList.add('hidden');
  updateCoPayerDisplay();
  document.querySelectorAll('.co-pay-btn').forEach(function(el) {
    el.closest('div').style.display = 'none';
  });
  var detailWrap = document.getElementById('co-pay-details');
  if (detailWrap) detailWrap.style.display = 'none';
  var beneficiaryWrap = document.getElementById('co-beneficiary');
  if (beneficiaryWrap && beneficiaryWrap.parentNode) beneficiaryWrap.parentNode.style.display = 'none';
  document.getElementById('btn-confirm-checkout').disabled = false;
  document.getElementById('checkout-modal').classList.remove('hidden');
};

validateCheckout = function() {
  document.getElementById('btn-confirm-checkout').disabled = !cartItems.length;
};

showReceipt = function(tx, items, payer) {
  // Finalized receipts now open as a dedicated page with a success banner,
  // instead of an in-terminal modal.
  if (tx && tx.transaction_id) {
    window.location.href = POS_RECEIPT_URL + '?tx=' + encodeURIComponent(tx.transaction_id) + '&new=1';
    return;
  }
  var now = new Date();
  function pad2(n) { return n < 10 ? '0'+n : ''+n; }
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var dateStr = pad2(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
  var h12 = now.getHours() % 12 || 12;
  var ampm = now.getHours() >= 12 ? 'PM' : 'AM';
  var timeStr = pad2(h12) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds()) + ' ' + ampm;

  document.getElementById('rct-number').textContent = tx.transaction_id || '--';
  document.getElementById('rct-datetime').textContent = dateStr + '  ' + timeStr;
  document.getElementById('rct-branch').textContent = BRANCH_NAME || '--';
  document.getElementById('rct-verify-ref').textContent = tx.transaction_id || '--';
  setReceiptPrintFormat((document.getElementById('receipt-print-format') || {}).value || 'half-letter');
  document.getElementById('rct-processed-by').textContent = 'Processed by: ' + CASHIER_NAME + ' · ' + dateStr + ' ' + timeStr;

  var payerHtml = '';
  function payerField(label, value) {
    return '<div><div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;">'
      + escHtml(label) + '</div><div style="font-weight:600;color:#111827;">' + escHtml(value) + '</div></div>';
  }
  if (payer && payer.type === 'customer') {
    var c = payer.data || {};
    payerHtml += payerField('Name', payer.name);
    payerHtml += payerField('Type', c.customer_type ? (c.customer_type.charAt(0).toUpperCase() + c.customer_type.slice(1)) : 'Individual');
    if (c.tax_id) payerHtml += payerField('TIN / ID #', c.tax_id);
    if (c.phone) payerHtml += payerField('Phone', c.phone);
    if (c.email) payerHtml += payerField('Email', c.email);
  } else if (payer && payer.type === 'department') {
    var dp = payer.data || {};
    payerHtml += payerField('Name', payer.name);
    payerHtml += payerField('Type', 'Government Department');
    if (dp.code) payerHtml += payerField('Code', dp.code);
    if (dp.ministry_name) payerHtml += payerField('Ministry', dp.ministry_name);
  } else {
    payerHtml += payerField('Name', 'Walk-in / Cash Customer');
    payerHtml += payerField('Type', 'Individual');
  }
  document.getElementById('rct-payer-block').innerHTML = payerHtml;

  var hasPendingBankDeposit = receiptHasPendingBankDeposit(items);

  // Totals (sum items; fall back to tx.total).
  var total = 0;
  for (var t = 0; t < items.length; t++) { total += parseFloat(items[t].amount || 0); }
  if (!total && tx && tx.total) total = parseFloat(tx.total || 0);

  // Order by payment type: collected payments (cash first) before bank
  // deposits, which are grouped at the bottom for a tear-off slip.
  function methodOrder(m) {
    var order = {cash:0, check:1, pos_terminal:2, online_transfer:3, e_invoicing:4, bank_deposit:9};
    return (order[m] != null) ? order[m] : 5;
  }
  var orderedItems = items.slice().sort(function(a, b) {
    return methodOrder(a.payment_method || '') - methodOrder(b.payment_method || '');
  });

  var methodTotals = {};
  var bankDepositTotal = 0;
  var itemsHtml = '';
  var bankSectionStarted = false;
  for (var i = 0; i < orderedItems.length; i++) {
    var it = orderedItems[i];
    var pd = parsePaymentDetails(it.payment_details);
    var m = it.payment_method || '';
    methodTotals[m] = (methodTotals[m] || 0) + parseFloat(it.amount || 0);
    var isBank = (m === 'bank_deposit');
    if (isBank) bankDepositTotal += parseFloat(it.amount || 0);

    if (isBank && !bankSectionStarted) {
      bankSectionStarted = true;
      if (itemsHtml) {
        itemsHtml += '<div style="border-top:2px dashed #1e4620;background:#f8fdf5;text-align:center;padding:7px 8px 6px;">'
          + '<span style="display:inline-block;background:#fff;border:1.5px solid #1e4620;border-radius:999px;padding:2px 12px;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1e4620;">&#9986; Bank Deposit &mdash; Tear Off &amp; Present At Bank</span>'
          + '</div>';
      }
    }

    var ref = posBankDepositReference(it) || ((m === 'online_transfer' && pd.reference) ? pd.reference : '');
    var refSize = isBank ? '8px' : '7px';
    var rowBg = isBank ? '#fbfcf8' : ((i % 2 === 0) ? '#fff' : '#f8fdf5');
    var paymentStateHtml = isBank
      ? '<div style="margin-top:2px;font-size:7px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#991b1b;">Pending / Not Paid</div>'
      : '';
    itemsHtml += '<div style="display:grid;grid-template-columns:1.2fr 1fr 86px 70px;gap:4px;padding:6px 8px;border-bottom:1px solid #e8f3e8;background:' + rowBg + ';">'
      + '<div style="font-size:10px;color:#111827;">'
      + '<div style="font-weight:600;">' + escHtml(it.activity_name || '') + '</div>'
      + (it.activity_code ? '<div style="font-size:8px;font-family:monospace;color:#6b7280;">' + escHtml(it.activity_code) + '</div>' : '')
      + (it.cost_center_name ? '<div style="font-size:8px;color:#9ca3af;">' + escHtml(it.cost_center_name) + '</div>' : '')
      + '</div>'
      + '<div style="font-size:9px;color:#374151;padding-top:3px;">' + escHtml(it.beneficiary_name || '--') + '</div>'
      + '<div style="font-size:8px;color:#111827;text-align:center;padding-top:2px;">'
      +   '<div style="font-weight:700;">' + escHtml(fmtMethod(m) || '--') + '</div>'
      +   (ref ? '<div style="font-size:' + refSize + ';font-weight:700;font-family:monospace;color:#1d4ed8;word-break:break-all;">' + escHtml(ref) + '</div>' : '')
      +   paymentStateHtml
      + '</div>'
      + '<div style="font-size:10px;font-weight:700;color:#111827;text-align:right;padding-top:3px;">$' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
      + '</div>';
  }
  if (bankSectionStarted && bankDepositTotal === total && total > 0) {
    itemsHtml = '<div style="background:#f8fdf5;text-align:center;padding:6px 8px;border-bottom:1px solid #e8f3e8;">'
      + '<span style="display:inline-block;background:#fff;border:1.5px solid #1e4620;border-radius:999px;padding:2px 12px;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1e4620;">&#9986; Bank Deposit &mdash; Present At Bank</span>'
      + '</div>' + itemsHtml;
  }
  if (!itemsHtml) itemsHtml = '<div style="padding:10px 8px;font-size:10px;color:#6b7280;text-align:center;">No items.</div>';
  document.getElementById('rct-items').innerHTML = itemsHtml;
  document.getElementById('rct-total').textContent = 'BZD $' + total.toFixed(2);
  document.getElementById('rct-amount-words').textContent = numberToWords(total);

  // Payment block: method breakdown + bank deposit references.
  var paymentBlockEl = document.getElementById('rct-payment-block');
  if (paymentBlockEl) {
    var pmHtml = '';
    Object.keys(methodTotals).forEach(function(mk) {
      pmHtml += payerField(fmtMethod(mk), 'BZD $' + methodTotals[mk].toFixed(2));
    });
    for (var r = 0; r < orderedItems.length; r++) {
      var rRef = posBankDepositReference(orderedItems[r]);
      if (rRef) pmHtml += payerField('Deposit Reference', rRef);
    }
    paymentBlockEl.innerHTML = pmHtml || payerField('Method', '--');
  }

  var statusBanner = document.getElementById('rct-payment-status-banner');
  var statusTitle = document.getElementById('rct-payment-status-title');
  var statusText = document.getElementById('rct-payment-status-text');
  var statusNote = document.getElementById('rct-payment-status-note');
  if (statusBanner) statusBanner.style.display = hasPendingBankDeposit ? 'block' : 'none';
  if (statusNote) statusNote.style.display = hasPendingBankDeposit ? 'block' : 'none';
  if (hasPendingBankDeposit) {
    if (statusTitle) statusTitle.textContent = 'Pending Payment / Not Paid';
    if (statusText) statusText.textContent = 'Bank deposit instructions were issued to the customer. Payment has not yet been received by Treasury and remains pending until matched by reference number.';
  }

  // Stash data for email delivery.
  currentReceiptEmailData = {
    number: tx.transaction_id || '',
    dateTime: dateStr + '  ' + timeStr,
    payer: payer,
    items: orderedItems,
    total: total,
    methodTotals: methodTotals
  };

  document.getElementById('receipt-modal').classList.remove('hidden');
};

loadDraftItems();

// ---- Three-panel inline POS flow ----
(function() {
  var payerMode = 'customer';
  var successActionMode = 'receipt';
  var latestBankInstructionData = null;
  function setPaymentSuccess(show) {
    var panel = document.getElementById('payment-success-panel');
    var entry = document.getElementById('payment-entry-panel');
    if (!panel) return;
    panel.classList.toggle('hidden', !show);
    if (entry) entry.classList.toggle('hidden', show);
  }

  function setInlineSuccessState(mode) {
    successActionMode = mode === 'bank_deposit' ? 'bank_deposit' : 'receipt';
    var title = document.getElementById('payment-success-title');
    var message = document.getElementById('payment-success-message');
    var actionBtn = document.getElementById('btn-print-from-success');
    if (!title || !message || !actionBtn) return;
    if (successActionMode === 'bank_deposit') {
      title.textContent = 'Bank deposit instructions generated.';
      message.textContent = 'Print or email the instructions now, or start a new transaction.';
      actionBtn.textContent = 'Open Print / Email Instructions';
      return;
    }
    title.textContent = 'Service added to receipt.';
    message.textContent = 'Start a new transaction or print the receipt now.';
    actionBtn.textContent = 'Finalize Transaction and Print Receipt';
  }

  function updateInlineChargeButtonLabel() {
    var btn = document.getElementById('btn-charge-inline');
    if (btn) btn.textContent = selectedPayMethod === 'bank_deposit'
      ? 'Generate Payment Instructions'
      : 'Charge & Add to Receipt';
    var footer = document.getElementById('inline-charge-footer');
    if (footer) footer.classList.toggle('hidden', selectedPayMethod === 'bank_deposit');
  }

  function resetInlinePaymentFields() {
    ['page-cash-tendered','page-check-number','page-check-bank','page-check-holder','page-ot-ref','page-ot-sender','page-ot-amount','page-beneficiary'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    ['page-bd-bank','page-ot-bank'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    document.querySelectorAll('.page-pay-btn').forEach(function(b) { b.classList.remove('selected'); });
    selectedPayMethod = '';
    showInlinePayDetails('');
    updateInlineChargeButtonLabel();
  }

  function buildBankDepositInstructionData(paymentDetails, beneficiaryName) {
    var activity = pendingCharge && pendingCharge.activity ? pendingCharge.activity : {};
    var payerData = selectedPayer && selectedPayer.type === 'customer' ? (selectedPayer.data || {}) : {};
    var purpose = activity.activity_name || 'Treasury payment';
    if (beneficiaryName) purpose += ' for ' + beneficiaryName;
    return {
      payerName: selectedPayer ? selectedPayer.name : 'Walk-in / Cash Customer',
      payerEmail: payerData.email || '',
      bankName: paymentDetails.bank_name || '',
      accountNumber: paymentDetails.account_number || paymentDetails.account_masked || '',
      accountMasked: paymentDetails.account_masked || '',
      reference: paymentDetails.reference || '',
      purpose: purpose,
      amount: parseFloat((pendingCharge && pendingCharge.amount) || 0)
    };
  }

  function clearPendingCharge(goToServices) {
    pendingCharge = null;
    resetInlinePaymentFields();
    setInlineSuccessState('receipt');
    updateInlineServiceCard();
    updateInlinePaymentSummary();
    validateInlineCharge();
    if (goToServices) {
      closeInlineServiceEntry();
    } else {
      setInlineWizardStep('details');
    }
  }

  function updatePayerModeButtons() {
    var custBtn = document.getElementById('payer-mode-customer');
    var deptBtn = document.getElementById('payer-mode-department');
    if (!custBtn || !deptBtn) return;
    custBtn.className = 'flex-1 py-2 rounded-xl text-xs font-bold uppercase tracking-wide ' + (payerMode === 'customer' ? 'bg-[#1e4620] text-white' : 'bg-white border border-gray-200 text-gray-600');
    deptBtn.className = 'flex-1 py-2 rounded-xl text-xs font-bold uppercase tracking-wide ' + (payerMode === 'department' ? 'bg-[#1e4620] text-white' : 'bg-white border border-gray-200 text-gray-600');
    var addBtn = document.getElementById('btn-inline-add-cust');
    if (addBtn) addBtn.style.display = payerMode === 'customer' ? '' : 'none';
  }

  function renderPayerResults(rows) {
    var wrap = document.getElementById('payer-results');
    var empty = document.getElementById('payer-search-empty');
    if (!wrap || !empty) return;
    wrap.innerHTML = '';
    if (!rows.length) {
      empty.classList.remove('hidden');
      return;
    }
    empty.classList.add('hidden');
    rows.forEach(function(row) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full text-left px-3 py-3 rounded-2xl border border-gray-200 bg-white hover:border-[#87a276] hover:bg-[#f6faf2] transition-all';
      if (payerMode === 'customer') {
        var name = row.customer_name || (((row.first_name || '') + ' ' + (row.last_name || '')).trim()) || '—';
        btn.innerHTML = '<div class="text-sm font-semibold text-gray-900">' + escHtml(name) + '</div>'
          + '<div class="text-xs text-gray-500 mt-1">' + escHtml(row.phone || row.email || row.tax_id || '') + '</div>';
        btn.addEventListener('click', function() {
          setSelectedPayer({type:'customer', id:row.id, name:name, data:row});
        });
      } else {
        btn.innerHTML = '<div class="text-sm font-semibold text-gray-900">' + escHtml(row.name || '') + '</div>'
          + '<div class="text-xs text-gray-500 mt-1">' + escHtml(row.code || '') + (row.ministry_name ? ' · ' + escHtml(row.ministry_name) : '') + '</div>';
        btn.addEventListener('click', function() {
          setSelectedPayer({type:'department', id:row.id, name:row.name, data:row});
        });
      }
      wrap.appendChild(btn);
    });
  }

  var payerSearchTimer = null;
  function runPayerSearch() {
    var input = document.getElementById('payer-search-input');
    if (!input) return;
    var q = input.value.trim();
    if (!q) {
      document.getElementById('payer-results').innerHTML = '';
      document.getElementById('payer-search-empty').classList.add('hidden');
      return;
    }
    clearTimeout(payerSearchTimer);
    payerSearchTimer = setTimeout(function() {
      apiPost({action: payerMode === 'customer' ? 'search_customers' : 'search_departments', query: q}).then(function(d) {
        renderPayerResults(payerMode === 'customer' ? (d.customers || []) : (d.departments || []));
      });
    }, 250);
  }

  function updateInlineServiceCard() {
    var card = document.getElementById('active-service-card');
    if (!card) return;
    if (!pendingCharge) {
      card.className = 'rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-400';
      card.innerHTML = 'No service selected yet.';
      return;
    }
    var activity = pendingCharge.activity;
    var amount = pendingCharge.amount;
    card.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4';
    card.innerHTML = '<div class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 mb-3">Selected Charge</div>'
      + '<div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Service</div>'
      + '<div class="text-base font-bold text-slate-900">' + escHtml(activity.activity_name || '') + '</div>'
      + (activity.activity_code ? '<div class="text-xs font-mono text-slate-500 mt-1">' + escHtml(activity.activity_code) + '</div>' : '')
      + '<div class="text-sm font-black text-[#1e4620] mt-3">BZD $' + parseFloat(amount || 0).toFixed(2) + '</div>';
  }

  function hideInlinePayDetails() {
    ['page-pd-cash','page-pd-check','page-pd-bank-deposit','page-pd-online-transfer'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.classList.add('hidden');
    });
  }

  function updateInlinePaymentMethodVisibility() {
    var wrap = document.getElementById('page-pay-methods');
    var switchBtn = document.getElementById('btn-switch-payment-method');
    var buttons = document.querySelectorAll('.page-pay-btn');
    if (!wrap || !switchBtn || !buttons.length) return;

    if (!selectedPayMethod) {
      wrap.classList.remove('hidden');
      switchBtn.classList.add('hidden');
      buttons.forEach(function(btn) {
        btn.classList.remove('hidden');
        btn.classList.remove('selected');
      });
      return;
    }

    wrap.classList.remove('hidden');
    switchBtn.classList.remove('hidden');
    buttons.forEach(function(btn) {
      var isSelected = btn.dataset.method === selectedPayMethod;
      btn.classList.toggle('hidden', !isSelected);
      btn.classList.toggle('selected', isSelected);
    });
  }

  function updateCashTenderedSummary() {
    var changeEl = document.getElementById('page-cash-change');
    if (!changeEl) return;
    var due = pendingCharge ? parseFloat(pendingCharge.amount || 0) : 0;
    var tendered = parseFloat((document.getElementById('page-cash-tendered') || {}).value || '0');
    var change = Math.max(0, tendered - due);
    changeEl.textContent = 'BZD $' + change.toFixed(2);
  }

  function showInlinePayDetails(method) {
    var wrap = document.getElementById('page-pay-details');
    if (!wrap) return;
    hideInlinePayDetails();
    if (method === 'cash') {
      wrap.classList.remove('hidden');
      document.getElementById('page-pd-cash').classList.remove('hidden');
      updateCashTenderedSummary();
    } else if (method === 'check') {
      wrap.classList.remove('hidden');
      document.getElementById('page-pd-check').classList.remove('hidden');
    } else if (method === 'bank_deposit') {
      wrap.classList.remove('hidden');
      document.getElementById('page-pd-bank-deposit').classList.remove('hidden');
      loadBankAccounts(function(a) { populateBankSelect('page-bd-bank', a); });
    } else if (method === 'online_transfer') {
      wrap.classList.remove('hidden');
      document.getElementById('page-pd-online-transfer').classList.remove('hidden');
      loadBankAccounts(function(a) { populateBankSelect('page-ot-bank', a); });
    } else {
      wrap.classList.add('hidden');
    }
    updateInlinePaymentMethodVisibility();
    updateInlineChargeButtonLabel();
  }

  function collectInlinePaymentDetails() {
    if (selectedPayMethod === 'cash') {
      return {amount_tendered: parseFloat(document.getElementById('page-cash-tendered').value || '0'), change_due: Math.max(0, parseFloat(document.getElementById('page-cash-tendered').value || '0') - parseFloat((pendingCharge && pendingCharge.amount) || 0))};
    }
    if (selectedPayMethod === 'check') {
      return {check_number: document.getElementById('page-check-number').value.trim(), bank_name: document.getElementById('page-check-bank').value.trim(), holder: document.getElementById('page-check-holder').value.trim()};
    }
    if (selectedPayMethod === 'pos_terminal') {
      return {bank_account_id: SHIFT_BANK_ACCOUNT_ID || '', bank_name: SHIFT_BANK_ACCOUNT_NAME || ''};
    }
    if (selectedPayMethod === 'bank_deposit') {
      var bdMeta = getSelectedBankAccountMeta('page-bd-bank');
      var beneficiary = document.getElementById('page-beneficiary');
      return {
        bank_account_id: bdMeta.bank_account_id,
        bank_name: bdMeta.bank_name,
        account_name: bdMeta.account_name,
        account_number: bdMeta.account_number,
        account_masked: bdMeta.account_masked,
        reference: generateBankDepositReference(),
        purpose: (pendingCharge && pendingCharge.activity && pendingCharge.activity.activity_name ? pendingCharge.activity.activity_name : 'Treasury payment') + ((beneficiary && beneficiary.value.trim()) ? ' for ' + beneficiary.value.trim() : ''),
        amount_to_be_paid: parseFloat((pendingCharge && pendingCharge.amount) || 0)
      };
    }
    if (selectedPayMethod === 'online_transfer') {
      var otMeta = getSelectedBankAccountMeta('page-ot-bank');
      return {bank_account_id: otMeta.bank_account_id, bank_name: otMeta.bank_name, reference: document.getElementById('page-ot-ref').value.trim(), sender_name: document.getElementById('page-ot-sender').value.trim(), amount_sent: parseFloat(document.getElementById('page-ot-amount').value || '0')};
    }
    return {};
  }

  function validateInlineCharge() {
    var beneficiary = document.getElementById('page-beneficiary');
    var ok = !!pendingCharge && !!selectedPayer && !!selectedPayMethod && !!(beneficiary && beneficiary.value.trim());
    if (ok && selectedPayMethod === 'cash') {
      ok = parseFloat(document.getElementById('page-cash-tendered').value || '0') >= parseFloat(pendingCharge.amount || 0);
      updateCashTenderedSummary();
    }
    if (ok && selectedPayMethod === 'check') ok = !!(document.getElementById('page-check-number').value.trim() && document.getElementById('page-check-bank').value.trim());
    if (ok && selectedPayMethod === 'bank_deposit') ok = !!document.getElementById('page-bd-bank').value;
    if (ok && selectedPayMethod === 'online_transfer') ok = !!(document.getElementById('page-ot-bank').value && document.getElementById('page-ot-ref').value.trim() && document.getElementById('page-ot-sender').value.trim() && document.getElementById('page-ot-amount').value);
    var btn = document.getElementById('btn-charge-inline');
    if (btn) btn.disabled = !ok;
    var genBtn = document.getElementById('btn-generate-reference');
    if (genBtn) genBtn.disabled = !ok;
  }

  renderCart = function() {
    var emptyEl  = document.getElementById('cart-empty');
    var listEl   = document.getElementById('cart-list');
    var countEl  = document.getElementById('cart-count');
    var totalEl  = document.getElementById('cart-total');
    var checkBtn = document.getElementById('btn-checkout');
    var summaryActions = document.getElementById('receipt-summary-actions');
    var headerBlock = document.getElementById('receipt-header-block');
    var previewPayer = document.getElementById('receipt-preview-payer');
    var previewBeneficiary = document.getElementById('receipt-preview-beneficiary');
    var workspacePayer = document.getElementById('receipt-workspace-payer');

    countEl.textContent = cartItems.length + (cartItems.length !== 1 ? ' items charged' : ' item charged');
    totalEl.textContent = '$' + cartTotal().toFixed(2);
    checkBtn.disabled = cartItems.length === 0;
    if (summaryActions) summaryActions.classList.toggle('hidden', !selectedPayer && !cartItems.length);
    if (headerBlock) headerBlock.classList.toggle('hidden', !selectedPayer && !cartItems.length);
    updateReceiptCardVisibility();
    if (previewPayer) previewPayer.textContent = selectedPayer ? selectedPayer.name : 'No payer selected';
    if (workspacePayer) workspacePayer.textContent = selectedPayer ? selectedPayer.name : 'No payer selected';
    if (previewBeneficiary) {
      var bn = cartItems.length ? (cartItems[0].beneficiary_name || '--') : '--';
      previewBeneficiary.textContent = 'Beneficiary: ' + bn;
    }

    if (!cartItems.length) {
      emptyEl.style.display = '';
      listEl.style.display = 'none';
      return;
    }
    emptyEl.style.display = 'none';
    listEl.style.display = 'block';

    var html = '';
    for (var i = 0; i < cartItems.length; i++) {
      var it = cartItems[i];
      html += '<div class="bg-white rounded-xl p-3 border border-gray-200">'
        + '<div class="flex items-start justify-between gap-2">'
        + '<div class="flex-1 min-w-0">'
        + '<div class="text-sm font-semibold text-gray-900">' + escHtml(it.activity_name || '') + '</div>'
        + '<div class="text-[11px] text-gray-500 mt-1">' + escHtml(fmtMethod(it.payment_method || '')) + '</div>'
        + (posBankDepositReference(it) ? '<div class="text-[11px] text-blue-700 font-mono mt-1">Ref: ' + escHtml(posBankDepositReference(it)) + '</div>' : '')
        + (it.beneficiary_name ? '<div class="text-[11px] text-gray-400 mt-1">Beneficiary: ' + escHtml(it.beneficiary_name) + '</div>' : '')
        + '</div>'
        + '<div class="text-right flex flex-col items-end gap-1 shrink-0">'
        + '<div class="text-sm font-bold text-gray-900">BZD $' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
        + '<button class="cart-del-btn text-red-400 hover:text-red-600 cursor-pointer" data-item-id="' + escHtml(String(it.id || '')) + '" title="Remove">Remove</button>'
        + '</div></div></div>';
    }
    listEl.innerHTML = html;
    listEl.querySelectorAll('.cart-del-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        apiPost({action:'delete_cart_item', item_id:this.dataset.itemId}).then(function() { return loadDraftItems(); });
      });
    });
  };

  addToCart = function(activity, amount) {
    pendingCharge = {activity: activity, amount: amount};
    latestBankInstructionData = null;
    setInlineSuccessState('receipt');
    setPaymentSuccess(false);
    resetInlinePaymentFields();
    var benefEl = document.getElementById('page-beneficiary');
    benefEl.value = selectedPayer ? (selectedPayer.name || '') : '';
    benefEl.classList.remove('pos-required-error');
    setBeneficiaryEditMode(false);
    updateInlineServiceCard();
    updateInlinePaymentSummary();
    validateInlineCharge();
    setInlineWizardStep('payment');
  };

  var payerInput = document.getElementById('payer-search-input');
  if (payerInput) {
    payerInput = replaceNode('payer-search-input');
    payerInput.addEventListener('input', runPayerSearch);
  }
  var serviceSearchInput = document.getElementById('search-services');
  if (serviceSearchInput) {
    serviceSearchInput = replaceNode('search-services');
    serviceSearchInput.addEventListener('input', function() {
      var q = this.value.toLowerCase().trim();
      if (!q) {
        renderServices(allServices);
        return;
      }
      renderServices(allServices.filter(function(a) {
        return (a.activity_name || '').toLowerCase().indexOf(q) !== -1
          || (a.activity_code || '').toLowerCase().indexOf(q) !== -1
          || (a.cost_center_name || '').toLowerCase().indexOf(q) !== -1;
      }));
    });
  }
  var custMode = document.getElementById('payer-mode-customer');
  var deptMode = document.getElementById('payer-mode-department');
  if (custMode) custMode.addEventListener('click', function() { payerMode = 'customer'; updatePayerModeButtons(); runPayerSearch(); });
  if (deptMode) deptMode.addEventListener('click', function() { payerMode = 'department'; updatePayerModeButtons(); runPayerSearch(); });
  var addCust = document.getElementById('btn-inline-add-cust');
  if (addCust) addCust.addEventListener('click', function() { document.getElementById('add-cust-modal').classList.remove('hidden'); });
  var changePayerSearchBtn = document.getElementById('btn-change-payer-search');
  if (changePayerSearchBtn) changePayerSearchBtn.addEventListener('click', function() {
    if (cartItems.length) {
      openReceiptLockedModal();
      return;
    }
    selectedPayer = null;
    updatePayerDisplay();
    updateCoPayerDisplay();
    updatePayerSearchState();
    renderCart();
    var payerInputFocus = document.getElementById('payer-search-input');
    if (payerInputFocus) {
      payerInputFocus.value = '';
      setTimeout(function() { payerInputFocus.focus(); }, 0);
    }
  });
  var switchPayMethodBtn = document.getElementById('btn-switch-payment-method');
  if (switchPayMethodBtn) switchPayMethodBtn.addEventListener('click', function() {
    selectedPayMethod = '';
    showInlinePayDetails('');
    validateInlineCharge();
    updateInlineChargeButtonLabel();
  });
  var closeReceiptLockedBtn = document.getElementById('btn-close-receipt-locked');
  if (closeReceiptLockedBtn) closeReceiptLockedBtn.addEventListener('click', function() {
    document.getElementById('receipt-locked-modal').classList.add('hidden');
  });
  var printReceiptLockedBtn = document.getElementById('btn-print-receipt-locked');
  if (printReceiptLockedBtn) printReceiptLockedBtn.addEventListener('click', function() {
    document.getElementById('receipt-locked-modal').classList.add('hidden');
    var printBtn = document.getElementById('btn-checkout');
    if (printBtn && !printBtn.disabled) printBtn.click();
  });
  var clearPayerBtnInline = replaceNode('btn-clear-payer');
  clearPayerBtnInline.addEventListener('click', function() {
    if (cartItems.length) {
      openReceiptLockedModal();
      return;
    }
    selectedPayer = null;
    setPaymentSuccess(false);
    clearPendingCharge(true);
    updatePayerDisplay();
    updateCoPayerDisplay();
    updatePayerSearchState();
    renderCart();
  });
  var backToServicesBtn = document.getElementById('btn-back-to-services');
  if (backToServicesBtn) backToServicesBtn.addEventListener('click', function() {
    setPaymentSuccess(false);
    clearPendingCharge(true);
  });
  var inlineWizardBackBtn = document.getElementById('btn-inline-wizard-back');
  if (inlineWizardBackBtn) inlineWizardBackBtn.addEventListener('click', function() {
    setPaymentSuccess(false);
    setInlineWizardStep('details');
  });
  var inlineInstructionsBackBtn = document.getElementById('btn-inline-instructions-back');
  if (inlineInstructionsBackBtn) inlineInstructionsBackBtn.addEventListener('click', function() {
    setPaymentSuccess(false);
    setInlineWizardStep('payment');
  });
  var genReferenceBtn = document.getElementById('btn-generate-reference');
  if (genReferenceBtn) genReferenceBtn.addEventListener('click', function() {
    var chargeBtn = document.getElementById('btn-charge-inline');
    if (chargeBtn && !chargeBtn.disabled) chargeBtn.click();
  });
  var newTransactionBtn = document.getElementById('btn-inline-new-transaction');
  if (newTransactionBtn) newTransactionBtn.addEventListener('click', function() {
    setPaymentSuccess(false);
    closeInlineServiceEntry();
  });
  var addAnotherBtn = document.getElementById('btn-add-another-service');
  if (addAnotherBtn) addAnotherBtn.addEventListener('click', function() {
    setPaymentSuccess(false);
    clearPendingCharge(true);
  });
  var printFromSuccessBtn = document.getElementById('btn-print-from-success');
  if (printFromSuccessBtn) printFromSuccessBtn.addEventListener('click', function() {
    if (successActionMode === 'bank_deposit' && latestBankInstructionData) {
      openBankDepositInstructionsModal(latestBankInstructionData);
      return;
    }
    var printBtn = document.getElementById('btn-checkout');
    if (printBtn && !printBtn.disabled) printBtn.click();
  });

  document.querySelectorAll('.page-pay-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (!ensureBeneficiaryFilled()) return;
      selectedPayMethod = this.dataset.method;
      showInlinePayDetails(selectedPayMethod);
      updateInlinePaymentSummary();
      validateInlineCharge();
      setInlineWizardStep('instructions');
    });
  });
  ['page-check-number','page-check-bank','page-check-holder','page-ot-ref','page-ot-sender','page-ot-amount'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', validateInlineCharge);
  });
  ['page-bd-bank','page-ot-bank'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', validateInlineCharge);
  });
  ['page-cash-tendered','page-beneficiary'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', function() {
      if (id === 'page-beneficiary' && this.value.trim()) this.classList.remove('pos-required-error');
      updateInlinePaymentSummary();
      validateInlineCharge();
    });
  });
  var benefBlur = document.getElementById('page-beneficiary');
  if (benefBlur) benefBlur.addEventListener('blur', function() {
    if (!this.value.trim()) buzzRequiredField(this);
  });
  var instructionPrintCb = document.getElementById('bdi-delivery-print');
  if (instructionPrintCb) instructionPrintCb.addEventListener('change', updateBankInstructionButtons);
  var instructionEmailCb = document.getElementById('bdi-delivery-email');
  if (instructionEmailCb) instructionEmailCb.addEventListener('change', updateBankInstructionButtons);
  var instructionEmailOptIn = document.getElementById('bdi-email-optin');
  if (instructionEmailOptIn) instructionEmailOptIn.addEventListener('change', syncBankInstructionDeliveryState);
  var instructionEmailInput = document.getElementById('bdi-email-input');
  if (instructionEmailInput) instructionEmailInput.addEventListener('input', function() {
    setSelectedPayerEmail(this.value);
    syncBankInstructionDeliveryState();
  });
  var closeInstructionBtn = document.getElementById('btn-close-bank-instructions');
  if (closeInstructionBtn) closeInstructionBtn.addEventListener('click', closeBankDepositInstructionsModal);
  var printInstructionBtn = document.getElementById('btn-print-bank-instructions');
  if (printInstructionBtn) printInstructionBtn.addEventListener('click', function() {
    if (this.disabled) return;
    window.print();
  });
  var emailInstructionBtn = document.getElementById('btn-email-bank-instructions');
  if (emailInstructionBtn) emailInstructionBtn.addEventListener('click', function() {
    if (this.disabled) return;
    emailBankDepositInstructions();
  });

  var amtBtn = replaceNode('btn-amt-add');
  amtBtn.addEventListener('click', function() {
    var amt = parseFloat(document.getElementById('amt-input').value);
    if (isNaN(amt) || amt <= 0) { document.getElementById('amt-input').focus(); return; }
    document.getElementById('amt-modal').classList.add('hidden');
    if (pendingActivity) { addToCart(pendingActivity, amt); pendingActivity = null; }
  });

  var checkoutBtn = replaceNode('btn-checkout');
  checkoutBtn.addEventListener('click', function() {
    if (!cartItems.length) return;
    var btn = this;
    var receiptItems = cartItems.slice();
    var receiptPayer = selectedPayer;
    btn.disabled = true;
    btn.textContent = 'Finalizing...';
    apiPost({action:'complete_transaction', shift_id:SHIFT_ID}).then(function(d) {
      if (!d.success) {
        alert(d.message || 'Failed to finalize receipt.');
        return;
      }
      setPaymentSuccess(false);
      showReceipt(d, receiptItems, receiptPayer);
      cartItems = [];
      selectedPayer = null;
      clearPendingCharge(true);
      updatePayerDisplay();
      updateCoPayerDisplay();
      updatePayerSearchState();
      renderCart();
    }).catch(function(e) {
      alert('Network error: ' + e.message);
    }).then(function() {
      btn.disabled = !cartItems.length;
      btn.textContent = 'Finalize Transaction and Print Receipt';
    });
  });

  var chargeBtn = document.getElementById('btn-charge-inline');
  if (chargeBtn) chargeBtn.addEventListener('click', function() {
    var err = document.getElementById('page-pay-error');
    err.classList.add('hidden');
    if (!pendingCharge || !selectedPayer || !selectedPayMethod) {
      err.textContent = 'Select payer, service, and payment details first.';
      err.classList.remove('hidden');
      return;
    }
    if (!ensureBeneficiaryFilled()) {
      err.textContent = 'Beneficiary name is required before charging.';
      err.classList.remove('hidden');
      return;
    }
    var btn = this;
    var completedMethod = selectedPayMethod;
    var beneficiaryName = document.getElementById('page-beneficiary').value.trim();
    var paymentDetails = collectInlinePaymentDetails();
    var instructionData = completedMethod === 'bank_deposit' ? buildBankDepositInstructionData(paymentDetails, beneficiaryName) : null;
    btn.disabled = true;
    btn.textContent = completedMethod === 'bank_deposit' ? 'Generating...' : 'Charging...';
    apiPost({
      action:'add_to_cart',
      shift_id:SHIFT_ID,
      activity_id:pendingCharge.activity.id,
      activity_name:pendingCharge.activity.activity_name,
      activity_code:pendingCharge.activity.activity_code || '',
      amount:pendingCharge.amount,
      payment_method:completedMethod,
      payment_details:paymentDetails,
      beneficiary_name:beneficiaryName,
      customer_id:selectedPayer.type === 'customer' ? selectedPayer.id : null,
      customer_name:selectedPayer.type === 'customer' ? selectedPayer.name : null,
      dept_id:selectedPayer.type === 'department' ? selectedPayer.id : null,
      dept_name:selectedPayer.type === 'department' ? selectedPayer.name : null
    }).then(function(d) {
      if (!d.success) {
        err.textContent = d.message || 'Failed to add paid item.';
        err.classList.remove('hidden');
        return;
      }
      if (instructionData) {
        instructionData.reference = instructionData.reference || generateBankDepositReference(d.uuid || d.id || Date.now());
        latestBankInstructionData = instructionData;
      } else {
        latestBankInstructionData = null;
      }
      pendingCharge = null;
      resetInlinePaymentFields();
      setInlineSuccessState(completedMethod);
      updateInlineServiceCard();
      setPaymentSuccess(true);
      var successMsg = document.getElementById('inline-success-message');
      if (successMsg) successMsg.textContent = completedMethod === 'bank_deposit'
        ? 'Payment reference ' + ((instructionData && instructionData.reference) || '') + ' generated. Print or email the instructions, or finalize and print the receipt.'
        : 'Service added to the receipt. Start a new transaction or finalize and print the receipt.';
      setInlineWizardStep('success');
      if (instructionData) openBankDepositInstructionsModal(instructionData);
      return loadDraftItems();
    }).catch(function(e) {
      err.textContent = 'Network error: ' + e.message;
      err.classList.remove('hidden');
    }).then(function() {
      btn.disabled = false;
      updateInlineChargeButtonLabel();
      validateInlineCharge();
    });
  });

  updatePayerModeButtons();
  updatePayerSearchState();
  setPaymentSuccess(false);
  updateInlineServiceCard();
  updateInlinePaymentSummary();
  updateInlineChargeButtonLabel();
  setInlineWizardStep('details');
})();

// ---- Session status helpers ----
function setSessionStatus(label, color) {
  var dot   = document.getElementById('ss-dot');
  var lbl   = document.getElementById('ss-label');
  if (dot)  { dot.style.background = color; dot.style.boxShadow = '0 0 0 2px '+color+'44'; }
  if (lbl)  { lbl.style.color = color; lbl.textContent = label; }
}
// Start in OPEN state; transitions to OFFLINE when fetch fails
(function() {
  var orig = window.fetch;
  var consecutive = 0;
  window.fetch = function() {
    return orig.apply(this, arguments).then(function(r) {
      consecutive = 0;
      setSessionStatus('ACTIVE', '#4ade80');
      return r;
    }, function(err) {
      consecutive++;
      if (consecutive >= 2) setSessionStatus('OFFLINE', '#f87171');
      throw err;
    });
  };
})();

// ---- Auto-lock on inactivity (10 min) ----
var _autoLockTimer = null;
function resetAutoLock() {
  clearTimeout(_autoLockTimer);
  _autoLockTimer = setTimeout(function() {
    // Only lock if no modal is open
    if (!document.getElementById('pin-modal').classList.contains('hidden')) return;
    document.getElementById('btn-lock').click();
  }, 10 * 60 * 1000);
}
['click','keydown','mousemove','touchstart'].forEach(function(e) {
  document.addEventListener(e, resetAutoLock, true);
});
resetAutoLock();

// ---- PIN Modal ----
var _pinBuffer = '';
var _pinTarget = '';

function openPinModal(target, subtitle) {
  _pinBuffer = '';
  _pinTarget = target;
  updatePinDots();
  document.getElementById('pin-error').classList.add('hidden');
  if (subtitle) document.getElementById('pin-modal-subtitle').textContent = subtitle;
  document.getElementById('pin-modal').classList.remove('hidden');
}

function updatePinDots() {
  for (var i = 0; i < 4; i++) {
    var dot = document.getElementById('pd-' + i);
    if (!dot) continue;
    if (i < _pinBuffer.length) {
      dot.style.background    = '#467328';
      dot.style.borderColor   = '#467328';
    } else {
      dot.style.background    = '';
      dot.style.borderColor   = '';
    }
  }
}

document.querySelectorAll('.pin-key').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (_pinBuffer.length >= 4) return;
    _pinBuffer += this.dataset.k;
    updatePinDots();
    if (_pinBuffer.length === 4) setTimeout(submitPin, 180);
  });
});
document.getElementById('pin-back').addEventListener('click', function() {
  _pinBuffer = _pinBuffer.slice(0, -1);
  updatePinDots();
  document.getElementById('pin-error').classList.add('hidden');
});
document.getElementById('pin-clear').addEventListener('click', function() {
  _pinBuffer = '';
  updatePinDots();
  document.getElementById('pin-error').classList.add('hidden');
});
document.getElementById('pin-cancel').addEventListener('click', function() {
  document.getElementById('pin-modal').classList.add('hidden');
  _pinBuffer = '';
});

function submitPin() {
  var pin = _pinBuffer;
  _pinBuffer = '';
  updatePinDots();
  apiPost({action: 'verify_pin', pin: pin, context: _pinTarget})
  .then(function(d) {
    if (!d.success) {
      document.getElementById('pin-error').classList.remove('hidden');
      return;
    }
    document.getElementById('pin-modal').classList.add('hidden');
    if (_pinTarget === 'totals') showTotalsFromData(d);
  }).catch(function() {
    document.getElementById('pin-error').classList.remove('hidden');
  });
}

// ---- Session Totals Modal ----
var _totalsCountdown = null;

function showTotalsFromData(d) {
  document.getElementById('tl-shift-id').textContent   = d.shift_id || SHIFT_ID;
  document.getElementById('tl-tx-count').textContent   = d.transaction_count;
  document.getElementById('tl-total').textContent      = 'BZD $' + parseFloat(d.total_amount).toFixed(2);
  document.getElementById('tl-cash').textContent       = 'BZD $' + parseFloat(d.cash_collected).toFixed(2);
  document.getElementById('tl-starting-cash').textContent = 'BZD $' + parseFloat(d.starting_cash).toFixed(2);
  document.getElementById('tl-drawer').textContent     = 'BZD $' + parseFloat(d.expected_drawer).toFixed(2);
  document.getElementById('tl-loading').style.display  = 'none';
  document.getElementById('tl-body').classList.remove('hidden');
  document.getElementById('tl-error').classList.add('hidden');
  document.getElementById('totals-modal').classList.remove('hidden');
  // Auto-hide after 30 s
  clearInterval(_totalsCountdown);
  var secs = 30;
  var msgEl = document.getElementById('tl-autohide');
  _totalsCountdown = setInterval(function() {
    secs--;
    if (msgEl) msgEl.textContent = 'Auto-closing in ' + secs + 's';
    if (secs <= 0) { clearInterval(_totalsCountdown); closeTotals(); }
  }, 1000);
}

function closeTotals() {
  clearInterval(_totalsCountdown);
  document.getElementById('totals-modal').classList.add('hidden');
  // Reset for next open
  document.getElementById('tl-loading').style.display  = '';
  document.getElementById('tl-body').classList.add('hidden');
  document.getElementById('tl-error').classList.add('hidden');
}

document.getElementById('btn-totals').addEventListener('click', function() {
  openPinModal('totals', 'Enter supervisor PIN to view session totals');
});

// ---- Daily Cash Sales / Generate Pay-In ----
var _dsPendingCash = 0;

function dsFmtTime(dt) {
  if (!dt) return '—';
  var parts = dt.replace('T',' ').split(' ');
  var tp = (parts[1] || '00:00').split(':');
  var h = parseInt(tp[0], 10);
  var ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return (h < 10 ? '0'+h : h) + ':' + tp[1] + ' ' + ap;
}

function openDaySalesModal() {
  var modal = document.getElementById('day-sales-modal');
  if (!modal) return;
  modal.classList.remove('hidden');
  loadDailySales();
}

function loadDailySales() {
  document.getElementById('ds-loading').classList.remove('hidden');
  document.getElementById('ds-content').classList.add('hidden');
  document.getElementById('ds-error').classList.add('hidden');
  var genBtn = document.getElementById('btn-generate-payin');
  genBtn.disabled = true;

  apiPost({action: 'daily_sales_report'}).then(function(d) {
    document.getElementById('ds-loading').classList.add('hidden');
    if (!d.success) {
      var errEl = document.getElementById('ds-error');
      errEl.textContent = d.message || 'Failed to load daily sales.';
      errEl.classList.remove('hidden');
      return;
    }

    _dsPendingCash = parseFloat(d.pending_cash) || 0;
    document.getElementById('ds-date-label').textContent   = d.date || '';
    document.getElementById('ds-total-cash').textContent   = 'BZD $' + (parseFloat(d.total_cash)||0).toFixed(2);
    document.getElementById('ds-settled-cash').textContent = 'BZD $' + (parseFloat(d.settled_cash)||0).toFixed(2);
    document.getElementById('ds-pending-cash').textContent = 'BZD $' + _dsPendingCash.toFixed(2);

    var rows = '';
    var items = d.items || [];
    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      var settled = !!it.pay_in_id;
      var badge = settled
        ? '<span class="inline-block text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded bg-gray-100 text-gray-500">Paid-In</span>'
        : '<span class="inline-block text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded bg-amber-100 text-amber-700">Pending</span>';
      var payer = it.beneficiary_name || it.customer_name || '—';
      rows += '<tr class="border-t border-gray-100' + (settled ? ' text-gray-400' : '') + '">'
            + '<td class="px-3 py-2 whitespace-nowrap text-xs">' + dsFmtTime(it.created_at) + '</td>'
            + '<td class="px-3 py-2 text-xs">' + escHtml(it.activity_name || '—') + '</td>'
            + '<td class="px-3 py-2 text-xs">' + escHtml(payer) + '</td>'
            + '<td class="px-3 py-2 text-right text-xs font-semibold">BZD $' + (parseFloat(it.amount)||0).toFixed(2) + '</td>'
            + '<td class="px-3 py-2 text-center">' + badge + '</td>'
            + '</tr>';
    }
    document.getElementById('ds-rows').innerHTML = rows;
    document.getElementById('ds-empty').classList.toggle('hidden', items.length > 0);
    document.getElementById('ds-content').classList.remove('hidden');

    genBtn.disabled = _dsPendingCash <= 0;
    var noteEl = document.getElementById('ds-footer-note');
    if (_dsPendingCash <= 0) {
      noteEl.textContent = 'All of today’s cash sales have already been paid in.';
    } else {
      noteEl.textContent = 'Pay-in will total BZD $' + _dsPendingCash.toFixed(2)
                         + ' across ' + d.pending_count + ' un-settled sale' + (d.pending_count === 1 ? '' : 's') + '.';
    }
  }).catch(function() {
    document.getElementById('ds-loading').classList.add('hidden');
    var errEl = document.getElementById('ds-error');
    errEl.textContent = 'Network error loading daily sales.';
    errEl.classList.remove('hidden');
  });
}

// ---- Recent Transactions (today, all methods) ----
function loadRecentTransactions() {
  document.getElementById('rtx-loading').classList.remove('hidden');
  document.getElementById('rtx-content').classList.add('hidden');
  document.getElementById('rtx-error').classList.add('hidden');

  apiPost({action: 'recent_transactions'}).then(function(d) {
    document.getElementById('rtx-loading').classList.add('hidden');
    if (!d.success) {
      var errEl = document.getElementById('rtx-error');
      errEl.textContent = d.message || 'Failed to load recent transactions.';
      errEl.classList.remove('hidden');
      return;
    }

    document.getElementById('rtx-grand').textContent = 'BZD $' + (parseFloat(d.grand_total)||0).toFixed(2);
    var txs = d.transactions || [];
    var rows = '';
    for (var i = 0; i < txs.length; i++) {
      var t = txs[i];
      var methods = (t.methods || []).map(function(m){ return fmtMethod(m); }).join(', ') || '—';
      var payer = t.customer_name || t.dept_name || 'Walk-in';
      var url = POS_RECEIPT_URL + '?tx=' + encodeURIComponent(t.uuid);
      rows += '<tr class="border-t border-gray-100 hover:bg-emerald-50 cursor-pointer" onclick="window.open(\'' + url + '\',\'_blank\')">'
            + '<td class="px-3 py-2 whitespace-nowrap text-xs">' + dsFmtTime(t.created_at) + '</td>'
            + '<td class="px-3 py-2 text-xs">' + escHtml(payer) + '</td>'
            + '<td class="px-3 py-2 text-center text-xs">' + (t.item_count || 0) + '</td>'
            + '<td class="px-3 py-2 text-xs">' + escHtml(methods) + '</td>'
            + '<td class="px-3 py-2 text-right text-xs font-semibold">BZD $' + (parseFloat(t.total_amount)||0).toFixed(2) + '</td>'
            + '<td class="px-3 py-2 text-right"><span class="text-[11px] text-emerald-600 font-semibold">Receipt &#8599;</span></td>'
            + '</tr>';
    }
    document.getElementById('rtx-rows').innerHTML = rows;
    document.getElementById('rtx-empty').classList.toggle('hidden', txs.length > 0);
    document.getElementById('rtx-content').classList.remove('hidden');
  }).catch(function() {
    document.getElementById('rtx-loading').classList.add('hidden');
    var errEl = document.getElementById('rtx-error');
    errEl.textContent = 'Network error loading recent transactions.';
    errEl.classList.remove('hidden');
  });
}

document.getElementById('btn-recent-tx').addEventListener('click', function() {
  document.getElementById('recent-tx-modal').classList.remove('hidden');
  loadRecentTransactions();
});

document.getElementById('btn-generate-payin').addEventListener('click', function() {
  if (_dsPendingCash <= 0) return;
  if (!confirm('Generate a pay-in for BZD $' + _dsPendingCash.toFixed(2) + ' of today’s cash sales?')) return;
  var btn = this; btn.disabled = true; btn.textContent = 'Generating…';
  apiPost({action: 'generate_sales_payin'}).then(function(d) {
    btn.textContent = 'Generate Pay-In';
    if (d.success) {
      document.getElementById('day-sales-modal').classList.add('hidden');
      document.getElementById('pd-summary').textContent  = 'BZD $' + parseFloat(d.total_cash).toFixed(2)
        + ' across ' + d.item_count + ' sale' + (d.item_count === 1 ? '' : 's') + '.';
      document.getElementById('pd-payin-id').textContent = d.pay_in_id;
      document.getElementById('pd-view-link').href       = d.view_url;
      document.getElementById('payin-done-modal').classList.remove('hidden');
    } else {
      alert(d.message || 'Failed to generate pay-in.');
      btn.disabled = false;
    }
  }).catch(function() {
    btn.textContent = 'Generate Pay-In';
    btn.disabled = false;
    alert('Network error generating pay-in.');
  });
});

// ---- End Shift ----
document.getElementById('btn-end-shift').addEventListener('click', function() {
  document.getElementById('end-shift-modal').classList.remove('hidden');
});
document.getElementById('btn-confirm-end-shift').addEventListener('click', function() {
  var btn = this; btn.disabled = true; btn.textContent = 'Ending…';
  apiPost({action: 'end_shift'}).then(function(d) {
    if (d.success) {
      document.getElementById('end-shift-modal').classList.add('hidden');
      showShiftReport(d);
    } else {
      alert(d.message || 'Failed to end shift.');
      btn.disabled = false; btn.textContent = 'End Shift';
    }
  }).catch(function() {
    btn.disabled = false; btn.textContent = 'End Shift';
  });
});

// ---- Shift Report ----
function showShiftReport(d) {
  function pad2(n) { return n < 10 ? '0'+n : ''+n; }
  function fmtDT(dt) {
    if (!dt) return '—';
    var s = dt.replace('T',' ').split(' ');
    var dp = s[0].split('-');
    var tp = (s[1] || '00:00').split(':');
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var h = parseInt(tp[0]);
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return pad2(parseInt(dp[2]))+' '+months[parseInt(dp[1])-1]+' '+dp[0]+'  '+pad2(h)+':'+pad2(parseInt(tp[1]))+' '+ampm;
  }
  function calcDuration(start, end) {
    if (!start || !end) return '—';
    var diffMs = new Date(end.replace(' ','T')) - new Date(start.replace(' ','T'));
    if (isNaN(diffMs) || diffMs < 0) return '—';
    var mins = Math.floor(diffMs / 60000);
    return Math.floor(mins/60)+'h '+( mins%60)+'m';
  }

  var now = new Date();
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var dateStr = pad2(now.getDate())+' '+months[now.getMonth()]+' '+now.getFullYear();
  var h12 = now.getHours()%12||12;
  var ap  = now.getHours()>=12?'PM':'AM';
  var timeStr = pad2(h12)+':'+pad2(now.getMinutes())+' '+ap;

  document.getElementById('sr-shift-id').textContent      = d.shift_id || '—';
  document.getElementById('sr-date').textContent          = dateStr;
  document.getElementById('sr-cashier').textContent       = CASHIER_NAME || '—';
  document.getElementById('sr-branch').textContent        = BRANCH_NAME  || '—';
  document.getElementById('sr-terminal').textContent      = TERMINAL_NAME || '—';
  document.getElementById('sr-opened').textContent        = fmtDT(d.started_at);
  document.getElementById('sr-closed').textContent        = fmtDT(d.ended_at);
  document.getElementById('sr-duration').textContent      = calcDuration(d.started_at, d.ended_at);
  document.getElementById('sr-tx-count').textContent      = d.transaction_count;
  document.getElementById('sr-total').textContent         = 'BZD $'+parseFloat(d.total_amount).toFixed(2);
  document.getElementById('sr-starting-cash').textContent = 'BZD $'+parseFloat(d.starting_cash).toFixed(2);
  document.getElementById('sr-cash-collected').textContent= 'BZD $'+parseFloat(d.cash_collected).toFixed(2);
  document.getElementById('sr-expected-drawer').textContent='BZD $'+parseFloat(d.expected_drawer).toFixed(2);
  document.getElementById('sr-generated-at').textContent  = dateStr+' '+timeStr;

  var breakdown = d.method_breakdown || [];
  var rows = '';
  for (var i = 0; i < breakdown.length; i++) {
    var b   = breakdown[i];
    var bg  = (i % 2 === 0) ? '#fff' : '#f8fdf5';
    rows += '<tr style="background:'+bg+';border-bottom:1px solid #e8f3e8;">'
          + '<td style="padding:7px 10px;font-size:11px;">'+escHtml(fmtMethod(b.payment_method))+'</td>'
          + '<td style="padding:7px 10px;font-size:11px;text-align:center;">'+escHtml(String(b.cnt))+'</td>'
          + '<td style="padding:7px 10px;font-size:11px;text-align:right;font-weight:600;">BZD $'+parseFloat(b.total).toFixed(2)+'</td>'
          + '</tr>';
  }
  if (!rows) {
    rows = '<tr><td colspan="3" style="padding:12px 10px;text-align:center;font-size:11px;color:#9ca3af;">No transactions recorded this shift.</td></tr>';
  }
  document.getElementById('sr-breakdown').innerHTML = rows;
  document.getElementById('shift-report-modal').classList.remove('hidden');
}

document.getElementById('btn-print-shift-report').addEventListener('click', function() { window.print(); });
document.getElementById('btn-shift-report-done').addEventListener('click', function() {
  window.location.href = SHIFT_START_URL;
});
</script>
</body>
</html>

