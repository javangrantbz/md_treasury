<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse([
        'success' => false,
        'message' => 'Invalid supplier id.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT
        id,
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
        created_at,
        updated_at
    FROM suppliers
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse([
        'success' => false,
        'message' => 'Supplier not found.'
    ], 404);
}

apiResponse([
    'success' => true,
    'data' => $row
]);