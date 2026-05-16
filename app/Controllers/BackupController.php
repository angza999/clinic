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
            'smart_exam_presets',
            'running_numbers',
            'audit_logs',
        ];

        $retentionLimit = 30;
        $filename = 'clinic_backup_' . date('Ymd_His') . '.sql';
        $fullPath = storage_path('exports/' . $filename);
        $exportDirectory = dirname($fullPath);
        $pdo = db();

        if (!is_dir($exportDirectory)) {
            mkdir($exportDirectory, 0777, true);
        }

        $dailyClose = $pdo->query(
            'SELECT COUNT(*) AS receipt_count,
                    COALESCE(SUM(total_amount), 0) AS total_amount
             FROM payments
             WHERE DATE(paid_at) = CURDATE()
               AND payment_status = "PAID"'
        )->fetch();

        $pendingWorkCount = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM queue_entries
             WHERE queue_date = CURDATE()
               AND status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")'
        )->fetchColumn();

        $sqlDump = "-- Dong Mahawan Clinic backup" . PHP_EOL;
        $sqlDump .= "-- Generated at: " . date('Y-m-d H:i:s') . PHP_EOL;
        $sqlDump .= "-- Daily close date: " . date('Y-m-d') . PHP_EOL;
        $sqlDump .= "-- Today receipt count: " . (string) ($dailyClose['receipt_count'] ?? 0) . PHP_EOL;
        $sqlDump .= "-- Today paid total: " . (string) ($dailyClose['total_amount'] ?? 0) . PHP_EOL;
        $sqlDump .= "-- Pending queue/payment work: " . (string) $pendingWorkCount . PHP_EOL;
        $sqlDump .= "-- Backup retention: keep latest " . (string) $retentionLimit . " clinic_backup_*.sql files" . PHP_EOL;
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
        $this->cleanupOldBackups($exportDirectory, $retentionLimit);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($fullPath);
        exit;
    }

    private function cleanupOldBackups(string $exportDirectory, int $retentionLimit): void
    {
        $files = glob(rtrim($exportDirectory, '/\\') . '/clinic_backup_*.sql');

        if (!$files || count($files) <= $retentionLimit) {
            return;
        }

        usort(
            $files,
            static fn(string $left, string $right): int => (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0)
        );

        foreach (array_slice($files, $retentionLimit) as $oldBackup) {
            if (is_file($oldBackup)) {
                unlink($oldBackup);
            }
        }
    }
}
