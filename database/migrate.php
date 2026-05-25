<?php
declare(strict_types=1);

/**
 * Treasury Revenue System — Database Migration Runner
 * 
 * Usage: php database/migrate.php
 */

require_once __DIR__ . '/../config/database.php';

// 1. Create migrations table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. Identify migration files
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');

if ($files === false) {
    echo "No migration files found.\n";
    exit;
}

sort($files); // Ensure they run in order (001, 002, etc.)

$appliedCount = 0;

echo "--- TRS Migration Runner ---\n";

foreach ($files as $file) {
    $name = basename($file);
    
    // Check if already applied
    $stmt = $pdo->prepare("SELECT id FROM migrations WHERE migration_name = :name");
    $stmt->execute(['name' => $name]);
    
    if ($stmt->fetch()) {
        continue;
    }
    
    echo "Applying: $name... ";
    
    $sql = file_get_contents($file);
    
    try {
        // DDL statements in MySQL/MariaDB cause implicit commits, 
        // so transactions are not effective for schema changes.
        $pdo->exec($sql);
        
        $pdo->prepare("INSERT INTO migrations (migration_name) VALUES (:name)")
            ->execute(['name' => $name]);
            
        echo "OK\n";
        $appliedCount++;
    } catch (Throwable $e) {
        echo "FAILED\n";
        echo "Error in $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($appliedCount === 0) {
    echo "Database is already up to date.\n";
} else {
    echo "Successfully applied $appliedCount migration(s).\n";
}
echo "---------------------------\n";
