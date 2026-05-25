<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.suppliers.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$supplierName = trim((string)($data['supplier_name'] ?? ''));
$contactName = trim((string)($data['contact_name'] ?? ''));
$taxId = trim((string)($data['tax_id'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$address1 = trim((string)($data['address_line_1'] ?? ''));
$address2 = trim((string)($data['address_line_2'] ?? ''));
$district = trim((string)($data['district'] ?? ''));
$country = trim((string)($data['country'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

if ($supplierName === '') {
    apiResponse([
        'success' => false,
        'message' => 'Supplier name is required.'
    ], 422);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse([
        'success' => false,
        'message' => 'Email is invalid.'
    ], 422);
}

$dup = $pdo->prepare("SELECT id FROM suppliers WHERE supplier_name = :supplier_name LIMIT 1");
$dup->execute(['supplier_name' => $supplierName]);

if ($dup->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Supplier name already exists.'
    ], 409);
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
    INSERT INTO suppliers (
        uuid,
        supplier_name,
        contact_name,
        tax_id,
        email,
        phone,
        address_line_1,
        address_line_2,
        district,
        country,
        notes,
        is_active,
        created_by,
        updated_by
    ) VALUES (
        :uuid,
        :supplier_name,
        :contact_name,
        :tax_id,
        :email,
        :phone,
        :address_line_1,
        :address_line_2,
        :district,
        :country,
        :notes,
        :is_active,
        :created_by,
        :updated_by
    )
");

$stmt->execute([
    'uuid' => $uuid,
    'supplier_name' => $supplierName,
    'contact_name' => $contactName !== '' ? $contactName : null,
    'tax_id' => $taxId !== '' ? $taxId : null,
    'email' => $email !== '' ? $email : null,
    'phone' => $phone !== '' ? $phone : null,
    'address_line_1' => $address1 !== '' ? $address1 : null,
    'address_line_2' => $address2 !== '' ? $address2 : null,
    'district' => $district !== '' ? $district : null,
    'country' => $country !== '' ? $country : null,
    'notes' => $notes !== '' ? $notes : null,
    'is_active' => $isActive === 1 ? 1 : 0,
    'created_by' => $user['id'] ?? null,
    'updated_by' => $user['id'] ?? null,
]);

$newSuppId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'supplier', $newSuppId, 'Supplier created.');
apiResponse([
    'success' => true,
    'message' => 'Supplier created successfully.',
    'id' => $newSuppId
], 201);