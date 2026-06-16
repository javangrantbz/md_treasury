<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$uuid  = isset($_GET['tx'])  ? trim($_GET['tx']) : '';
$isNew = isset($_GET['new']) && (string)$_GET['new'] === '1';

if ($uuid === '') {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pos-terminal.php'));
    exit;
}

// Load the completed transaction (snapshot) + cashier + shift department.
$s = $pdo->prepare("
    SELECT t.*, CONCAT(u.first_name,' ',u.last_name) AS cashier_name,
           ps.department_id AS shift_dept_id
    FROM pos_transactions t
    LEFT JOIN users u ON u.id = t.uid
    LEFT JOIN pos_shifts ps ON ps.shift_id = t.shift_id
    WHERE t.uuid = :uuid
    LIMIT 1
");
$s->execute(['uuid' => $uuid]);
$tx = $s->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pos-terminal.php'));
    exit;
}

// Resolve treasury branch from the shift's department (fallback to payer dept).
$branchName = 'Treasury';
$branchDeptId = !empty($tx['shift_dept_id']) ? (int)$tx['shift_dept_id'] : (!empty($tx['dept_id']) ? (int)$tx['dept_id'] : 0);
if ($branchDeptId) {
    $bs = $pdo->prepare("SELECT name, short_name FROM departments WHERE id = :id LIMIT 1");
    $bs->execute(['id' => $branchDeptId]);
    $bd = $bs->fetch(PDO::FETCH_ASSOC);
    if ($bd) $branchName = $bd['short_name'] ?: $bd['name'];
}

$items = [];
if (!empty($tx['items'])) {
    $decoded = json_decode($tx['items'], true);
    if (is_array($decoded)) $items = $decoded;
}

// ---- Receipt helpers ----
function rcptMethod($m) {
    $map = ['cash'=>'Cash','check'=>'Cheque','bank_deposit'=>'Bank Deposit',
            'pos_terminal'=>'POS Terminal','online_transfer'=>'Online Transfer','e_invoicing'=>'E-Invoice'];
    return isset($map[$m]) ? $map[$m] : ($m !== '' ? $m : '--');
}
function rcptDetails($pd) {
    if (is_array($pd)) return $pd;
    if (is_string($pd) && $pd !== '') {
        $d = json_decode($pd, true);
        return is_array($d) ? $d : [];
    }
    return [];
}
function rcptRef($it) {
    $pd = rcptDetails(isset($it['payment_details']) ? $it['payment_details'] : []);
    if (!empty($pd['reference_number'])) return $pd['reference_number'];
    if (!empty($pd['reference']))        return $pd['reference'];
    return '';
}
function rcptMethodOrder($m) {
    $order = ['cash'=>0,'check'=>1,'pos_terminal'=>2,'online_transfer'=>3,'e_invoicing'=>4,'bank_deposit'=>9];
    return isset($order[$m]) ? $order[$m] : 5;
}
function rcptNumWords($amount) {
    $amount = round((float)$amount, 2);
    $whole  = (int)floor($amount);
    $cents  = (int)round(($amount - $whole) * 100);
    $ones   = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
               'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
               'Seventeen','Eighteen','Nineteen'];
    $tens   = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $result = '';
    $n = $whole;
    if ($n >= 1000) {
        $m = (int)floor($n / 1000);
        $h = (int)floor($m / 100); $r = $m % 100; $t = '';
        if ($h) $t = $ones[$h] . ' Hundred';
        if ($r > 0 && $r < 20)  $t .= ($t ? ' ' : '') . $ones[$r];
        elseif ($r >= 20) { $t .= ($t ? ' ' : '') . $tens[(int)floor($r/10)]; if ($r%10) $t .= ' ' . $ones[$r%10]; }
        $result .= $t . ' Thousand';
        $n = $n % 1000;
        if ($n) $result .= ' ';
    }
    if ($n > 0) {
        $h = (int)floor($n / 100); $r = $n % 100;
        if ($h) $result .= $ones[$h] . ' Hundred';
        if ($r > 0 && $r < 20)  $result .= ($h ? ' ' : '') . $ones[$r];
        elseif ($r >= 20) { $result .= ($h ? ' ' : '') . $tens[(int)floor($r/10)]; if ($r%10) $result .= ' ' . $ones[$r%10]; }
    }
    if ($result === '') $result = 'Zero';
    $result .= ' Dollar' . ($whole !== 1 ? 's' : '');
    if ($cents > 0) {
        $cw = ($cents < 20) ? $ones[$cents] : $tens[(int)floor($cents/10)] . ($cents%10 ? ' '.$ones[$cents%10] : '');
        $result .= ' and ' . $cw . ' Cent' . ($cents !== 1 ? 's' : '');
    }
    return $result . ' Only';
}

