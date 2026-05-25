<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid activity id.'], 422);
}

$stmt = $pdo->prepare("
    SELECT
        cca.*,
        cc.code AS cost_center_code,
        cc.name AS cost_center_name
    FROM cost_center_activities cca
    INNER JOIN cost_centers cc ON cc.id = cca.cost_center_id
    WHERE cca.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Activity not found.'], 404);
}

apiResponse(['success' => true, 'data' => $row]);