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
        'message' => 'Invalid expenditure type id.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT
        id,
        uuid,
        expenditure_code,
        expenditure_name,
        description,
        is_active,
        created_at,
        updated_at
    FROM expenditure_types
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse([
        'success' => false,
        'message' => 'Expenditure type not found.'
    ], 404);
}

apiResponse([
    'success' => true,
    'data' => $row
]);