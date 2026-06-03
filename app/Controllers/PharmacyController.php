<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use Throwable;

class PharmacyController extends Controller
{
    public function labels(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);
        $this->ensureSchema();

        $visitId = (int) ($_GET['visit_id'] ?? 0);
        $labelSize = $this->normalizeLabelSize((string) ($_GET['size'] ?? '58x40'));
        $visit = $this->findVisit($visitId);

        if (!$visit) {
            flash('error', 'ไม่พบเคสที่ต้องการพิมพ์สติ๊กเกอร์ยา');
            redirect('queue');
        }

        $prescription = $this->syncPrescription($visitId);
        $items = $this->prescriptionItems((int) $prescription['id']);
        $printLogs = $this->printLogs($visitId);

        $this->render('pharmacy/labels', [
            'pageTitle' => 'พิมพ์สติ๊กเกอร์ยา',
            'pageTopbarMode' => 'compact',
            'visit' => $visit,
            'prescription' => $prescription,
            'items' => $items,
            'printLogs' => $printLogs,
            'labelSize' => $labelSize,
            'clinicName' => (string) system_setting('clinic_name', 'ดงมหาวันคลินิก'),
            'pageStyles' => [app_url('assets/css/pharmacy-labels.css')],
            'pageScripts' => [app_url('assets/js/pharmacy-labels.js')],
        ]);
    }

    public function printLog(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);
        $this->ensureSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $prescriptionItemIds = array_map('intval', (array) ($_POST['prescription_item_ids'] ?? []));
        $labelSize = $this->normalizeLabelSize((string) ($_POST['label_size'] ?? '58x40'));
        $visit = $this->findVisit($visitId);

        if (!$visit || !$prescriptionItemIds) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'ยังไม่มีรายการยาที่พร้อมบันทึกประวัติการพิมพ์',
            ], 422);
        }

        $prescription = $this->syncPrescription($visitId);
        $validItems = $this->prescriptionItems((int) $prescription['id']);
        $validIds = array_map(static fn(array $row): int => (int) $row['id'], $validItems);
        $selectedIds = array_values(array_intersect($prescriptionItemIds, $validIds));

        if (!$selectedIds) {
            $this->jsonResponse([
                'ok' => false,
                'message' => 'ไม่พบรายการยาที่ตรงกับเคสนี้',
            ], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $existingLogStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM medication_print_logs WHERE prescription_item_id = :prescription_item_id'
            );
            $insertStmt = $pdo->prepare(
                'INSERT INTO medication_print_logs (
                    prescription_item_id, visit_id, patient_id, label_size, printer_mode, status,
                    payload_json, printed_by, printed_at, created_at
                 ) VALUES (
                    :prescription_item_id, :visit_id, :patient_id, :label_size, "BROWSER", :status,
                    :payload_json, :printed_by, NOW(), NOW()
                 )'
            );

            $payload = [
                'label_size' => $labelSize,
                'clinic_name' => (string) system_setting('clinic_name', 'ดงมหาวันคลินิก'),
                'visit_no' => (string) ($visit['visit_no'] ?? ''),
                'hn' => (string) ($visit['hn'] ?? ''),
            ];

            foreach ($selectedIds as $itemId) {
                $existingLogStmt->execute(['prescription_item_id' => $itemId]);
                $status = ((int) $existingLogStmt->fetchColumn() > 0) ? 'REPRINT' : 'PRINTED';
                $insertStmt->execute([
                    'prescription_item_id' => $itemId,
                    'visit_id' => $visitId,
                    'patient_id' => (int) $visit['patient_id'],
                    'label_size' => $labelSize,
                    'status' => $status,
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'printed_by' => current_user()['id'] ?? null,
                ]);
            }

            $pdo->prepare('UPDATE prescriptions SET status = "PRINTED", updated_at = NOW() WHERE id = :id')
                ->execute(['id' => (int) $prescription['id']]);

            $pdo->commit();
        } catch (Throwable $throwable) {
            $pdo->rollBack();
            $this->jsonResponse([
                'ok' => false,
                'message' => 'บันทึกประวัติการพิมพ์ไม่สำเร็จ: ' . $throwable->getMessage(),
            ], 422);
        }

        $this->jsonResponse([
            'ok' => true,
            'message' => 'บันทึกประวัติการพิมพ์สติ๊กเกอร์ยาแล้ว',
            'printed_count' => count($selectedIds),
        ]);
    }

    private function ensureSchema(): void
    {
        $pdo = db();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS drug_profiles (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                item_id bigint unsigned NOT NULL,
                drug_short_name varchar(100) DEFAULT NULL,
                drug_category varchar(100) DEFAULT NULL,
                default_dose_qty varchar(30) DEFAULT NULL,
                default_dose_unit varchar(50) DEFAULT NULL,
                default_frequency varchar(100) DEFAULT NULL,
                default_timing varchar(100) DEFAULT NULL,
                default_instruction text DEFAULT NULL,
                warning_text text DEFAULT NULL,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_drug_profiles_item_id (item_id),
                KEY idx_drug_profiles_category (drug_category),
                CONSTRAINT fk_drug_profiles_item_id FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS prescriptions (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                visit_id bigint unsigned NOT NULL,
                patient_id bigint unsigned NOT NULL,
                status enum("DRAFT","READY","PRINTED","CANCELLED") NOT NULL DEFAULT "DRAFT",
                created_by bigint unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_prescriptions_visit_id (visit_id),
                KEY idx_prescriptions_patient_id (patient_id),
                KEY idx_prescriptions_status (status),
                CONSTRAINT fk_prescriptions_visit_id FOREIGN KEY (visit_id) REFERENCES visits (id) ON DELETE CASCADE,
                CONSTRAINT fk_prescriptions_patient_id FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
                CONSTRAINT fk_prescriptions_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS prescription_items (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                prescription_id bigint unsigned NOT NULL,
                visit_item_usage_id bigint unsigned DEFAULT NULL,
                item_id bigint unsigned NOT NULL,
                drug_name_snapshot varchar(180) NOT NULL,
                qty decimal(10,2) NOT NULL DEFAULT 0.00,
                unit_name varchar(50) DEFAULT NULL,
                instruction_text text DEFAULT NULL,
                warning_text text DEFAULT NULL,
                note text DEFAULT NULL,
                sort_order int NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_prescription_items_usage_id (visit_item_usage_id),
                KEY idx_prescription_items_prescription_id (prescription_id),
                KEY idx_prescription_items_item_id (item_id),
                CONSTRAINT fk_prescription_items_prescription_id FOREIGN KEY (prescription_id) REFERENCES prescriptions (id) ON DELETE CASCADE,
                CONSTRAINT fk_prescription_items_usage_id FOREIGN KEY (visit_item_usage_id) REFERENCES visit_item_usages (id) ON DELETE SET NULL,
                CONSTRAINT fk_prescription_items_item_id FOREIGN KEY (item_id) REFERENCES inventory_items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS medication_print_logs (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                prescription_item_id bigint unsigned NOT NULL,
                visit_id bigint unsigned NOT NULL,
                patient_id bigint unsigned NOT NULL,
                label_size varchar(20) NOT NULL DEFAULT "58x40",
                printer_mode varchar(30) NOT NULL DEFAULT "BROWSER",
                status enum("PRINTED","REPRINT") NOT NULL DEFAULT "PRINTED",
                payload_json longtext DEFAULT NULL,
                printed_by bigint unsigned DEFAULT NULL,
                printed_at datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_medication_print_logs_item (prescription_item_id),
                KEY idx_medication_print_logs_visit (visit_id),
                KEY idx_medication_print_logs_patient (patient_id),
                CONSTRAINT fk_medication_print_logs_item FOREIGN KEY (prescription_item_id) REFERENCES prescription_items (id) ON DELETE CASCADE,
                CONSTRAINT fk_medication_print_logs_visit FOREIGN KEY (visit_id) REFERENCES visits (id) ON DELETE CASCADE,
                CONSTRAINT fk_medication_print_logs_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
                CONSTRAINT fk_medication_print_logs_user FOREIGN KEY (printed_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'INSERT IGNORE INTO drug_profiles (
                item_id, drug_short_name, drug_category, default_dose_qty, default_dose_unit,
                default_frequency, default_timing, default_instruction, warning_text, is_active, created_at, updated_at
             )
             SELECT id, item_name, "ทั่วไป", "1", unit_name, "วันละ 3 ครั้ง", "หลังอาหาร",
                    CONCAT("รับประทานครั้งละ 1 ", unit_name, " วันละ 3 ครั้ง หลังอาหาร"),
                    NULL, is_active, NOW(), NOW()
             FROM inventory_items
             WHERE item_type = "DRUG"'
        );
    }

    private function findVisit(int $visitId): array|false
    {
        $stmt = db()->prepare(
            'SELECT visits.*, queue_entries.queue_no, queue_entries.status AS queue_status,
                    patients.id AS patient_id, patients.hn, patients.first_name, patients.last_name,
                    patients.gender, patients.birth_date, patients.drug_allergy, patients.underlying_disease
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             WHERE visits.id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);

        return $stmt->fetch();
    }

    private function syncPrescription(int $visitId): array
    {
        $visit = $this->findVisit($visitId);
        if (!$visit) {
            throw new \RuntimeException('Visit not found');
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'INSERT INTO prescriptions (visit_id, patient_id, status, created_by, created_at, updated_at)
                 VALUES (:visit_id, :patient_id, "DRAFT", :created_by, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE patient_id = VALUES(patient_id), updated_at = NOW()'
            )->execute([
                'visit_id' => $visitId,
                'patient_id' => (int) $visit['patient_id'],
                'created_by' => current_user()['id'] ?? null,
            ]);

            $prescriptionStmt = $pdo->prepare('SELECT * FROM prescriptions WHERE visit_id = :visit_id LIMIT 1');
            $prescriptionStmt->execute(['visit_id' => $visitId]);
            $prescription = $prescriptionStmt->fetch();

            $usageStmt = $pdo->prepare(
                'SELECT visit_item_usages.*, inventory_items.item_name, inventory_items.item_type, inventory_items.unit_name,
                        drug_profiles.default_instruction, drug_profiles.warning_text
                 FROM visit_item_usages
                 INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
                 LEFT JOIN drug_profiles ON drug_profiles.item_id = inventory_items.id
                 WHERE visit_item_usages.visit_id = :visit_id
                   AND inventory_items.item_type = "DRUG"
                 ORDER BY visit_item_usages.id ASC'
            );
            $usageStmt->execute(['visit_id' => $visitId]);
            $usages = $usageStmt->fetchAll();
            $usageIds = array_map(static fn(array $row): int => (int) $row['id'], $usages);

            $upsertItemStmt = $pdo->prepare(
                'INSERT INTO prescription_items (
                    prescription_id, visit_item_usage_id, item_id, drug_name_snapshot,
                    qty, unit_name, instruction_text, warning_text, note, sort_order, created_at, updated_at
                 ) VALUES (
                    :prescription_id, :visit_item_usage_id, :item_id, :drug_name_snapshot,
                    :qty, :unit_name, :instruction_text, :warning_text, :note, :sort_order, NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    item_id = VALUES(item_id),
                    drug_name_snapshot = VALUES(drug_name_snapshot),
                    qty = VALUES(qty),
                    unit_name = VALUES(unit_name),
                    instruction_text = VALUES(instruction_text),
                    warning_text = VALUES(warning_text),
                    note = VALUES(note),
                    sort_order = VALUES(sort_order),
                    updated_at = NOW()'
            );

            foreach ($usages as $index => $usage) {
                $usageNote = trim((string) ($usage['usage_note'] ?? ''));
                $defaultInstruction = trim((string) ($usage['default_instruction'] ?? ''));
                $instruction = $usageNote !== '' ? $usageNote : ($defaultInstruction !== '' ? $defaultInstruction : 'ใช้ยาตามคำแนะนำของเจ้าหน้าที่');
                $warning = trim((string) ($usage['warning_text'] ?? ''));

                $upsertItemStmt->execute([
                    'prescription_id' => (int) $prescription['id'],
                    'visit_item_usage_id' => (int) $usage['id'],
                    'item_id' => (int) $usage['item_id'],
                    'drug_name_snapshot' => (string) $usage['item_name'],
                    'qty' => (float) $usage['qty'],
                    'unit_name' => (string) ($usage['unit_name'] ?? ''),
                    'instruction_text' => $instruction,
                    'warning_text' => $warning !== '' ? $warning : null,
                    'note' => null,
                    'sort_order' => $index + 1,
                ]);
            }

            if ($usageIds) {
                $placeholders = implode(',', array_fill(0, count($usageIds), '?'));
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM prescription_items
                     WHERE prescription_id = ?
                       AND visit_item_usage_id IS NOT NULL
                       AND visit_item_usage_id NOT IN ({$placeholders})"
                );
                $deleteStmt->execute(array_merge([(int) $prescription['id']], $usageIds));
            } else {
                $pdo->prepare('DELETE FROM prescription_items WHERE prescription_id = :prescription_id')
                    ->execute(['prescription_id' => (int) $prescription['id']]);
            }

            $status = count($usages) > 0 ? 'READY' : 'DRAFT';
            $pdo->prepare(
                'UPDATE prescriptions
                 SET status = CASE WHEN status = "PRINTED" AND :status_check = "READY" THEN "PRINTED" ELSE :status_value END,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'status_check' => $status,
                'status_value' => $status,
                'id' => (int) $prescription['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }

        $prescriptionStmt = db()->prepare('SELECT * FROM prescriptions WHERE visit_id = :visit_id LIMIT 1');
        $prescriptionStmt->execute(['visit_id' => $visitId]);

        return $prescriptionStmt->fetch();
    }

    private function prescriptionItems(int $prescriptionId): array
    {
        $stmt = db()->prepare(
            'SELECT prescription_items.*,
                    COALESCE(print_counts.print_count, 0) AS print_count,
                    print_counts.last_printed_at
             FROM prescription_items
             LEFT JOIN (
                SELECT prescription_item_id, COUNT(*) AS print_count, MAX(printed_at) AS last_printed_at
                FROM medication_print_logs
                GROUP BY prescription_item_id
             ) AS print_counts ON print_counts.prescription_item_id = prescription_items.id
             WHERE prescription_items.prescription_id = :prescription_id
             ORDER BY prescription_items.sort_order ASC, prescription_items.id ASC'
        );
        $stmt->execute(['prescription_id' => $prescriptionId]);

        return $stmt->fetchAll();
    }

    private function printLogs(int $visitId): array
    {
        $stmt = db()->prepare(
            'SELECT medication_print_logs.*, prescription_items.drug_name_snapshot, users.full_name AS printed_by_name
             FROM medication_print_logs
             INNER JOIN prescription_items ON prescription_items.id = medication_print_logs.prescription_item_id
             LEFT JOIN users ON users.id = medication_print_logs.printed_by
             WHERE medication_print_logs.visit_id = :visit_id
             ORDER BY medication_print_logs.printed_at DESC, medication_print_logs.id DESC
             LIMIT 20'
        );
        $stmt->execute(['visit_id' => $visitId]);

        return $stmt->fetchAll();
    }

    private function normalizeLabelSize(string $size): string
    {
        return in_array($size, ['58x40', '80x50', '100x75'], true) ? $size : '58x40';
    }

    private function jsonResponse(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
