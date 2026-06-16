<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requirePost();

// apiPost() sends a JSON body; fall back to form-encoded.
function nsb_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

/**
 * Parse a date that may be ISO, day-first (DD/MM/YYYY — Belize), or a few
 * common written forms. Returns 'Y-m-d' or null. Day-first is tried before
 * US month-first so 15/07/2021 reads as 15 July, not an invalid month.
 */
function nsb_parse_date(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'Y/m/d', 'j/n/Y', 'j-n-Y', 'd M Y', 'd F Y', 'M j, Y', 'F j, Y'];
    foreach ($formats as $f) {
        $dt = DateTime::createFromFormat('!' . $f, $s);
        if ($dt !== false) {
            $errs = DateTime::getLastErrors();
            if ($errs === false || (empty($errs['warning_count']) && empty($errs['error_count']))) {
                return $dt->format('Y-m-d');
            }
        }
    }
    $ts = strtotime($s);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

/**
 * Assign a sequential account number (NSB000001…) when one isn't supplied.
 */
function nsb_next_account_number(PDO $pdo): string
{
    $max = (int)$pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(account_number, 4) AS UNSIGNED)), 0)
        FROM nsb_accounts
        WHERE account_number LIKE 'NSB%'
    ")->fetchColumn();
    return 'NSB' . str_pad((string)($max + 1), 6, '0', STR_PAD_LEFT);
}

try {
    $in = nsb_input();

    $customerName   = trim((string)($in['customer_name'] ?? ''));
    $startingBal    = (float)($in['starting_balance'] ?? 0);
    $startingDateIn = trim((string)($in['starting_date'] ?? ''));
    $accountNumber  = trim((string)($in['account_number'] ?? ''));
    $branchId       = (int)($in['branch_id'] ?? 0);
    $remarks        = trim((string)($in['remarks'] ?? ''));

    if ($customerName === '') throw new Exception('Customer name is required.');
    if ($startingDateIn === '') throw new Exception('Starting date is required.');

    $startingDate = nsb_parse_date($startingDateIn);
    if ($startingDate === null) throw new Exception('Starting date is not a valid date (use YYYY-MM-DD or DD/MM/YYYY).');

    if ($startingBal < 0) throw new Exception('Starting balance cannot be negative.');

    if ($accountNumber === '') {
        $accountNumber = nsb_next_account_number($pdo);
    } else {
        $dupe = $pdo->prepare("SELECT id FROM nsb_accounts WHERE account_number = :a LIMIT 1");
        $dupe->execute(['a' => $accountNumber]);
        if ($dupe->fetch()) throw new Exception('Account number "' . $accountNumber . '" already exists.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO nsb_accounts
            (account_number, customer_name, starting_balance, current_balance,
             starting_date, status, branch_id, remarks, created_by)
        VALUES (:acct, :name, :sb, :sb2, :sd, 'active', :branch, :remarks, :uid)
    ");
    $stmt->execute([
        'acct'    => $accountNumber,
        'name'    => $customerName,
        'sb'      => $startingBal,
        'sb2'     => $startingBal,
        'sd'      => $startingDate,
        'branch'  => $branchId > 0 ? $branchId : null,
        'remarks' => $remarks !== '' ? $remarks : null,
        'uid'     => Auth::userId(),
    ]);

    apiResponse([
        'success'        => true,
        'message'        => 'Account created.',
        'id'             => $pdo->lastInsertId(),
        'account_number' => $accountNumber,
    ]);
} catch (Throwable $e) {
    apiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
