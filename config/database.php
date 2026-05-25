<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';

$dsn = "mysql:host=" . ENV_DB_HOST . ";dbname=" . ENV_DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, ENV_DB_USER, ENV_DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed.');
}
