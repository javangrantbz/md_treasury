<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
Auth::requireAuth();

$payInId = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($payInId === '') {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pay-in-list.php'));
    exit;
}

try {
    $s = $pdo->prepare("
        SELECT pi.*, CONCAT(u.first_name,' ',u.last_name) AS cashier_name
        FROM pay_ins pi
        LEFT JOIN users u ON u.id = pi.cashier_uid
        WHERE pi.pay_in_id = :pid
        LIMIT 1
    ");
    $s->execute(['pid' => $payInId]);
    $payIn = $s->fetch(PDO::FETCH_ASSOC);

    if (!$payIn) {
        ob_end_clean();
        header('Location: ' . url('views/pay-in/pay-in-list.php'));
        exit;
    }

    $s = $pdo->prepare("SELECT * FROM pay_in_cash WHERE pay_in_id = :pid LIMIT 1");
    $s->execute(['pid' => $payInId]);
    $cash = $s->fetch(PDO::FETCH_ASSOC);

    $s = $pdo->prepare("SELECT * FROM pay_in_cheques WHERE pay_in_id = :pid ORDER BY id ASC");
    $s->execute(['pid' => $payInId]);
    $cheques = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    ob_end_clean();
    header('Location: ' . url('views/pay-in/pay-in-list.php'));
    exit;
}

function piNumWords($amount) {
    $amount  = round((float)$amount, 2);
    $whole   = (int)floor($amount);
    $cents   = (int)round(($amount - $whole) * 100);
    $ones    = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                'Seventeen','Eighteen','Nineteen'];
    $tensArr = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

    $result = '';
    $n = $whole;

    if ($n >= 1000000) {
        $m = (int)floor($n / 1000000);
        $h = (int)floor($m / 100); $r = $m % 100;
        $t = '';
        if ($h) $t = $ones[$h] . ' Hundred';
        if ($r > 0 && $r < 20)  $t .= ($t ? ' ' : '') . $ones[$r];
        elseif ($r >= 20) { $t .= ($t ? ' ' : '') . $tensArr[(int)floor($r/10)]; if ($r%10) $t .= ' ' . $ones[$r%10]; }
        $result .= $t . ' Million';
        $n = $n % 1000000;
        if ($n) $result .= ' ';
    }
    if ($n >= 1000) {
        $m = (int)floor($n / 1000);
        $h = (int)floor($m / 100); $r = $m % 100;
        $t = '';
        if ($h) $t = $ones[$h] . ' Hundred';
        if ($r > 0 && $r < 20)  $t .= ($t ? ' ' : '') . $ones[$r];
        elseif ($r >= 20) { $t .= ($t ? ' ' : '') . $tensArr[(int)floor($r/10)]; if ($r%10) $t .= ' ' . $ones[$r%10]; }
        $result .= $t . ' Thousand';
        $n = $n % 1000;
        if ($n) $result .= ' ';
    }
    if ($n > 0) {
        $h = (int)floor($n / 100); $r = $n % 100;
        if ($h) $result .= $ones[$h] . ' Hundred';
        if ($r > 0 && $r < 20)  $result .= ($h ? ' ' : '') . $ones[$r];
        elseif ($r >= 20) {
            $result .= ($h ? ' ' : '') . $tensArr[(int)floor($r/10)];
            if ($r % 10) $result .= ' ' . $ones[$r % 10];
        }
    }

    if ($result === '') $result = 'Zero';
    $result .= ' Dollar' . ($whole !== 1 ? 's' : '');
    if ($cents > 0) {
        $cw = ($cents < 20) ? $ones[$cents]
            : $tensArr[(int)floor($cents/10)] . ($cents%10 ? ' '.$ones[$cents%10] : '');
        $result .= ' and ' . $cw . ' Cent' . ($cents !== 1 ? 's' : '');
    }
    return $result . ' Only';
}

function piStatusLabel($s) {
    if ($s === 'verified') return '<span style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;padding:2px 8px;border-radius:99px;">Verified</span>';
    if ($s === 'rejected') return '<span style="background:#fee2e2;color:#991b1b;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;padding:2px 8px;border-radius:99px;">Rejected</span>';
    return '<span style="background:#fef9c3;color:#854d0e;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;padding:2px 8px;border-radius:99px;">Submitted</span>';
}

