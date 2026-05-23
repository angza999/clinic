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

        $pdo = db();

        $waitingRows = $pdo->query(
            'SELECT visits.id AS visit_id, visits.visit_no, visits.visit_datetime, patients.hn,
                    patients.first_name, patients.last_name, queue_entries.id AS queue_id,
                    queue_entries.queue_no, queue_entries.status, queue_entries.updated_at AS sent_to_payment_at,
                    COALESCE(service_totals.total_service, 0) AS total_service,
                    COALESCE(service_totals.service_count, 0) AS service_count,
                    COALESCE(item_totals.total_item, 0) AS total_item,
                    COALESCE(item_totals.item_count, 0) AS item_count,
                    (COALESCE(service_totals.total_service, 0) + COALESCE(item_totals.total_item, 0)) AS grand_total
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             INNER JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_id, SUM(line_total) AS total_service, COUNT(*) AS service_count
                FROM visit_services
                GROUP BY visit_id
             ) AS service_totals ON service_totals.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_id, SUM(line_total) AS total_item, COUNT(*) AS item_count
                FROM visit_item_usages
                GROUP BY visit_id
             ) AS item_totals ON item_totals.visit_id = visits.id
             WHERE queue_entries.status = "WAITING_PAYMENT"
             ORDER BY queue_entries.updated_at ASC, queue_entries.queue_no ASC'
        )->fetchAll();

        $receiptRows = $pdo->query(
            'SELECT payments.id AS payment_id, payments.visit_id, payments.receipt_no, payments.subtotal_service,
                    payments.subtotal_item, payments.discount_amount, payments.total_amount, payments.paid_amount,
                    payments.change_amount, payments.payment_method, payments.payment_status, payments.paid_at,
                    visits.visit_no, visits.visit_datetime, patients.hn, patients.first_name, patients.last_name,
                    queue_entries.queue_no, users.full_name AS cashier_name
             FROM payments
             INNER JOIN visits ON visits.id = payments.visit_id
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN users ON users.id = payments.paid_by
             WHERE payments.payment_status IN ("PAID", "VOID")
             ORDER BY payments.paid_at DESC, payments.id DESC
             LIMIT 100'
        )->fetchAll();

        $todaySummary = $pdo->query(
            'SELECT COALESCE(SUM(CASE WHEN payment_status = "PAID" THEN 1 ELSE 0 END), 0) AS paid_count,
                    COALESCE(SUM(CASE WHEN payment_status = "PAID" THEN total_amount ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(CASE WHEN payment_status = "PAID" THEN discount_amount ELSE 0 END), 0) AS discount_total,
                    COALESCE(SUM(CASE WHEN payment_status = "VOID" THEN 1 ELSE 0 END), 0) AS void_count
             FROM payments
             WHERE DATE(paid_at) = CURDATE()
               AND payment_status IN ("PAID", "VOID")'
        )->fetch();

        $methodRows = $pdo->query(
            'SELECT payment_method, COUNT(*) AS method_count, COALESCE(SUM(total_amount), 0) AS method_total
             FROM payments
             WHERE DATE(paid_at) = CURDATE()
               AND payment_status = "PAID"
             GROUP BY payment_method'
        )->fetchAll();

        $methodSummary = [];
        foreach ($methodRows as $methodRow) {
            $methodSummary[(string) $methodRow['payment_method']] = $methodRow;
        }

        $waitingTotal = array_sum(array_map(static fn(array $row): float => (float) $row['grand_total'], $waitingRows));

        $this->render('payments/index', [
            'pageTitle' => 'การเงินและรับชำระ',
            'waitingRows' => $waitingRows,
            'receiptRows' => $receiptRows,
            'todaySummary' => $todaySummary ?: [],
            'methodSummary' => $methodSummary,
            'latestReceipt' => $receiptRows[0] ?? null,
            'waitingTotal' => $waitingTotal,
            'pageStyles' => [app_url('assets/css/payments.css')],
            'pageScripts' => [app_url('assets/js/payments.js')],
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'CASHIER']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $discountRaw = trim((string) ($_POST['discount_amount'] ?? '0'));
        $paidRaw = trim((string) ($_POST['paid_amount'] ?? '0'));
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'CASH'));
        $receiptPaymentId = 0;

        if ($visitId <= 0) {
            flash('error', 'ไม่พบข้อมูลการชำระเงินที่ต้องการบันทึก');
            redirect('payments');
        }

        try {
            if (!in_array($paymentMethod, ['CASH', 'TRANSFER', 'QR'], true)) {
                throw new RuntimeException('วิธีชำระเงินไม่ถูกต้อง');
            }

            if ($discountRaw !== '' && !is_numeric($discountRaw)) {
                throw new RuntimeException('ส่วนลดต้องเป็นตัวเลขเท่านั้น');
            }

            if ($paidRaw !== '' && !is_numeric($paidRaw)) {
                throw new RuntimeException('ยอดรับชำระต้องเป็นตัวเลขเท่านั้น');
            }

            $discountAmount = max(0, (float) ($discountRaw === '' ? 0 : $discountRaw));
            $paidAmount = max(0, (float) ($paidRaw === '' ? 0 : $paidRaw));

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

            $grossTotal = $serviceTotal + $itemTotal;

            if ($grossTotal <= 0) {
                throw new RuntimeException('ยังไม่มีรายการคิดเงิน กรุณาตรวจสอบข้อมูลจากห้องตรวจก่อน');
            }

            if ($discountAmount > $grossTotal) {
                throw new RuntimeException('ส่วนลดมากกว่ายอดรวมไม่ได้');
            }

            $totalAmount = max(0, $grossTotal - $discountAmount);

            if ($paymentMethod !== 'CASH') {
                $paidAmount = $totalAmount;
                $changeAmount = 0.0;
            } else {
                $changeAmount = max(0, $paidAmount - $totalAmount);
            }

            if ($paymentMethod === 'CASH' && $paidAmount < $totalAmount) {
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
