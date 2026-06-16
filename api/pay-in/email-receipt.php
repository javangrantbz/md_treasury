<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Mailer.php';

Auth::requireAuth();
requirePost();

// Accept JSON body or form-encoded.
$input = [];
$raw = file_get_contents('php://input');
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $input = $decoded;
}
if (empty($input)) $input = $_POST;

$uuid  = isset($input['tx'])    ? trim((string)$input['tx'])    : '';
$email = isset($input['email']) ? trim((string)$input['email']) : '';

if ($uuid === '') {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Missing transaction reference.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    apiResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
}

// ---- Load the transaction snapshot ----
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
    apiResponse(['success' => false, 'message' => 'Transaction not found.'], 404);
}

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

function erMethod($m) {
    $map = ['cash'=>'Cash','check'=>'Cheque','bank_deposit'=>'Bank Deposit',
            'pos_terminal'=>'POS Terminal','online_transfer'=>'Online Transfer','e_invoicing'=>'E-Invoice'];
    return isset($map[$m]) ? $map[$m] : ($m !== '' ? $m : '--');
}
function erRef($it) {
    $pd = isset($it['payment_details']) ? $it['payment_details'] : [];
    if (is_string($pd) && $pd !== '') { $d = json_decode($pd, true); $pd = is_array($d) ? $d : []; }
    if (!is_array($pd)) $pd = [];
    if (!empty($pd['reference_number'])) return $pd['reference_number'];
    if (!empty($pd['reference']))        return $pd['reference'];
    return '';
}
function erOrder($m) {
    $o = ['cash'=>0,'check'=>1,'pos_terminal'=>2,'online_transfer'=>3,'e_invoicing'=>4,'bank_deposit'=>9];
    return isset($o[$m]) ? $o[$m] : 5;
}

usort($items, function ($a, $b) {
    return erOrder($a['payment_method'] ?? '') - erOrder($b['payment_method'] ?? '');
});

$total = 0.0;
$pending = false;
foreach ($items as $it) {
    $total += (float)($it['amount'] ?? 0);
    if (($it['payment_method'] ?? '') === 'bank_deposit') $pending = true;
}
if ($total <= 0 && !empty($tx['total_amount'])) $total = (float)$tx['total_amount'];

$payerName = trim((string)($tx['customer_name'] ?? ''));
if ($payerName === '' && !empty($tx['dept_name'])) $payerName = $tx['dept_name'];
if ($payerName === '') $payerName = 'Walk-in / Cash Customer';

$cashierName = trim((string)($tx['cashier_name'] ?? '')) ?: 'Unknown';
$dateTime    = !empty($tx['created_at']) ? date('d M Y  h:i:s A', strtotime($tx['created_at'])) : date('d M Y  h:i:s A');

// ---- Plain text body ----
$L = [];
$L[] = 'Government of Belize - Treasury Revenue System';
$L[] = 'OFFICIAL RECEIPT';
$L[] = '';
$L[] = 'Receipt No: ' . $uuid;
$L[] = 'Date/Time:  ' . $dateTime;
$L[] = 'Branch:     ' . $branchName;
$L[] = 'Cashier:    ' . $cashierName;
$L[] = 'Payer:      ' . $payerName;
$L[] = '';
$L[] = 'ITEMS';
$L[] = '-----';
$bankStarted = false;
$n = 0;
foreach ($items as $it) {
    $n++;
    $isBank = (($it['payment_method'] ?? '') === 'bank_deposit');
    if ($isBank && !$bankStarted) {
        $bankStarted = true;
        $L[] = '- - - - -  TEAR OFF / PRESENT AT BANK  - - - - -';
        $L[] = '';
    }
    $ref = erRef($it);
    $L[] = $n . '. ' . ($it['activity_name'] ?? '') . '  -  BZD $' . number_format((float)($it['amount'] ?? 0), 2);
    if (!empty($it['beneficiary_name'])) $L[] = '   Beneficiary: ' . $it['beneficiary_name'];
    $L[] = '   Payment: ' . erMethod($it['payment_method'] ?? '') . ($ref !== '' ? ' (Ref ' . $ref . ')' : '');
    if ($isBank) $L[] = '   Status: PENDING / NOT PAID';
    $L[] = '';
}
$L[] = 'TOTAL: BZD $' . number_format($total, 2);
$L[] = '';
if ($pending) {
    $L[] = 'NOTE: This receipt includes bank deposit item(s). Those payments are pending';
    $L[] = 'and are only confirmed once the referenced deposit is received by Treasury.';
    $L[] = '';
}
$L[] = 'Verify at treasury.gov.bz/verify  -  Ref: ' . $uuid;
$text = implode("\n", $L);

