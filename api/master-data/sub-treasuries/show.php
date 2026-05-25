<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireAuth();
requireGet();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    apiResponse(['success' => false, 'message' => 'Invalid sub-treasury id.'], 422);
}

$stmt = $pdo->prepare("
    SELECT
        st.*,
        d.name AS department_name
    FROM sub_treasuries st
    INNER JOIN departments d ON d.id = st.department_id
    WHERE st.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiResponse(['success' => false, 'message' => 'Sub-treasury not found.'], 404);
}

apiResponse(['success' => true, 'data' => $row]);