function piFmtDate($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : htmlspecialchars($d);
}

// Denomination display rows (only non-zero)
$denomDefs = [
    ['d_100', 100.00, '$100.00'],
    ['d_50',   50.00,  '$50.00'],
    ['d_20',   20.00,  '$20.00'],
    ['d_10',   10.00,  '$10.00'],
    ['d_5',     5.00,   '$5.00'],
    ['d_2',     2.00,   '$2.00'],
    ['d_1',     1.00,   '$1.00'],
    ['c_50',    0.50,  '¢0.50'],
    ['c_25',    0.25,  '¢0.25'],
    ['c_10',    0.10,  '¢0.10'],
    ['c_5',     0.05,  '¢0.05'],
    ['c_1',     0.01,  '¢0.01'],
];
$nonZeroDenoms = [];
if ($cash) {
    foreach ($denomDefs as $dd) {
        $qty = isset($cash[$dd[0]]) ? (int)$cash[$dd[0]] : 0;
        if ($qty > 0) {
            $nonZeroDenoms[] = ['label' => $dd[2], 'qty' => $qty, 'unit' => $dd[1], 'sub' => round($qty * $dd[1], 2)];
        }
    }
}

$cashierName = trim($payIn['cashier_name']) ?: 'Unknown';
$deptName    = $payIn['department_name'] ?: 'Walk-in / Unspecified';
$totalAmtWords = piNumWords((float)$payIn['total_amount']);
$generatedAt   = date('d M Y  h:i A');

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay-In <?= htmlspecialchars($payInId) ?> — Treasury Revenue System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; margin: 0; background: #f1f5f9; color: #111; }
.toolbar { background: #1e4620; color: #fff; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.toolbar a, .toolbar button { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; padding: 5px 14px; border-radius: 5px; cursor: pointer; text-decoration: none; }
.tb-back   { color: rgba(255,255,255,.65); background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); }
.tb-back:hover { background: rgba(255,255,255,.18); color: #fff; }
.tb-new    { color: rgba(255,255,255,.65); background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); }
.tb-new:hover { background: rgba(255,255,255,.18); color: #fff; }
.tb-print  { background: #fff; color: #1e4620; border: none; }
.tb-print:hover { background: #e8f3e8; }
.wrapper   { max-width: 820px; margin: 24px auto; padding: 0 16px 40px; }
.paper     { background: #fff; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,.08); overflow: hidden; }
.rpad      { padding: 32px 40px 36px; }
.off-hdr   { text-align: center; padding-bottom: 18px; margin-bottom: 20px; border-bottom: 2px solid #1e4620; }
.off-inner { display: inline-flex; align-items: center; gap: 14px; margin-bottom: 10px; }
.off-title { text-align: left; }
.gov-lbl   { font-size: 8px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #4a6e4b; margin-bottom: 2px; }
.sys-nm    { font-size: 17px; font-weight: 900; color: #1e4620; line-height: 1.15; }
.min-lbl   { font-size: 8px; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; color: #6b8f6c; margin-top: 2px; }
.doc-badge { display: inline-block; background: #1e4620; color: #fff; font-size: 11px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; padding: 4px 20px; border-radius: 4px; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px 24px; background: #f8fdf5; border: 1px solid #d1e8d2; border-radius: 8px; padding: 14px 18px; margin-bottom: 18px; font-size: 11px; }
.info-lbl  { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #6b8f6c; display: block; margin-bottom: 2px; }
.info-val  { font-weight: 600; color: #111; }
.words-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px; font-size: 12px; color: #14532d; }
.words-lbl { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #16a34a; margin-bottom: 3px; }
.sec-lbl   { font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #1e4620; margin-bottom: 9px; display: flex; align-items: center; gap: 5px; }
.dtable    { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 18px; }
.dtable thead tr { background: #1e4620; color: #fff; }
.dtable thead th { padding: 7px 10px; font-size: 8.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.dtable thead th:first-child { text-align: left; }
.dtable thead th.tc { text-align: center; }
.dtable thead th.tr { text-align: right; }
.dtable tbody td { padding: 7px 10px; border-bottom: 1px solid #e8f3e8; font-size: 11px; }
.dtable tbody td.tc { text-align: center; }
.dtable tbody td.tr { text-align: right; font-weight: 600; }
.dtable tfoot td { padding: 9px 10px; font-weight: 900; background: #f0f7eb; border-top: 2px solid #1e4620; }
.dtable tfoot td.tr { text-align: right; font-size: 13px; color: #1e4620; }
.dtable tfoot td.tc { text-align: center; }
.grand-box { display: flex; justify-content: space-between; align-items: center; background: #1e4620; color: #fff; border-radius: 8px; padding: 14px 18px; margin-bottom: 28px; }
.grand-lbl { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #a7d9a8; }
.grand-val { font-size: 26px; font-weight: 900; color: #fff; }
.slip-box  { background: #f8fdf5; border: 1px solid #d1e8d2; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px; font-size: 11px; }
.notes-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 22px; font-size: 11px; color: #374151; }
.sig-grid  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 24px; font-size: 10.5px; }
.sig-block .sig-line { border-top: 1px solid #374151; padding-top: 6px; font-weight: 700; color: #111; margin-bottom: 10px; }
.sig-block .sig-field { color: #4b5563; line-height: 2.2; }
.rpt-footer { border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 8px; color: #9ca3af; line-height: 1.8; }
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
<div class="toolbar">
  <div style="display:flex;align-items:center;gap:10px;">
    <img src="<?= url('assets/img/coat-of-arms.png') ?>" style="width:24px;height:24px;object-fit:contain;" alt="">
    <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,.75);">Pay-In &mdash; <?= htmlspecialchars($payInId) ?></span>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="<?= url('views/pay-in/pay-in-list.php') ?>" class="tb-back">&#8592; History</a>
    <a href="<?= url('views/pay-in/pay-in-new.php') ?>" class="tb-new">+ New Pay-In</a>
    <button onclick="window.print()" class="tb-print">&#128438; Print</button>
  </div>
</div>

<div class="wrapper">
  <div class="paper">
    <div class="rpad">

      <!-- Official header -->
      <div class="off-hdr">
        <div class="off-inner">
          <img src="<?= url('assets/img/coat-of-arms.png') ?>" alt="Belize Coat of Arms" style="width:50px;height:50px;object-fit:contain;">
          <div class="off-title">
            <div class="gov-lbl">Government of Belize</div>
            <div class="sys-nm">Treasury Revenue System</div>
            <div class="min-lbl">Ministry of Finance &amp; Economic Development</div>
          </div>
        </div>
        <div><span class="doc-badge">Revenue Collector's Bank Pay-In Form</span></div>
      </div>

      <!-- Pay-in identity -->
      <div class="info-grid">
        <div>
          <span class="info-lbl">Pay-In ID</span>
          <span class="info-val" style="font-family:monospace;color:#1e4620;"><?= htmlspecialchars($payInId) ?></span>
        </div>
        <div>
          <span class="info-lbl">Date</span>
          <span class="info-val"><?= piFmtDate($payIn['pay_in_date']) ?></span>
        </div>
        <div>
          <span class="info-lbl">Department / Unit</span>
          <span class="info-val"><?= htmlspecialchars($deptName) ?></span>
        </div>
        <div>
          <span class="info-lbl">Submitted By</span>
          <span class="info-val"><?= htmlspecialchars($cashierName) ?></span>
        </div>
        <div>
          <span class="info-lbl">Status</span>
          <?= piStatusLabel($payIn['status']) ?>
        </div>
        <div>
          <span class="info-lbl">Submitted</span>
          <span class="info-val"><?= htmlspecialchars(isset($payIn['created_at']) ? date('d M Y  h:i A', strtotime($payIn['created_at'])) : '—') ?></span>
        </div>
      </div>

      <!-- Amount in words -->
      <div class="words-box">
        <div class="words-lbl">Please receive this sum of:</div>
        <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($totalAmtWords) ?></div>
      </div>

      <!-- Cash breakdown -->
      <?php if (!empty($nonZeroDenoms)): ?>
      <div class="sec-lbl">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Cash Breakdown
      </div>
      <table class="dtable">
        <thead>
          <tr>
            <th>Denomination</th>
            <th class="tc">Count</th>
            <th class="tr">Sub-total (BZD)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($nonZeroDenoms as $i => $dn): ?>
          <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fdf5' ?>;">
            <td class="fw-semibold"><?= htmlspecialchars($dn['label']) ?></td>
            <td class="tc"><?= (int)$dn['qty'] ?></td>
            <td class="tr">$<?= number_format($dn['sub'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Cash Total</td>
            <td class="tr">BZD $<?= number_format((float)$payIn['total_cash'], 2) ?></td>
          </tr>
        </tfoot>
      </table>
      <?php endif; ?>

      <!-- Cheques -->
      <?php if (!empty($cheques)): ?>
      <div class="sec-lbl">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        Cheques
      </div>
      <table class="dtable">
        <thead>
          <tr>
            <th>Bank</th>
            <th>Cheque Number</th>
            <th class="tr">Amount (BZD)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cheques as $i => $ch): ?>
          <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fdf5' ?>;">
            <td><?= htmlspecialchars($ch['bank_name']) ?></td>
            <td style="font-family:monospace;font-size:10.5px;"><?= htmlspecialchars($ch['cheque_number']) ?></td>
            <td class="tr">$<?= number_format((float)$ch['amount'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Cheques Total</td>
            <td class="tr">BZD $<?= number_format((float)$payIn['total_cheques'], 2) ?></td>
          </tr>
        </tfoot>
      </table>
      <?php endif; ?>

      <!-- Grand total -->
      <div class="grand-box">
        <div>
          <div class="grand-lbl">Grand Total</div>
          <?php if ((float)$payIn['total_cash'] > 0 && (float)$payIn['total_cheques'] > 0): ?>
          <div style="font-size:9.5px;color:rgba(255,255,255,.55);margin-top:3px;">
            Cash BZD $<?= number_format((float)$payIn['total_cash'], 2) ?>
            &nbsp;+&nbsp;
            Cheques BZD $<?= number_format((float)$payIn['total_cheques'], 2) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="grand-val">BZD $<?= number_format((float)$payIn['total_amount'], 2) ?></div>
      </div>

      <!-- Bank slip -->
      <div class="sec-lbl">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Bank Deposit Slip
      </div>
      <div class="slip-box" style="margin-bottom:22px;">
        <?php if ($payIn['bank_slip_path']): ?>
        <a href="<?= url($payIn['bank_slip_path']) ?>" target="_blank" style="color:#1e4620;font-weight:600;">
          <svg style="width:13px;height:13px;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <?= htmlspecialchars(basename($payIn['bank_slip_path'])) ?>
        </a>
        <span class="no-print" style="color:#6b7280;font-size:10px;margin-left:8px;">(click to open)</span>
        <?php else: ?>
        <span style="color:#9ca3af;font-style:italic;">No bank deposit slip attached.</span>
        <?php endif; ?>
      </div>

      <!-- Notes -->
      <?php if (!empty($payIn['notes'])): ?>
      <div class="sec-lbl">
        <svg style="width:11px;height:11px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="12" y2="17"/></svg>
        Notes
      </div>
      <div class="notes-box"><?= nl2br(htmlspecialchars($payIn['notes'])) ?></div>
      <?php endif; ?>

      <!-- Signatures -->
      <div class="sig-grid">
        <div class="sig-block">
          <div class="sig-line">Cashier</div>
          <div class="sig-field">
            Name: ______________________<br>
            Date: _______________________
          </div>
        </div>
        <div class="sig-block">
          <div class="sig-line">Supervisor</div>
          <div class="sig-field">
            Name: ______________________<br>
            Date: _______________________
          </div>
        </div>
        <div class="sig-block">
          <div class="sig-line">Verified By</div>
          <div class="sig-field">
            Name: ______________________<br>
            Date: _______________________
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="rpt-footer">
        Generated <?= $generatedAt ?> &nbsp;|&nbsp; Treasury Revenue System &nbsp;|&nbsp;
        For enquiries: <strong>treasury@belize.gov.bz</strong> &nbsp;|&nbsp; Tel: +501 822-2362
      </div>

    </div>
  </div>
</div>

</body>
</html>
