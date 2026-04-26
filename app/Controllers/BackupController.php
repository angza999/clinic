<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class BackupController extends Controller
{
    public function download(): void
    {
        require_roles(['ADMIN']);

        $tables = [
            'roles',
            'users',
            'patients',
            'visits',
            'queue_entries',
            'visit_vitals',
            'services',
            'visit_services',
            'inventory_items',
            'inventory_batches',
            'stock_movements',
            'visit_item_usages',
            'payments',
            'appointments',
            'system_settings',
            'running_numbers',
            'audit_logs',
        ];

        $filename = 'clinic_backup_' . date('Ymd_His') . '.sql';
        $fullPath = storage_path('exports/' . $filename);
        $pdo = db();

        $sqlDump = "-- Backup generated at " . date('Y-m-d H:i:s') . PHP_EOL;
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL . PHP_EOL;

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
            if (!$create) {
                continue;
            }

            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;" . PHP_EOL;
            $sqlDump .= $create['Create Table'] . ';' . PHP_EOL . PHP_EOL;

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll();
            foreach ($rows as $row) {
                $columns = array_map(static fn(string $column): string => "`{$column}`", array_keys($row));
                $values = array_map(static fn(mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
                $sqlDump .= "INSERT INTO `{$table}` (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');' . PHP_EOL;
            }

            $sqlDump .= PHP_EOL;
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;

        file_put_contents($fullPath, $sqlDump);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($fullPath);
        exit;
    }
}
