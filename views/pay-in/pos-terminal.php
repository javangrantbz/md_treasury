<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$userId = (int)$_SESSION['user']['id'];

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
            $accounts = [];
            if ($deptId) {
                $stmt = $pdo->prepare("
                    SELECT dba.id AS link_id, dba.department_id, dba.bank_account_id,
                           dba.is_default, dba.status AS link_status,
                           ba.id AS bank_id, ba.bank_name, ba.account_name,
                           ba.account_number, ba.currency_code, ba.account_masked, ba.account_type
                    FROM department_bank_accounts dba
                    INNER JOIN bank_accounts ba ON ba.id = dba.bank_account_id
                    WHERE dba.department_id = :dept_id AND ba.status = 'active'
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
                    FROM bank_accounts WHERE status = 'active'
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

        if (!$actId || $amount <= 0 || !$method) {
            ob_end_clean();
            echo json_encode(['success'=>false,'message'=>'Missing required cart fields.']);
            exit;
        }
        if (!$deptId) {
            $ctx = pos_get_user_context($pdo, $userId);
            $deptId = $ctx['department_id'];
        }
        try {
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
        $stmt = $pdo->prepare("SELECT * FROM pos_cart_items WHERE shift_id = :sid AND uid = :uid AND status = 'pending' ORDER BY created_at ASC");
        $stmt->execute(['sid'=>$shiftId,'uid'=>$userId]);
        ob_end_clean();
        echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ===== DELETE CART ITEM =====
    if ($action === 'delete_cart_item') {
        $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;
        if (!$itemId) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Missing item ID.']); exit; }
        $stmt = $pdo->prepare("DELETE FROM pos_cart_items WHERE id = :id AND uid = :uid");
        $stmt->execute(['id'=>$itemId,'uid'=>$userId]);
        ob_end_clean();
        echo json_encode(['success'=>true]);
        exit;
    }

    // ===== COMPLETE TRANSACTION =====
    if ($action === 'complete_transaction') {
        $shiftId = isset($input['shift_id']) ? trim($input['shift_id']) : '';
        $stmt = $pdo->prepare("SELECT * FROM pos_cart_items WHERE shift_id = :sid AND uid = :uid AND status = 'pending' ORDER BY created_at ASC");
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
            $stmt = $pdo->prepare("UPDATE pos_cart_items SET status = 'completed' WHERE shift_id = :sid AND uid = :uid AND status = 'pending'");
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

    // ===== LOAD ACTIVITIES (default) =====
    try {
        $ctx    = pos_get_user_context($pdo, $userId);
        $deptId = $ctx['department_id'];

        $costCenters = [];
        if ($deptId) {
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
body{font-family:'Inter',sans-serif;margin:0;overflow:hidden}
.hdr-btn:hover{background:rgba(255,255,255,.14)!important;color:#fff!important;}
.svc-card{transition:all .15s;border:1.5px solid #e2e8f0;cursor:pointer;}
.svc-card:hover{border-color:#6db344;background:#f8fdf5;transform:translateY(-1px);box-shadow:0 4px 12px -2px rgba(59,98,34,.14);}
.svc-card.flash{border-color:#467328;background:#f0f7eb;}
.co-pay-btn{transition:all .12s;border:1.5px solid #e2e8f0;}
.co-pay-btn.selected{border-color:#334155!important;background:#f1f5f9;color:#0f172a;font-weight:600;}

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
<body class="h-screen flex flex-col bg-slate-50">

<style>
body{font-family:'Inter',sans-serif;margin:0;overflow:hidden;background:#f8fafc}
.svc-card{transition:all .15s;border:1px solid #e2e8f0;cursor:pointer;}
.svc-card:hover{border-color:#1e4620;background:#f8fdf5;transform:translateY(-1px);box-shadow:0 4px 12px -2px rgba(30,70,32,.15);}
.svc-card.flash{border-color:#1e4620;background:#f0f7eb;}

/* Two-layer header & utility bar */
.hdr-main{background:#1e4620;color:#fff;}
.hdr-util{background:#f1f5f9;border-bottom:1px solid #e2e8f0;color:#475569;}
.hdr-btn:hover{background:rgba(255,255,255,.15);color:#fff;}

/* Metadata dividers */
.meta-item{border-right:1px solid rgba(255,255,255,.15);padding:0 12px;}
.meta-item:last-child{border:none;}

/* Cart panel */
.treasury-panel{background:#fff;border-left:1px solid #e2e8f0;}
.co-pay-btn{transition:all .12s;border:1.5px solid #e2e8f0;}
.co-pay-btn.selected{border-color:#1e4620!important;background:#f0fdf4;color:#14532d;font-weight:600;}
.hdr-act:hover{background:rgba(255,255,255,.16)!important;color:#fff!important;}
@media print{
  body>*:not(#receipt-print-root):not(#shift-report-modal){display:none!important;}
  #receipt-print-root,#shift-report-modal{display:block!important;position:static!important;background:#fff!important;}
  .no-print{display:none!important;}
  #rct-paper{box-shadow:none!important;border:none!important;max-width:100%!important;margin:0!important;font-size:11pt;}
  .sr-paper{box-shadow:none!important;border:none!important;border-radius:0!important;max-width:100%!important;margin:0!important;font-size:11pt;}
  @page{size:A4 portrait;margin:15mm;}
}
</style>

<!-- ===== HEADER LAYER 1: IDENTITY ===== -->
<header class="shrink-0" style="background:linear-gradient(135deg,#1a3e1f 0%,#1e4a24 100%);box-shadow:0 1px 0 rgba(255,255,255,.06),0 2px 10px rgba(0,0,0,.35);">
  <div style="display:flex;align-items:center;height:48px;padding:0 14px;gap:0;">

    <!-- Back to portal -->
    <a href="<?= url('views/pay-in/index.php') ?>"
       style="display:flex;align-items:center;gap:3px;padding-right:12px;margin-right:2px;border-right:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.32);text-decoration:none;font-size:10px;font-weight:600;letter-spacing:.04em;flex-shrink:0;transition:color .15s;"
       onmouseover="this.style.color='rgba(255,255,255,.65)'" onmouseout="this.style.color='rgba(255,255,255,.32)'">
      <svg style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Portal
    </a>

    <!-- Seal + Title -->
    <div style="display:flex;align-items:center;gap:10px;padding:0 14px;border-right:1px solid rgba(255,255,255,.1);flex-shrink:0;">
      <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms"
           style="width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 1px 3px rgba(0,0,0,.4));">
      <div>
        <div style="font-size:7.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.38);line-height:1;margin-bottom:2px;">Government of Belize &middot; Ministry of Finance</div>
        <div style="font-size:14px;font-weight:900;color:#fff;letter-spacing:-.02em;line-height:1.15;">Treasury Revenue System</div>
      </div>
    </div>

    <!-- Env badge -->
    <div style="padding:0 14px;border-right:1px solid rgba(255,255,255,.08);flex-shrink:0;">
      <span style="display:inline-block;background:<?= $envBg ?>;color:#fff;font-size:7.5px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;padding:2px 8px;border-radius:99px;"><?= $appEnv ?></span>
    </div>

    <!-- Branch -->
    <div style="display:flex;align-items:baseline;gap:5px;padding:0 16px;border-right:1px solid rgba(255,255,255,.08);flex-shrink:0;">
      <span style="font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.3);">Branch</span>
      <span style="font-size:12px;font-weight:700;color:#e8f0e9;"><?= htmlspecialchars($branchName) ?></span>
    </div>

    <?php if ($terminalName): ?>
    <!-- Terminal -->
    <div style="display:flex;align-items:baseline;gap:5px;padding:0 16px;border-right:1px solid rgba(255,255,255,.08);flex-shrink:0;">
      <span style="font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.3);">Terminal</span>
      <span style="font-size:12px;font-weight:700;color:#e8f0e9;"><?= htmlspecialchars($terminalName) ?></span>
    </div>
    <?php endif; ?>

    <!-- Shift # -->
    <div style="display:flex;align-items:baseline;gap:5px;padding:0 16px;flex-shrink:0;">
      <span style="font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.3);">Shift</span>
      <span style="font-size:11px;font-weight:700;font-family:monospace;color:rgba(255,255,255,.48);"><?= htmlspecialchars($shiftId) ?></span>
    </div>

    <div style="flex:1;"></div>

    <!-- Cashier -->
    <div style="display:flex;align-items:center;gap:10px;padding-left:14px;border-left:1px solid rgba(255,255,255,.1);flex-shrink:0;">
      <div style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg style="width:13px;height:13px;color:rgba(255,255,255,.7);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div>
        <div style="font-size:12px;font-weight:700;color:#fff;line-height:1.2;"><?= htmlspecialchars($userName) ?></div>
        <?php if ($roleName): ?>
        <div style="font-size:9.5px;color:rgba(255,255,255,.38);line-height:1;"><?= htmlspecialchars($roleName) ?></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</header>

<!-- ===== HEADER LAYER 2: OPERATIONAL STATUS & CONTROLS ===== -->
<div class="shrink-0" style="background:#112b14;border-bottom:2px solid rgba(0,0,0,.3);">
  <div style="display:flex;align-items:center;height:32px;padding:0 18px;">

    <!-- Left: Live status -->
    <div style="display:flex;align-items:center;flex:1;overflow:hidden;">

      <!-- Session status -->
      <div style="display:flex;align-items:center;gap:5px;padding-right:14px;border-right:1px solid rgba(255,255,255,.09);flex-shrink:0;">
        <span id="ss-dot" style="width:6px;height:6px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 2px rgba(74,222,128,.22);flex-shrink:0;"></span>
        <span id="ss-label" style="font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#4ade80;">ACTIVE</span>
      </div>

      <?php if ($shiftStart): ?>
      <!-- Since -->
      <div style="display:flex;align-items:baseline;gap:4px;padding:0 14px;border-right:1px solid rgba(255,255,255,.09);flex-shrink:0;">
        <span style="font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.28);">Since</span>
        <span style="font-size:11px;font-weight:600;color:rgba(255,255,255,.48);"><?= date('h:i A', strtotime($shiftStart)) ?></span>
      </div>
      <?php endif; ?>

      <!-- Live clock -->
      <div style="padding-left:14px;">
        <span style="font-size:10px;font-weight:600;color:rgba(255,255,255,.3);font-family:monospace;" id="hdr-sync-time">--:--:--</span>
      </div>

    </div>

    <!-- Right: Action buttons -->
    <div style="display:flex;align-items:center;gap:3px;padding-left:14px;border-left:1px solid rgba(255,255,255,.09);flex-shrink:0;">
      <button id="btn-totals" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Totals</button>
      <button id="btn-lock" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Lock</button>
      <button id="btn-supervisor" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.55);cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">Supervisor</button>
      <div style="width:1px;height:13px;background:rgba(255,255,255,.1);margin:0 2px;"></div>
      <button id="btn-end-shift" class="hdr-act" style="font-size:10px;padding:3px 11px;border-radius:4px;background:rgba(220,38,38,.18);border:1px solid rgba(220,38,38,.32);color:#fca5a5;cursor:pointer;font-weight:700;text-transform:uppercase;letter-spacing:.06em;transition:all .15s;">End Shift</button>
    </div>

  </div>
</div>

<!-- MAIN CONTENT -->
<div class="flex flex-1 overflow-hidden">
  <!-- Services Left (60%) -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <div class="p-4 border-b border-gray-200">
      <input type="text" id="search-services" placeholder="Search services..." class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="flex-1 overflow-y-auto p-4 space-y-6" id="services-container">
      <div id="services-loading" class="text-center text-gray-400 py-12 text-sm">Loading services…</div>
      <div id="services-empty" class="hidden text-center text-gray-400 py-12 text-sm">No services available. Please contact your administrator.</div>
      <div id="favorites-section" class="hidden">
        <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Favorites</div>
        <div class="flex gap-2 pb-2" id="favorites-grid"></div>
      </div>
      <div>
        <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">All Services</div>
        <div class="grid grid-cols-3 gap-3" id="services-grid"></div>
      </div>
    </div>
  </div>

  <!-- Cart Right (40%) -->
  <div class="treasury-panel w-[40%] flex flex-col overflow-hidden">
    <div class="p-4 border-b border-gray-100 bg-slate-50">
      <div class="text-[10px] font-black uppercase text-gray-500 tracking-widest mb-2">Transaction Detail</div>
      <div id="payer-placeholder" class="text-sm text-gray-400 italic mb-2">No payer selected</div>
      <div id="payer-display" class="text-sm font-semibold text-gray-900 mb-2" style="display:none;"></div>
      <div class="flex gap-2">
        <button id="btn-select-customer" class="flex-1 text-xs py-1.5 border rounded bg-white font-semibold">Customer</button>
        <button id="btn-select-dept" class="flex-1 text-xs py-1.5 border rounded bg-white font-semibold">Dept</button>
        <button id="btn-clear-payer" class="text-xs py-1.5 px-3 border border-red-200 rounded bg-red-50 text-red-600 font-semibold" style="display:none;">&#10005; Clear</button>
      </div>
      <!-- Payer info panel (shown when payer selected) -->
      <div id="payer-info-panel" class="mt-3 rounded-xl border overflow-hidden" style="display:none;">
        <div id="payer-info-header" class="px-3 py-2 flex items-center justify-between">
          <div class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Payer Details</div>
          <div id="payer-verify-badge"></div>
        </div>
        <div id="payer-info-body" class="px-3 py-2 grid grid-cols-2 gap-2"></div>
      </div>
    </div>
    
    <div class="flex-1 overflow-y-auto p-4">
      <div id="cart-empty" class="text-center text-gray-400 py-10 text-sm italic">Cart is empty. Select services to begin.</div>
      <div id="cart-list" class="space-y-2" style="display:none;"></div>
    </div>

    <div class="p-4 border-t border-gray-100 bg-gray-50">
      <div class="flex justify-between items-end mb-4">
        <div>
          <div class="text-[10px] uppercase text-gray-500 tracking-widest font-bold">Total Due</div>
          <div class="text-sm text-gray-700" id="cart-count">0 items</div>
        </div>
        <div class="text-2xl font-black text-[#1e4620]" id="cart-total">$0.00</div>
      </div>
      <button id="btn-checkout" class="w-full py-3 bg-[#1e4620] text-white font-black uppercase rounded shadow-lg disabled:opacity-50">Checkout</button>
    </div>
  </div>
</div>


<!-- ===== MODALS ===== -->

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
          <div class="flex items-center border rounded-xl overflow-hidden focus-within:border-brand-400 transition-colors">
            <span class="px-3 py-3 bg-gray-50 border-r text-sm font-bold text-gray-500 shrink-0">BZD $</span>
            <input type="number" id="amt-input" step="0.01" min="0.01" placeholder="0.00"
                   class="flex-1 px-4 py-3 text-xl font-semibold text-center outline-none bg-white">
          </div>
        </div>
        <div class="flex gap-2">
          <button onclick="document.getElementById('amt-modal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm cursor-pointer">Cancel</button>
          <button id="btn-amt-add" class="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold cursor-pointer">Add to Cart</button>
        </div>
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
        <h3 class="text-base font-bold">Checkout</h3>
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
          Confirm &amp; Charge
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
          <button id="btn-print-receipt" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold cursor-pointer hover:bg-brand-700">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
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
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Receipt No.</div>
              <div style="font-size:13px;font-weight:900;color:#1e4620;font-family:monospace;" id="rct-number">—</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Date &amp; Time</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-datetime">—</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Branch</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-branch">—</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Terminal</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-terminal">—</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Cashier</div>
              <div style="font-size:11px;font-weight:600;color:#1a1a1a;" id="rct-cashier">—</div>
            </div>
            <div>
              <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Shift ID</div>
              <div style="font-size:10px;font-weight:700;color:#1a1a1a;font-family:monospace;" id="rct-shift">—</div>
            </div>
          </div>
        </div>

        <div style="padding:0 24px;">

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
            <!-- Items header -->
            <div style="display:grid;grid-template-columns:1fr 64px 64px 70px;gap:4px;background:#1e4620;color:#fff;font-size:8px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:5px 8px;border-radius:4px 4px 0 0;">
              <div>Service</div><div style="text-align:center;">Rev. Code</div><div style="text-align:center;">GL Acct</div><div style="text-align:right;">Amount</div>
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

          <!-- ===== PAYMENT ===== -->
          <div style="margin:12px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
            <div style="font-size:8px;font-weight:800;color:#1e4620;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
              <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
              Payment Details
            </div>
            <div id="rct-payment-block" style="display:grid;grid-template-columns:1fr 1fr;gap:5px 16px;font-size:11px;"></div>
          </div>

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
        <button id="btn-print-receipt-2" class="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold cursor-pointer hover:bg-brand-700">
          <svg class="w-4 h-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Print Receipt
        </button>
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
var SELF_URL        = '<?= url('views/pay-in/pos-terminal.php') ?>';
var SHIFT_START_URL = '<?= url('views/pay-in/pos-shift-start.php') ?>';
var SHIFT_ID        = '<?= $shiftId ?>';
var CASHIER_NAME         = '<?= htmlspecialchars($userName) ?>';
var BRANCH_NAME          = '<?= htmlspecialchars($branchName) ?>';
var TERMINAL_NAME        = '<?= htmlspecialchars($terminalName) ?>';
var CUSTOMER_PROFILE_URL = '<?= url('views/cashiering/master-data/customers/details.php') ?>';

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

  countEl.textContent = cartItems.length + ' item' + (cartItems.length !== 1 ? 's' : '');
  totalEl.textContent = '$' + cartTotal().toFixed(2);
  checkBtn.disabled   = cartItems.length === 0;

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
      if (amount > 0) {
        addToCart(activity, amount);
      } else {
        pendingActivity = activity;
        openAmtModal(activity);
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

// ---- Amount Modal ----
function openAmtModal(activity) {
  document.getElementById('amt-service-name').textContent = activity.activity_name || '';

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

document.getElementById('btn-clear-payer').addEventListener('click', function() {
  selectedPayer = null; updatePayerDisplay(); updateCoPayerDisplay();
});

// ---- Customer Modal ----
function openCustModal() {
  document.getElementById('cust-modal').classList.remove('hidden');
  document.getElementById('cust-search-input').value = '';
  document.getElementById('cust-results').innerHTML = '';
  document.getElementById('cust-no-results').classList.add('hidden');
  setTimeout(function() { document.getElementById('cust-search-input').focus(); }, 100);
}
document.getElementById('btn-select-customer').addEventListener('click', openCustModal);

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
          selectedPayer = {type: 'customer', id: c.id, name: name, data: c};
          updatePayerDisplay(); updateCoPayerDisplay();
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
      selectedPayer = {type:'customer', id:c.id, name:name, data:c};
      updatePayerDisplay(); updateCoPayerDisplay();
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
document.getElementById('btn-select-dept').addEventListener('click', openDeptModal);

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
          selectedPayer = {type:'department', id:dept.id, name:dept.name, data:dept};
          updatePayerDisplay(); updateCoPayerDisplay();
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
    sel.appendChild(opt);
  }
  sel.addEventListener('change', validateCheckout);
}

function validateCheckout() {
  var ok = !!selectedPayMethod && !!selectedPayer;
  if (ok && selectedPayMethod === 'check') {
    ok = !!(document.getElementById('pd-check-number').value.trim() && document.getElementById('pd-check-bank').value.trim());
  } else if (ok && selectedPayMethod === 'bank_deposit') {
    ok = !!(document.getElementById('pd-bd-bank').value && document.getElementById('pd-bd-ref').value.trim() && document.getElementById('pd-bd-amount').value);
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

function showReceipt(tx, items, payer, method, payDetails) {
  var now = new Date();
  function pad2(n) { return n < 10 ? '0'+n : ''+n; }
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var dateStr = pad2(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
  var h12 = now.getHours() % 12 || 12;
  var ampm = now.getHours() >= 12 ? 'PM' : 'AM';
  var timeStr = pad2(h12) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds()) + ' ' + ampm;

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

  // --- Items table rows ---
  var total = parseFloat(tx.total || 0);
  var itemsHtml = '';
  for (var i = 0; i < items.length; i++) {
    var it = items[i];
    var rowBg = (i % 2 === 0) ? '#fff' : '#f8fdf5';
    itemsHtml += '<div style="display:grid;grid-template-columns:1fr 64px 64px 70px;gap:4px;padding:6px 8px;border-bottom:1px solid #e8f3e8;background:' + rowBg + ';">'
      + '<div style="font-size:10px;color:#111827;">'
      +   '<div style="font-weight:600;">' + escHtml(it.activity_name || '') + '</div>'
      +   (it.activity_code    ? '<div style="font-size:8px;font-family:monospace;color:#6b7280;">' + escHtml(it.activity_code) + '</div>' : '')
      +   (it.cost_center_name ? '<div style="font-size:8px;color:#9ca3af;">' + escHtml(it.cost_center_name) + '</div>' : '')
      + '</div>'
      + '<div style="font-size:9px;font-family:monospace;color:#1d4ed8;text-align:center;font-weight:700;padding-top:3px;">' + escHtml(it.revenue_code || '—') + '</div>'
      + '<div style="font-size:9px;font-family:monospace;color:#15803d;text-align:center;font-weight:700;padding-top:3px;">' + escHtml(it.gl_account || '—') + '</div>'
      + '<div style="font-size:10px;font-weight:700;color:#111827;text-align:right;padding-top:3px;">$' + parseFloat(it.amount || 0).toFixed(2) + '</div>'
      + '</div>';
  }
  if (!itemsHtml) itemsHtml = '<div style="padding:10px 8px;font-size:10px;color:#6b7280;text-align:center;">No items.</div>';
  document.getElementById('rct-items').innerHTML = itemsHtml;

  // --- Totals ---
  document.getElementById('rct-total').textContent       = 'BZD $' + total.toFixed(2);
  document.getElementById('rct-amount-words').textContent = numberToWords(total);

  // --- Payment block ---
  var pmHtml = payerField('Method', fmtMethod(method));
  payDetails = payDetails || {};
  if (method === 'check') {
    if (payDetails.check_number) pmHtml += payerField('Cheque No.', payDetails.check_number);
    if (payDetails.bank_name)    pmHtml += payerField('Bank', payDetails.bank_name);
    if (payDetails.holder)       pmHtml += payerField('Account Holder', payDetails.holder);
  } else if (method === 'bank_deposit') {
    if (payDetails.bank_name)         pmHtml += payerField('Bank', payDetails.bank_name);
    if (payDetails.reference)         pmHtml += payerField('Reference', payDetails.reference);
    if (payDetails.amount_deposited)  pmHtml += payerField('Amount Deposited', 'BZD $' + parseFloat(payDetails.amount_deposited).toFixed(2));
  } else if (method === 'online_transfer') {
    if (payDetails.bank_name)   pmHtml += payerField('Bank', payDetails.bank_name);
    if (payDetails.reference)   pmHtml += payerField('Reference', payDetails.reference);
    if (payDetails.sender_name) pmHtml += payerField('Sender', payDetails.sender_name);
    if (payDetails.amount_sent) pmHtml += payerField('Amount Sent', 'BZD $' + parseFloat(payDetails.amount_sent).toFixed(2));
  }
  document.getElementById('rct-payment-block').innerHTML = pmHtml;

  // Show
  document.getElementById('receipt-modal').classList.remove('hidden');
}

document.getElementById('btn-print-receipt').addEventListener('click', function() { window.print(); });
document.getElementById('btn-print-receipt-2').addEventListener('click', function() { window.print(); });
document.getElementById('btn-close-receipt').addEventListener('click', function() {
  document.getElementById('receipt-modal').classList.add('hidden');
});
document.getElementById('btn-done-receipt').addEventListener('click', function() {
  document.getElementById('receipt-modal').classList.add('hidden');
  selectedPayer = null; updatePayerDisplay();
});

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
