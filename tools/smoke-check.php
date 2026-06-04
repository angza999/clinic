<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$checks = [];

$add = static function (string $name, bool $ok, string $message) use (&$checks): void {
    $checks[] = [
        'name' => $name,
        'ok' => $ok,
        'message' => $message,
    ];
};

try {
    $pdo = db();
    $add('database', true, 'Database connection OK');

    $requiredTables = [
        'users',
        'patients',
        'visits',
        'queue_entries',
        'services',
        'inventory_items',
        'inventory_batches',
        'stock_movements',
        'payments',
        'audit_logs',
        'backup_logs',
    ];
    $existingTables = array_map(
        static fn(array $row): string => array_values($row)[0],
        $pdo->query('SHOW TABLES')->fetchAll()
    );
    $missing = array_values(array_diff($requiredTables, $existingTables));
    $add('schema', $missing === [], $missing === [] ? 'Required tables OK' : 'Missing tables: ' . implode(', ', $missing));
} catch (Throwable $throwable) {
    $add('database', false, $throwable->getMessage());
}

$paths = [
    'storage/exports' => storage_path('exports'),
    'storage/imports' => storage_path('imports'),
    'storage/patient-photos' => storage_path('patient-photos'),
];

foreach ($paths as $label => $path) {
    $add($label, is_dir($path) && is_writable($path), is_dir($path) ? 'Directory exists' : 'Directory missing');
}

$bridgePayload = @file_get_contents('http://127.0.0.1:8189/health');
$bridgeData = $bridgePayload ? json_decode($bridgePayload, true) : null;
$add(
    'smart-card-bridge',
    is_array($bridgeData) && !empty($bridgeData['ok']),
    is_array($bridgeData) ? 'Bridge responded' : 'Bridge not reachable'
);

$failed = array_filter($checks, static fn(array $check): bool => !$check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[WARN] ') . $check['name'] . ' - ' . $check['message'] . PHP_EOL;
}

exit($failed ? 1 : 0);
