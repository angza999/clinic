<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\NumberGenerator;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'CASHIER']);

        $rows = db()->query(
            'SELECT visits.id AS visit_id, visits.visit_no, visits.visit_datetime, patients.hn,
                    patients.first_name, patients.last_name, queue_entries.id AS queue_id, queue_entries.queue_no, queue_entries.status,
                    COALESCE(service_totals.total_service, 0) AS total_service,
                    COALESCE(item_totals.total_item, 0) AS total_item,
                    (COALESCE(service_totals.total_service, 0) + COALESCE(item_totals.total_item, 0)) AS grand_total,
                    payments.id AS payment_id, payments.receipt_no, payments.payment_status, payments.total_amount AS payment_total
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             INNER JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_id, SUM(line_total) AS total_service
                FROM visit_services
                GROUP BY visit_id
             ) AS service_totals ON service_totals.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_id, SUM(line_total) AS total_item
                FROM visit_item_usages
                GROUP BY visit_id
             ) AS item_totals ON item_totals.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
             WHERE queue_entries.status IN ("WAITING_PAYMENT", "COMPLETED")
             ORDER BY queue_entries.status ASC, visits.visit_datetime DESC'
        )->fetchAll();

        $this->render('payments/index', [
            'pageTitle' => 'การเงินและรับชำระ',
            'rows' => $rows,
            'pageStyles' => [app_url('assets/css/payments.css')],
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'CASHIER']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $discountAmount = (float) ($_POST['discount_amount'] ?? 0);
        $paidAmount = (float) ($_POST['paid_amount'] ?? 0);
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'CASH'));
        $receiptPaymentId = 0;

        if ($visitId <= 0) {
            flash('error', 'ไม่พบข้อมูลการชำระเงินที่ต้องการบันทึก');
            redirect('payments');
        }

        try {
            $queue = $this->findQueueByVisit($visitId);
            if (!$queue) {
                throw new RuntimeException('ไม่พบคิวของผู้รับบริการรายการนี้');
            }

            if ($queue['status'] !== 'WAITING_PAYMENT') {
                throw new RuntimeException('รายการนี้ยังไม่อยู่ในสถานะรอชำระเงิน');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $serviceStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) AS total_amount FROM visit_services WHERE visit_id = :visit_id');
            $serviceStmt->execute(['visit_id' => $visitId]);
            $serviceTotal = (float) ($serviceStmt->fetch()['total_amount'] ?? 0);

            $itemStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) AS total_amount FROM visit_item_usages WHERE visit_id = :visit_id');
            $itemStmt->execute(['visit_id' => $visitId]);
            $itemTotal = (float) ($itemStmt->fetch()['total_amount'] ?? 0);

            if (($serviceTotal + $itemTotal) <= 0) {
                throw new RuntimeException('ยังไม่มีรายการคิดเงิน กรุณาตรวจสอบข้อมูลจากห้องตรวจก่อน');
            }

            $totalAmount = max(0, $serviceTotal + $itemTotal - $discountAmount);
            $changeAmount = max(0, $paidAmount - $totalAmount);

            if ($paidAmount < $totalAmount) {
                throw new RuntimeException('ยอดรับชำระน้อยกว่ายอดที่ต้องชำระ');
            }

            $receiptNo = NumberGenerator::nextReceiptNo();

            $pdo->prepare(
                'INSERT INTO payments (
                    visit_id, receipt_no, subtotal_service, subtotal_item, discount_amount, total_amount,
                    paid_amount, change_amount, payment_method, payment_status, paid_at, paid_by, created_at, updated_at
                 ) VALUES (
                    :visit_id, :receipt_no, :subtotal_service, :subtotal_item, :discount_amount, :total_amount,
                    :paid_amount, :change_amount, :payment_method, "PAID", NOW(), :paid_by, NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    receipt_no = VALUES(receipt_no),
                    subtotal_service = VALUES(subtotal_service),
                    subtotal_item = VALUES(subtotal_item),
                    discount_amount = VALUES(discount_amount),
                    total_amount = VALUES(total_amount),
                    paid_amount = VALUES(paid_amount),
                    change_amount = VALUES(change_amount),
                    payment_method = VALUES(payment_method),
                    payment_status = "PAID",
                    paid_at = NOW(),
                    paid_by = VALUES(paid_by),
                    updated_at = NOW()'
            )->execute([
                'visit_id' => $visitId,
                'receipt_no' => $receiptNo,
                'subtotal_service' => $serviceTotal,
                'subtotal_item' => $itemTotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'paid_by' => current_user()['id'],
            ]);

            $pdo->prepare(
                'UPDATE queue_entries
                 SET status = "COMPLETED", finished_at = NOW(), updated_at = NOW()
                 WHERE visit_id = :visit_id'
            )->execute(['visit_id' => $visitId]);

            $paymentIdStmt = $pdo->prepare('SELECT id FROM payments WHERE visit_id = :visit_id LIMIT 1');
            $paymentIdStmt->execute(['visit_id' => $visitId]);
            $receiptPaymentId = (int) $paymentIdStmt->fetchColumn();

            $pdo->commit();
            flash('success', 'รับชำระเงินและปิดเคสเรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            $receiptPaymentId = 0;
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถบันทึกการชำระเงินได้: ' . $throwable->getMessage());
        }

        if ($receiptPaymentId > 0) {
            redirect('receipt', ['id' => $receiptPaymentId, 'source' => 'payments']);
        }

        redirect('payments');
    }

    public function sendBack(): void
    {
        require_roles(['ADMIN', 'CASHIER']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);

        try {
            $queue = $this->findQueueByVisit($visitId);
            if (!$queue) {
                throw new RuntimeException('ไม่พบคิวที่ต้องการส่งกลับห้องตรวจ');
            }

            if ($queue['status'] !== 'WAITING_PAYMENT') {
                throw new RuntimeException('ส่งกลับห้องตรวจได้เฉพาะเคสที่อยู่ในสถานะรอชำระเงิน');
            }

            db()->prepare(
                'UPDATE queue_entries
                 SET status = "IN_SERVICE", updated_at = NOW()
                 WHERE visit_id = :visit_id'
            )->execute(['visit_id' => $visitId]);

            flash('success', 'ส่งเคสกลับไปที่ห้องตรวจเรียบร้อยแล้ว');
            redirect('visit-edit', ['id' => $visitId]);
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถส่งเคสกลับห้องตรวจได้: ' . $throwable->getMessage());
        }

        redirect('payments');
    }

    public function receipt(): void
    {
        require_roles(['ADMIN', 'CASHIER', 'NURSE']);

        $paymentId = (int) ($_GET['id'] ?? 0);
        $source = trim((string) ($_GET['source'] ?? 'payments'));
        $stmt = db()->prepare(
            'SELECT payments.*, visits.visit_no, visits.visit_datetime, visits.advice, visits.followup_date,
                    patients.hn, patients.first_name, patients.last_name,
                    users.full_name AS cashier_name
             FROM payments
             INNER JOIN visits ON visits.id = payments.visit_id
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN users ON users.id = payments.paid_by
             WHERE payments.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            http_response_code(404);
            exit('Receipt not found');
        }

        $serviceLines = db()->prepare(
            'SELECT services.service_name AS item_name, visit_services.qty, visit_services.unit_price, visit_services.line_total
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visit_services.visit_id = :visit_id'
        );
        $serviceLines->execute(['visit_id' => $payment['visit_id']]);

        $itemLines = db()->prepare(
            'SELECT inventory_items.item_name, visit_item_usages.qty, visit_item_usages.unit_price, visit_item_usages.line_total
             FROM visit_item_usages
             INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
             WHERE visit_item_usages.visit_id = :visit_id'
        );
        $itemLines->execute(['visit_id' => $payment['visit_id']]);

        $this->render('payments/receipt', [
            'pageTitle' => 'ใบเสร็จรับเงิน',
            'payment' => $payment,
            'serviceLines' => $serviceLines->fetchAll(),
            'itemLines' => $itemLines->fetchAll(),
            'source' => $source,
        ], 'layouts/blank');
    }

    private function findQueueByVisit(int $visitId): ?array
    {
        $stmt = db()->prepare(
            'SELECT *
             FROM queue_entries
             WHERE visit_id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);

        $queue = $stmt->fetch();
        return $queue ?: null;
    }
}
