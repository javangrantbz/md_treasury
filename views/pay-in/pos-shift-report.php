<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$shiftId = isset($_GET['shift_id']) ? trim($_GET['shift_id']) : '';
if (!$shiftId) {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pos-shift-history.php'));
    exit;
}

// Fetch shift
$s = $pdo->prepare("
    SELECT ps.*, CONCAT(u.first_name,' ',u.last_name) AS cashier_name,
           d.name AS dept_name, d.short_name AS dept_short,
           r.register_name, r.register_code
    FROM pos_shifts ps
    LEFT JOIN users u ON u.id = ps.uid
    LEFT JOIN departments d ON d.id = ps.department_id
    LEFT JOIN registers r ON r.id = ps.register_id
    WHERE ps.shift_id = :sid
    LIMIT 1
");
$s->execute(['sid' => $shiftId]);
$shift = $s->fetch(PDO::FETCH_ASSOC);

if (!$shift) {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pos-shift-history.php'));
    exit;
}

// Transaction count + total
$s = $pdo->prepare("SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount),0) AS total FROM pos_transactions WHERE shift_id = :sid");
$s->execute(['sid' => $shiftId]);
$txRow = $s->fetch(PDO::FETCH_ASSOC);

// Breakdown by payment method
$s = $pdo->prepare("SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM pos_cart_items WHERE shift_id = :sid AND status = 'completed' GROUP BY payment_method ORDER BY total DESC");
$s->execute(['sid' => $shiftId]);
$breakdown = $s->fetchAll(PDO::FETCH_ASSOC);

$cashCollected = 0;
foreach ($breakdown as $b) {
    if ($b['payment_method'] === 'cash') { $cashCollected = (float)$b['total']; break; }
}

$expectedDrawer = (float)$shift['starting_cash'] + $cashCollected;
$txCount        = (int)$txRow['tx_count'];
$totalAmount    = (float)$txRow['total'];

$cashierName  = trim($shift['cashier_name']) ?: 'Unknown';
$branchName   = $shift['dept_short'] ?: ($shift['dept_name'] ?: 'Treasury');
$terminalName = $shift['register_name'] ?: ($shift['register_code'] ?: '—');
$startedAt    = $shift['started_at'] ?: '';
$endedAt      = $shift['ended_at']   ?: '';

function shiftFmtDT($dt) {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('d M Y  h:i A', $ts) : '—';
}
function shiftFmtMethod($m) {
    $map = ['cash'=>'Cash','check'=>'Cheque','bank_deposit'=>'Bank Deposit',
            'pos_terminal'=>'POS Terminal','online_transfer'=>'Online Transfer','e_invoicing'=>'E-Invoice'];
    return isset($map[$m]) ? $map[$m] : $m;
}
function shiftDuration($start, $end) {
    if (!$start || !$end) return '—';
    $diff = strtotime($end) - strtotime($start);
    if ($diff <= 0) return '—';
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return $h.'h '.$m.'m';
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shift Report <?= htmlspecialchars($shiftId) ?> — Treasury Revenue System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; margin: 0; background: #f1f5f9; color: #111; }
.toolbar { background: #1e4620; color: #fff; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.toolbar a, .toolbar button { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; padding: 5px 14px; border-radius: 5px; cursor: pointer; text-decoration: none; }
.tb-back { color: rgba(255,255,255,.6); background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.15); }
.tb-back:hover { background: rgba(255,255,255,.15); color: #fff; }
.tb-print { background: #fff; color: #1e4620; border: none; }
.tb-print:hover { background: #e8f3e8; }
.wrapper { max-width: 800px; margin: 24px auto; padding: 0 16px 40px; }
.paper { background: #fff; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,.08); overflow: hidden; }
.report-pad { padding: 32px 40px 36px; }
.official-hdr { text-align: center; padding-bottom: 18px; margin-bottom: 22px; border-bottom: 2px solid #1e4620; }
.official-hdr img { width: 48px; height: 48px; object-fit: contain; vertical-align: middle; margin-right: 14px; }
.official-hdr-inner { display: inline-flex; align-items: center; gap: 14px; margin-bottom: 10px; }
.official-hdr-text { text-align: left; }
.gov-label { font-size: 8px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #4a6e4b; margin-bottom: 2px; }
.sys-name { font-size: 17px; font-weight: 900; color: #1e4620; line-height: 1.15; }
.min-label { font-size: 8px; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; color: #6b8f6c; margin-top: 2px; }
.report-title { display: inline-block; background: #1e4620; color: #fff; font-size: 11px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; padding: 4px 20px; border-radius: 4px; }
.section-label { font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #1e4620; margin-bottom: 9px; display: flex; align-items: center; gap: 5px; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px 24px; background: #f8fdf5; border: 1px solid #d1e8d2; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px; font-size: 11px; }
.info-lbl { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #6b8f6c; display: block; margin-bottom: 2px; }
.info-val { font-weight: 600; color: #111; }
.tx-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 22px; }
.tx-table thead tr { background: #1e4620; color: #fff; }
.tx-table thead th { padding: 7px 10px; font-size: 8.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.tx-table thead th:first-child { text-align: left; }
.tx-table thead th:nth-child(2) { text-align: center; }
.tx-table thead th:last-child { text-align: right; }
.tx-table tbody td { padding: 7px 10px; border-bottom: 1px solid #e8f3e8; }
.tx-table tbody td:nth-child(2) { text-align: center; }
.tx-table tbody td:last-child { text-align: right; font-weight: 600; }
.tx-table tfoot td { padding: 9px 10px; font-weight: 900; font-size: 12px; color: #1e4620; background: #f0f7eb; border-top: 2px solid #1e4620; }
.tx-table tfoot td:nth-child(2) { text-align: center; font-size: 13px; }
.tx-table tfoot td:last-child { text-align: right; font-size: 13px; }
.recon-box { border: 1px solid #d1e8d2; border-radius: 8px; overflow: hidden; margin-bottom: 28px; font-size: 11px; }
.recon-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 14px; }
.recon-row + .recon-row { border-top: 1px solid #e8f3e8; }
.recon-row.total { background: #1e4620; }
.recon-row.total span:first-child { color: #a7d9a8; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: .07em; }
.recon-row.total span:last-child { color: #fff; font-weight: 900; font-size: 14px; }
.recon-row.blank span:last-child { color: #d1d5db; font-style: italic; }
.sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 24px; font-size: 10.5px; }
.sig-block .sig-line { border-top: 1px solid #374151; padding-top: 6px; font-weight: 700; color: #111; margin-bottom: 10px; }
.sig-block .sig-field { color: #4b5563; line-height: 2.2; }
.report-footer { border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 8px; color: #9ca3af; line-height: 1.8; }
@media print {
  body { background: #fff; }
  .toolbar { display: none !important; }
  .wrapper { max-width: 100%; margin: 0; padding: 0; }
  .paper { border-radius: 0; box-shadow: none; }
  @page { size: A4 portrait; margin: 15mm; }
}
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="toolbar no-print">
  <div style="display:flex;align-items:center;gap:8px;">
    <img src="<?= url('assets/img/coat-of-arms.png') ?>" style="width:24px;height:24px;object-fit:contain;" alt="">
    <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,.7);">Shift Report &mdash; <?= htmlspecialchars($shiftId) ?></span>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="<?= url('views/pay-in/pos-shift-history.php') ?>" class="tb-back">&#8592; Back to History</a>
    <button onclick="window.print()" class="tb-print">&#128438; Print</button>
  </div>
</div>

<div class="wrapper">
  <div class="paper">
    <div class="report-pad">

      <!-- Official header -->
      <div class="official-hdr">
        <div class="official-hdr-inner">
          <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms">
          <div class="official-hdr-text">
            <div class="gov-label">Government of Belize</div>
            <div class="sys-name">Treasury Revenue System</div>
            <div class="min-label">Ministry of Finance &amp; Economic Development</div>
          </div>
        </div>
        <div><span class="report-title">End of Shift Report</span></div>
      </div>

      <!-- Shift identity -->
      <div class="section-label">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Shift Details
      </div>
      <div class="info-grid">
        <div><span class="info-lbl">Shift ID</span><span class="info-val" style="font-family:monospace;color:#1e4620;"><?= htmlspecialchars($shiftId) ?></span></div>
        <div><span class="info-lbl">Date</span><span class="info-val"><?= date('d M Y') ?></span></div>
        <div><span class="info-lbl">Cashier</span><span class="info-val"><?= htmlspecialchars($cashierName) ?></span></div>
        <div><span class="info-lbl">Branch</span><span class="info-val"><?= htmlspecialchars($branchName) ?></span></div>
        <div><span class="info-lbl">Terminal</span><span class="info-val"><?= htmlspecialchars($terminalName) ?></span></div>
        <div><span class="info-lbl">Duration</span><span class="info-val"><?= shiftDuration($startedAt, $endedAt) ?></span></div>
        <div><span class="info-lbl">Shift Opened</span><span class="info-val"><?= shiftFmtDT($startedAt) ?></span></div>
        <div><span class="info-lbl">Shift Closed</span><span class="info-val"><?= $endedAt ? shiftFmtDT($endedAt) : '<em style="color:#9ca3af;">Still open</em>' ?></span></div>
      </div>

      <!-- Transaction summary -->
      <div class="section-label">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Transaction Summary
      </div>
      <table class="tx-table">
        <thead>
          <tr>
            <th>Payment Method</th>
            <th>Transactions</th>
            <th>Amount (BZD)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($breakdown)): ?>
          <tr><td colspan="3" style="text-align:center;padding:14px;color:#9ca3af;font-style:italic;">No transactions recorded this shift.</td></tr>
          <?php else: ?>
          <?php foreach ($breakdown as $i => $b): ?>
          <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fdf5' ?>;">
            <td><?= htmlspecialchars(shiftFmtMethod($b['payment_method'])) ?></td>
            <td style="text-align:center;"><?= (int)$b['cnt'] ?></td>
            <td style="text-align:right;font-weight:600;">BZD $<?= number_format((float)$b['total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td style="text-transform:uppercase;letter-spacing:.05em;">Total</td>
            <td style="text-align:center;"><?= $txCount ?></td>
            <td style="text-align:right;">BZD $<?= number_format($totalAmount, 2) ?></td>
          </tr>
        </tfoot>
      </table>

      <!-- Cash reconciliation -->
      <div class="section-label">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Cash Drawer Reconciliation
      </div>
      <div class="recon-box" style="margin-bottom:28px;">
        <div class="recon-row"><span style="color:#4b5563;">Starting Cash Float</span><span style="font-weight:600;">BZD $<?= number_format((float)$shift['starting_cash'], 2) ?></span></div>
        <div class="recon-row"><span style="color:#4b5563;">Cash Collected This Shift</span><span style="font-weight:600;color:#15803d;">BZD $<?= number_format($cashCollected, 2) ?></span></div>
        <div class="recon-row total"><span>Expected Drawer</span><span>BZD $<?= number_format($expectedDrawer, 2) ?></span></div>
        <div class="recon-row blank"><span style="color:#4b5563;">Actual Cash Count</span><span>_______________</span></div>
        <div class="recon-row blank"><span style="color:#4b5563;">Over / (Short)</span><span>_______________</span></div>
      </div>

      <!-- Signatures -->
      <div class="sig-grid">
        <div class="sig-block">
          <div class="sig-line">Cashier Signature</div>
          <div class="sig-field">
            Name: ________________________________<br>
            Date: _________________________________
          </div>
        </div>
        <div class="sig-block">
          <div class="sig-line">Supervisor Signature</div>
          <div class="sig-field">
            Name: ________________________________<br>
            Date: _________________________________
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="report-footer">
        Generated <?= date('d M Y  h:i A') ?> &nbsp;|&nbsp; Treasury Revenue System &nbsp;|&nbsp;
        For enquiries: <strong>treasury@belize.gov.bz</strong> &nbsp;|&nbsp; Tel: +501 822-2362
      </div>

    </div>
  </div>
</div>

</body>
</html>
