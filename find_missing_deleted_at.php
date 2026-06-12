<?php
require_once __DIR__ . '/config/database.php';
$tabs = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($tabs as $t) {
    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('deleted_at', $cols)) {
        echo "$t\n";
    }
}
