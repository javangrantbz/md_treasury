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

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$stmt = $pdo->prepare("
    INSERT INTO customers (
        uuid,
        customer_type,
        customer_name,
        first_name,
        last_name,
        organization_name,
        email,
        phone,
        tax_id,
        address_line_1,
        address_line_2,
        district,
        country,
        notes,
        status,
        created_by,
        updated_by
    ) VALUES (
        :uuid,
        :customer_type,
        :customer_name,
        :first_name,
        :last_name,
        :organization_name,
        :email,
        :phone,
        :tax_id,
        :address_line_1,
        :address_line_2,
        :district,
        :country,
        :notes,
        :status,
        :created_by,
        :updated_by
    )
");

$stmt->execute([
    'uuid'              => $uuid,
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
    'created_by'        => $user['id'] ?? null,
    'updated_by'        => $user['id'] ?? null,
]);

$newCustId = (int)$pdo->lastInsertId();
AuditLog::log($pdo, 'create', 'customer', $newCustId, 'Customer created.');
apiResponse([
    'success' => true,
    'message' => 'Customer created successfully.',
    'id'      => $newCustId
], 201);
