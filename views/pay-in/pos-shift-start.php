<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

function pos_shift_fetch_cost_centers(PDO $pdo, int $departmentId): array
{
    if ($departmentId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT cc.id, cc.code, cc.name
        FROM department_cost_centers dcc
        INNER JOIN cost_centers cc ON cc.id = dcc.cost_center_id
        WHERE dcc.department_id = :department_id
          AND dcc.deleted_at IS NULL
          AND cc.deleted_at IS NULL
          AND cc.status = 'active'
        ORDER BY cc.name ASC
    ");
    $stmt->execute(['department_id' => $departmentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        return $rows;
    }

    $stmt = $pdo->prepare("
        SELECT cc.id, cc.code, cc.name
        FROM cost_centers cc
        WHERE cc.department_id = :department_id
          AND cc.deleted_at IS NULL
          AND cc.status = 'active'
        ORDER BY cc.name ASC
    ");
    $stmt->execute(['department_id' => $departmentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pos_shift_fetch_bank_accounts(PDO $pdo, int $departmentId, int $costCenterId): array
{
    if ($costCenterId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                ba.id,
                ba.bank_name,
                ba.account_name,
                ba.account_masked,
                ba.currency_code,
                'cost_center' AS source_key,
                'Assigned to this cost center' AS source_label
            FROM cost_center_bank_accounts ccba
            INNER JOIN bank_accounts ba ON ba.id = ccba.bank_account_id
            WHERE ccba.cost_center_id = :cost_center_id
              AND ccba.deleted_at IS NULL
              AND ba.deleted_at IS NULL
              AND ba.status = 'active'
            ORDER BY ba.bank_name ASC, ba.account_name ASC
        ");
        $stmt->execute(['cost_center_id' => $costCenterId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    }

    if ($departmentId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                ba.id,
                ba.bank_name,
                ba.account_name,
                ba.account_masked,
                ba.currency_code,
                'department' AS source_key,
                'Fallback from department bank accounts' AS source_label
            FROM department_bank_accounts dba
            INNER JOIN bank_accounts ba ON ba.id = dba.bank_account_id
            WHERE dba.department_id = :department_id
              AND dba.deleted_at IS NULL
              AND ba.deleted_at IS NULL
              AND ba.status = 'active'
            ORDER BY dba.is_default DESC, ba.bank_name ASC, ba.account_name ASC
        ");
        $stmt->execute(['department_id' => $departmentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            ba.id,
            ba.bank_name,
            ba.account_name,
            ba.account_masked,
            ba.currency_code,
            'system_fallback' AS source_key,
            'Fallback from active bank accounts' AS source_label
        FROM bank_accounts ba
        WHERE ba.deleted_at IS NULL
          AND ba.status = 'active'
        ORDER BY ba.bank_name ASC, ba.account_name ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw  = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = [];
    $userId = (int)$_SESSION['user']['id'];
    $action = isset($input['action']) ? $input['action'] : 'load_data';

    // ===== LOAD DATA =====
    if ($action === 'load_data') {
        try {
            // Check for existing active shift
            $stmt = $pdo->prepare("SELECT shift_id FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
            $stmt->execute(['uid' => $userId]);
            $existingShift = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingShift) {
                ob_end_clean();
                echo json_encode(['success' => true, 'has_active_shift' => true, 'shift_id' => $existingShift['shift_id']]);
                exit;
            }

            // Get all registers assigned to this user
            $stmt = $pdo->prepare("
                SELECT r.id, r.register_code, r.register_name, r.department_id, r.sub_treasury_id
                FROM registers r
                INNER JOIN register_users ru ON ru.register_id = r.id
                WHERE ru.user_id = :uid AND r.is_active = 1
                ORDER BY r.register_name ASC
            ");
            $stmt->execute(['uid' => $userId]);
            $registers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $register  = !empty($registers) ? $registers[0] : null;

            // Get department (from first register or user profile)
            $deptId = null;
            if ($register && !empty($register['department_id'])) {
                $deptId = (int)$register['department_id'];
            } elseif (!empty($_SESSION['user']['department_id'])) {
                $deptId = (int)$_SESSION['user']['department_id'];
            }

            $department = null;
            if ($deptId) {
                $stmt = $pdo->prepare("SELECT id, code, name, ministry_name, short_name FROM departments WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $deptId]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $costCenters = $deptId ? pos_shift_fetch_cost_centers($pdo, $deptId) : [];
            $defaultCostCenterId = !empty($costCenters) ? (int)$costCenters[0]['id'] : 0;
            $bankAccounts = pos_shift_fetch_bank_accounts($pdo, $deptId ?: 0, $defaultCostCenterId);

            ob_end_clean();
            echo json_encode([
                'success'          => true,
                'has_active_shift' => false,
                'registers'        => $registers,
                'department'       => $department,
                'cost_centers'     => $costCenters,
                'bank_accounts'    => $bankAccounts,
            ]);
        } catch (Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'load_bank_options') {
        $deptId = isset($input['department_id']) ? (int)$input['department_id'] : 0;
        $costCenterId = isset($input['cost_center_id']) ? (int)$input['cost_center_id'] : 0;

        try {
            $accounts = pos_shift_fetch_bank_accounts($pdo, $deptId, $costCenterId);
            ob_end_clean();
            echo json_encode(['success' => true, 'bank_accounts' => $accounts]);
        } catch (Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ===== SAVE BALANCES =====
    if ($action === 'save_balances') {
        $balances  = isset($input['balances']) ? $input['balances'] : [];
        $deptId    = isset($input['department_id']) ? (int)$input['department_id'] : 0;
        $startCash = isset($input['cash_balance']) ? (float)$input['cash_balance'] : 0.00;

        if (empty($balances) || $deptId <= 0) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing balances or department.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $shiftId = strtoupper(bin2hex(random_bytes(6)));
            $stmt = $pdo->prepare("
                INSERT INTO pos_bank_balances (bb_uuid, shift_id, departmentId, bankId, balance, startingCash, uid)
                VALUES (:bb_uuid, :shift_id, :departmentId, :bankId, :balance, :startingCash, :uid)
            ");
            foreach ($balances as $b) {
                $stmt->execute([
                    'bb_uuid'      => bin2hex(random_bytes(18)),
                    'shift_id'     => $shiftId,
                    'departmentId' => $deptId,
                    'bankId'       => (int)$b['id'],
                    'balance'      => (float)$b['balance'],
                    'startingCash' => $startCash,
                    'uid'          => $userId,
                ]);
            }
            $pdo->commit();
            ob_end_clean();
            echo json_encode(['success' => true, 'shift_id' => $shiftId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save balances: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== START SHIFT =====
    if ($action === 'start_shift') {
        $shiftId    = isset($input['shift_id']) && !empty($input['shift_id']) ? $input['shift_id'] : strtoupper(bin2hex(random_bytes(6)));
        $deptId     = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $startCash  = isset($input['cash_balance']) ? (float)$input['cash_balance'] : 0.00;
        $registerId  = null;
        $inputRegId  = isset($input['register_id']) ? (int)$input['register_id'] : 0;
        $costCenterId = isset($input['cost_center_id']) ? (int)$input['cost_center_id'] : 0;
        $bankAccountId = isset($input['bank_account_id']) ? (int)$input['bank_account_id'] : 0;

        // Validate the selected register belongs to this user; fall back to first assigned
        if ($inputRegId) {
            $stmt = $pdo->prepare("
                SELECT r.id, r.department_id FROM registers r
                INNER JOIN register_users ru ON ru.register_id = r.id
                WHERE ru.user_id = :uid AND r.id = :rid AND r.is_active = 1 LIMIT 1
            ");
            $stmt->execute(['uid' => $userId, 'rid' => $inputRegId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.id, r.department_id FROM registers r
                INNER JOIN register_users ru ON ru.register_id = r.id
                WHERE ru.user_id = :uid AND r.is_active = 1 LIMIT 1
            ");
            $stmt->execute(['uid' => $userId]);
        }
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reg) {
            $registerId = (int)$reg['id'];
            if (!$deptId) $deptId = (int)$reg['department_id'];
        }
        if (!$deptId && !empty($_SESSION['user']['department_id'])) {
            $deptId = (int)$_SESSION['user']['department_id'];
        }

        if ($costCenterId <= 0) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Select the cashier location cost center before starting the shift.']);
            exit;
        }

        if ($bankAccountId <= 0) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Select the bank account for POS payments before starting the shift.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT
                cc.id,
                cc.department_id,
                cc.status,
                EXISTS(
                    SELECT 1
                    FROM department_cost_centers dcc
                    WHERE dcc.department_id = :department_id
                      AND dcc.cost_center_id = cc.id
                      AND dcc.deleted_at IS NULL
                ) AS is_linked_to_department
            FROM cost_centers cc
            WHERE cc.id = :cost_center_id
              AND cc.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'department_id' => $deptId,
            'cost_center_id' => $costCenterId,
        ]);
        $costCenter = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$costCenter) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Selected cost center does not exist.']);
            exit;
        }
        if (($costCenter['status'] ?? '') !== 'active') {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Selected cost center is inactive.']);
            exit;
        }
        $belongsToDepartment = (int)($costCenter['department_id'] ?? 0) === (int)$deptId || (int)($costCenter['is_linked_to_department'] ?? 0) === 1;
        if (!$belongsToDepartment) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Selected cost center is not available for this cashier location.']);
            exit;
        }

        $validBankIds = array_map(static function(array $row): int {
            return (int)$row['id'];
        }, pos_shift_fetch_bank_accounts($pdo, (int)$deptId, $costCenterId));
        if (!in_array($bankAccountId, $validBankIds, true)) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Selected bank account is not available for the chosen cost center.']);
            exit;
        }

        // Check no active shift exists
        $stmt = $pdo->prepare("SELECT id FROM pos_shifts WHERE uid = :uid AND status = 1 LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        if ($stmt->fetch()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You already have an active shift.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO pos_shifts (shift_id, uid, department_id, register_id, cost_center_id, bank_account_id, starting_cash, status)
                VALUES (:sid, :uid, :did, :rid, :ccid, :baid, :cash, 1)
            ");
            $stmt->execute([
                'sid' => $shiftId,
                'uid' => $userId,
                'did' => $deptId,
                'rid' => $registerId,
                'ccid' => $costCenterId,
                'baid' => $bankAccountId,
                'cash' => $startCash,
            ]);
            ob_end_clean();
            echo json_encode(['success' => true, 'shift_id' => $shiftId]);
        } catch (Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to start shift: ' . $e->getMessage()]);
        }
        exit;
    }

    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ===== GET: Render page =====
$sessionUser = $_SESSION['user'];
$userName    = trim(($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? ''));
if (!$userName) $userName = $sessionUser['username'] ?? 'Cashier';

require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

  <div class="page-body">
    <div class="container-xl">

      <!-- Page identity card -->
      <div class="card mb-3" style="border-left: 4px solid var(--tblr-success);background:#d4edca;">
        <div class="card-body py-2">
          <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.63rem;letter-spacing:.1em;">Pay-In &middot; POS Terminal</div>
          <div class="d-flex align-items-center gap-3 flex-wrap">

            <!-- Title + Cashier -->
            <div style="min-width:140px;">
              <div class="fw-bold" style="font-size:1rem;line-height:1.25;">Start Shift</div>
              <div class="text-muted" style="font-size:.78rem;margin-top:.1rem;"><?= htmlspecialchars($userName) ?></div>
            </div>

            <div style="width:1px;align-self:stretch;background:var(--tblr-border-color);"></div>

            <!-- Department | Register (filled by JS) -->
            <div id="header-loading" class="text-muted align-items-center gap-1" style="display:flex;font-size:.82rem;">
              <div class="spinner-border spinner-border-sm" style="width:.7rem;height:.7rem;border-width:2px;"></div>
              Loading...
            </div>
            <div id="header-info" class="d-flex gap-3 flex-wrap" style="display:none!important;">
              <div>
                <div class="text-uppercase text-muted fw-semibold" style="font-size:.6rem;letter-spacing:.07em;">Department</div>
                <div style="font-size:.85rem;" id="info-department">—</div>
              </div>
              <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.6rem;letter-spacing:.07em;">Register</div>
                <select id="register-select" class="form-select form-select-sm" style="font-size:.82rem;min-width:150px;">
                  <option value="">— Not assigned —</option>
                </select>
              </div>
            </div>

            <div class="ms-auto">
              <a href="<?= url('views/pay-in/index.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Back</a>
            </div>
          </div>
        </div>
      </div>

      <div id="page-alert" class="alert mb-3" style="display:none;"></div>

      <!-- Main content (shown after JS load) -->
      <div id="main-content" style="display:none;">

        <div class="card" style="max-width:480px;">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Cashier Location Cost Center</label>
              <select class="form-select" id="cost-center-select">
                <option value="">-- Select cost center --</option>
              </select>
              <div class="form-text">This establishes the cashier location for the shift.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">POS Settlement Bank Account</label>
              <select class="form-select" id="bank-account-select">
                <option value="">-- Select bank account --</option>
              </select>
              <div class="form-text" id="bank-source-note">Bank accounts will load from the selected cost center.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Starting Cash Float (BZD)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control form-control-lg" id="starting-cash"
                       step="0.01" value="0.00" placeholder="0.00" style="font-size:1.25rem;font-weight:700;">
              </div>
              <div class="form-text">Physical cash counted in the register drawer at shift start.</div>
            </div>
            <div id="shift-alert" class="alert mb-3" style="display:none;font-size:.85rem;"></div>
            <button class="btn btn-success btn-lg w-100" id="btn-start-shift">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              Start Shift
            </button>
          </div>
        </div>

      </div><!-- /main-content -->

    </div>
  </div>

<script>
var SELF_URL     = '<?= url('views/pay-in/pos-shift-start.php') ?>';
var TERMINAL_URL = '<?= url('views/pay-in/pos-terminal.php') ?>';

function showAlert(id, msg, type) {
  type = type || 'danger';
  var el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}

var departmentId = null;

function populateCostCenters(rows) {
  var sel = document.getElementById('cost-center-select');
  sel.innerHTML = '<option value="">-- Select cost center --</option>';
  (rows || []).forEach(function(cc) {
    var opt = document.createElement('option');
    opt.value = cc.id;
    opt.textContent = (cc.code ? cc.code + ' - ' : '') + (cc.name || 'Unnamed Cost Center');
    sel.appendChild(opt);
  });
  sel.disabled = !(rows && rows.length);
}

function populateBankAccounts(rows) {
  var sel = document.getElementById('bank-account-select');
  var note = document.getElementById('bank-source-note');
  sel.innerHTML = '<option value="">-- Select bank account --</option>';
  (rows || []).forEach(function(bank) {
    var opt = document.createElement('option');
    var label = (bank.bank_name || 'Bank') + ' - ' + (bank.account_name || 'Account');
    if (bank.account_masked) label += ' (' + bank.account_masked + ')';
    if (bank.currency_code) label += ' [' + bank.currency_code + ']';
    opt.value = bank.id;
    opt.textContent = label;
    sel.appendChild(opt);
  });
  sel.disabled = !(rows && rows.length);
  note.textContent = (rows && rows.length) ? (rows[0].source_label || 'Bank accounts loaded.') : 'No bank accounts are available for the selected cost center.';
}

function loadBankOptions() {
  var costCenterId = parseInt(document.getElementById('cost-center-select').value, 10) || 0;
  populateBankAccounts([]);
  if (!departmentId || !costCenterId) return;

  fetch(SELF_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'load_bank_options', department_id: departmentId, cost_center_id: costCenterId})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.success) {
      showAlert('shift-alert', data.message || 'Failed to load bank accounts.');
      return;
    }
    populateBankAccounts(data.bank_accounts || []);
  })
  .catch(function(e) {
    showAlert('shift-alert', 'Error loading bank accounts: ' + e.message);
  });
}

// Load info on init
fetch(SELF_URL, {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({action: 'load_data'})
})
.then(function(r) { return r.json(); })
.then(function(data) {
  document.getElementById('header-loading').style.display = 'none';

  if (!data.success) {
    showAlert('page-alert', data.message || 'Failed to load shift data.');
    return;
  }

  if (data.has_active_shift) {
    window.location.href = TERMINAL_URL;
    return;
  }

  var dept = data.department;
  if (dept) {
    departmentId = dept.id;
    document.getElementById('info-department').textContent = dept.name;
  } else {
    document.getElementById('info-department').textContent = '— Not assigned —';
  }

  var costCenters = data.cost_centers || [];
  populateCostCenters(costCenters);
  if (costCenters.length > 0) {
    document.getElementById('cost-center-select').value = String(costCenters[0].id);
    populateBankAccounts(data.bank_accounts || []);
  } else {
    populateBankAccounts([]);
    showAlert('page-alert', 'No active cost center is available for this cashier location. Assign at least one cost center before starting a shift.', 'warning');
  }

  var registers = data.registers || [];
  var sel = document.getElementById('register-select');
  sel.innerHTML = '';
  if (registers.length === 0) {
    sel.innerHTML = '<option value="">— Not assigned —</option>';
    sel.disabled = true;
  } else {
    registers.forEach(function(r) {
      var opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.register_name || r.register_code;
      sel.appendChild(opt);
    });
  }

  document.getElementById('header-info').style.cssText = '';
  document.getElementById('main-content').style.display = '';
})
.catch(function(e) {
  document.getElementById('header-loading').style.display = 'none';
  showAlert('page-alert', 'Error loading shift data: ' + e.message);
});

document.getElementById('cost-center-select').addEventListener('change', loadBankOptions);

document.getElementById('btn-start-shift').addEventListener('click', function() {
  var el = document.getElementById('shift-alert');
  el.style.display = 'none';

  var btn = this;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Starting…';

  var cash  = parseFloat(document.getElementById('starting-cash').value) || 0;
  var regId = parseInt(document.getElementById('register-select').value) || null;
  var costCenterId = parseInt(document.getElementById('cost-center-select').value) || null;
  var bankAccountId = parseInt(document.getElementById('bank-account-select').value) || null;

  if (!costCenterId) {
    showAlert('shift-alert', 'Select the cashier location cost center before starting the shift.');
    btn.disabled = false;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Shift';
    return;
  }

  if (!bankAccountId) {
    showAlert('shift-alert', 'Select the bank account for POS payments before starting the shift.');
    btn.disabled = false;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Shift';
    return;
  }

  fetch(SELF_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      action: 'start_shift',
      department_id: departmentId,
      register_id: regId,
      cost_center_id: costCenterId,
      bank_account_id: bankAccountId,
      cash_balance: cash
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      window.location.href = TERMINAL_URL;
    } else {
      showAlert('shift-alert', data.message || 'Failed to start shift.');
      btn.disabled = false;
      btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Shift';
    }
  })
  .catch(function(e) {
    showAlert('shift-alert', 'Error: ' + e.message);
    btn.disabled = false;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Shift';
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
