<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use RuntimeException;
use Throwable;

class VisitController extends Controller
{
    public function edit(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);

        $visitId = (int) ($_GET['id'] ?? 0);
        $visit = $this->findVisit($visitId);

        if (!$visit) {
            http_response_code(404);
            exit('Visit not found');
        }

        $addedServicesStmt = db()->prepare(
            'SELECT visit_services.*, services.service_name
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visit_services.visit_id = :visit_id
             ORDER BY visit_services.id DESC'
        );
        $addedServicesStmt->execute(['visit_id' => $visitId]);
        $addedServices = $addedServicesStmt->fetchAll();

        $usedItemsStmt = db()->prepare(
            'SELECT visit_item_usages.*, inventory_items.item_name, inventory_items.unit_name
             FROM visit_item_usages
             INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
             WHERE visit_item_usages.visit_id = :visit_id
             ORDER BY visit_item_usages.id DESC'
        );
        $usedItemsStmt->execute(['visit_id' => $visitId]);
        $usedItems = $usedItemsStmt->fetchAll();

        $serviceTotal = array_sum(array_map(static fn(array $row): float => (float) $row['line_total'], $addedServices));
        $itemTotal = array_sum(array_map(static fn(array $row): float => (float) $row['line_total'], $usedItems));
        $serviceCount = count($addedServices);
        $itemCount = count($usedItems);
        $grandTotal = $serviceTotal + $itemTotal;
        $patientId = (int) $visit['patient_id'];

