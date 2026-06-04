<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

class ProductionController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN']);

        $readiness = $this->readiness();

        $this->render('production/index', [
            'pageTitle' => 'Production Readiness',
            'readiness' => $readiness,
            'pageStyles' => [app_url('assets/css/production.css')],
        ]);
    }

    public function smoke(): void
    {
        require_roles(['ADMIN']);

        $readiness = $this->readiness();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $readiness['summary']['failed'] === 0,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $readiness['summary'],
            'checks' => $readiness['checks'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function readiness(): array
    {
        $this->ensureSchema();

        $checks = array_merge(
            $this->databaseChecks(),
            $this->backupChecks(),
            $this->securityChecks(),
            $this->operationChecks(),
            $this->integrationChecks(),
            $this->privacyChecks()
        );

        $summary = [
            'passed' => 0,
            'warning' => 0,
            'failed' => 0,
        ];

        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'summary' => $summary,
            'checks' => $checks,
            'backup_logs' => $this->backupLogs(),
            'audit_logs' => $this->auditLogs(),
        ];
    }

    private function databaseChecks(): array
    {
        $checks = [];
        $requiredTables = [
            'roles',
            'users',
            'patients',
            'visits',
            'queue_entries',
            'visit_vitals',
            'services',
            'service_price_history',
            'visit_services',
            'inventory_items',
            'inventory_batches',
            'stock_movements',
            'visit_item_usages',
            'drug_profiles',
            'prescriptions',
            'prescription_items',
            'medication_print_logs',
            'payments',
            'appointments',
            'system_settings',
            'smart_exam_presets',
            'import_logs',
            'import_log_rows',
            'running_numbers',
            'audit_logs',
            'backup_logs',
        ];

        try {
            $pdo = db();
            $existingTables = array_map(
                static fn(array $row): string => array_values($row)[0],
                $pdo->query('SHOW TABLES')->fetchAll()
            );
            $missing = array_values(array_diff($requiredTables, $existingTables));

            $checks[] = $this->check(
                $missing === [] ? 'passed' : 'failed',
                'Database schema',
                $missing === [] ? 'ตารางสำคัญครบสำหรับ production workflow' : 'ยังขาดตาราง: ' . implode(', ', $missing),
                'Migration / Restore'
            );

            $checks[] = $this->check('passed', 'Database connection', 'เชื่อมต่อฐานข้อมูลได้', 'Migration / Restore');
        } catch (Throwable $throwable) {
            $checks[] = $this->check('failed', 'Database connection', $throwable->getMessage(), 'Migration / Restore');
        }

        return $checks;
    }

    private function backupChecks(): array
    {
        $checks = [];
        $exportDirectory = storage_path('exports');
        $files = is_dir($exportDirectory) ? glob($exportDirectory . '/clinic_backup_*.sql') : [];
        $latestFile = null;

        if ($files) {
            usort($files, static fn(string $left, string $right): int => (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0));
            $latestFile = $files[0];
        }

        $isToday = $latestFile && date('Y-m-d', filemtime($latestFile) ?: 0) === date('Y-m-d');
        $checks[] = $this->check(
            $isToday ? 'passed' : ($latestFile ? 'warning' : 'failed'),
            'Daily backup',
            $isToday ? 'มี backup ของวันนี้แล้ว' : ($latestFile ? 'มี backup ล่าสุด แต่ยังไม่ใช่วันนี้' : 'ยังไม่พบ backup'),
            'Backup / Restore'
        );

        $checks[] = $this->check(
            is_dir($exportDirectory) && is_writable($exportDirectory) ? 'passed' : 'failed',
            'Backup directory',
            is_dir($exportDirectory) ? 'storage/exports พร้อมเขียนไฟล์' : 'ยังไม่มี storage/exports',
            'Backup / Restore'
        );

        return $checks;
    }

    private function securityChecks(): array
    {
        $checks = [];

        $activeAdmins = (int) db()->query(
            'SELECT COUNT(*)
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_code = "ADMIN" AND users.is_active = 1'
        )->fetchColumn();

        $auditCount = (int) db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

        $checks[] = $this->check(
            $activeAdmins > 0 ? 'passed' : 'failed',
            'Admin account',
            $activeAdmins > 0 ? 'มีผู้ดูแลระบบที่เปิดใช้งาน' : 'ยังไม่มี Admin ที่เปิดใช้งาน',
            'Permission / Audit'
        );

        $checks[] = $this->check(
            $auditCount > 0 ? 'passed' : 'warning',
            'Audit log',
            $auditCount > 0 ? 'มี audit log แล้ว ' . $auditCount . ' รายการ' : 'ยังไม่มี audit log ควรตรวจหลังใช้งานจริง',
            'Permission / Audit'
        );

        return $checks;
    }

    private function operationChecks(): array
    {
        $pendingQueue = (int) db()->query(
            'SELECT COUNT(*)
             FROM queue_entries
             WHERE queue_date = CURDATE()
               AND status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")'
        )->fetchColumn();

        $stockAlertRows = db()->query(
            'SELECT COUNT(*)
             FROM inventory_items
             LEFT JOIN inventory_batches ON inventory_batches.item_id = inventory_items.id
             WHERE inventory_items.is_active = 1
             GROUP BY inventory_items.id, inventory_items.reorder_level
             HAVING COALESCE(SUM(inventory_batches.qty_balance), 0) <= inventory_items.reorder_level'
        )->fetchAll();
        $stockAlerts = count($stockAlertRows);

        return [
            $this->check(
                $pendingQueue === 0 ? 'passed' : 'warning',
                'Pending clinic work',
                $pendingQueue === 0 ? 'ไม่มีงานค้างวันนี้' : 'มีงานคิว/ชำระเงินค้าง ' . $pendingQueue . ' รายการ',
                'Reports / Daily Close'
            ),
            $this->check(
                $stockAlerts === 0 ? 'passed' : 'warning',
                'Inventory alert',
                $stockAlerts === 0 ? 'ยังไม่มีรายการต่ำกว่าจุดเตือน' : 'มีรายการคลังที่ต้องตรวจ ' . $stockAlerts . ' รายการ',
                'Reports / Daily Close'
            ),
        ];
    }

    private function integrationChecks(): array
    {
        $bridge = $this->smartCardBridgeHealth();

        return [
            $this->check(
                $bridge['ok'] ? 'passed' : 'warning',
                'Smart Card Bridge',
                $bridge['message'],
                'Deployment / Smart Card'
            ),
            $this->check(
                'warning',
                'Printer mode',
                'ระบบใช้ Browser Print สำหรับใบเสร็จและสติ๊กเกอร์ยา ต้องทดสอบกับเครื่องพิมพ์จริงก่อน go-live',
                'Printer'
            ),
        ];
    }

    private function privacyChecks(): array
    {
        $photoDir = storage_path('patient-photos');
        $importDir = storage_path('imports');

        return [
            $this->check(
                is_dir($photoDir) && is_writable($photoDir) ? 'passed' : 'warning',
                'Patient photo storage',
                'รูปบัตรถูกเก็บใน storage และเปิดผ่าน protected route ไม่ใช่ public URL ตรง',
                'Privacy / Security'
            ),
            $this->check(
                is_dir($importDir) && is_writable($importDir) ? 'passed' : 'warning',
                'Import staging',
                is_dir($importDir) ? 'storage/imports พร้อมใช้งาน ควรล้างไฟล์เก่าตามรอบ' : 'ยังไม่มี storage/imports',
                'Privacy / Security'
            ),
        ];
    }

    private function backupLogs(): array
    {
        try {
            return db()->query(
                'SELECT backup_logs.*, users.full_name AS created_by_name
                 FROM backup_logs
                 LEFT JOIN users ON users.id = backup_logs.created_by
                 ORDER BY backup_logs.created_at DESC, backup_logs.id DESC
                 LIMIT 8'
            )->fetchAll();
        } catch (Throwable $throwable) {
            return [];
        }
    }

    private function auditLogs(): array
    {
        try {
            return db()->query(
                'SELECT audit_logs.*, users.full_name AS actor_name
                 FROM audit_logs
                 LEFT JOIN users ON users.id = audit_logs.user_id
                 ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
                 LIMIT 8'
            )->fetchAll();
        } catch (Throwable $throwable) {
            return [];
        }
    }

    private function smartCardBridgeHealth(): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 1,
                'ignore_errors' => true,
            ],
        ]);

        $payload = @file_get_contents('http://127.0.0.1:8189/health', false, $context);
        if ($payload === false || trim($payload) === '') {
            return [
                'ok' => false,
                'message' => 'ยังติดต่อ Dongmahawan Smart Card Bridge ไม่ได้ ถ้าใช้เครื่องอ่านบัตรให้เปิด bridge และ service ให้ครบ',
            ];
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'message' => 'Bridge ตอบกลับแต่รูปแบบข้อมูลไม่ถูกต้อง',
            ];
        }

        return [
            'ok' => (bool) ($data['ok'] ?? false),
            'message' => !empty($data['ok'])
                ? 'Smart Card Bridge พร้อมใช้งาน'
                : 'Bridge ยังไม่พร้อม กรุณาตรวจ service และ MQTT',
        ];
    }

    private function ensureSchema(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS backup_logs (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                file_name varchar(255) NOT NULL,
                file_path varchar(255) NOT NULL,
                file_size_bytes bigint unsigned NOT NULL DEFAULT 0,
                receipt_count int unsigned NOT NULL DEFAULT 0,
                paid_total decimal(12,2) NOT NULL DEFAULT 0.00,
                pending_work_count int unsigned NOT NULL DEFAULT 0,
                retention_limit int unsigned NOT NULL DEFAULT 30,
                status enum("CREATED","FAILED") NOT NULL DEFAULT "CREATED",
                created_by bigint unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_backup_logs_created_at (created_at),
                KEY idx_backup_logs_created_by (created_by),
                CONSTRAINT fk_backup_logs_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function check(string $status, string $title, string $message, string $group): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'group' => $group,
        ];
    }
}
