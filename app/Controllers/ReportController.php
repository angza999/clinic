<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class ReportController extends Controller
{
    public function export(): void
    {
        require_roles(['ADMIN']);

        $type = trim((string) ($_GET['type'] ?? ''));

        [$filename, $headers, $rows] = match ($type) {
            'patients' => $this->exportPatients(),
            'visits_today' => $this->exportVisitsToday(),
            'revenue_month' => $this->exportRevenueMonth(),
            'inventory_alerts' => $this->exportInventoryAlerts(),
            default => [null, [], []],
        };

        if ($filename === null) {
            flash('error', 'ไม่พบรูปแบบ export ที่ร้องขอ');
            redirect('dashboard');
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    private function exportPatients(): array
    {
        $rows = db()->query(
            'SELECT hn, first_name, last_name, gender, birth_date, phone, drug_allergy, underlying_disease
             FROM patients
             WHERE is_active = 1
             ORDER BY id DESC'
        )->fetchAll();

        return ['patients_export.csv', ['HN', 'ชื่อ', 'นามสกุล', 'เพศ', 'วันเกิด', 'โทรศัพท์', 'แพ้ยา', 'โรคประจำตัว'], $rows];
    }

    private function exportVisitsToday(): array
    {
        $rows = db()->query(
            'SELECT visits.visit_no, patients.hn, patients.first_name, patients.last_name, visits.visit_datetime, queue_entries.status
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             WHERE DATE(visits.visit_datetime) = CURDATE()
             ORDER BY visits.visit_datetime ASC'
        )->fetchAll();

        return ['visits_today_export.csv', ['VN', 'HN', 'ชื่อ', 'นามสกุล', 'วันเวลา', 'สถานะ'], $rows];
    }

    private function exportRevenueMonth(): array
    {
        $rows = db()->query(
            'SELECT receipt_no, paid_at, total_amount, payment_method
             FROM payments
             WHERE MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE())
             ORDER BY paid_at DESC'
        )->fetchAll();

        return ['revenue_month_export.csv', ['เลขที่ใบเสร็จ', 'วันที่ชำระ', 'ยอดรวม', 'วิธีชำระ'], $rows];
    }

    private function exportInventoryAlerts(): array
    {
        $rows = db()->query(
            'SELECT inventory_items.item_code, inventory_items.item_name, inventory_items.unit_name,
                    COALESCE(SUM(inventory_batches.qty_balance), 0) AS qty_balance,
                    inventory_items.reorder_level,
                    MIN(CASE WHEN inventory_batches.qty_balance > 0 THEN inventory_batches.expiry_date END) AS nearest_expiry
             FROM inventory_items
             LEFT JOIN inventory_batches ON inventory_batches.item_id = inventory_items.id
             WHERE inventory_items.is_active = 1
             GROUP BY inventory_items.id
             ORDER BY qty_balance ASC'
        )->fetchAll();

        return ['inventory_alerts_export.csv', ['รหัส', 'ชื่อรายการ', 'หน่วย', 'คงเหลือ', 'จุดสั่งซื้อ', 'หมดอายุใกล้สุด'], $rows];
    }
}

