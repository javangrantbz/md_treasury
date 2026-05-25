<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/Auth.php';
Auth::requireAuth();
requireGet();

$stmt = $pdo->query("
    SELECT
        id,
        customer_type,
        CASE
            WHEN customer_type = 'organization' THEN COALESCE(NULLIF(customer_name,''), organization_name)
            ELSE COALESCE(NULLIF(customer_name,''), CONCAT_WS(' ', first_name, last_name))
        END AS customer_name,
        first_name,
        last_name,
        organization_name,
        tax_id,
        email,
        phone,
        status
    FROM customers
    WHERE deleted_at IS NULL
    ORDER BY customer_name ASC
");
apiResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
