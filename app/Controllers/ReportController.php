<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use DateTimeImmutable;

class ReportController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN']);

        $dailyDate = $this->normalizeDate((string) ($_GET['date'] ?? date('Y-m-d')));
        $monthValue = $this->normalizeMonth((string) ($_GET['month'] ?? date('Y-m')));

        $daily = $this->buildDailyReport($dailyDate);
        $monthly = $this->buildMonthlyReport($monthValue);

        $this->render('reports/index', [
            'pageTitle' => 'รายงานและสำรองข้อมูล',
            'dailyDate' => $dailyDate,
            'monthValue' => $monthValue,
            'daily' => $daily,
            'monthly' => $monthly,
        ]);
    }

    public function print(): void
    {
        require_roles(['ADMIN']);

        $type = trim((string) ($_GET['type'] ?? 'daily'));

        if ($type === 'monthly') {
            $monthValue = $this->normalizeMonth((string) ($_GET['month'] ?? date('Y-m')));
            $monthly = $this->buildMonthlyReport($monthValue);

            $this->render('reports/print_monthly', [
                'pageTitle' => 'พิมพ์รายงานประจำเดือน',
                'monthValue' => $monthValue,
                'monthly' => $monthly,
            ], 'layouts/blank');
            return;
        }

        $dailyDate = $this->normalizeDate((string) ($_GET['date'] ?? date('Y-m-d')));
        $daily = $this->buildDailyReport($dailyDate);

        $this->render('reports/print_daily', [
            'pageTitle' => 'พิมพ์รายงานประจำวัน',
            'dailyDate' => $dailyDate,
            'daily' => $daily,
        ], 'layouts/blank');
    }

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
            redirect('reports');
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

    private function buildDailyReport(string $date): array
    {
        $summaryStmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM visits WHERE DATE(visit_datetime) = ?) AS visit_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = ? AND status = "WAITING") AS waiting_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = ? AND status = "IN_SERVICE") AS in_service_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = ? AND status = "WAITING_PAYMENT") AS waiting_payment_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = ? AND status = "COMPLETED") AS completed_count,
                (SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE DATE(paid_at) = ? AND payment_status = "PAID") AS revenue_total'
        );
        $summaryStmt->execute([$date, $date, $date, $date, $date, $date]);
        $summary = $summaryStmt->fetch() ?: [];

        $visitsStmt = db()->prepare(
            'SELECT visits.id AS visit_id,
                    visits.visit_no,
                    visits.visit_datetime,
                    visits.chief_complaint,
                    patients.hn,
                    patients.first_name,
                    patients.last_name,
                    queue_entries.queue_no,
                    queue_entries.status,
                    payments.total_amount,
                    payments.payment_method,
                    payments.receipt_no
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
             WHERE DATE(visits.visit_datetime) = ?
             ORDER BY visits.visit_datetime ASC, visits.id ASC'
        );
        $visitsStmt->execute([$date]);

        $serviceStmt = db()->prepare(
            'SELECT services.service_name,
                    SUM(visit_services.qty) AS total_qty,
                    SUM(visit_services.line_total) AS total_income
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             INNER JOIN visits ON visits.id = visit_services.visit_id
             WHERE DATE(visits.visit_datetime) = ?
             GROUP BY services.id, services.service_name
             ORDER BY total_qty DESC, total_income DESC
             LIMIT 10'
        );
        $serviceStmt->execute([$date]);

        $paymentStmt = db()->prepare(
            'SELECT payment_method,
                    COUNT(*) AS payment_count,
                    SUM(total_amount) AS total_amount
             FROM payments
             WHERE DATE(paid_at) = ? AND payment_status = "PAID"
             GROUP BY payment_method
             ORDER BY total_amount DESC'
        );
        $paymentStmt->execute([$date]);

        return [
            'summary' => $summary,
            'visits' => $visitsStmt->fetchAll(),
            'popular_services' => $serviceStmt->fetchAll(),
            'payment_methods' => $paymentStmt->fetchAll(),
        ];
    }

    private function buildMonthlyReport(string $monthValue): array
    {
        $period = DateTimeImmutable::createFromFormat('Y-m-d', $monthValue . '-01') ?: new DateTimeImmutable('first day of this month');
        $startDate = $period->format('Y-m-01');
        $endDate = $period->format('Y-m-t');

        $summaryStmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM visits WHERE DATE(visit_datetime) BETWEEN ? AND ?) AS visit_count,
                (SELECT COUNT(*) FROM payments WHERE DATE(paid_at) BETWEEN ? AND ? AND payment_status = "PAID") AS paid_count,
                (SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE DATE(paid_at) BETWEEN ? AND ? AND payment_status = "PAID") AS revenue_total,
                (SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?) AS appointment_count'
        );
        $summaryStmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        $summary = $summaryStmt->fetch() ?: [];

        $revenueStmt = db()->prepare(
            'SELECT DATE(paid_at) AS paid_date,
                    COUNT(*) AS receipt_count,
                    SUM(total_amount) AS total_amount
             FROM payments
             WHERE DATE(paid_at) BETWEEN ? AND ? AND payment_status = "PAID"
             GROUP BY DATE(paid_at)
             ORDER BY paid_date ASC'
        );
        $revenueStmt->execute([$startDate, $endDate]);

        $serviceStmt = db()->prepare(
            'SELECT services.service_name,
                    SUM(visit_services.qty) AS total_qty,
                    SUM(visit_services.line_total) AS total_income
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             INNER JOIN visits ON visits.id = visit_services.visit_id
             WHERE DATE(visits.visit_datetime) BETWEEN ? AND ?
             GROUP BY services.id, services.service_name
             ORDER BY total_qty DESC, total_income DESC
             LIMIT 10'
        );
        $serviceStmt->execute([$startDate, $endDate]);

        $paymentStmt = db()->prepare(
            'SELECT payment_method,
                    COUNT(*) AS payment_count,
                    SUM(total_amount) AS total_amount
             FROM payments
             WHERE DATE(paid_at) BETWEEN ? AND ? AND payment_status = "PAID"
             GROUP BY payment_method
             ORDER BY total_amount DESC'
        );
        $paymentStmt->execute([$startDate, $endDate]);

        $topVisitsStmt = db()->prepare(
            'SELECT visits.visit_no,
                    visits.visit_datetime,
                    patients.hn,
                    patients.first_name,
                    patients.last_name,
                    payments.receipt_no,
                    payments.total_amount
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN payments ON payments.visit_id = visits.id
             WHERE DATE(visits.visit_datetime) BETWEEN ? AND ?
             ORDER BY visits.visit_datetime DESC, visits.id DESC
             LIMIT 20'
        );
        $topVisitsStmt->execute([$startDate, $endDate]);

        return [
            'summary' => $summary,
            'daily_revenue' => $revenueStmt->fetchAll(),
            'popular_services' => $serviceStmt->fetchAll(),
            'payment_methods' => $paymentStmt->fetchAll(),
            'recent_visits' => $topVisitsStmt->fetchAll(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'month_label' => $period->format('m/Y'),
        ];
    }

    private function normalizeDate(string $date): string
    {
        $normalized = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $normalized ? $normalized->format('Y-m-d') : date('Y-m-d');
    }

    private function normalizeMonth(string $monthValue): string
    {
        $normalized = DateTimeImmutable::createFromFormat('Y-m', $monthValue);
        return $normalized ? $normalized->format('Y-m') : date('Y-m');
    }

    private function exportPatients(): array
    {
        $rows = db()->query(
            'SELECT hn, first_name, last_name, gender, birth_date, phone, drug_allergy, underlying_disease
             FROM patients
             WHERE is_active = 1
             ORDER BY id DESC'
        )->fetchAll();

        return ['patients_export.csv', ['HN', 'ชื่อ', 'นามสกุล', 'เพศ', 'วันเกิด', 'เบอร์โทร', 'แพ้ยา', 'โรคประจำตัว'], $rows];
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

        return ['inventory_alerts_export.csv', ['รหัส', 'ชื่อรายการ', 'หน่วย', 'คงเหลือ', 'จุดเตือนต่ำสุด', 'หมดอายุใกล้สุด'], $rows];
    }
}
