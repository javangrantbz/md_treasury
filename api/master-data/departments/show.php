<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid department id.'], 422);
}

$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.code,
        d.name,
        d.ministry_name,
        d.branch_id,
        d.status,
        d.short_name,
        d.description,
        d.created_at,
        b.name AS branch_name
    FROM departments d
    LEFT JOIN branches b ON b.id = d.branch_id
    WHERE d.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Department not found.'], 404);
}

apiResponse(['success' => true, 'data' => $row]);