<?php
require __DIR__ . '/config/env.php';
$pdo = new PDO('mysql:host=' . ENV_DB_HOST . ';dbname=' . ENV_DB_NAME . ';charset=utf8mb4', ENV_DB_USER, ENV_DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach (['pos_shifts','pos_transactions','pos_cart_items'] as $table) {
  echo "TABLE=$table\n";
  $rows = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll();
  foreach ($rows as $row) echo $row['Field'], "\n";
}
