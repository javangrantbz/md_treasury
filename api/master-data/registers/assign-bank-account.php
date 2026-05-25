<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.registers.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$registerId = (int)($data['register_id'] ?? 0);
$bankAccountId = (int)($data['bank_account_id'] ?? 0);
$isDefault = isset($data['is_default']) ? (int)$data['is_default'] : 0;

if ($registerId <= 0 || $bankAccountId <= 0) {
    apiResponse(['success' => false, 'message' => 'Register and bank account are required.'], 422);
}

$reg = $pdo->prepare("SELECT id FROM registers WHERE id = :id LIMIT 1");
$reg->execute(['id' => $registerId]);
if (!$reg->fetch()) {
    apiResponse(['success' => false, 'message' => 'Register not found.'], 404);
}

$ba = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = :id LIMIT 1");
$ba->execute(['id' => $bankAccountId]);
if (!$ba->fetch()) {
    apiResponse(['success' => false, 'message' => 'Bank account not found.'], 404);
}

$dup = $pdo->prepare("
    SELECT id
    FROM register_bank_accounts
    WHERE register_id = :register_id
      AND bank_account_id = :bank_account_id
    LIMIT 1
");
$dup->execute([
    'register_id' => $registerId,
    'bank_account_id' => $bankAccountId,
]);
if ($dup->fetch()) {
    apiResponse(['success' => false, 'message' => 'This bank account is already assigned to the register.'], 409);
}

if ($isDefault === 1) {
    $pdo->prepare("
        UPDATE register_bank_accounts
        SET is_default = 0
        WHERE register_id = :register_id
    ")->execute(['register_id' => $registerId]);
}

$stmt = $pdo->prepare("
    INSERT INTO register_bank_accounts (
        register_id, bank_account_id, is_default, is_active, created_by
    ) VALUES (
        :register_id, :bank_account_id, :is_default, 1, :created_by
    )
");

$stmt->execute([
    'register_id' => $registerId,
    'bank_account_id' => $bankAccountId,
    'is_default' => $isDefault === 1 ? 1 : 0,
    'created_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'assign', 'register', $registerId, 'Bank account #' . $bankAccountId . ' assigned to register #' . $registerId . '.');
apiResponse([
    'success' => true,
    'message' => 'Bank account assigned successfully.'
]);