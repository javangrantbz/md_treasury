<?php
require_once __DIR__ . '/config/database.php';
$tables = ['department_bank_accounts', 'department_cost_centers', 'register_users', 'cost_center_bank_accounts'];
foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    $indexes = $pdo->query("SHOW INDEX FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $idx) {
        echo "Key: {$idx['Key_name']}, Column: {$idx['Column_name']}, Unique: " . ($idx['Non_unique'] ? '0' : '1') . "\n";
    }
    echo "\n";
}
