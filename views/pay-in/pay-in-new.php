<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$userId   = (int)$_SESSION['user']['id'];
$userName = trim(
    (isset($_SESSION['user']['first_name']) ? $_SESSION['user']['first_name'] : '') . ' ' .
    (isset($_SESSION['user']['last_name'])  ? $_SESSION['user']['last_name']  : '')
);
if (!$userName) $userName = isset($_SESSION['user']['username']) ? $_SESSION['user']['username'] : 'Cashier';

$errors   = [];
$formData = [];

// Load departments
$departments = [];
try {
    $s = $pdo->prepare("SELECT id, name, short_name, code FROM departments ORDER BY name ASC");
    $s->execute();
    $departments = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* non-fatal */ }

// ===== POST: Save =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId   = isset($_POST['department_id'])   ? (int)$_POST['department_id']   : 0;
    $deptName = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';
    $payDate  = isset($_POST['pay_in_date'])     ? trim($_POST['pay_in_date'])     : '';
    $notes    = isset($_POST['notes'])           ? trim($_POST['notes'])           : '';

    // Bills
    $d100 = max(0, (int)(isset($_POST['d_100']) ? $_POST['d_100'] : 0));
    $d50  = max(0, (int)(isset($_POST['d_50'])  ? $_POST['d_50']  : 0));
    $d20  = max(0, (int)(isset($_POST['d_20'])  ? $_POST['d_20']  : 0));
    $d10  = max(0, (int)(isset($_POST['d_10'])  ? $_POST['d_10']  : 0));
    $d5   = max(0, (int)(isset($_POST['d_5'])   ? $_POST['d_5']   : 0));
    $d2   = max(0, (int)(isset($_POST['d_2'])   ? $_POST['d_2']   : 0));
    $d1   = max(0, (int)(isset($_POST['d_1'])   ? $_POST['d_1']   : 0));
    // Coins
    $c50  = max(0, (int)(isset($_POST['c_50'])  ? $_POST['c_50']  : 0));
    $c25  = max(0, (int)(isset($_POST['c_25'])  ? $_POST['c_25']  : 0));
    $c10  = max(0, (int)(isset($_POST['c_10'])  ? $_POST['c_10']  : 0));
    $c5   = max(0, (int)(isset($_POST['c_5'])   ? $_POST['c_5']   : 0));
    $c1   = max(0, (int)(isset($_POST['c_1'])   ? $_POST['c_1']   : 0));

    $cashTotal = round(
        ($d100 * 100) + ($d50 * 50) + ($d20 * 20) + ($d10 * 10)
        + ($d5 * 5) + ($d2 * 2) + ($d1 * 1)
        + ($c50 * 0.50) + ($c25 * 0.25) + ($c10 * 0.10) + ($c5 * 0.05) + ($c1 * 0.01),
        2
    );

    // Cheques
    $cheques      = [];
    $chequesTotal = 0.0;
    if (isset($_POST['cheque_bank']) && is_array($_POST['cheque_bank'])) {
        foreach ($_POST['cheque_bank'] as $i => $bname) {
            $bname = trim($bname);
            $cnum  = isset($_POST['cheque_number'][$i]) ? trim($_POST['cheque_number'][$i]) : '';
            $camt  = isset($_POST['cheque_amount'][$i]) ? round((float)$_POST['cheque_amount'][$i], 2) : 0.0;
            if ($bname !== '' && $cnum !== '' && $camt > 0) {
                $cheques[]     = ['bank' => $bname, 'number' => $cnum, 'amount' => $camt];
                $chequesTotal += $camt;
            }
        }
    }
    $chequesTotal = round($chequesTotal, 2);
    $totalAmount  = round($cashTotal + $chequesTotal, 2);

    // Resolve dept name from ID if not sent
    if ($deptId > 0 && $deptName === '') {
        foreach ($departments as $d) {
            if ((int)$d['id'] === $deptId) { $deptName = $d['name']; break; }
        }
    }

    // Validation
    if (!$payDate) {
        $errors[] = 'Pay-in date is required.';
    }
    if ($totalAmount <= 0) {
        $errors[] = 'Total amount must be greater than zero. Enter at least one denomination or cheque.';
    }

    // Bank slip upload
    $slipPath = null;
    if (isset($_FILES['bank_slip']) && $_FILES['bank_slip']['error'] === UPLOAD_ERR_OK) {
        $origName = isset($_FILES['bank_slip']['name']) ? (string)$_FILES['bank_slip']['name'] : '';
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Bank slip must be a JPG, PNG or PDF file.';
        } elseif ((int)$_FILES['bank_slip']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Bank slip must be under 5 MB.';
        } else {
            $uploadDir = __DIR__ . '/../../assets/uploads/pay-in-slips/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newName = 'slip_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['bank_slip']['tmp_name'], $uploadDir . $newName)) {
                $slipPath = 'assets/uploads/pay-in-slips/' . $newName;
            } else {
                $errors[] = 'Failed to upload bank slip.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $datePrefix = date('Ymd', strtotime($payDate));
            $cntStmt    = $pdo->prepare("SELECT COUNT(*) FROM pay_ins WHERE pay_in_id LIKE :p");
            $cntStmt->execute(['p' => 'PI-' . $datePrefix . '-%']);
            $seq     = (int)$cntStmt->fetchColumn() + 1;
            $payInId = 'PI-' . $datePrefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO pay_ins
                    (pay_in_id, department_id, department_name, cashier_uid, pay_in_date,
                     total_cash, total_cheques, total_amount, notes, bank_slip_path, status)
                VALUES (:pid,:did,:dn,:uid,:dt,:tc,:tch,:tot,:notes,:slip,'submitted')
            ")->execute([
                'pid'  => $payInId,
                'did'  => ($deptId > 0) ? $deptId : null,
                'dn'   => ($deptName !== '') ? $deptName : null,
                'uid'  => $userId,
                'dt'   => $payDate,
                'tc'   => $cashTotal,
                'tch'  => $chequesTotal,
                'tot'  => $totalAmount,
                'notes'=> ($notes !== '') ? $notes : null,
                'slip' => $slipPath,
            ]);

            $pdo->prepare("
                INSERT INTO pay_in_cash
                    (pay_in_id,d_100,d_50,d_20,d_10,d_5,d_2,d_1,c_50,c_25,c_10,c_5,c_1,cash_total)
                VALUES (:pid,:d100,:d50,:d20,:d10,:d5,:d2,:d1,:c50,:c25,:c10,:c5,:c1,:ct)
            ")->execute([
                'pid'=>$payInId,'d100'=>$d100,'d50'=>$d50,'d20'=>$d20,'d10'=>$d10,
                'd5'=>$d5,'d2'=>$d2,'d1'=>$d1,'c50'=>$c50,'c25'=>$c25,
                'c10'=>$c10,'c5'=>$c5,'c1'=>$c1,'ct'=>$cashTotal,
            ]);

            if (!empty($cheques)) {
                $sc = $pdo->prepare("
                    INSERT INTO pay_in_cheques (pay_in_id, bank_name, cheque_number, amount)
                    VALUES (:pid,:bn,:cn,:amt)
                ");
                foreach ($cheques as $ch) {
                    $sc->execute(['pid'=>$payInId,'bn'=>$ch['bank'],'cn'=>$ch['number'],'amt'=>$ch['amount']]);
                }
            }

            $pdo->commit();
            ob_end_clean();
            header('Location: ' . url('views/pay-in/pay-in-view.php') . '?id=' . urlencode($payInId));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    $formData = $_POST;
}