// Order items: collected payments first, bank deposits last (tear-off).
usort($items, function ($a, $b) {
    return rcptMethodOrder($a['payment_method'] ?? '') - rcptMethodOrder($b['payment_method'] ?? '');
});

$total = 0.0;
$hasPendingBankDeposit = false;
foreach ($items as $it) {
    $total += (float)($it['amount'] ?? 0);
    if (($it['payment_method'] ?? '') === 'bank_deposit') $hasPendingBankDeposit = true;
}
if ($total <= 0 && !empty($tx['total_amount'])) $total = (float)$tx['total_amount'];

$payerName = trim((string)($tx['customer_name'] ?? ''));
$payerType = 'Individual';
if ($payerName === '' && !empty($tx['dept_name'])) {
    $payerName = $tx['dept_name'];
    $payerType = 'Government Department';
} elseif ($payerName === '') {
    $payerName = 'Walk-in / Cash Customer';
}

$cashierName = trim((string)($tx['cashier_name'] ?? '')) ?: 'Unknown';
$txDateTime  = !empty($tx['created_at']) ? date('d M Y  h:i:s A', strtotime($tx['created_at'])) : date('d M Y  h:i:s A');

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?= htmlspecialchars($uuid) ?> — Treasury Revenue System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; margin: 0; background: #eef2e9; color: #111; }
.toolbar { background: #1e4620; color: #fff; padding: 10px 22px; display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; z-index: 10; }
.toolbar .tb-left { display: flex; align-items: center; gap: 9px; }
.toolbar a, .toolbar button { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 7px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; border: none; display: inline-flex; align-items: center; gap: 5px; }
.tb-ghost { color: rgba(255,255,255,.72); background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); }
.tb-ghost:hover { background: rgba(255,255,255,.18); color: #fff; }
.tb-print { background: #fff; color: #1e4620; }
.tb-print:hover { background: #e8f3e8; }
.tb-new { background: #f5b301; color: #5a3e00; }
.tb-new:hover { background: #ffc62e; }
.wrapper { max-width: 840px; margin: 22px auto; padding: 0 14px 48px; }
.tb-select { font-size: 11px; font-weight: 700; padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.1); color: #fff; cursor: pointer; }
.tb-select option { color: #111; }
.paper { margin: 0 auto; transition: max-width .15s; }
#rct-paper[data-print-format="thermal"]     { max-width: 24rem; }
#rct-paper[data-print-format="half-letter"] { max-width: 34rem; }
#rct-paper[data-print-format="a5"]          { max-width: 38rem; }
#rct-paper[data-print-format="letter"]      { max-width: 50rem; }
.success-bar { background: linear-gradient(135deg,#15803d 0%,#1e9e4a 100%); color: #fff; border-radius: 14px; padding: 18px 22px; margin-bottom: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 6px 22px rgba(21,128,61,.28); }
.success-bar .chk { width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.success-bar h1 { font-size: 16px; font-weight: 900; margin: 0 0 2px; letter-spacing: .01em; }
.success-bar p { font-size: 11px; margin: 0; opacity: .9; }
.success-bar .amt { margin-left: auto; text-align: right; flex-shrink: 0; }
.success-bar .amt .lbl { font-size: 8px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; opacity: .85; }
.success-bar .amt .val { font-size: 20px; font-weight: 900; }
.paper { background: #fff; border-radius: 12px; box-shadow: 0 2px 18px rgba(0,0,0,.10); overflow: hidden; }
@media print {
  body { background: #fff; }
  .no-print { display: none !important; }
  .wrapper { max-width: 100%; margin: 0; padding: 0; }
  .paper { border-radius: 0; box-shadow: none; }
  @page { size: auto; margin: 12mm; }
  #rct-paper[data-print-format="thermal"]     { width: 80mm!important;  max-width: 80mm!important;  font-size: 9pt!important; }
  #rct-paper[data-print-format="half-letter"] { width: 5.5in!important; max-width: 5.5in!important; font-size: 10pt!important; }
  #rct-paper[data-print-format="a5"]          { width: 148mm!important; max-width: 148mm!important; font-size: 10.5pt!important; }
  #rct-paper[data-print-format="letter"]      { width: 8in!important;   max-width: 8in!important;   font-size: 11pt!important; }
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar no-print">
  <div class="tb-left">
    <img src="<?= url('assets/img/coat-of-arms.png') ?>" style="width:22px;height:22px;object-fit:contain;" alt="">
    <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,.72);">Official Receipt</span>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="<?= url('views/cashiering/dashboard.php') ?>" class="tb-ghost">&#8592; Cashiering</a>
    <a href="<?= url('views/pay-in/pos-terminal.php') ?>" class="tb-new">+ New Transaction</a>
    <select id="receipt-print-format" class="tb-select" title="Print format">
      <option value="thermal">Thermal 80mm</option>
      <option value="half-letter" selected>Half Letter</option>
      <option value="a5">A5</option>
      <option value="letter">Letter</option>
    </select>
    <button id="btn-email-receipt" class="tb-ghost">&#9993; Email</button>
    <button onclick="window.print()" class="tb-print">&#128438; Print</button>
  </div>
</div>

<div class="wrapper">

  <?php if ($isNew): ?>
  <!-- Success banner -->
  <div class="success-bar no-print">
    <div class="chk">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <div>
      <h1>Transaction Completed</h1>
      <p>Receipt issued &middot; Cashier: <?= htmlspecialchars($cashierName) ?></p>
    </div>
    <div class="amt">
      <div class="lbl">Total Collected</div>
      <div class="val">BZD $<?= number_format($total, 2) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="paper" id="rct-paper" data-print-format="half-letter">

    <!-- Official header -->
    <div style="background:#1e4620;padding:20px 24px 16px;text-align:center;">
      <div style="display:flex;align-items:center;justify-content:center;gap:14px;">
        <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms" style="width:52px;height:52px;object-fit:contain;filter:brightness(1.1);">
        <div style="text-align:left;">
          <div style="color:#a7d9a8;font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:2px;">Government of Belize</div>
          <div style="color:#fff;font-size:16px;font-weight:900;line-height:1.15;letter-spacing:.01em;">Treasury Revenue System</div>
          <div style="color:#86c98a;font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-top:2px;">Ministry of Finance &amp; Economic Development</div>
        </div>
      </div>
      <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,.15);">
        <span style="display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:4px 16px;color:#fff;font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;">Official Receipt</span>
      </div>
    </div>

    <!-- Meta -->
    <div style="background:#f8fdf5;border-bottom:2px solid #d1e8d2;padding:10px 24px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;">
        <div>
          <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Receipt No.</div>
          <div style="font-size:12px;font-weight:900;color:#1e4620;font-family:monospace;word-break:break-all;"><?= htmlspecialchars($uuid) ?></div>
        </div>
        <div>
          <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Date &amp; Time</div>
          <div style="font-size:11px;font-weight:600;color:#1a1a1a;"><?= htmlspecialchars($txDateTime) ?></div>
        </div>
        <div>
          <div style="font-size:8px;font-weight:700;color:#6b8f6c;text-transform:uppercase;letter-spacing:.08em;">Branch</div>
          <div style="font-size:11px;font-weight:600;color:#1a1a1a;"><?= htmlspecialchars($branchName) ?></div>
        </div>
      </div>
    </div>

    <div style="padding:0 24px;">

      <?php if ($hasPendingBankDeposit): ?>
      <div style="margin:14px 0 0;padding:12px 14px;border-radius:8px;border:1px solid #f5c2c7;background:#fff5f5;color:#991b1b;">
        <div style="font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;">Pending Payment / Not Paid</div>
        <div style="font-size:10px;line-height:1.6;margin-top:4px;">Bank deposit instructions were issued to the customer. Payment has not yet been received by Treasury and remains pending until matched by reference number.</div>
      </div>
      <?php endif; ?>

      <!-- Payer -->
      <div style="margin:14px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
        <div style="font-size:8px;font-weight:800;color:#1e4620;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;">Payer Information</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px 16px;font-size:11px;">
          <div><div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;">Name</div><div style="font-weight:600;color:#111827;"><?= htmlspecialchars($payerName) ?></div></div>
          <div><div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;">Type</div><div style="font-weight:600;color:#111827;"><?= htmlspecialchars($payerType) ?></div></div>
        </div>
      </div>

      <!-- Items -->
      <div style="margin:12px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
        <div style="font-size:8px;font-weight:800;color:#1e4620;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;">Description of Services</div>
        <div style="display:grid;grid-template-columns:1.2fr 1fr 86px 70px;gap:4px;background:#1e4620;color:#fff;font-size:8px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:5px 8px;border-radius:4px 4px 0 0;">
          <div>Service</div><div>Beneficiary</div><div style="text-align:center;">Payment</div><div style="text-align:right;">Amount</div>
        </div>
        <div style="border:1px solid #d1e8d2;border-top:none;border-radius:0 0 4px 4px;overflow:hidden;">
          <?php if (empty($items)): ?>
          <div style="padding:10px 8px;font-size:10px;color:#6b7280;text-align:center;">No items.</div>
          <?php else: ?>
          <?php
          $bankStarted = false;
          foreach ($items as $i => $it):
              $m      = $it['payment_method'] ?? '';
              $isBank = ($m === 'bank_deposit');
              $ref    = rcptRef($it);
              $rowBg  = $isBank ? '#fbfcf8' : (($i % 2 === 0) ? '#fff' : '#f8fdf5');
              if ($isBank && !$bankStarted):
                  $bankStarted = true; ?>
                  <div style="border-top:2px dashed #1e4620;background:#f8fdf5;text-align:center;padding:7px 8px 6px;">
                    <span style="display:inline-block;background:#fff;border:1.5px solid #1e4620;border-radius:999px;padding:2px 12px;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1e4620;">&#9986; Bank Deposit &mdash; Tear Off &amp; Present At Bank</span>
                  </div>
              <?php endif; ?>
              <div style="display:grid;grid-template-columns:1.2fr 1fr 86px 70px;gap:4px;padding:6px 8px;border-bottom:1px solid #e8f3e8;background:<?= $rowBg ?>;">
                <div style="font-size:10px;color:#111827;">
                  <div style="font-weight:600;"><?= htmlspecialchars($it['activity_name'] ?? '') ?></div>
                  <?php if (!empty($it['activity_code'])): ?><div style="font-size:8px;font-family:monospace;color:#6b7280;"><?= htmlspecialchars($it['activity_code']) ?></div><?php endif; ?>
                  <?php if (!empty($it['cost_center_name'])): ?><div style="font-size:8px;color:#9ca3af;"><?= htmlspecialchars($it['cost_center_name']) ?></div><?php endif; ?>
                </div>
                <div style="font-size:9px;color:#374151;padding-top:3px;"><?= htmlspecialchars(!empty($it['beneficiary_name']) ? $it['beneficiary_name'] : '--') ?></div>
                <div style="font-size:8px;color:#111827;text-align:center;padding-top:2px;">
                  <div style="font-weight:700;"><?= htmlspecialchars(rcptMethod($m)) ?></div>
                  <?php if ($ref !== ''): ?><div style="font-size:<?= $isBank ? '8px' : '7px' ?>;font-weight:700;font-family:monospace;color:#1d4ed8;word-break:break-all;"><?= htmlspecialchars($ref) ?></div><?php endif; ?>
                  <?php if ($isBank): ?><div style="margin-top:2px;font-size:7px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#991b1b;">Pending / Not Paid</div><?php endif; ?>
                </div>
                <div style="font-size:10px;font-weight:700;color:#111827;text-align:right;padding-top:3px;">$<?= number_format((float)($it['amount'] ?? 0), 2) ?></div>
              </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Totals -->
      <div style="margin:10px 0 0;padding-bottom:12px;border-bottom:1.5px dashed #cde3ce;">
        <div style="display:flex;justify-content:flex-end;margin-bottom:6px;">
          <div style="background:#1e4620;color:#fff;border-radius:6px;padding:8px 16px;text-align:right;min-width:200px;">
            <div style="font-size:8px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#a7d9a8;margin-bottom:3px;">Total Amount</div>
            <div style="font-size:20px;font-weight:900;letter-spacing:.01em;">BZD $<?= number_format($total, 2) ?></div>
          </div>
        </div>
        <div style="font-size:9px;color:#4a6e4b;font-style:italic;text-align:right;"><?= htmlspecialchars(rcptNumWords($total)) ?></div>
      </div>

      <!-- Footer -->
      <div style="margin:12px 0 16px;text-align:center;">
        <div style="font-size:8px;color:#4a6e4b;line-height:1.6;margin-bottom:10px;">
          This is an official receipt issued by the Government of Belize, Treasury Department.<br>
          Please retain this receipt for your records and tax purposes.<br>
          For enquiries: <strong>treasury@belize.gov.bz</strong> &nbsp;|&nbsp; Tel: +501 822-2362
        </div>
        <div style="font-size:8px;color:#aaa;border-top:1px solid #e8f3e8;padding-top:8px;margin-top:6px;">Processed by: <?= htmlspecialchars($cashierName) ?> &middot; <?= htmlspecialchars($txDateTime) ?></div>
      </div>

    </div>
  </div>
</div>

<?php
$emailItems = [];
foreach ($items as $it) {
    $emailItems[] = [
        'name'        => $it['activity_name'] ?? '',
        'amount'      => (float)($it['amount'] ?? 0),
        'beneficiary' => $it['beneficiary_name'] ?? '',
        'method'      => rcptMethod($it['payment_method'] ?? ''),
        'ref'         => rcptRef($it),
        'is_bank'     => (($it['payment_method'] ?? '') === 'bank_deposit'),
    ];
}
$receiptPayload = [
    'number'   => $uuid,
    'dateTime' => $txDateTime,
    'branch'   => $branchName,
    'cashier'  => $cashierName,
    'payer'    => $payerName,
    'items'    => $emailItems,
    'total'    => $total,
    'pending'  => $hasPendingBankDeposit,
];
?>
<script>
var RECEIPT = <?= json_encode($receiptPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function setReceiptPrintFormat(fmt) {
  var p = document.getElementById('rct-paper');
  if (p) p.setAttribute('data-print-format', fmt || 'half-letter');
}
document.getElementById('receipt-print-format').addEventListener('change', function() {
  setReceiptPrintFormat(this.value);
});

var EMAIL_RECEIPT_URL = '<?= url('api/pay-in/email-receipt.php') ?>';

document.getElementById('btn-email-receipt').addEventListener('click', function() {
  var email = (window.prompt('Email this receipt to:') || '').trim();
  if (!email) return;
  var btn = this;
  var orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'Sending…';
  fetch(EMAIL_RECEIPT_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ tx: RECEIPT.number, email: email })
  }).then(function(r) { return r.json(); }).then(function(d) {
    btn.disabled = false;
    btn.innerHTML = orig;
    alert((d && d.message) ? d.message : (d && d.success ? 'Receipt sent.' : 'Failed to send the receipt.'));
  }).catch(function() {
    btn.disabled = false;
    btn.innerHTML = orig;
    alert('Network error sending the receipt.');
  });
});
</script>
</body>
</html>
