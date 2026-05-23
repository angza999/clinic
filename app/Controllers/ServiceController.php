<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use Throwable;

class ServiceController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensurePhase2Schema();

        $services = db()->query(
            'SELECT services.*,
                    COALESCE(usage_stats.total_qty, 0) AS total_qty,
                    COALESCE(usage_stats.total_income, 0) AS total_income,
                    usage_stats.last_used_at,
                    COALESCE(smart_exam_stats.preset_count, 0) AS smart_exam_preset_count
             FROM services
             LEFT JOIN (
                SELECT visit_services.service_id,
                       SUM(visit_services.qty) AS total_qty,
                       SUM(visit_services.line_total) AS total_income,
                       MAX(visit_services.created_at) AS last_used_at
                FROM visit_services
                GROUP BY visit_services.service_id
             ) AS usage_stats ON usage_stats.service_id = services.id
             LEFT JOIN (
                SELECT services.id AS service_id, COUNT(smart_exam_presets.id) AS preset_count
                FROM services
                LEFT JOIN smart_exam_presets ON smart_exam_presets.is_active = 1
                    AND FIND_IN_SET(services.service_code, REPLACE(COALESCE(smart_exam_presets.service_codes, ""), " ", "")) > 0
                GROUP BY services.id
             ) AS smart_exam_stats ON smart_exam_stats.service_id = services.id
             ORDER BY services.is_active DESC, services.service_name ASC'
        )->fetchAll();

        $categories = array_values(array_unique(array_filter(array_map(
            static fn(array $service): string => trim((string) ($service['category'] ?? '')),
            $services
        ))));
        sort($categories);

        $priceHistory = $this->servicePriceHistory();
        $recentAudit = $this->recentServiceAudit();

        $this->render('services/index', [
            'pageTitle' => 'บริการและราคา',
            'pageTopbarMode' => 'compact',
            'pageStyles' => [app_url('assets/css/services.css')],
            'pageScripts' => [app_url('assets/js/services.js')],
            'services' => $services,
            'categories' => $categories,
            'priceHistory' => $priceHistory,
            'recentAudit' => $recentAudit,
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);
        $this->ensurePhase2Schema();

        $serviceCode = strtoupper(trim((string) ($_POST['service_code'] ?? '')));
        $serviceName = trim((string) ($_POST['service_name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $priceRaw = trim((string) ($_POST['price'] ?? '0'));
        $price = (float) $priceRaw;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($serviceCode === '' || $serviceName === '') {
            flash('error', 'กรุณากรอกรหัสบริการและชื่อบริการให้ครบ');
            redirect('services');
        }

        if (!is_numeric($priceRaw) || $price < 0) {
            flash('error', 'ราคาบริการต้องเป็นตัวเลขและห้ามติดลบ');
            redirect('services');
        }

        $existingStmt = db()->prepare('SELECT * FROM services WHERE service_code = :service_code LIMIT 1');
        $existingStmt->execute(['service_code' => $serviceCode]);
        $existing = $existingStmt->fetch() ?: null;

        db()->beginTransaction();

        try {
            db()->prepare(
                'INSERT INTO services (service_code, service_name, category, price, is_active, created_at, updated_at)
                 VALUES (:service_code, :service_name, :category, :price, :is_active, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    service_name = VALUES(service_name),
                    category = VALUES(category),
                    price = VALUES(price),
                    is_active = VALUES(is_active),
                    updated_at = NOW()'
            )->execute([
                'service_code' => $serviceCode,
                'service_name' => $serviceName,
                'category' => $category ?: null,
                'price' => $price,
                'is_active' => $isActive,
            ]);

            $serviceId = $existing ? (int) $existing['id'] : (int) db()->lastInsertId();
            if ($serviceId <= 0) {
                $idStmt = db()->prepare('SELECT id FROM services WHERE service_code = :service_code LIMIT 1');
                $idStmt->execute(['service_code' => $serviceCode]);
                $serviceId = (int) ($idStmt->fetchColumn() ?: 0);
            }

            $oldPrice = $existing ? (float) $existing['price'] : null;
            if ($serviceId > 0 && ($oldPrice === null || abs($oldPrice - $price) > 0.0001)) {
                db()->prepare(
                    'INSERT INTO service_price_history (service_id, old_price, new_price, changed_by, changed_at, note)
                     VALUES (:service_id, :old_price, :new_price, :changed_by, NOW(), :note)'
                )->execute([
                    'service_id' => $serviceId,
                    'old_price' => $oldPrice,
                    'new_price' => $price,
                    'changed_by' => current_user()['id'] ?? null,
                    'note' => $existing ? 'แก้ไขราคาจากหน้า Service Workstation' : 'สร้างบริการใหม่',
                ]);
            }

            db()->commit();

            $this->logServiceAudit($existing ? 'SERVICE_UPDATED' : 'SERVICE_CREATED', $serviceId, [
                'service_code' => $serviceCode,
                'service_name' => $serviceName,
                'category' => $category ?: null,
                'old_price' => $oldPrice,
                'new_price' => $price,
                'is_active' => $isActive,
            ]);
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            flash('error', 'บันทึกบริการไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองอีกครั้ง');
            redirect('services');
        }

        flash('success', 'บันทึกรายการบริการเรียบร้อยแล้ว');
        redirect('services');
    }

    public function toggle(): void
    {
        require_roles(['ADMIN']);
        $this->ensurePhase2Schema();

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($serviceId <= 0) {
            flash('error', 'ไม่พบบริการที่ต้องการปรับสถานะ');
            redirect('services');
        }

        $beforeStmt = db()->prepare('SELECT service_code, service_name, is_active FROM services WHERE id = :id LIMIT 1');
        $beforeStmt->execute(['id' => $serviceId]);
        $before = $beforeStmt->fetch() ?: null;

        db()->prepare('UPDATE services SET is_active = :is_active, updated_at = NOW() WHERE id = :id')->execute([
            'is_active' => $isActive,
            'id' => $serviceId,
        ]);

        $this->logServiceAudit($isActive ? 'SERVICE_ENABLED' : 'SERVICE_DISABLED', $serviceId, [
            'service_code' => $before['service_code'] ?? null,
            'service_name' => $before['service_name'] ?? null,
            'old_active' => $before ? (int) $before['is_active'] : null,
            'new_active' => $isActive,
        ]);

        flash('success', $isActive ? 'เปิดใช้งานบริการเรียบร้อยแล้ว' : 'ปิดใช้งานบริการเรียบร้อยแล้ว');
        redirect('services');
    }

    public function export(): void
    {
        require_roles(['ADMIN']);
        $this->ensurePhase2Schema();

        $rows = db()->query(
            'SELECT services.service_code,
                    services.service_name,
                    COALESCE(services.category, "-") AS category,
                    services.price,
                    CASE WHEN services.is_active = 1 THEN "ACTIVE" ELSE "INACTIVE" END AS status,
                    COALESCE(usage_stats.total_qty, 0) AS total_qty,
                    COALESCE(usage_stats.total_income, 0) AS total_income,
                    usage_stats.last_used_at
             FROM services
             LEFT JOIN (
                SELECT service_id,
                       SUM(qty) AS total_qty,
                       SUM(line_total) AS total_income,
                       MAX(created_at) AS last_used_at
                FROM visit_services
                GROUP BY service_id
             ) AS usage_stats ON usage_stats.service_id = services.id
             ORDER BY services.is_active DESC, services.service_name ASC'
        )->fetchAll();

        $this->logServiceAudit('SERVICE_EXPORTED', null, [
            'row_count' => count($rows),
            'format' => 'csv',
        ]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="services_export.csv"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['รหัสบริการ', 'ชื่อบริการ', 'หมวดหมู่', 'ราคา', 'สถานะ', 'ใช้แล้ว', 'รายได้รวม', 'ใช้ล่าสุด']);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    private function ensurePhase2Schema(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS service_price_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                old_price DECIMAL(10,2) DEFAULT NULL,
                new_price DECIMAL(10,2) NOT NULL,
                changed_by BIGINT UNSIGNED DEFAULT NULL,
                changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                note VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_service_price_history_service_id (service_id),
                KEY idx_service_price_history_changed_by (changed_by),
                CONSTRAINT fk_service_price_history_service_id FOREIGN KEY (service_id) REFERENCES services (id),
                CONSTRAINT fk_service_price_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function servicePriceHistory(): array
    {
        $rows = db()->query(
            'SELECT service_price_history.*,
                    users.full_name AS changed_by_name
             FROM service_price_history
             LEFT JOIN users ON users.id = service_price_history.changed_by
             ORDER BY service_price_history.changed_at DESC, service_price_history.id DESC
             LIMIT 100'
        )->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            if ($serviceId <= 0) {
                continue;
            }

            $grouped[$serviceId] ??= [];
            if (count($grouped[$serviceId]) < 8) {
                $grouped[$serviceId][] = [
                    'old_price' => $row['old_price'],
                    'new_price' => $row['new_price'],
                    'changed_by_name' => $row['changed_by_name'] ?: '-',
                    'changed_at' => $row['changed_at'],
                    'note' => $row['note'],
                ];
            }
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentServiceAudit(): array
    {
        $stmt = db()->query(
            'SELECT audit_logs.*, users.full_name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE audit_logs.action IN ("SERVICE_CREATED", "SERVICE_UPDATED", "SERVICE_ENABLED", "SERVICE_DISABLED", "SERVICE_EXPORTED")
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT 8'
        );

        return $stmt->fetchAll();
    }

    private function logServiceAudit(string $action, ?int $serviceId, array $detail): void
    {
        try {
            $actor = current_user();
            db()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, detail_json, created_at)
                 VALUES (:user_id, :action, "services", :record_id, :detail_json, NOW())'
            )->execute([
                'user_id' => $actor['id'] ?? null,
                'action' => $action,
                'record_id' => $serviceId,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            // Audit must never block clinic operation.
        }
    }
}
