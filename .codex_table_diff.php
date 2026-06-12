<?php
$sql = file_get_contents(__DIR__ . '/database/migrations/live_db.sql');
preg_match_all('/CREATE TABLE `([^`]+)`/i', $sql, $m);
$live = array_values(array_unique($m[1]));
sort($live);
require __DIR__ . '/config/env.php';
$pdo = new PDO('mysql:host=' . ENV_DB_HOST . ';dbname=' . ENV_DB_NAME . ';charset=utf8mb4', ENV_DB_USER, ENV_DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_NUM]);
$localRows = $pdo->query('SHOW TABLES')->fetchAll();
$local = array_map(fn($r) => $r[0], $localRows);
sort($local);
$missingLocal = array_values(array_diff($live, $local));
$missingLive = array_values(array_diff($local, $live));
echo 'LIVE_TABLE_COUNT=' . count($live) . PHP_EOL;
echo 'LOCAL_TABLE_COUNT=' . count($local) . PHP_EOL;
echo 'MISSING_FROM_LOCAL=' . count($missingLocal) . PHP_EOL;
foreach ($missingLocal as $t) { echo 'LOCAL_MISSING: ' . $t . PHP_EOL; }
echo 'MISSING_FROM_LIVE=' . count($missingLive) . PHP_EOL;
foreach ($missingLive as $t) { echo 'LIVE_MISSING: ' . $t . PHP_EOL; }