        $this->render('visits/edit', [
            'pageTitle' => 'ประวัติเคส',
            'visit' => $visit,
            'addedServices' => $addedServices,
            'usedItems' => $usedItems,
            'serviceTotal' => $serviceTotal,
            'itemTotal' => $itemTotal,
            'serviceCount' => $serviceCount,
            'itemCount' => $itemCount,
            'grandTotal' => $grandTotal,
            'hasBillableItems' => $grandTotal > 0,
            'isAdminReview' => has_role(['ADMIN']),
            'visitTimeline' => $this->visitTimelineForPatient($patientId),
            'serviceHistory' => $this->serviceHistoryForPatient($patientId),
            'drugHistory' => $this->drugHistoryForPatient($patientId),
            'paymentHistory' => $this->paymentHistoryForPatient($patientId),
            'auditLogs' => $this->auditLogsForVisit($visitId),
        ]);
    }

    public function saveClinical(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $visit = $this->findVisit($visitId);

        if (!$visit) {
            flash('error', 'ไม่พบข้อมูลการรักษาที่ต้องการบันทึก');
            redirect('queue');
        }

        if (!is_visit_editable_status($visit['status'] ?? null)) {
            flash('error', 'เคสนี้ไม่ได้อยู่ในสถานะกำลังตรวจ กรุณาเรียกคิวเข้าตรวจก่อน หรือส่งกลับจากการเงินก่อนแก้ไข');
            $this->redirectAfterVisitAction($visitId);
        }

        $data = [
            'visit_id' => $visitId,
            'chief_complaint' => trim((string) ($_POST['chief_complaint'] ?? '')),
            'nursing_note' => trim((string) ($_POST['nursing_note'] ?? '')),
            'advice' => trim((string) ($_POST['advice'] ?? '')),
            'followup_date' => trim((string) ($_POST['followup_date'] ?? '')),
            'bp_systolic' => $_POST['bp_systolic'] !== '' ? (int) $_POST['bp_systolic'] : null,
            'bp_diastolic' => $_POST['bp_diastolic'] !== '' ? (int) $_POST['bp_diastolic'] : null,
            'temp_c' => $_POST['temp_c'] !== '' ? (float) $_POST['temp_c'] : null,
            'pulse_rate' => $_POST['pulse_rate'] !== '' ? (int) $_POST['pulse_rate'] : null,
            'resp_rate' => $_POST['resp_rate'] !== '' ? (int) $_POST['resp_rate'] : null,
            'spo2' => $_POST['spo2'] !== '' ? (int) $_POST['spo2'] : null,
            'weight_kg' => $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
        ];

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $pdo->prepare(
                'UPDATE visits
                 SET chief_complaint = :chief_complaint,
                     nursing_note = :nursing_note,
                     advice = :advice,
                     followup_date = :followup_date,
                     updated_at = NOW()
                 WHERE id = :visit_id'
            )->execute([
                'chief_complaint' => $data['chief_complaint'] !== '' ? $data['chief_complaint'] : null,
                'nursing_note' => $data['nursing_note'] !== '' ? $data['nursing_note'] : null,
                'advice' => $data['advice'] !== '' ? $data['advice'] : null,
                'followup_date' => $data['followup_date'] !== '' ? $data['followup_date'] : null,
                'visit_id' => $visitId,
            ]);

            $pdo->prepare(
                'INSERT INTO visit_vitals (
                    visit_id, bp_systolic, bp_diastolic, temp_c, pulse_rate, resp_rate, spo2, weight_kg, recorded_by, recorded_at, created_at, updated_at
                 ) VALUES (
                    :visit_id, :bp_systolic, :bp_diastolic, :temp_c, :pulse_rate, :resp_rate, :spo2, :weight_kg, :recorded_by, NOW(), NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    bp_systolic = VALUES(bp_systolic),
                    bp_diastolic = VALUES(bp_diastolic),
                    temp_c = VALUES(temp_c),
                    pulse_rate = VALUES(pulse_rate),
                    resp_rate = VALUES(resp_rate),
                    spo2 = VALUES(spo2),
                    weight_kg = VALUES(weight_kg),
                    recorded_by = VALUES(recorded_by),
                    recorded_at = NOW(),
                    updated_at = NOW()'
            )->execute([
                'visit_id' => $visitId,
                'bp_systolic' => $data['bp_systolic'],
                'bp_diastolic' => $data['bp_diastolic'],
                'temp_c' => $data['temp_c'],
                'pulse_rate' => $data['pulse_rate'],
                'resp_rate' => $data['resp_rate'],
                'spo2' => $data['spo2'],
                'weight_kg' => $data['weight_kg'],
                'recorded_by' => current_user()['id'],
            ]);

            if ($data['followup_date'] !== '') {
                $pdo->prepare(
                    'INSERT INTO appointments (
                        patient_id, visit_id, appointment_date, purpose, status, note, created_at, updated_at
                     ) VALUES (
                        :patient_id, :visit_id, :appointment_date, :purpose, "SCHEDULED", :note, NOW(), NOW()
                     )'
                )->execute([
                    'patient_id' => $visit['patient_id'],
                    'visit_id' => $visitId,
                    'appointment_date' => $data['followup_date'],
                    'purpose' => 'นัดติดตามอาการ',
                    'note' => $data['advice'] !== '' ? $data['advice'] : null,
                ]);
            }

            $this->syncFollowupAppointment($pdo, $visitId);

            $workflowAction = (string) ($_POST['workflow_action'] ?? 'save');
            if ($workflowAction === 'save_and_payment') {
                if (!$this->visitHasBillableItems($visitId)) {
                    $pdo->commit();
                    flash('error', 'บันทึกข้อมูลแล้ว แต่ยังส่งการเงินไม่ได้ กรุณาเพิ่มบริการหรือยา/เวชภัณฑ์/อุปกรณ์อย่างน้อย 1 รายการก่อน');
                    redirect('visit-edit', ['id' => $visitId]);
                }

                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "WAITING_PAYMENT", updated_at = NOW()
                     WHERE visit_id = :visit_id'
                )->execute(['visit_id' => $visitId]);
            }

            $pdo->commit();

            if ($workflowAction === 'save_and_payment') {
                flash('success', 'บันทึกข้อมูลและส่งต่อไปยังการเงินเรียบร้อยแล้ว');
                redirect('queue');
            }

            flash('success', 'บันทึกข้อมูลการรักษาเรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถบันทึกข้อมูลการรักษาได้: ' . $throwable->getMessage());
        }

        $this->redirectAfterVisitAction($visitId);
    }

    private function syncFollowupAppointment(\PDO $pdo, int $visitId): void
    {
        $stmt = $pdo->prepare(
            'SELECT visits.patient_id, visits.followup_date, visits.advice
             FROM visits
             WHERE visits.id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);
        $visit = $stmt->fetch();

        if (!$visit) {
            return;
        }

        if (empty($visit['followup_date'])) {
            $pdo->prepare(
                'DELETE FROM appointments
                 WHERE visit_id = :visit_id
                   AND status = "SCHEDULED"'
            )->execute(['visit_id' => $visitId]);
            return;
        }

        $existingStmt = $pdo->prepare(
            'SELECT id
             FROM appointments
             WHERE visit_id = :visit_id
               AND status = "SCHEDULED"
             ORDER BY id ASC
             LIMIT 1'
        );
        $existingStmt->execute(['visit_id' => $visitId]);
        $appointmentId = (int) $existingStmt->fetchColumn();

        $payload = [
            'patient_id' => $visit['patient_id'],
            'visit_id' => $visitId,
            'appointment_date' => $visit['followup_date'],
            'purpose' => 'นัดติดตามอาการ',
            'note' => !empty($visit['advice']) ? $visit['advice'] : null,
        ];

        if ($appointmentId > 0) {
            $payload['id'] = $appointmentId;
            $pdo->prepare(
                'UPDATE appointments
                 SET patient_id = :patient_id,
                     visit_id = :visit_id,
                     appointment_date = :appointment_date,
                     purpose = :purpose,
                     note = :note,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute($payload);

            $pdo->prepare(
                'DELETE FROM appointments
                 WHERE visit_id = :visit_id
                   AND status = "SCHEDULED"
                   AND id <> :id'
            )->execute([
                'visit_id' => $visitId,
                'id' => $appointmentId,
            ]);
            return;
        }

        $pdo->prepare(
            'INSERT INTO appointments (
                patient_id, visit_id, appointment_date, purpose, status, note, created_at, updated_at
             ) VALUES (
                :patient_id, :visit_id, :appointment_date, :purpose, "SCHEDULED", :note, NOW(), NOW()
             )'
        )->execute($payload);
    }

    public function addService(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $this->assertVisitEditable($visitId);

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));

        $serviceStmt = db()->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
        $serviceStmt->execute(['id' => $serviceId]);
        $service = $serviceStmt->fetch();

        if (!$service || $visitId <= 0) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'ไม่พบบริการที่เลือก',
                ], 422);
            }

            flash('error', 'ไม่พบบริการที่เลือก');
            redirect('queue');
        }

        $lineTotal = $qty * (float) $service['price'];

        db()->prepare(
            'INSERT INTO visit_services (visit_id, service_id, qty, unit_price, line_total, created_at, updated_at)
             VALUES (:visit_id, :service_id, :qty, :unit_price, :line_total, NOW(), NOW())'
        )->execute([
            'visit_id' => $visitId,
            'service_id' => $serviceId,
            'qty' => $qty,
            'unit_price' => $service['price'],
            'line_total' => $lineTotal,
        ]);

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'ok' => true,
                'message' => 'เพิ่มบริการเรียบร้อย',
                'summary' => $this->visitOrderSummary($visitId),
            ]);
        }

        flash('success', 'เพิ่มบริการเรียบร้อยแล้ว');
        $this->redirectAfterVisitAction($visitId);
    }

    public function removeService(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $serviceLineId = (int) ($_POST['service_line_id'] ?? 0);
        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $this->assertVisitEditable($visitId);

        db()->prepare('DELETE FROM visit_services WHERE id = :id')->execute(['id' => $serviceLineId]);

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'ok' => true,
                'message' => 'ลบบริการเรียบร้อย',
                'summary' => $this->visitOrderSummary($visitId),
            ]);
        }

        flash('success', 'ลบบริการเรียบร้อยแล้ว');
        $this->redirectAfterVisitAction($visitId);
    }

    public function addItemUsage(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $this->assertVisitEditable($visitId);

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = (float) ($_POST['qty'] ?? 0);
        $usageNote = trim((string) ($_POST['usage_note'] ?? ''));

        if ($visitId <= 0 || $itemId <= 0 || $qty <= 0) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'กรุณาเลือกรายการยา/เวชภัณฑ์/อุปกรณ์และจำนวนให้ถูกต้อง',
                ], 422);
            }

            flash('error', 'กรุณาเลือกรายการยา/เวชภัณฑ์/อุปกรณ์และจำนวนให้ถูกต้อง');
            $this->redirectAfterVisitAction($visitId);
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $itemStmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
            $itemStmt->execute(['id' => $itemId]);
            $item = $itemStmt->fetch();

            if (!$item) {
                throw new RuntimeException('ไม่พบรายการคลังที่เลือก');
            }

            $remainingQty = $qty;
            $batchStmt = $pdo->prepare(
                'SELECT * FROM inventory_batches
                 WHERE item_id = :item_id AND qty_balance > 0
                 ORDER BY expiry_date IS NULL ASC, expiry_date ASC, received_date ASC, id ASC
                 FOR UPDATE'
            );
            $batchStmt->execute(['item_id' => $itemId]);
            $batches = $batchStmt->fetchAll();

            $availableQty = array_sum(array_map(static fn(array $batch): float => (float) $batch['qty_balance'], $batches));
            if ($availableQty < $qty) {
                throw new RuntimeException('สต็อกคงเหลือไม่เพียงพอ');
            }

            $lineTotal = $qty * (float) $item['default_price'];
            $pdo->prepare(
                'INSERT INTO visit_item_usages (visit_id, item_id, qty, unit_price, line_total, usage_note, created_at, updated_at)
                 VALUES (:visit_id, :item_id, :qty, :unit_price, :line_total, :usage_note, NOW(), NOW())'
            )->execute([
                'visit_id' => $visitId,
                'item_id' => $itemId,
                'qty' => $qty,
                'unit_price' => $item['default_price'],
                'line_total' => $lineTotal,
                'usage_note' => $usageNote !== '' ? $usageNote : null,
            ]);

            $usageId = (int) $pdo->lastInsertId();

            foreach ($batches as $batch) {
                if ($remainingQty <= 0) {
                    break;
                }

                $takeQty = min($remainingQty, (float) $batch['qty_balance']);
                $newBalance = (float) $batch['qty_balance'] - $takeQty;

                $pdo->prepare(
                    'UPDATE inventory_batches SET qty_balance = :qty_balance, updated_at = NOW() WHERE id = :id'
                )->execute([
                    'qty_balance' => $newBalance,
                    'id' => $batch['id'],
                ]);

                $pdo->prepare(
                    'INSERT INTO stock_movements (
                        batch_id, item_id, movement_type, qty, unit_cost, reference_type, reference_id, note,
                        movement_datetime, created_by, created_at, updated_at
                     ) VALUES (
                        :batch_id, :item_id, "OUT", :qty, :unit_cost, "VISIT_USAGE", :reference_id, :note,
                        NOW(), :created_by, NOW(), NOW()
                     )'
                )->execute([
                    'batch_id' => $batch['id'],
                    'item_id' => $itemId,
                    'qty' => $takeQty,
                    'unit_cost' => $batch['cost_per_unit'],
                    'reference_id' => $usageId,
                    'note' => $usageNote !== '' ? $usageNote : null,
                    'created_by' => current_user()['id'],
                ]);

                $remainingQty -= $takeQty;
            }

            $pdo->commit();
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => true,
                    'message' => 'เพิ่มยา/เวชภัณฑ์เรียบร้อย',
                    'summary' => $this->visitOrderSummary($visitId),
                ]);
            }

            flash('success', 'เพิ่มรายการยา/เวชภัณฑ์/อุปกรณ์เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'ไม่สามารถเพิ่มยา/เวชภัณฑ์ได้: ' . $throwable->getMessage(),
                ], 422);
            }

            flash('error', 'ไม่สามารถเพิ่มรายการยา/เวชภัณฑ์/อุปกรณ์ได้: ' . $throwable->getMessage());
        }

        $this->redirectAfterVisitAction($visitId);
    }

    public function removeItemUsage(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $usageId = (int) ($_POST['usage_id'] ?? 0);
        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $this->assertVisitEditable($visitId);

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $usageStmt = $pdo->prepare('SELECT * FROM visit_item_usages WHERE id = :id LIMIT 1');
            $usageStmt->execute(['id' => $usageId]);
            $usage = $usageStmt->fetch();

            if (!$usage) {
                throw new RuntimeException('ไม่พบรายการที่ต้องการลบ');
            }

            $movementsStmt = $pdo->prepare(
                'SELECT * FROM stock_movements WHERE reference_type = "VISIT_USAGE" AND reference_id = :reference_id'
            );
            $movementsStmt->execute(['reference_id' => $usageId]);
            $movements = $movementsStmt->fetchAll();

            foreach ($movements as $movement) {
                $pdo->prepare(
                    'UPDATE inventory_batches SET qty_balance = qty_balance + :qty, updated_at = NOW() WHERE id = :id'
                )->execute([
                    'qty' => $movement['qty'],
                    'id' => $movement['batch_id'],
                ]);
            }

            $pdo->prepare('DELETE FROM stock_movements WHERE reference_type = "VISIT_USAGE" AND reference_id = :reference_id')->execute([
                'reference_id' => $usageId,
            ]);
            $pdo->prepare('DELETE FROM visit_item_usages WHERE id = :id')->execute(['id' => $usageId]);

            $pdo->commit();
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => true,
                    'message' => 'ลบยา/เวชภัณฑ์เรียบร้อย',
                    'summary' => $this->visitOrderSummary($visitId),
                ]);
            }

            flash('success', 'ลบรายการยา/เวชภัณฑ์/อุปกรณ์เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'ไม่สามารถลบยา/เวชภัณฑ์ได้: ' . $throwable->getMessage(),
                ], 422);
            }

            flash('error', 'ไม่สามารถลบรายการได้: ' . $throwable->getMessage());
        }

        $this->redirectAfterVisitAction($visitId);
    }

    public function markReadyForPayment(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $visit = $this->findVisit($visitId);

        if (!$visit) {
            flash('error', 'ไม่พบข้อมูลการรักษา');
            redirect('queue');
        }

        if (!is_visit_editable_status($visit['status'] ?? null)) {
            flash('error', 'เคสนี้ไม่ได้อยู่ในสถานะกำลังตรวจ');
        $this->redirectAfterVisitAction($visitId);
        }

        if (!$this->visitHasBillableItems($visitId)) {
            flash('error', 'ยังไม่มีรายการคิดเงิน กรุณาเพิ่มบริการหรือยา/เวชภัณฑ์/อุปกรณ์ก่อนส่งชำระเงิน');
            redirect('visit-edit', ['id' => $visitId]);
        }

        db()->prepare(
            'UPDATE queue_entries
             SET status = "WAITING_PAYMENT", updated_at = NOW()
             WHERE visit_id = :visit_id'
        )->execute(['visit_id' => $visitId]);

        flash('success', 'ส่งเคสไปยังห้องการเงินเรียบร้อยแล้ว');
        redirect('queue');
    }

    private function findVisit(int $visitId): array|false
    {
        $stmt = db()->prepare(
            'SELECT visits.*, patients.id AS patient_id, patients.hn, patients.first_name, patients.last_name, patients.gender, patients.birth_date,
                    patients.phone, patients.drug_allergy, patients.underlying_disease,
                    queue_entries.id AS queue_id, queue_entries.queue_no, queue_entries.status,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c,
                    visit_vitals.pulse_rate, visit_vitals.resp_rate, visit_vitals.spo2, visit_vitals.weight_kg
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
             WHERE visits.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $visitId]);

        return $stmt->fetch();
    }

    private function redirectAfterVisitAction(int $visitId): void
    {
        $returnTo = (string) ($_POST['return_to'] ?? '');

        if ($returnTo === 'queue-exam') {
            redirect('queue-exam', ['id' => $visitId]);
        }

        redirect('visit-edit', ['id' => $visitId]);
    }

    private function visitTimelineForPatient(int $patientId): array
    {
        $stmt = db()->prepare(
            'SELECT visits.id, visits.visit_no, visits.visit_datetime, visits.chief_complaint, visits.diagnosis,
                    queue_entries.queue_no, queue_entries.status,
                    COALESCE(payments.total_amount, 0) AS paid_total,
                    payments.receipt_no
             FROM visits
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id AND payments.payment_status = "PAID"
             WHERE visits.patient_id = :patient_id
             ORDER BY visits.visit_datetime DESC, visits.id DESC
             LIMIT 20'
        );
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function serviceHistoryForPatient(int $patientId): array
    {
        $stmt = db()->prepare(
            'SELECT visits.id AS visit_id, visits.visit_no, visits.visit_datetime,
                    services.service_name, visit_services.qty, visit_services.unit_price, visit_services.line_total
             FROM visit_services
             INNER JOIN visits ON visits.id = visit_services.visit_id
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visits.patient_id = :patient_id
             ORDER BY visits.visit_datetime DESC, visit_services.id DESC
             LIMIT 80'
        );
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function drugHistoryForPatient(int $patientId): array
    {
        $stmt = db()->prepare(
            'SELECT visits.id AS visit_id, visits.visit_no, visits.visit_datetime,
                    inventory_items.item_name, inventory_items.unit_name,
                    visit_item_usages.qty, visit_item_usages.usage_note, visit_item_usages.line_total
             FROM visit_item_usages
             INNER JOIN visits ON visits.id = visit_item_usages.visit_id
             INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
             WHERE visits.patient_id = :patient_id
             ORDER BY visits.visit_datetime DESC, visit_item_usages.id DESC
             LIMIT 80'
        );
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function paymentHistoryForPatient(int $patientId): array
    {
        $stmt = db()->prepare(
            'SELECT payments.*, visits.visit_no, visits.visit_datetime, users.full_name AS cashier_name
             FROM payments
             INNER JOIN visits ON visits.id = payments.visit_id
             LEFT JOIN users ON users.id = payments.paid_by
             WHERE visits.patient_id = :patient_id
             ORDER BY payments.paid_at DESC, payments.id DESC
             LIMIT 50'
        );
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function auditLogsForVisit(int $visitId): array
    {
        $stmt = db()->prepare(
            'SELECT audit_logs.*, users.full_name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE (audit_logs.record_id = :visit_id_exact AND audit_logs.table_name IN (
                    "visits", "queue_entries", "visit_services", "visit_item_usages", "payments", "prescriptions", "medication_print_logs"
                ))
                OR audit_logs.detail_json LIKE :visit_id_json
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT 40'
        );
        $stmt->execute([
            'visit_id_exact' => $visitId,
            'visit_id_json' => '%"visit_id":' . $visitId . '%',
        ]);

        return $stmt->fetchAll();
    }

    private function recentVisitsForPatient(int $patientId, int $excludeVisitId): array
    {
        $stmt = db()->prepare(
            'SELECT visits.id, visits.visit_no, visits.visit_datetime, visits.chief_complaint, visits.nursing_note, visits.advice,
                    queue_entries.queue_no, queue_entries.status,
                    COALESCE((SELECT SUM(line_total) FROM visit_services WHERE visit_id = visits.id), 0) AS service_total,
                    COALESCE((SELECT SUM(line_total) FROM visit_item_usages WHERE visit_id = visits.id), 0) AS item_total
             FROM visits
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
               AND visits.id <> :exclude_visit_id
             ORDER BY visits.visit_datetime DESC, visits.id DESC
             LIMIT 3'
        );
        $stmt->execute([
            'patient_id' => $patientId,
            'exclude_visit_id' => $excludeVisitId,
        ]);

        return $stmt->fetchAll();
    }

    private function visitHasBillableItems(int $visitId): bool
    {
        $stmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM visit_services WHERE visit_id = :visit_id) AS service_count,
                (SELECT COUNT(*) FROM visit_item_usages WHERE visit_id = :visit_id) AS item_count'
        );
        $stmt->execute(['visit_id' => $visitId]);
        $result = $stmt->fetch() ?: ['service_count' => 0, 'item_count' => 0];

        return ((int) $result['service_count'] + (int) $result['item_count']) > 0;
    }

    private function visitOrderSummary(int $visitId): array
    {
        $serviceStmt = db()->prepare(
            'SELECT visit_services.id, services.service_name, visit_services.qty, visit_services.line_total
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visit_services.visit_id = :visit_id
             ORDER BY visit_services.id DESC'
        );
        $serviceStmt->execute(['visit_id' => $visitId]);
        $serviceLines = $serviceStmt->fetchAll();

        $itemStmt = db()->prepare(
            'SELECT visit_item_usages.id, inventory_items.item_name, inventory_items.unit_name, visit_item_usages.qty, visit_item_usages.line_total
             FROM visit_item_usages
             INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
             WHERE visit_item_usages.visit_id = :visit_id
             ORDER BY visit_item_usages.id DESC'
        );
        $itemStmt->execute(['visit_id' => $visitId]);
        $itemLines = $itemStmt->fetchAll();

        $serviceTotal = array_sum(array_map(static fn(array $row): float => (float) $row['line_total'], $serviceLines));
        $itemTotal = array_sum(array_map(static fn(array $row): float => (float) $row['line_total'], $itemLines));

        return [
            'visitId' => $visitId,
            'serviceCount' => count($serviceLines),
            'itemCount' => count($itemLines),
            'serviceTotal' => $serviceTotal,
            'itemTotal' => $itemTotal,
            'grandTotal' => $serviceTotal + $itemTotal,
            'serviceTotalText' => format_money($serviceTotal),
            'itemTotalText' => format_money($itemTotal),
            'grandTotalText' => format_money($serviceTotal + $itemTotal),
            'services' => array_map(static fn(array $line): array => [
                'id' => (int) $line['id'],
                'name' => (string) $line['service_name'],
                'qty' => (string) $line['qty'],
                'qtyText' => (string) $line['qty'],
                'lineTotal' => (float) $line['line_total'],
                'lineTotalText' => format_money($line['line_total']),
            ], $serviceLines),
            'items' => array_map(static fn(array $line): array => [
                'id' => (int) $line['id'],
                'name' => (string) $line['item_name'],
                'unitName' => (string) ($line['unit_name'] ?? ''),
                'qty' => (string) $line['qty'],
                'qtyText' => format_money($line['qty']),
                'lineTotal' => (float) $line['line_total'],
                'lineTotalText' => format_money($line['line_total']),
            ], $itemLines),
        ];
    }

    private function isAjaxRequest(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

        return strcasecmp($requestedWith, 'XMLHttpRequest') === 0 || str_contains($accept, 'application/json');
    }

    private function jsonResponse(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function assertVisitEditable(int $visitId): void
    {
        $visit = $this->findVisit($visitId);

        if (!$visit) {
            flash('error', 'ไม่พบข้อมูลการรักษา');
            redirect('queue');
        }

        if (!is_visit_editable_status($visit['status'] ?? null)) {
            flash('error', 'แก้ไขข้อมูลได้เฉพาะเคสที่อยู่ระหว่างตรวจเท่านั้น');
            redirect('visit-edit', ['id' => $visitId]);
        }
    }

    private function statusGuidance(string $status, bool $hasBillableItems): array
    {
        return match ($status) {
            'WAITING' => [
                'title' => 'เคสนี้ยังไม่ได้เริ่มตรวจ',
                'message' => 'กรุณาเรียกคิวเข้าตรวจก่อน จึงจะบันทึกข้อมูลการรักษาและเพิ่มรายการได้',
                'class' => 'warning',
            ],
            'IN_SERVICE' => [
                'title' => 'กำลังตรวจ',
                'message' => $hasBillableItems
                    ? 'ขั้นตอนถัดไป: ตรวจสอบข้อมูลให้ครบ แล้วกดบันทึกและส่งชำระเงิน'
                    : 'ขั้นตอนถัดไป: บันทึกข้อมูลให้ครบ และเพิ่มบริการหรือยา/เวชภัณฑ์/อุปกรณ์ก่อนส่งชำระเงิน',
                'class' => 'info',
            ],
            'WAITING_PAYMENT' => [
                'title' => 'เคสนี้รอชำระเงิน',
                'message' => 'ยอดถูกส่งไปการเงินแล้ว หากต้องการแก้รายการให้ส่งกลับห้องตรวจก่อน',
                'class' => 'secondary',
            ],
            'COMPLETED' => [
                'title' => 'เคสนี้ปิดเรียบร้อยแล้ว',
                'message' => 'ข้อมูลถูกปิดเคสแล้ว หน้านี้เปิดไว้เพื่อดูรายละเอียดเท่านั้น',
                'class' => 'success',
            ],
            'CANCELLED' => [
                'title' => 'เคสนี้ถูกยกเลิกแล้ว',
                'message' => 'คิวนี้ไม่ควรถูกแก้ไขต่อใน flow ปกติ',
                'class' => 'danger',
            ],
            default => [
                'title' => 'สถานะยังไม่ชัดเจน',
                'message' => 'กรุณากลับไปตรวจสอบที่หน้าคิวอีกครั้ง',
                'class' => 'warning',
            ],
        };
    }

    private function nursingTemplates(): array
    {
        return [
            ['label' => 'ซักประวัติและประเมินอาการ', 'text' => 'ซักประวัติและประเมินอาการเบื้องต้นเรียบร้อย'],
            ['label' => 'วัดสัญญาณชีพ', 'text' => 'วัดสัญญาณชีพครบถ้วนและบันทึกผลเรียบร้อย'],
            ['label' => 'ทำแผล', 'text' => 'ทำแผล/ล้างแผลตามขั้นตอน และประเมินแผลหลังทำเรียบร้อย'],
            ['label' => 'ให้ยาและอุปกรณ์', 'text' => 'ให้ยาและอุปกรณ์ตามรายการ พร้อมอธิบายวิธีใช้เรียบร้อย'],
            ['label' => 'ให้คำแนะนำ', 'text' => 'ให้คำแนะนำการดูแลตนเองและอาการที่ควรกลับมาพบเจ้าหน้าที่'],
        ];
    }

    private function adviceTemplates(): array
    {
        return [
            ['label' => 'พักผ่อนและดื่มน้ำ', 'text' => 'พักผ่อนให้เพียงพอ ดื่มน้ำมาก ๆ และสังเกตอาการต่อเนื่อง'],
            ['label' => 'รับประทานยาตามแผน', 'text' => 'รับประทานยาตามที่ได้รับอย่างครบถ้วนและตรงเวลา'],
            ['label' => 'อาการแย่ลงให้กลับมา', 'text' => 'หากมีไข้สูง หอบเหนื่อย ปวดมากขึ้น หรืออาการแย่ลง ให้กลับมาพบเจ้าหน้าที่ทันที'],
            ['label' => 'ดูแลแผล', 'text' => 'ดูแลแผลให้แห้งสะอาด หลีกเลี่ยงการแกะเกา และกลับมาพบเจ้าหน้าที่หากมีบวมแดงหรือมีหนอง'],
            ['label' => 'มาตามนัด', 'text' => 'มาตามวันนัดเพื่อติดตามอาการและประเมินผลการรักษา'],
        ];
    }
}
