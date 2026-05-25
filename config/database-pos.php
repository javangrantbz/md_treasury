<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';

$dsn_pos = "mysql:host=" . ENV_POS_DB_HOST . ";dbname=" . ENV_POS_DB_NAME . ";charset=utf8mb4";

$pdo_pos = new PDO($dsn_pos, ENV_POS_DB_USER, ENV_POS_DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
