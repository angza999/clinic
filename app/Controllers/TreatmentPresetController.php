<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use Throwable;

class TreatmentPresetController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN']);
        $this->ensureTreatmentPresetSchema();

        $selectedId = (int) ($_GET['id'] ?? 0);
        $presets = $this->presets();
        $selectedPreset = $selectedId > 0 ? $this->presetDetail($selectedId) : null;

        $this->render('treatment_presets/index', [
            'pageTitle' => 'จัดการ Preset การรักษา',
            'pageTopbarMode' => 'compact',
            'pageStyles' => [app_url('assets/css/treatment-presets.css')],
            'presets' => $presets,
            'selectedPreset' => $selectedPreset,
            'services' => $this->activeServices(),
            'medicines' => $this->activeItems('DRUG'),
            'supplies' => $this->activeItems('SUPPLY'),
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);
        $this->ensureTreatmentPresetSchema();

        $presetId = (int) ($_POST['preset_id'] ?? 0);
        $presetName = trim((string) ($_POST['preset_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($presetName === '') {
            flash('error', 'กรุณากรอกชื่อ Treatment Preset');
            redirect('treatment-presets', $presetId > 0 ? ['id' => $presetId] : []);
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();

            if ($presetId > 0) {
                $pdo->prepare(
                    'UPDATE preset_master
                     SET preset_name = :preset_name,
                         description = :description,
                         is_active = :is_active,
                         updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'preset_name' => $presetName,
                    'description' => $description ?: null,
                    'is_active' => $isActive,
                    'id' => $presetId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO preset_master (preset_name, description, is_active, created_at, updated_at)
                     VALUES (:preset_name, :description, :is_active, NOW(), NOW())'
                )->execute([
                    'preset_name' => $presetName,
                    'description' => $description ?: null,
                    'is_active' => $isActive,
                ]);
                $presetId = (int) $pdo->lastInsertId();
            }

            $this->replaceServices($pdo, $presetId);
            $this->replaceMedications($pdo, $presetId);
            $this->replaceSupplies($pdo, $presetId);

            $pdo->commit();
            flash('success', 'บันทึก Treatment Preset เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'ไม่สามารถบันทึก Treatment Preset ได้: ' . $throwable->getMessage());
        }

        redirect('treatment-presets', ['id' => $presetId]);
    }

    public function delete(): void
    {
        require_roles(['ADMIN']);
        $this->ensureTreatmentPresetSchema();

        $presetId = (int) ($_POST['preset_id'] ?? 0);
        if ($presetId > 0) {
            db()->prepare('UPDATE preset_master SET is_active = 0, updated_at = NOW() WHERE id = :id')
                ->execute(['id' => $presetId]);
            flash('success', 'ปิดใช้งาน Treatment Preset แล้ว');
        }

        redirect('treatment-presets');
    }

    private function ensureTreatmentPresetSchema(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_master (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_master_active (is_active, preset_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_services (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                service_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_services_preset_id (preset_id),
                KEY idx_preset_services_service_id (service_id),
                CONSTRAINT fk_preset_services_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_services_service_id FOREIGN KEY (service_id) REFERENCES services (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_medications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                medicine_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                instruction TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_medications_preset_id (preset_id),
                KEY idx_preset_medications_medicine_id (medicine_id),
                CONSTRAINT fk_preset_medications_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_medications_medicine_id FOREIGN KEY (medicine_id) REFERENCES inventory_items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_supplies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                supply_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_supplies_preset_id (preset_id),
                KEY idx_preset_supplies_supply_id (supply_id),
                CONSTRAINT fk_preset_supplies_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_supplies_supply_id FOREIGN KEY (supply_id) REFERENCES inventory_items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedTreatmentPresets();
    }

    private function seedTreatmentPresets(): void
    {
        $count = (int) db()->query('SELECT COUNT(*) FROM preset_master')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->seedPreset('URI', 'ตรวจ URI แบบเร็ว พร้อมยาเบื้องต้น', ['SRV001'], ['Paracetamol' => 10], []);
        $this->seedPreset('ทำแผล', 'เพิ่มบริการทำแผลและเวชภัณฑ์พื้นฐาน', ['SRV002'], [], ['Normal Saline' => 1, 'ผ้าก๊อซ' => 2]);
        $this->seedPreset('ฉีดยา', 'เพิ่มค่าบริการฉีดยาเป็น bundle เริ่มต้น', ['SRV003'], [], []);
    }

    private function seedPreset(string $name, string $description, array $serviceCodes, array $drugNames, array $supplyNames): void
    {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO preset_master (preset_name, description, is_active, created_at, updated_at)
             VALUES (:preset_name, :description, 1, NOW(), NOW())'
        )->execute([
            'preset_name' => $name,
            'description' => $description,
        ]);
        $presetId = (int) $pdo->lastInsertId();

        foreach ($serviceCodes as $serviceCode) {
            $serviceId = $this->serviceIdByCode($serviceCode);
            if ($serviceId > 0) {
                $pdo->prepare('INSERT INTO preset_services (preset_id, service_id, qty, created_at, updated_at) VALUES (:preset_id, :service_id, 1, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'service_id' => $serviceId]);
            }
        }

        foreach ($drugNames as $drugName => $qty) {
            $itemId = $this->itemIdLike((string) $drugName, 'DRUG');
            if ($itemId > 0) {
                $pdo->prepare('INSERT INTO preset_medications (preset_id, medicine_id, qty, instruction, created_at, updated_at) VALUES (:preset_id, :medicine_id, :qty, :instruction, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'medicine_id' => $itemId, 'qty' => (float) $qty, 'instruction' => null]);
            }
        }

        foreach ($supplyNames as $supplyName => $qty) {
            $itemId = $this->itemIdLike((string) $supplyName, 'SUPPLY');
            if ($itemId > 0) {
                $pdo->prepare('INSERT INTO preset_supplies (preset_id, supply_id, qty, created_at, updated_at) VALUES (:preset_id, :supply_id, :qty, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'supply_id' => $itemId, 'qty' => (float) $qty]);
            }
        }
    }

    private function serviceIdByCode(string $serviceCode): int
    {
        $stmt = db()->prepare('SELECT id FROM services WHERE service_code = :service_code LIMIT 1');
        $stmt->execute(['service_code' => $serviceCode]);
        return (int) $stmt->fetchColumn();
    }

    private function itemIdLike(string $itemName, string $itemType): int
    {
        $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_type = :item_type AND item_name LIKE :item_name LIMIT 1');
        $stmt->execute(['item_type' => $itemType, 'item_name' => '%' . $itemName . '%']);
        return (int) $stmt->fetchColumn();
    }

    private function presets(): array
    {
        return db()->query(
            'SELECT preset_master.*,
                    (SELECT COUNT(*) FROM preset_services WHERE preset_services.preset_id = preset_master.id) AS service_count,
                    (SELECT COUNT(*) FROM preset_medications WHERE preset_medications.preset_id = preset_master.id) AS medication_count,
                    (SELECT COUNT(*) FROM preset_supplies WHERE preset_supplies.preset_id = preset_master.id) AS supply_count
             FROM preset_master
             ORDER BY preset_master.is_active DESC, preset_master.preset_name ASC'
        )->fetchAll();
    }

    private function presetDetail(int $presetId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM preset_master WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $presetId]);
        $preset = $stmt->fetch();
        if (!$preset) {
            return null;
        }

        $preset['services'] = $this->presetRows('preset_services', 'service_id', $presetId);
        $preset['medications'] = $this->presetRows('preset_medications', 'medicine_id', $presetId);
        $preset['supplies'] = $this->presetRows('preset_supplies', 'supply_id', $presetId);

        return $preset;
    }

    private function presetRows(string $table, string $field, int $presetId): array
    {
        $stmt = db()->prepare("SELECT * FROM {$table} WHERE preset_id = :preset_id ORDER BY id ASC");
        $stmt->execute(['preset_id' => $presetId]);
        return $stmt->fetchAll();
    }

    private function activeServices(): array
    {
        return db()->query('SELECT id, service_code, service_name, price FROM services WHERE is_active = 1 ORDER BY service_name ASC')->fetchAll();
    }

    private function activeItems(string $itemType): array
    {
        $stmt = db()->prepare('SELECT id, item_code, item_name, unit_name, default_price FROM inventory_items WHERE item_type = :item_type AND is_active = 1 ORDER BY item_name ASC');
        $stmt->execute(['item_type' => $itemType]);
        return $stmt->fetchAll();
    }

    private function replaceServices(PDO $pdo, int $presetId): void
    {
        $pdo->prepare('DELETE FROM preset_services WHERE preset_id = :preset_id')->execute(['preset_id' => $presetId]);
        $serviceIds = $_POST['service_id'] ?? [];
        $qtys = $_POST['service_qty'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO preset_services (preset_id, service_id, qty, created_at, updated_at) VALUES (:preset_id, :service_id, :qty, NOW(), NOW())');

        foreach ((array) $serviceIds as $index => $serviceId) {
            $serviceId = (int) $serviceId;
            if ($serviceId <= 0) {
                continue;
            }
            $stmt->execute([
                'preset_id' => $presetId,
                'service_id' => $serviceId,
                'qty' => max(0.01, (float) ($qtys[$index] ?? 1)),
            ]);
        }
    }

    private function replaceMedications(PDO $pdo, int $presetId): void
    {
        $pdo->prepare('DELETE FROM preset_medications WHERE preset_id = :preset_id')->execute(['preset_id' => $presetId]);
        $medicineIds = $_POST['medicine_id'] ?? [];
        $qtys = $_POST['medicine_qty'] ?? [];
        $instructions = $_POST['medicine_instruction'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO preset_medications (preset_id, medicine_id, qty, instruction, created_at, updated_at) VALUES (:preset_id, :medicine_id, :qty, :instruction, NOW(), NOW())');

        foreach ((array) $medicineIds as $index => $medicineId) {
            $medicineId = (int) $medicineId;
            if ($medicineId <= 0) {
                continue;
            }
            $stmt->execute([
                'preset_id' => $presetId,
                'medicine_id' => $medicineId,
                'qty' => max(0.01, (float) ($qtys[$index] ?? 1)),
                'instruction' => trim((string) ($instructions[$index] ?? '')) ?: null,
            ]);
        }
    }

    private function replaceSupplies(PDO $pdo, int $presetId): void
    {
        $pdo->prepare('DELETE FROM preset_supplies WHERE preset_id = :preset_id')->execute(['preset_id' => $presetId]);
        $supplyIds = $_POST['supply_id'] ?? [];
        $qtys = $_POST['supply_qty'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO preset_supplies (preset_id, supply_id, qty, created_at, updated_at) VALUES (:preset_id, :supply_id, :qty, NOW(), NOW())');

        foreach ((array) $supplyIds as $index => $supplyId) {
            $supplyId = (int) $supplyId;
            if ($supplyId <= 0) {
                continue;
            }
            $stmt->execute([
                'preset_id' => $presetId,
                'supply_id' => $supplyId,
                'qty' => max(0.01, (float) ($qtys[$index] ?? 1)),
            ]);
        }
    }
}
