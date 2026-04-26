<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        require_login();

        $todayStats = db()->query(
            'SELECT
                (SELECT COUNT(*) FROM visits WHERE DATE(visit_datetime) = CURDATE()) AS visit_count_today,
                (SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE DATE(paid_at) = CURDATE() AND payment_status = "PAID") AS revenue_today,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = "WAITING") AS waiting_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = "IN_SERVICE") AS in_service_count,
                (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = "WAITING_PAYMENT") AS waiting_payment_count,
                (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = "SCHEDULED") AS followup_today'
        )->fetch();

        $monthlyRevenue = db()->query(
            'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS month_label, COALESCE(SUM(total_amount), 0) AS total_amount
             FROM payments
             WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) AND payment_status = "PAID"
             GROUP BY DATE_FORMAT(paid_at, "%Y-%m")
             ORDER BY month_label ASC'
        )->fetchAll();

        $popularServices = db()->query(
            'SELECT services.service_name, SUM(visit_services.qty) AS total_qty, SUM(visit_services.line_total) AS total_income
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             INNER JOIN visits ON visits.id = visit_services.visit_id
             WHERE MONTH(visits.visit_datetime) = MONTH(CURDATE()) AND YEAR(visits.visit_datetime) = YEAR(CURDATE())
             GROUP BY services.id, services.service_name
             ORDER BY total_qty DESC
             LIMIT 5'
        )->fetchAll();

        $workQueues = db()->query(
            'SELECT queue_entries.status, queue_entries.queue_no, visits.id AS visit_id, visits.visit_no, visits.chief_complaint,
                    patients.hn, patients.first_name, patients.last_name
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             INNER JOIN patients ON patients.id = visits.patient_id
             WHERE queue_entries.queue_date = CURDATE()
               AND queue_entries.status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")
             ORDER BY
                CASE queue_entries.status
                    WHEN "IN_SERVICE" THEN 1
                    WHEN "WAITING" THEN 2
                    WHEN "WAITING_PAYMENT" THEN 3
                END,
                queue_entries.queue_no ASC
             LIMIT 8'
        )->fetchAll();

        $todayAppointments = db()->query(
            'SELECT appointments.appointment_date, patients.hn, patients.first_name, patients.last_name, appointments.purpose
             FROM appointments
             INNER JOIN patients ON patients.id = appointments.patient_id
             WHERE appointments.appointment_date = CURDATE() AND appointments.status = "SCHEDULED"
             ORDER BY appointments.id ASC
             LIMIT 5'
        )->fetchAll();

        $lowStocks = db()->query(
            'SELECT inventory_items.item_name,
                    inventory_items.unit_name,
                    inventory_items.reorder_level,
                    COALESCE(SUM(inventory_batches.qty_balance), 0) AS qty_balance
             FROM inventory_items
             LEFT JOIN inventory_batches ON inventory_batches.item_id = inventory_items.id
             WHERE inventory_items.is_active = 1
             GROUP BY inventory_items.id, inventory_items.item_name, inventory_items.unit_name, inventory_items.reorder_level
             HAVING qty_balance <= inventory_items.reorder_level
             ORDER BY qty_balance ASC
             LIMIT 5'
        )->fetchAll();

        $expiryAlerts = db()->query(
            'SELECT inventory_items.item_name, inventory_batches.lot_no, inventory_batches.expiry_date, inventory_batches.qty_balance
             FROM inventory_batches
             INNER JOIN inventory_items ON inventory_items.id = inventory_batches.item_id
             WHERE inventory_batches.qty_balance > 0
               AND inventory_batches.expiry_date IS NOT NULL
               AND inventory_batches.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY inventory_batches.expiry_date ASC
             LIMIT 5'
        )->fetchAll();

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'todayStats' => $todayStats,
            'monthlyRevenue' => $monthlyRevenue,
            'popularServices' => $popularServices,
            'workQueues' => $workQueues,
            'todayAppointments' => $todayAppointments,
            'lowStocks' => $lowStocks,
            'expiryAlerts' => $expiryAlerts,
        ]);
    }
}
