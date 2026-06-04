<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();
$migrationPath = __DIR__ . '/../database/production_readiness.sql';
$sql = file_get_contents($migrationPath);

if ($sql === false) {
    fwrite(STDERR, "Cannot read migration: {$migrationPath}\n");
    exit(1);
}

$pdo->exec($sql);

echo "Production readiness migration applied.\n";
