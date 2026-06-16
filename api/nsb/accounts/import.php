<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requirePost();

/**
 * Bulk import of opening balances from the client's list.
 * Expects JSON: { "rows": [ { customer_name, starting_balance, starting_date, account_number? }, ... ] }
 * Each row without an account_number is auto-assigned one (NSB000001…).
 */
function nsb_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

// Day-first aware date parser (DD/MM/YYYY is Belize convention). Returns 'Y-m-d' or null.
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

try {
    $in   = nsb_input();
    $rows = isset($in['rows']) && is_array($in['rows']) ? $in['rows'] : [];

    if (empty($rows)) {
        apiResponse(['success' => false, 'message' => 'No rows to import.'], 400);
    }

    $uid = Auth::userId();

    // Seed the running counter once, then increment in memory to avoid a query per row.
    $seq = (int)$pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(account_number, 4) AS UNSIGNED)), 0)
        FROM nsb_accounts
        WHERE account_number LIKE 'NSB%'
    ")->fetchColumn();

    $insert = $pdo->prepare("
        INSERT INTO nsb_accounts
            (account_number, customer_name, starting_balance, current_balance,
             starting_date, status, branch_id, remarks, created_by)
        VALUES (:acct, :name, :sb, :sb2, :sd, 'active', NULL, :remarks, :uid)
    ");

    $imported = 0;
    $errors   = [];

    $pdo->beginTransaction();
    foreach ($rows as $i => $r) {
        $line = $i + 1;
        $name = trim((string)($r['customer_name'] ?? ''));
        $sdIn = trim((string)($r['starting_date'] ?? ''));
        $bal  = (float)($r['starting_balance'] ?? 0);
        $acct = trim((string)($r['account_number'] ?? ''));
        $rem  = trim((string)($r['remarks'] ?? ''));

        if ($name === '') { $errors[] = "Row $line: missing customer name."; continue; }
        if ($sdIn === '') { $errors[] = "Row $line: missing starting date."; continue; }
        $sd = nsb_parse_date($sdIn);
        if ($sd === null) { $errors[] = "Row $line: invalid starting date '$sdIn'."; continue; }
        if ($bal < 0) { $errors[] = "Row $line: negative starting balance."; continue; }

        if ($acct === '') {
            $seq++;
            $acct = 'NSB' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
        }

        try {
            $insert->execute([
                'acct'    => $acct,
                'name'    => $name,
                'sb'      => $bal,
                'sb2'     => $bal,
                'sd'      => $sd,
                'remarks' => $rem !== '' ? $rem : null,
                'uid'     => $uid,
            ]);
            $imported++;
        } catch (Throwable $e) {
            $errors[] = "Row $line ($name): " . $e->getMessage();
        }
    }
    $pdo->commit();

    apiResponse([
        'success'  => true,
        'message'  => "Imported $imported account(s)." . (count($errors) ? ' ' . count($errors) . ' skipped.' : ''),
        'imported' => $imported,
        'skipped'  => count($errors),
        'errors'   => $errors,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
