<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

class SettingsController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN']);
        $this->ensureSettingsSchema();
        $this->ensureSmartPresetSchema();
        $this->seedDefaultSmartPresets();

        $settings = db()->query('SELECT * FROM system_settings ORDER BY id ASC LIMIT 1')->fetch();

        if (!$settings) {
            $settings = [
                'id' => 0,
                'clinic_name' => config('app.name'),
                'clinic_address' => '',
                'clinic_phone' => '',
                'clinic_tax_id' => '',
                'receipt_logo_text' => '',
                'receipt_footer' => config('app.receipt_footer'),
                'receipt_prefix' => 'RC',
                'hn_prefix' => 'HN',
                'expiry_alert_days' => 30,
                'queue_note' => config('app.receipt_footer'),
            ];
        }

        $this->render('settings/index', [
            'pageTitle' => 'ตั้งค่าคลินิก',
            'pageTopbarMode' => 'compact',
            'settings' => $settings,
            'smartPresets' => $this->smartPresets(),
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);
        $this->ensureSettingsSchema();

        $settingsId = (int) ($_POST['settings_id'] ?? 0);
        $clinicName = trim((string) ($_POST['clinic_name'] ?? ''));
        $clinicAddress = trim((string) ($_POST['clinic_address'] ?? ''));
        $clinicPhone = trim((string) ($_POST['clinic_phone'] ?? ''));
        $clinicTaxId = trim((string) ($_POST['clinic_tax_id'] ?? ''));
        $receiptLogoText = trim((string) ($_POST['receipt_logo_text'] ?? ''));
        $receiptFooter = trim((string) ($_POST['receipt_footer'] ?? ''));
        $receiptPrefix = strtoupper(trim((string) ($_POST['receipt_prefix'] ?? 'RC')));
        $hnPrefix = strtoupper(trim((string) ($_POST['hn_prefix'] ?? 'HN')));
        $expiryAlertDays = max(1, (int) ($_POST['expiry_alert_days'] ?? 30));
        $queueNote = trim((string) ($_POST['queue_note'] ?? ''));

        if ($clinicName === '') {
            flash('error', 'กรุณากรอกชื่อคลินิก');
            redirect('settings');
        }

        try {
            if ($settingsId > 0) {
                db()->prepare(
                    'UPDATE system_settings
                     SET clinic_name = :clinic_name,
                         clinic_address = :clinic_address,
                         clinic_phone = :clinic_phone,
                         clinic_tax_id = :clinic_tax_id,
                         receipt_logo_text = :receipt_logo_text,
                         receipt_footer = :receipt_footer,
                         receipt_prefix = :receipt_prefix,
                         hn_prefix = :hn_prefix,
                         expiry_alert_days = :expiry_alert_days,
                         queue_note = :queue_note,
                         updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'clinic_name' => $clinicName,
                    'clinic_address' => $clinicAddress ?: null,
                    'clinic_phone' => $clinicPhone ?: null,
                    'clinic_tax_id' => $clinicTaxId ?: null,
                    'receipt_logo_text' => $receiptLogoText ?: null,
                    'receipt_footer' => $receiptFooter ?: null,
                    'receipt_prefix' => $receiptPrefix ?: 'RC',
                    'hn_prefix' => $hnPrefix ?: 'HN',
                    'expiry_alert_days' => $expiryAlertDays,
                    'queue_note' => $queueNote ?: null,
                    'id' => $settingsId,
                ]);
            } else {
                db()->prepare(
                    'INSERT INTO system_settings (
                        clinic_name, clinic_address, clinic_phone, clinic_tax_id, receipt_logo_text, receipt_footer,
                        receipt_prefix, hn_prefix, expiry_alert_days, queue_note, created_at, updated_at
                     ) VALUES (
                        :clinic_name, :clinic_address, :clinic_phone, :clinic_tax_id, :receipt_logo_text, :receipt_footer,
                        :receipt_prefix, :hn_prefix, :expiry_alert_days, :queue_note, NOW(), NOW()
                     )'
                )->execute([
                    'clinic_name' => $clinicName,
                    'clinic_address' => $clinicAddress ?: null,
                    'clinic_phone' => $clinicPhone ?: null,
                    'clinic_tax_id' => $clinicTaxId ?: null,
                    'receipt_logo_text' => $receiptLogoText ?: null,
                    'receipt_footer' => $receiptFooter ?: null,
                    'receipt_prefix' => $receiptPrefix ?: 'RC',
                    'hn_prefix' => $hnPrefix ?: 'HN',
                    'expiry_alert_days' => $expiryAlertDays,
                    'queue_note' => $queueNote ?: null,
                ]);
            }

            flash('success', 'บันทึกตั้งค่าคลินิกเรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกตั้งค่าคลินิกได้: ' . $throwable->getMessage());
        }

        redirect('settings');
    }

    private function ensureSettingsSchema(): void
    {
        $columns = $this->tableColumns('system_settings');
        $alterStatements = [];

        if (!in_array('clinic_tax_id', $columns, true)) {
            $alterStatements[] = 'ADD COLUMN clinic_tax_id VARCHAR(50) NULL AFTER clinic_phone';
        }

        if (!in_array('receipt_logo_text', $columns, true)) {
            $alterStatements[] = 'ADD COLUMN receipt_logo_text VARCHAR(80) NULL AFTER clinic_tax_id';
        }

        if (!in_array('receipt_footer', $columns, true)) {
            $alterStatements[] = 'ADD COLUMN receipt_footer TEXT NULL AFTER receipt_logo_text';
        }

        if ($alterStatements) {
            db()->exec('ALTER TABLE system_settings ' . implode(', ', $alterStatements));
        }
    }

    private function tableColumns(string $tableName): array
    {
        $stmt = db()->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
        return array_column($stmt->fetchAll(), 'Field');
    }

    public function storePreset(): void
    {
        require_roles(['ADMIN']);
        $this->ensureSmartPresetSchema();

        $presetId = (int) ($_POST['preset_id'] ?? 0);
        $payload = [
            'preset_key' => preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_POST['preset_key'] ?? '')))),
            'label' => trim((string) ($_POST['label'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'theme' => trim((string) ($_POST['theme'] ?? 'preset-custom')),
            'service_codes' => trim((string) ($_POST['service_codes'] ?? '')),
            'item_codes_json' => $this->normalizePresetItems((string) ($_POST['item_codes'] ?? '')),
            'cc' => trim((string) ($_POST['cc'] ?? '')),
            'pi' => trim((string) ($_POST['pi'] ?? '')),
            'pe' => trim((string) ($_POST['pe'] ?? '')),
            'dx' => trim((string) ($_POST['dx'] ?? '')),
            'advice' => trim((string) ($_POST['advice'] ?? '')),
            'followup_days' => ($_POST['followup_days'] ?? '') !== '' ? max(0, (int) $_POST['followup_days']) : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 50),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($payload['preset_key'] === '' || $payload['label'] === '') {
            flash('error', 'กรุณากรอก key และชื่อ preset');
            redirect('settings');
        }

        try {
            if ($presetId > 0) {
                db()->prepare(
                    'UPDATE smart_exam_presets
                     SET preset_key = :preset_key, label = :label, description = :description, theme = :theme,
                         service_codes = :service_codes, item_codes_json = :item_codes_json, cc = :cc, pi = :pi,
                         pe = :pe, dx = :dx, advice = :advice, followup_days = :followup_days,
                         sort_order = :sort_order, is_active = :is_active, updated_at = NOW()
                     WHERE id = :id'
                )->execute($payload + ['id' => $presetId]);
            } else {
                db()->prepare(
                    'INSERT INTO smart_exam_presets (
                        preset_key, label, description, theme, service_codes, item_codes_json, cc, pi, pe, dx,
                        advice, followup_days, sort_order, is_active, created_at, updated_at
                     ) VALUES (
                        :preset_key, :label, :description, :theme, :service_codes, :item_codes_json, :cc, :pi, :pe, :dx,
                        :advice, :followup_days, :sort_order, :is_active, NOW(), NOW()
                     )'
                )->execute($payload);
            }

            flash('success', 'บันทึก Smart Exam preset เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึก preset ได้: ' . $throwable->getMessage());
        }

        redirect('settings');
    }

    private function ensureSmartPresetSchema(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS smart_exam_presets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_key VARCHAR(80) NOT NULL,
                label VARCHAR(120) NOT NULL,
                description TEXT NULL,
                theme VARCHAR(80) NULL,
                service_codes TEXT NULL,
                item_codes_json TEXT NULL,
                cc VARCHAR(255) NULL,
                pi TEXT NULL,
                pe TEXT NULL,
                dx VARCHAR(255) NULL,
                advice TEXT NULL,
                followup_days INT NULL,
                sort_order INT NOT NULL DEFAULT 50,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uk_smart_exam_presets_key (preset_key),
                KEY idx_smart_exam_presets_active (is_active, sort_order)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function smartPresets(): array
    {
        return db()->query(
            'SELECT *
             FROM smart_exam_presets
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    private function seedDefaultSmartPresets(): void
    {
        $count = (int) db()->query('SELECT COUNT(*) FROM smart_exam_presets')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $defaults = [
            ['wound_dressing', 'ทำแผล', 'เพิ่มค่าทำแผล พร้อมน้ำเกลือ ผ้าก๊อซ และคำแนะนำดูแลแผล', 'preset-wound', 'SRV002', '[{"code":"MED002","qty":1},{"code":"MED003","qty":2}]', 'มีแผล', 'มีแผลจากอุบัติเหตุ ไม่มีเลือดออกมาก', 'Wound clean, no active bleeding', 'Wound', 'ดูแลแผลให้แห้ง ทำความสะอาดตามคำแนะนำ และกลับมาพบเจ้าหน้าที่หากปวดบวมแดงมากขึ้น', 2, 10],
            ['injection', 'ฉีดยา', 'เพิ่มค่าฉีดยา พร้อมบันทึกคำแนะนำหลังฉีดเพื่อสรุปเคสได้เร็วขึ้น', 'preset-injection', 'SRV003', '[]', 'รับบริการฉีดยา', 'มารับบริการฉีดยาตามแผนการรักษา ไม่มีอาการผิดปกติระหว่างรอรับบริการ', 'General appearance good, no acute distress', 'Injection service', 'สังเกตอาการปวด บวม แดง หรือผื่นหลังฉีด หากมีอาการผิดปกติให้กลับมาพบเจ้าหน้าที่ทันที', null, 20],
            ['vital_signs', 'วัดสัญญาณชีพ', 'เพิ่มค่าบริการวัดสัญญาณชีพและช่วยเตรียมแบบฟอร์มสำหรับบันทึก vital signs', 'preset-vitals', 'SRV004', '[]', 'ติดตามอาการ', 'มาประเมินอาการและตรวจวัดสัญญาณชีพเบื้องต้น', 'General appearance fair', 'Observation', 'ติดตามอาการต่อเนื่องตามนัด และบันทึกสัญญาณชีพหากมีอาการเปลี่ยนแปลง', null, 30],
            ['followup', 'ติดตามอาการ', 'เพิ่มค่าตรวจทั่วไป พร้อมข้อความตัวอย่างสำหรับเคสนัดติดตามอาการ', 'preset-followup', 'SRV001', '[]', 'ติดตามอาการ', 'มาติดตามอาการหลังรับบริการครั้งก่อน อาการโดยรวมคงที่', 'General appearance stable', 'Follow up', 'รับประทานยาหรือปฏิบัติตามคำแนะนำเดิมต่อเนื่อง และกลับมาตามนัดครั้งถัดไป', 7, 40],
        ];

        $stmt = db()->prepare(
            'INSERT INTO smart_exam_presets (
                preset_key, label, description, theme, service_codes, item_codes_json, cc, pi, pe, dx,
                advice, followup_days, sort_order, is_active, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );

        foreach ($defaults as $preset) {
            $stmt->execute($preset);
        }
    }

    private function normalizePresetItems(string $raw): string
    {
        $items = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$code, $qty] = array_pad(preg_split('/[:x\* ]+/', $line) ?: [], 2, '1');
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }

            $items[] = ['code' => $code, 'qty' => max(0.01, (float) $qty)];
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