// ---- HTML body ----
$rowsHtml = '';
$bankStarted = false;
foreach ($items as $i => $it) {
    $isBank = (($it['payment_method'] ?? '') === 'bank_deposit');
    $ref = erRef($it);
    if ($isBank && !$bankStarted) {
        $bankStarted = true;
        $rowsHtml .= '<tr><td colspan="3" style="border-top:2px dashed #1e4620;background:#f8fdf5;text-align:center;padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e4620;letter-spacing:.1em;">&#9986; Bank Deposit &mdash; Present At Bank</td></tr>';
    }
    $bg = $isBank ? '#fbfcf8' : ($i % 2 === 0 ? '#ffffff' : '#f8fdf5');
    $rowsHtml .= '<tr style="background:' . $bg . ';">'
        . '<td style="padding:7px 10px;border-bottom:1px solid #e8f3e8;font-size:12px;color:#111;">'
        . '<div style="font-weight:600;">' . htmlspecialchars($it['activity_name'] ?? '') . '</div>'
        . (!empty($it['beneficiary_name']) ? '<div style="font-size:10px;color:#555;">Beneficiary: ' . htmlspecialchars($it['beneficiary_name']) . '</div>' : '')
        . '</td>'
        . '<td style="padding:7px 10px;border-bottom:1px solid #e8f3e8;font-size:11px;color:#111;text-align:center;">'
        . htmlspecialchars(erMethod($it['payment_method'] ?? ''))
        . ($ref !== '' ? '<div style="font-size:10px;font-family:monospace;color:#1d4ed8;">' . htmlspecialchars($ref) . '</div>' : '')
        . ($isBank ? '<div style="font-size:9px;font-weight:700;text-transform:uppercase;color:#991b1b;">Pending / Not Paid</div>' : '')
        . '</td>'
        . '<td style="padding:7px 10px;border-bottom:1px solid #e8f3e8;font-size:12px;font-weight:700;text-align:right;color:#111;">$' . number_format((float)($it['amount'] ?? 0), 2) . '</td>'
        . '</tr>';
}

$pendingHtml = $pending
    ? '<div style="margin:14px 0;padding:10px 12px;border:1px solid #f5c2c7;background:#fff5f5;color:#991b1b;border-radius:6px;font-size:11px;"><strong>Pending Payment / Not Paid.</strong> Bank deposit instructions were issued. Payment is confirmed only once the referenced deposit is received by Treasury.</div>'
    : '';

$html = '<!DOCTYPE html><html><body style="margin:0;background:#eef2e9;font-family:Arial,Helvetica,sans-serif;">'
    . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #d1e8d2;">'
    . '<div style="background:#1e4620;color:#fff;padding:18px 22px;text-align:center;">'
    . '<div style="font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:#a7d9a8;">Government of Belize</div>'
    . '<div style="font-size:17px;font-weight:800;">Treasury Revenue System</div>'
    . '<div style="margin-top:8px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;background:rgba(255,255,255,.12);display:inline-block;padding:3px 14px;border-radius:5px;">Official Receipt</div>'
    . '</div>'
    . '<div style="padding:18px 22px;">'
    . '<table style="width:100%;font-size:12px;color:#222;border-collapse:collapse;margin-bottom:12px;">'
    . '<tr><td style="padding:2px 0;color:#6b8f6c;width:90px;">Receipt No.</td><td style="padding:2px 0;font-family:monospace;font-weight:700;color:#1e4620;">' . htmlspecialchars($uuid) . '</td></tr>'
    . '<tr><td style="padding:2px 0;color:#6b8f6c;">Date/Time</td><td style="padding:2px 0;">' . htmlspecialchars($dateTime) . '</td></tr>'
    . '<tr><td style="padding:2px 0;color:#6b8f6c;">Branch</td><td style="padding:2px 0;">' . htmlspecialchars($branchName) . '</td></tr>'
    . '<tr><td style="padding:2px 0;color:#6b8f6c;">Cashier</td><td style="padding:2px 0;">' . htmlspecialchars($cashierName) . '</td></tr>'
    . '<tr><td style="padding:2px 0;color:#6b8f6c;">Payer</td><td style="padding:2px 0;">' . htmlspecialchars($payerName) . '</td></tr>'
    . '</table>'
    . $pendingHtml
    . '<table style="width:100%;border-collapse:collapse;border:1px solid #d1e8d2;border-radius:6px;overflow:hidden;">'
    . '<thead><tr style="background:#1e4620;color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:.06em;">'
    . '<th style="padding:6px 10px;text-align:left;">Service</th><th style="padding:6px 10px;text-align:center;">Payment</th><th style="padding:6px 10px;text-align:right;">Amount</th></tr></thead>'
    . '<tbody>' . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="3" style="padding:10px;text-align:center;color:#777;">No items.</td></tr>') . '</tbody>'
    . '</table>'
    . '<div style="text-align:right;margin-top:12px;"><span style="background:#1e4620;color:#fff;border-radius:6px;padding:8px 16px;font-size:16px;font-weight:800;">BZD $' . number_format($total, 2) . '</span></div>'
    . '<div style="margin-top:18px;border-top:1px solid #e8f3e8;padding-top:10px;font-size:9px;color:#888;text-align:center;">'
    . 'This is an official receipt issued by the Government of Belize, Treasury Department.<br>For enquiries: treasury@belize.gov.bz &middot; Tel: +501 822-2362'
    . '</div>'
    . '</div></div></body></html>';

$subject = 'Treasury Receipt ' . $uuid;

$error = null;
$sent = Mailer::send($email, $payerName, $subject, $html, $text, $error);

ob_end_clean();
if ($sent) {
    apiResponse(['success' => true, 'message' => 'Receipt emailed to ' . $email . '.']);
}

$payload = ['success' => false, 'message' => $error ?: 'Failed to send the email.'];
// Set define('SMTP_DEBUG', true) in env.php to surface the SMTP transcript while troubleshooting.
if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
    $payload['smtp_log'] = Mailer::$lastLog;
}
apiResponse($payload, 500);
