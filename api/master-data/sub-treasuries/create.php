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

$departmentId = (int)($data['department_id'] ?? 0);
$name = trim((string)($data['sub_treasury_name'] ?? ''));
$district = trim((string)($data['district'] ?? ''));
$address = trim((string)($data['address_line'] ?? ''));
$phone = trim((string)($data['contact_phone'] ?? ''));
$email = trim((string)($data['contact_email'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

$code = uniqid();


if ($departmentId <= 0 || $code === '' || $name === '') {
    apiResponse([
        'success' => false,
        'message' => 'Department, code, and name are required.'
    ], 422);
}

$deptCheck = $pdo->prepare("SELECT id FROM departments WHERE id = :id LIMIT 1");
$deptCheck->execute(['id' => $departmentId]);

if (!$deptCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Selected department does not exist.'
    ], 422);
}

$dupCheck = $pdo->prepare("SELECT id FROM sub_treasuries WHERE sub_treasury_code = :code LIMIT 1");
$dupCheck->execute(['code' => $code]);

if ($dupCheck->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Sub-treasury code already exists.'
    ], 409);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse([
        'success' => false,
        'message' => 'Contact email is invalid.'
    ], 422);
}

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$stmt = $pdo->prepare("
    INSERT INTO sub_treasuries (
        uuid, department_id, sub_treasury_code, sub_treasury_name,
        district, address_line, contact_phone, contact_email,
        is_active, created_by, updated_by
    ) VALUES (
        :uuid, :department_id, :sub_treasury_code, :sub_treasury_name,
        :district, :address_line, :contact_phone, :contact_email,
        :is_active, :created_by, :updated_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'department_id' => $departmentId,
    'sub_treasury_code' => $code,
    'sub_treasury_name' => $name,
    'district' => $district !== '' ? $district : null,
    'address_line' => $address !== '' ? $address : null,
    'contact_phone' => $phone !== '' ? $phone : null,
    'contact_email' => $email !== '' ? $email : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'created_by' => $user['id'] ?? null,
    'updated_by' => $user['id'] ?? null,
]);

$newSubId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'sub_treasury', $newSubId, 'Sub-treasury created.');
apiResponse([
    'success' => true,
    'message' => 'Sub-treasury created successfully.',
    'id' => $newSubId
], 201);