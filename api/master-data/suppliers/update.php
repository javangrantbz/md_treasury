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

$id = (int)($data['id'] ?? 0);
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

if ($id <= 0 || $supplierName === '') {
    apiResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 422);
}

$exists = $pdo->prepare("SELECT id FROM suppliers WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);

if (!$exists->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Supplier not found.'
    ], 404);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse([
        'success' => false,
        'message' => 'Email is invalid.'
    ], 422);
}

$dup = $pdo->prepare("
    SELECT id
    FROM suppliers
    WHERE supplier_name = :supplier_name
      AND id <> :id
    LIMIT 1
");
$dup->execute([
    'supplier_name' => $supplierName,
    'id' => $id
]);

if ($dup->fetch()) {
    apiResponse([
        'success' => false,
        'message' => 'Another supplier already uses that name.'
    ], 409);
}

$stmt = $pdo->prepare("
    UPDATE suppliers
    SET
        supplier_name = :supplier_name,
        contact_name = :contact_name,
        tax_id = :tax_id,
        email = :email,
        phone = :phone,
        address_line_1 = :address_line_1,
        address_line_2 = :address_line_2,
        district = :district,
        country = :country,
        notes = :notes,
        is_active = :is_active,
        updated_by = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id' => $id,
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
    'updated_by' => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'supplier', $id, 'Supplier #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Supplier updated successfully.'
]);