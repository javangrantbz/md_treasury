<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/Rbac.php';

Auth::requireAuth();
Rbac::require($pdo, 'master_data.customers.manage');
requirePost();

$data = requestData();
$user = Auth::user();

$id              = (int)($data['id'] ?? 0);
$customerType    = trim((string)($data['customer_type'] ?? 'individual'));
$firstName       = trim((string)($data['first_name'] ?? ''));
$lastName        = trim((string)($data['last_name'] ?? ''));
$organizationName = trim((string)($data['organization_name'] ?? ''));
$email           = trim((string)($data['email'] ?? ''));
$phone           = trim((string)($data['phone'] ?? ''));
$taxId           = trim((string)($data['tax_id'] ?? ''));
$addressLine1    = trim((string)($data['address_line_1'] ?? ''));
$addressLine2    = trim((string)($data['address_line_2'] ?? ''));
$district        = trim((string)($data['district'] ?? ''));
$country         = trim((string)($data['country'] ?? ''));
$notes           = trim((string)($data['notes'] ?? ''));
$status          = trim((string)($data['status'] ?? 'active'));

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid request.'], 422);
}

$exists = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
$exists->execute(['id' => $id]);
if (!$exists->fetch()) {
    apiResponse(['success' => false, 'message' => 'Customer not found.'], 404);
}

$allowedTypes = ['individual', 'organization'];
if (!in_array($customerType, $allowedTypes, true)) {
    $customerType = 'individual';
}

if ($customerType === 'individual' && ($firstName === '' || $lastName === '')) {
    apiResponse(['success' => false, 'message' => 'First name and last name are required for individual customers.'], 422);
}

if ($customerType === 'organization' && $organizationName === '') {
    apiResponse(['success' => false, 'message' => 'Organization name is required for organization customers.'], 422);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiResponse(['success' => false, 'message' => 'Email is invalid.'], 422);
}

$allowedStatus = ['active', 'inactive'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'active';
}

$customerName = $customerType === 'organization'
    ? $organizationName
    : trim($firstName . ' ' . $lastName);

$stmt = $pdo->prepare("
    UPDATE customers
    SET
        customer_type     = :customer_type,
        customer_name     = :customer_name,
        first_name        = :first_name,
        last_name         = :last_name,
        organization_name = :organization_name,
        email             = :email,
        phone             = :phone,
        tax_id            = :tax_id,
        address_line_1    = :address_line_1,
        address_line_2    = :address_line_2,
        district          = :district,
        country           = :country,
        notes             = :notes,
        status            = :status,
        updated_by        = :updated_by
    WHERE id = :id
");

$stmt->execute([
    'id'                => $id,
    'customer_type'     => $customerType,
    'customer_name'     => $customerName,
    'first_name'        => $firstName !== '' ? $firstName : null,
    'last_name'         => $lastName !== '' ? $lastName : null,
    'organization_name' => $organizationName !== '' ? $organizationName : null,
    'email'             => $email !== '' ? $email : null,
    'phone'             => $phone !== '' ? $phone : null,
    'tax_id'            => $taxId !== '' ? $taxId : null,
    'address_line_1'    => $addressLine1 !== '' ? $addressLine1 : null,
    'address_line_2'    => $addressLine2 !== '' ? $addressLine2 : null,
    'district'          => $district !== '' ? $district : null,
    'country'           => $country !== '' ? $country : null,
    'notes'             => $notes !== '' ? $notes : null,
    'status'            => $status,
    'updated_by'        => $user['id'] ?? null,
]);

AuditLog::log($pdo, 'update', 'customer', $id, 'Customer #' . $id . ' updated.');
apiResponse([
    'success' => true,
    'message' => 'Customer updated successfully.'
]);
