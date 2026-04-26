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

        $settings = db()->query('SELECT * FROM system_settings ORDER BY id ASC LIMIT 1')->fetch();

        if (!$settings) {
            $settings = [
                'id' => 0,
                'clinic_name' => config('app.name'),
                'clinic_address' => '',
                'clinic_phone' => '',
                'receipt_prefix' => 'RC',
                'hn_prefix' => 'HN',
                'expiry_alert_days' => 30,
                'queue_note' => config('app.receipt_footer'),
            ];
        }

        $this->render('settings/index', [
            'pageTitle' => 'ตั้งค่าคลินิก',
            'settings' => $settings,
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);

        $settingsId = (int) ($_POST['settings_id'] ?? 0);
        $clinicName = trim((string) ($_POST['clinic_name'] ?? ''));
        $clinicAddress = trim((string) ($_POST['clinic_address'] ?? ''));
        $clinicPhone = trim((string) ($_POST['clinic_phone'] ?? ''));
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
                    'receipt_prefix' => $receiptPrefix ?: 'RC',
                    'hn_prefix' => $hnPrefix ?: 'HN',
                    'expiry_alert_days' => $expiryAlertDays,
                    'queue_note' => $queueNote ?: null,
                    'id' => $settingsId,
                ]);
            } else {
                db()->prepare(
                    'INSERT INTO system_settings (
                        clinic_name, clinic_address, clinic_phone, receipt_prefix, hn_prefix, expiry_alert_days, queue_note, created_at, updated_at
                     ) VALUES (
                        :clinic_name, :clinic_address, :clinic_phone, :receipt_prefix, :hn_prefix, :expiry_alert_days, :queue_note, NOW(), NOW()
                     )'
                )->execute([
                    'clinic_name' => $clinicName,
                    'clinic_address' => $clinicAddress ?: null,
                    'clinic_phone' => $clinicPhone ?: null,
                    'receipt_prefix' => $receiptPrefix ?: 'RC',
                    'hn_prefix' => $hnPrefix ?: 'HN',
                    'expiry_alert_days' => $expiryAlertDays,
                    'queue_note' => $queueNote ?: null,
                ]);
            }

            flash('success', 'บันทึกตั้งค่าคลินิกเรียบร้อย');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกตั้งค่าคลินิกได้: ' . $throwable->getMessage());
        }

        redirect('settings');
    }
}