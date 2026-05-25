<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.sub_treasuries.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$id = (int)($data['id'] ?? 0);
$departmentId = (int)($data['department_id'] ?? 0);
$code = trim((string)($data['sub_treasury_code'] ?? ''));
$name = trim((string)($data['sub_treasury_name'] ?? ''));
$district = trim((string)($data['district'] ?? ''));
$address = trim((string)($data['address_line'] ?? ''));
$phone = trim((string)($data['contact_phone'] ?? ''));
$email = trim((string)($data['contact_email'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($id <= 0 || $departmentId <= 0 || $code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$exists = $pdo->prepare("SELECT id FROM sub_treasuries WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Sub-treasury not found.'
    ], 404);
}

$deptCheck = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
$deptCheck->execute(['id' => $departmentId]);

if (!$deptCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Selected department does not exist.'
    ], 422);
}

$dupCheck = $pdo->prepare("
    SELECT id
    FROM sub_treasuries
    WHERE sub_treasury_code = :code
      AND id <> :id
    LIMIT 1
");
$dupCheck->execute([
    'code' => $code,
    'id' => $id,
]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Another sub-treasury already uses that code.'
    ], 409);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse([
        'success' => false,
        'message' => 'Contact email is invalid.'
    ], 422);
}

$stmt = $pdo->prepare("
    UPDATE sub_treasuries
    SET
        department_id = :department_id,
        sub_treasury_code = :sub_treasury_code,
        sub_treasury_name = :sub_treasury_name,
        district = :district,
        address_line = :address_line,
        contact_phone = :contact_phone,
        contact_email = :contact_email,
        is_active = :is_active,
        updated_by = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
    'department_id' => $departmentId,
    'sub_treasury_code' => $code,
    'sub_treasury_name' => $name,
    'district' => $district !== '' ? $district : null,
    'address_line' => $address !== '' ? $address : null,
    'contact_phone' => $phone !== '' ? $phone : null,
    'contact_email' => $email !== '' ? $email : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'updated_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'sub_treasury', $id, 'Sub-treasury #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Sub-treasury updated successfully.'
]);