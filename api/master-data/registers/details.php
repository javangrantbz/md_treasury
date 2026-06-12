<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid register id.'], 422);
}

$regStmt = $pdo->prepare("
    SELECT
        r.*,
        d.name AS department_name,
        st.sub_treasury_name
    FROM registers r
    INNER JOIN departments d ON d.id = r.department_id AND d.deleted_at IS NULL
    INNER JOIN sub_treasuries st ON st.id = r.sub_treasury_id AND st.deleted_at IS NULL
    WHERE r.id = :id AND r.deleted_at IS NULL
    LIMIT 1
");
$regStmt->execute(['id' => $id]);
$register = $regStmt->fetch(PDO::FETCH_ASSOC);

if (!$register) {
    apiResponse(['success' => false, 'message' => 'Register not found.'], 404);
}

$bankStmt = $pdo->prepare("
    SELECT
        rba.id,
        rba.register_id,
        rba.bank_account_id,
        rba.is_default,
        rba.is_active,
        ba.bank_name,
        ba.account_name,
        ba.account_number,
        ba.account_masked,
        ba.currency_code
    FROM register_bank_accounts rba
    INNER JOIN bank_accounts ba ON ba.id = rba.bank_account_id
    WHERE rba.register_id = :id
      AND rba.is_active = 1
      AND rba.deleted_at IS NULL
      AND ba.deleted_at IS NULL
    ORDER BY ba.bank_name ASC, ba.account_name ASC
");
$bankStmt->execute(['id' => $id]);
$bankAccounts = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        first_name,
        last_name,
        department_id,
        sub_treasury_id,
        register_id,
        status
    FROM users
    WHERE register_id = :id AND deleted_at IS NULL
    ORDER BY first_name ASC, last_name ASC
");
$userStmt->execute(['id' => $id]);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

apiResponse([
    'success' => true,
    'data' => [
        'register' => $register,
        'bank_accounts' => $bankAccounts,
        'users' => $users
    ]
]);