// Sticky form helpers
function fv($key, $default = '') {
    global $formData;
    return htmlspecialchars(isset($formData[$key]) ? (string)$formData[$key] : (string)$default);
}
function fvi($key, $default = 0) {
    global $formData;
    return isset($formData[$key]) ? max(0, (int)$formData[$key]) : (int)$default;
}

// Sticky cheque rows
$chequeRows = [];
if (!empty($formData['cheque_bank']) && is_array($formData['cheque_bank'])) {
    foreach ($formData['cheque_bank'] as $i => $bname) {
        $chequeRows[] = [
            'bank'   => htmlspecialchars((string)$bname),
            'number' => htmlspecialchars(isset($formData['cheque_number'][$i]) ? (string)$formData['cheque_number'][$i] : ''),
            'amount' => isset($formData['cheque_amount'][$i]) ? (float)$formData['cheque_amount'][$i] : '',
        ];
    }
}
if (empty($chequeRows)) {
    $chequeRows[] = ['bank' => '', 'number' => '', 'amount' => ''];
}

ob_end_clean();
require_once __DIR__ . '/../../includes/layout-tabler-header.php';
require_once __DIR__ . '/../../includes/layout-tabler-sidebar.php';
?>

<div class="page-body">
  <div class="container-xl">

    <!-- Page header -->
    <div class="card mb-4" style="border-left:4px solid #b45309;background:linear-gradient(135deg,#fffbeb 0%,#fff 60%);">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-uppercase fw-semibold mb-1" style="font-size:.63rem;letter-spacing:.1em;color:#b45309;">Pay-In &middot; Revenue Collection &middot; Belize Dollar (BZD)</div>
            <div class="fw-bold" style="font-size:1.05rem;line-height:1.2;color:#78350f;">New Pay-In</div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-muted" style="font-size:.82rem;">Cashier: <strong><?= htmlspecialchars($userName) ?></strong></span>
            <a href="<?= url('views/pay-in/pay-in-list.php') ?>" class="btn btn-outline-secondary btn-sm">&#8592; Pay-In List</a>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4">
      <div class="fw-bold mb-1">Please correct the following:</div>
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="payin-form">

      <div class="row g-4">

        <!-- Left column: Info + Denominations -->
        <div class="col-lg-8">

          <!-- Pay-in details -->
          <div class="card mb-4">
            <div class="card-header">
              <div class="card-title">Pay-In Details</div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label required">Department</label>
                  <select name="department_id" id="department_id" class="form-select">
                    <option value="">— Walk-in / Unspecified —</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"
                      <?= ((int)$d['id'] === (int)fv('department_id')) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d['name']) ?>
                      <?= $d['code'] ? '(' . htmlspecialchars($d['code']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <!-- Hidden: capture dept name for denormalized snapshot -->
                  <input type="hidden" name="department_name" id="department_name" value="<?= fv('department_name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Pay-In Date</label>
                  <input type="date" name="pay_in_date" class="form-control"
                         value="<?= fv('pay_in_date', date('Y-m-d')) ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Cash Breakdown -->
          <div class="card mb-4">
            <div class="card-header" style="background:linear-gradient(135deg,#92400e 0%,#b45309 100%);color:#fff;">
              <div class="card-title text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Cash Breakdown &mdash; Belize Dollar (BZD)
              </div>
            </div>
            <div class="card-body">
              <div class="row g-3">

                <!-- Bills -->
                <div class="col-md-7">
                  <div class="text-uppercase fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.07em;color:#92400e;">Notes (Bills)</div>
                  <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#92400e;color:#fff;">
                      <tr>
                        <th>Denomination</th>
                        <th class="text-center" style="width:90px;">Count</th>
                        <th class="text-end" style="width:100px;">Value (BZD)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $billDenoms = [
                          ['d_100', 100, 'BZD $100'],
                          ['d_50',   50, 'BZD $50'],
                          ['d_20',   20, 'BZD $20'],
                          ['d_10',   10, 'BZD $10'],
                          ['d_5',     5, 'BZD $5'],
                          ['d_2',     2, 'BZD $2'],
                      ];
                      foreach ($billDenoms as $bd):
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= $bd[2] ?></td>
                        <td class="p-1">
                          <input type="number" name="<?= $bd[0] ?>" id="<?= $bd[0] ?>"
                                 class="form-control form-control-sm text-center denom-input"
                                 data-val="<?= $bd[1] ?>"
                                 value="<?= fvi($bd[0]) ?>" min="0" placeholder="0">
                        </td>
                        <td class="text-end fw-semibold" id="sub_<?= $bd[0] ?>">$0.00</td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr style="background:#fef3c7;">
                        <td colspan="2" class="fw-bold" style="color:#78350f;">Bills Sub-total</td>
                        <td class="text-end fw-bold" id="bills-subtotal" style="color:#78350f;">BZD $0.00</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Coins -->
                <div class="col-md-5">
                  <div class="text-uppercase fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.07em;color:#92400e;">Coins</div>
                  <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#92400e;color:#fff;">
                      <tr>
                        <th>Denomination</th>
                        <th class="text-center" style="width:80px;">Count</th>
                        <th class="text-end" style="width:90px;">Value (BZD)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $coinDenoms = [
                          ['d_1',  1.00, '$1.00'],
                          ['c_50', 0.50, '$0.50'],
                          ['c_25', 0.25, '$0.25'],
                          ['c_10', 0.10, '$0.10'],
                          ['c_5',  0.05, '$0.05'],
                          ['c_1',  0.01, '$0.01'],
                      ];
                      foreach ($coinDenoms as $cd):
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= $cd[2] ?></td>
                        <td class="p-1">
                          <input type="number" name="<?= $cd[0] ?>" id="<?= $cd[0] ?>"
                                 class="form-control form-control-sm text-center denom-input"
                                 data-val="<?= $cd[1] ?>"
                                 value="<?= fvi($cd[0]) ?>" min="0" placeholder="0">
                        </td>
                        <td class="text-end fw-semibold" id="sub_<?= $cd[0] ?>">$0.00</td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr style="background:#fef3c7;">
                        <td colspan="2" class="fw-bold" style="color:#78350f;">Coins Sub-total</td>
                        <td class="text-end fw-bold" id="coins-subtotal" style="color:#78350f;">BZD $0.00</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

              </div><!-- /row -->

              <!-- Cash total bar -->
              <div class="d-flex justify-content-between align-items-center mt-3 p-3 rounded"
                   style="background:linear-gradient(135deg,#92400e 0%,#b45309 100%);color:#fff;">
                <span class="fw-bold text-uppercase" style="letter-spacing:.07em;font-size:.85rem;">Cash Total</span>
                <span class="fw-black fs-4" id="cash-total-disp">BZD $0.00</span>
              </div>
            </div>
          </div>

          <!-- Cheques -->
          <div class="card mb-4">
            <div class="card-header">
              <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-blue" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Cheques
              </div>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0" id="cheques-table">
                <thead class="table-light">
                  <tr>
                    <th style="width:35%;">Bank Name</th>
                    <th style="width:30%;">Cheque Number</th>
                    <th style="width:25%;">Amount (BZD)</th>
                    <th style="width:10%;"></th>
                  </tr>
                </thead>
                <tbody id="cheques-tbody">
                  <?php foreach ($chequeRows as $cr): ?>
                  <tr class="cheque-row">
                    <td class="p-1"><input type="text" name="cheque_bank[]" class="form-control form-control-sm" value="<?= $cr['bank'] ?>" placeholder="e.g. Atlantic Bank"></td>
                    <td class="p-1"><input type="text" name="cheque_number[]" class="form-control form-control-sm" value="<?= $cr['number'] ?>" placeholder="Cheque #"></td>
                    <td class="p-1"><input type="number" name="cheque_amount[]" class="form-control form-control-sm cheque-amt" step="0.01" min="0" value="<?= $cr['amount'] !== '' ? htmlspecialchars((string)$cr['amount']) : '' ?>" placeholder="0.00"></td>
                    <td class="p-1 text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-cheque-btn px-2" title="Remove">&#215;</button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between">
                <button type="button" id="add-cheque-btn" class="btn btn-sm btn-outline-secondary">
                  + Add Cheque
                </button>
                <div class="fw-bold text-muted" style="font-size:.85rem;">
                  Cheques Total: <span id="cheques-total-disp" class="text-dark">BZD $0.00</span>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /left col -->

        <!-- Right column: Slip + Notes + Summary -->
        <div class="col-lg-4">

          <!-- Bank Deposit Slip -->
          <div class="card mb-4">
            <div class="card-header">
              <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm me-1 text-muted" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Bank Deposit Slip
                <span class="badge bg-secondary-lt text-secondary ms-1" style="font-size:.65rem;">Optional</span>
              </div>
            </div>
            <div class="card-body">
              <input type="file" name="bank_slip" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
              <div class="form-hint mt-1">JPG, PNG or PDF &middot; Max 5 MB</div>
            </div>
          </div>

          <!-- Notes -->
          <div class="card mb-4">
            <div class="card-header">
              <div class="card-title">Notes</div>
            </div>
            <div class="card-body">
              <textarea name="notes" class="form-control" rows="3"
                        placeholder="Optional narrative or reference..."><?= fv('notes') ?></textarea>
            </div>
          </div>

          <!-- Grand Total Summary -->
          <div class="card" style="border:2px solid #b45309;position:sticky;top:1rem;">
            <div class="card-header" style="background:linear-gradient(135deg,#92400e 0%,#b45309 100%);color:#fff;">
              <div class="card-title text-white fw-bold">Pay-In Summary</div>
            </div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Cash</span>
                <span class="fw-semibold" id="sum-cash">BZD $0.00</span>
              </div>
              <div class="d-flex justify-content-between mb-3">
                <span class="text-muted">Cheques</span>
                <span class="fw-semibold" id="sum-cheques">BZD $0.00</span>
              </div>
              <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                <span class="fw-bold text-uppercase" style="letter-spacing:.05em;font-size:.85rem;">Grand Total</span>
                <span class="fw-black fs-3" id="sum-grand" style="color:#92400e;">BZD $0.00</span>
              </div>
            </div>
            <div class="card-footer" style="background:#fef3c7;">
              <button type="submit" class="btn w-100 fw-bold" id="btn-submit"
                      style="background:linear-gradient(135deg,#92400e 0%,#b45309 100%);color:#fff;border:none;">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Submit Pay-In
              </button>
            </div>
          </div>

        </div><!-- /right col -->

      </div><!-- /row -->

    </form>

  </div>
</div>

<script>
var _cashTotal    = 0;
var _chequesTotal = 0;

var BILL_DENOMS = [
    {id:'d_100', val:100},
    {id:'d_50',  val:50},
    {id:'d_20',  val:20},
    {id:'d_10',  val:10},
    {id:'d_5',   val:5},
    {id:'d_2',   val:2}
];
var COIN_DENOMS = [
    {id:'d_1',  val:1.00},
    {id:'c_50', val:0.50},
    {id:'c_25', val:0.25},
    {id:'c_10', val:0.10},
    {id:'c_5',  val:0.05},
    {id:'c_1',  val:0.01}
];

function fmtBZD(n) { return 'BZD $' + n.toFixed(2); }

function recalcDenoms() {
    var billsTotal = 0;
    var coinsTotal = 0;
    var i, d, inp, qty, sub;

    for (i = 0; i < BILL_DENOMS.length; i++) {
        d   = BILL_DENOMS[i];
        inp = document.getElementById(d.id);
        qty = Math.max(0, parseInt(inp.value) || 0);
        sub = qty * d.val;
        billsTotal += sub;
        document.getElementById('sub_' + d.id).textContent = '$' + sub.toFixed(2);
    }

    for (i = 0; i < COIN_DENOMS.length; i++) {
        d   = COIN_DENOMS[i];
        inp = document.getElementById(d.id);
        qty = Math.max(0, parseInt(inp.value) || 0);
        sub = Math.round(qty * d.val * 100) / 100;
        coinsTotal += sub;
        document.getElementById('sub_' + d.id).textContent = '$' + sub.toFixed(2);
    }

    document.getElementById('bills-subtotal').textContent = fmtBZD(billsTotal);
    document.getElementById('coins-subtotal').textContent = fmtBZD(coinsTotal);

    _cashTotal = Math.round((billsTotal + coinsTotal) * 100) / 100;
    document.getElementById('cash-total-disp').textContent = fmtBZD(_cashTotal);
    document.getElementById('sum-cash').textContent        = fmtBZD(_cashTotal);
    recalcGrand();
}

function recalcCheques() {
    _chequesTotal = 0;
    var inputs = document.querySelectorAll('.cheque-amt');
    for (var i = 0; i < inputs.length; i++) {
        _chequesTotal += Math.max(0, parseFloat(inputs[i].value) || 0);
    }
    _chequesTotal = Math.round(_chequesTotal * 100) / 100;
    document.getElementById('cheques-total-disp').textContent = fmtBZD(_chequesTotal);
    document.getElementById('sum-cheques').textContent        = fmtBZD(_chequesTotal);
    recalcGrand();
}

function recalcGrand() {
    var grand = Math.round((_cashTotal + _chequesTotal) * 100) / 100;
    document.getElementById('sum-grand').textContent = fmtBZD(grand);
}

// Attach denom inputs
(function() {
    var all = document.querySelectorAll('.denom-input');
    for (var i = 0; i < all.length; i++) {
        all[i].addEventListener('input', recalcDenoms);
    }
    recalcDenoms();
})();

// Cheque rows
function attachChequeRow(row) {
    var amtInput = row.querySelector('.cheque-amt');
    var remBtn   = row.querySelector('.remove-cheque-btn');
    if (amtInput) amtInput.addEventListener('input', recalcCheques);
    if (remBtn)   remBtn.addEventListener('click', function() {
        row.parentNode.removeChild(row);
        recalcCheques();
    });
}

(function() {
    var rows = document.querySelectorAll('.cheque-row');
    for (var i = 0; i < rows.length; i++) { attachChequeRow(rows[i]); }
    recalcCheques();
})();

document.getElementById('add-cheque-btn').addEventListener('click', function() {
    var tbody = document.getElementById('cheques-tbody');
    var tr    = document.createElement('tr');
    tr.className = 'cheque-row';
    tr.innerHTML = '<td class="p-1"><input type="text" name="cheque_bank[]" class="form-control form-control-sm" placeholder="e.g. Atlantic Bank"></td>'
        + '<td class="p-1"><input type="text" name="cheque_number[]" class="form-control form-control-sm" placeholder="Cheque #"></td>'
        + '<td class="p-1"><input type="number" name="cheque_amount[]" class="form-control form-control-sm cheque-amt" step="0.01" min="0" placeholder="0.00"></td>'
        + '<td class="p-1 text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-cheque-btn px-2" title="Remove">&#215;</button></td>';
    tbody.appendChild(tr);
    attachChequeRow(tr);
    tr.querySelector('input[type="text"]').focus();
});

// Capture dept name on change
document.getElementById('department_id').addEventListener('change', function() {
    var sel = this;
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('department_name').value = opt ? opt.textContent.trim() : '';
});

// Submit guard
document.getElementById('payin-form').addEventListener('submit', function() {
    var btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
});
</script>

<?php require_once __DIR__ . '/../../includes/layout-tabler-footer.php'; ?>
