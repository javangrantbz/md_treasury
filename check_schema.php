<?php
require_once __DIR__ . '/config/database.php';

$tables = [
    'transaction_items',
    'register_users',
    'cost_center_bank_accounts',
    'department_bank_accounts',
    'department_cost_centers',
    'register_bank_accounts',
    'role_permissions',
    'user_roles',
    'user_notes',
    'ip_login_attempts',
    'pos_cart_items',
    'pos_service_favorites'
];

foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE `$table` text");
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "Field: {$col['Field']}, Type: {$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
