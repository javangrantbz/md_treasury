<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid cost center id.'], 422);
}

$stmt = $pdo->prepare("
    SELECT
        cc.*,
        d.name AS department_name,
        st.sub_treasury_name
    FROM cost_centers cc
    LEFT JOIN departments d ON d.id = cc.department_id
    LEFT JOIN sub_treasuries st ON st.id = cc.sub_treasury_id
    WHERE cc.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Cost center not found.'], 404);
}

apiResponse(['success' => true, 'data' => $row]);