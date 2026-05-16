<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClinicWorkflow;
use App\Core\Controller;
use App\Core\NumberGenerator;
use PDO;
use RuntimeException;
use Throwable;

class QueueController extends Controller
{
    public function index(): void
    {
        require_login();
        $this->ensureSmartExamSchema();

        $todayQueues = $this->todayQueues();
        $patients = $this->patientsForQueue();
        $todayAppointments = $this->todayAppointments();
        $nextWaiting = $this->nextWaitingFrom($todayQueues);
        $currentQueue = $this->currentServiceFrom($todayQueues);
        $activeVisit = $this->resolveActiveVisit($todayQueues);

        $this->render('queue/index', [
            'pageTitle' => 'ระบบคิว',
            'todayQueues' => $todayQueues,
            'patients' => $patients,
            'todayAppointments' => $todayAppointments,
            'nextWaiting' => $nextWaiting,
            'currentQueue' => $currentQueue,
            'activeVisit' => $activeVisit,
            'quickPresets' => $this->quickPresets(),
            'prefillPatientId' => (int) ($_GET['patient_id'] ?? 0),
            'pageStyles' => [app_url('assets/css/smart-exam.css'), app_url('assets/css/queue.css')],
            'pageScripts' => [app_url('assets/js/queue.js')],
        ]);
    }

    public function exam(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();

        $visitId = (int) ($_GET['id'] ?? ($_GET['visit_id'] ?? 0));
        if ($visitId <= 0) {
            flash('error', 'ไม่พบเคสที่ต้องการเปิดหน้าตรวจ');
            redirect('queue');
        }

        $visit = $this->findWorkflowVisit($visitId);
        if (!$visit) {
            flash('error', 'ไม่พบข้อมูลเคสที่ต้องการเปิดหน้าตรวจ');
            redirect('queue');
        }

        if (!in_array($visit['status'], ['WAITING', 'IN_SERVICE'], true)) {
            flash('error', 'เคสนี้ไม่อยู่ในสถานะที่สามารถเปิด Smart Exam ได้');
            redirect('queue', ['visit_id' => $visitId]);
        }

        if ($visit['status'] === 'WAITING') {
            db()->prepare(
                'UPDATE queue_entries
                 SET status = "IN_SERVICE", called_at = COALESCE(called_at, NOW()), updated_at = NOW()
                 WHERE visit_id = :visit_id'
            )->execute(['visit_id' => $visitId]);

            $visit = $this->findWorkflowVisit($visitId);
        }

        $services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY service_name ASC')->fetchAll();
        $expiryAlertDays = (int) system_setting('expiry_alert_days', 30);
        $items = db()->query(
            'SELECT inventory_items.*, COALESCE(stock_totals.qty_balance, 0) AS qty_balance, stock_totals.nearest_expiry
             FROM inventory_items
             LEFT JOIN (
                SELECT item_id, SUM(qty_balance) AS qty_balance, MIN(CASE WHEN qty_balance > 0 THEN expiry_date END) AS nearest_expiry
                FROM inventory_batches
                GROUP BY item_id
             ) AS stock_totals ON stock_totals.item_id = inventory_items.id
             WHERE inventory_items.is_active = 1
             ORDER BY inventory_items.item_name ASC'
        )->fetchAll();
        $frequentServices = db()->query(
            'SELECT services.id, services.service_name, services.price, COALESCE(SUM(visit_services.qty), 0) AS total_qty
             FROM services
             LEFT JOIN visit_services ON visit_services.service_id = services.id
             WHERE services.is_active = 1
             GROUP BY services.id, services.service_name, services.price
             ORDER BY total_qty DESC, services.service_name ASC
             LIMIT 4'
        )->fetchAll();
        $frequentItems = db()->query(
            'SELECT inventory_items.id, inventory_items.item_name, inventory_items.unit_name, inventory_items.default_price, inventory_items.reorder_level,
                    COALESCE(stock_totals.qty_balance, 0) AS qty_balance, stock_totals.nearest_expiry,
                    COALESCE(SUM(visit_item_usages.qty), 0) AS total_qty
             FROM inventory_items
             LEFT JOIN visit_item_usages ON visit_item_usages.item_id = inventory_items.id
             LEFT JOIN (
                SELECT item_id, SUM(qty_balance) AS qty_balance, MIN(CASE WHEN qty_balance > 0 THEN expiry_date END) AS nearest_expiry
                FROM inventory_batches
                GROUP BY item_id
             ) AS stock_totals ON stock_totals.item_id = inventory_items.id
             WHERE inventory_items.is_active = 1
             GROUP BY inventory_items.id, inventory_items.item_name, inventory_items.unit_name, inventory_items.default_price, inventory_items.reorder_level, stock_totals.qty_balance, stock_totals.nearest_expiry
             ORDER BY total_qty DESC, inventory_items.item_name ASC
             LIMIT 4'
        )->fetchAll();

        $this->render('queue/exam', [
            'pageTitle' => 'Smart Exam',
            'pageTopbarMode' => 'compact',
            'activeVisit' => $visit,
            'quickPresets' => $this->quickPresets(),
            'patientSnapshot' => $this->patientSnapshot((int) ($visit['patient_id'] ?? 0), $visitId),
            'services' => $services,
            'items' => $items,
            'frequentServices' => $frequentServices,
            'frequentItems' => $frequentItems,
            'expiryAlertDays' => $expiryAlertDays,
            'pageStyles' => [app_url('assets/css/smart-exam.css')],
            'pageScripts' => [app_url('assets/js/smart-exam.js')],
        ]);
    }

    public function display(): void
    {
        require_login();

        $todayQueues = $this->todayQueues();
        $currentQueue = $this->currentServiceFrom($todayQueues);
        $nextWaiting = $this->nextWaitingFrom($todayQueues);
        $waitingList = array_values(array_filter(
            $todayQueues,
            static fn(array $queue): bool => $queue['status'] === 'WAITING'
        ));

        if ($nextWaiting && !empty($waitingList)) {
            array_shift($waitingList);
        }

        $waitingList = array_slice($waitingList, 0, 6);

        $this->render('queue/display', [
            'pageTitle' => 'หน้าจอเรียกคิว',
            'todayQueues' => $todayQueues,
            'currentQueue' => $currentQueue,
            'nextWaiting' => $nextWaiting,
            'waitingList' => $waitingList,
        ], 'layouts/display');
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));

        if ($patientId <= 0) {
            flash('error', 'กรุณาเลือกคนไข้ก่อนรับเคส');
            redirect('queue');
        }

        try {
            $result = ClinicWorkflow::createVisitAndQueue($patientId, $chiefComplaint, (int) current_user()['id']);
            flash('success', 'รับเคสใหม่เรียบร้อยแล้ว');
            redirect('queue', ['visit_id' => $result['visit_id']]);
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถรับเคสใหม่ได้: ' . $throwable->getMessage());
            redirect('queue');
        }
    }

    public function quickRegister(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $fullName = trim((string) ($_POST['quick_full_name'] ?? ''));
        $phone = trim((string) ($_POST['quick_phone'] ?? ''));
        $gender = trim((string) ($_POST['quick_gender'] ?? ''));
        $chiefComplaint = trim((string) ($_POST['quick_chief_complaint'] ?? ''));
        $drugAllergy = trim((string) ($_POST['quick_drug_allergy'] ?? ''));
        $confirmDuplicate = (int) ($_POST['confirm_duplicate'] ?? 0) === 1;
        [$firstName, $lastName] = $this->splitQuickPatientName($fullName);

        if ($firstName === '') {
            remember_old_input($_POST);
            flash('error', 'กรุณากรอกชื่อคนไข้ก่อนลงทะเบียนด่วน');
            redirect('queue');
        }

        $duplicates = $this->quickRegisterDuplicates($firstName, $lastName, $phone);
        if ($duplicates !== [] && !$confirmDuplicate) {
            remember_old_input($_POST);
            $names = array_map(
                static fn(array $patient): string => trim($patient['hn'] . ' ' . $patient['first_name'] . ' ' . $patient['last_name']),
                array_slice($duplicates, 0, 3)
            );
            flash('error', 'พบคนไข้เดิมที่อาจซ้ำ: ' . implode(', ', $names) . ' หากตรวจแล้วไม่ซ้ำ ให้ติ๊ก "ยืนยันสร้างคนไข้ใหม่" แล้วส่งอีกครั้ง');
            redirect('queue');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();
            $hn = NumberGenerator::nextHn();

            $pdo->prepare(
                'INSERT INTO patients (
                    hn, first_name, last_name, gender, phone, drug_allergy, is_active, created_at, updated_at
                 ) VALUES (
                    :hn, :first_name, :last_name, :gender, :phone, :drug_allergy, 1, NOW(), NOW()
                 )'
            )->execute([
                'hn' => $hn,
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : '-',
                'gender' => in_array($gender, ['M', 'F', 'O'], true) ? $gender : null,
                'phone' => $phone !== '' ? $phone : null,
                'drug_allergy' => $drugAllergy !== '' ? $drugAllergy : null,
            ]);

            $patientId = (int) $pdo->lastInsertId();
            $workflow = ClinicWorkflow::createVisitAndQueue($patientId, $chiefComplaint, (int) current_user()['id']);

            $pdo->commit();
            clear_old_input();
            flash('success', 'ลงทะเบียนด่วนและเปิด Smart Exam เรียบร้อยแล้ว HN: ' . $hn);
            redirect('queue-exam', ['id' => $workflow['visit_id']]);
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            remember_old_input($_POST);
            flash('error', 'ไม่สามารถลงทะเบียนด่วนได้: ' . $throwable->getMessage());
            redirect('queue');
        }
    }

    public function appointmentCheckin(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        if ($appointmentId <= 0) {
            flash('error', 'ไม่พบนัดหมายที่ต้องการรับเข้าคิว');
            redirect('queue');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $appointmentStmt = $pdo->prepare(
                'SELECT appointments.*, patients.hn, patients.first_name, patients.last_name
                 FROM appointments
                 INNER JOIN patients ON patients.id = appointments.patient_id
                 WHERE appointments.id = :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $appointmentStmt->execute(['id' => $appointmentId]);
            $appointment = $appointmentStmt->fetch();

            if (!$appointment) {
                throw new RuntimeException('ไม่พบนัดหมายนี้');
            }

            if ($appointment['status'] !== 'SCHEDULED') {
                throw new RuntimeException('นัดหมายนี้ไม่ได้อยู่ในสถานะรอรับบริการ');
            }

            $activeQueue = $this->activeQueueForPatientToday((int) $appointment['patient_id']);
            if ($activeQueue) {
                $pdo->commit();
                flash('success', 'คนไข้มีคิววันนี้อยู่แล้ว เปิด Smart Exam จากคิวเดิมได้เลย');
                redirect('queue-exam', ['id' => (int) $activeQueue['visit_id']]);
            }

            $chiefComplaint = trim((string) ($appointment['purpose'] ?? ''));
            if ($chiefComplaint === '') {
                $chiefComplaint = 'นัดติดตามอาการ';
            }

            $workflow = ClinicWorkflow::createVisitAndQueue(
                (int) $appointment['patient_id'],
                $chiefComplaint,
                (int) current_user()['id']
            );

            $pdo->prepare(
                'UPDATE appointments
                 SET status = "COMPLETED",
                     visit_id = :visit_id,
                     note = COALESCE(note, :note),
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'visit_id' => $workflow['visit_id'],
                'note' => 'รับเข้าคิวจากหน้านัดหมาย',
                'id' => $appointmentId,
            ]);

            $pdo->commit();
            flash('success', 'รับนัดเข้าคิวและเปิด Smart Exam เรียบร้อยแล้ว');
            redirect('queue-exam', ['id' => $workflow['visit_id']]);
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถรับนัดเข้าคิวได้: ' . $throwable->getMessage());
            redirect('queue');
        }
    }

    public function applyPreset(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $presetKey = trim((string) ($_POST['preset_key'] ?? ''));
        $preset = $this->quickPresets()[$presetKey] ?? null;

        if ($visitId <= 0 || !$preset) {
            flash('error', 'ไม่พบข้อมูล preset ที่ต้องการใช้งาน');
            redirect('queue');
        }

        $visit = $this->findWorkflowVisit($visitId);
        if (!$visit) {
            flash('error', 'ไม่พบเคสที่ต้องการใช้ preset');
            redirect('queue');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($visit['status'] === 'WAITING') {
                $this->assertTransitionAllowed($visit, 'IN_SERVICE');
                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "IN_SERVICE", called_at = COALESCE(called_at, NOW()), updated_at = NOW()
                     WHERE visit_id = :visit_id'
                )->execute(['visit_id' => $visitId]);
                $visit['status'] = 'IN_SERVICE';
            }

            if (!$this->presetAlreadyApplied($pdo, $visitId, $presetKey)) {
                foreach ($preset['services'] as $serviceCode) {
                    $this->insertPresetService($pdo, $visitId, $serviceCode, $presetKey);
                }

                foreach ($preset['items'] as $item) {
                    $this->insertPresetItemUsage(
                        $pdo,
                        $visitId,
                        $item['code'],
                        (float) $item['qty'],
                        $presetKey,
                        $preset['label']
                    );
                }
            }

            $this->saveQuickClinical($pdo, $visitId, $visit, $preset);

            $pdo->commit();
            flash('success', 'เพิ่ม preset ' . $preset['label'] . ' เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถเพิ่ม preset ได้: ' . $throwable->getMessage());
        }

        redirect('queue-exam', ['id' => $visitId, 'preset' => $presetKey]);
    }

    public function smartFinish(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $finishMode = trim((string) ($_POST['finish_mode'] ?? 'payment'));
        $visit = $this->findWorkflowVisit($visitId);

        if (!$visit) {
            flash('error', 'ไม่พบเคสที่ต้องการบันทึก');
            redirect('queue');
        }

        $input = [
            'cc' => trim((string) ($_POST['cc'] ?? '')),
            'pi' => trim((string) ($_POST['pi'] ?? '')),
            'pe' => trim((string) ($_POST['pe'] ?? '')),
            'dx' => trim((string) ($_POST['dx'] ?? '')),
            'weight_kg' => $_POST['weight_kg'] ?? null,
            'temp_c' => $_POST['temp_c'] ?? null,
            'pulse_rate' => $_POST['pulse_rate'] ?? null,
            'resp_rate' => $_POST['resp_rate'] ?? null,
            'bp_systolic' => $_POST['bp_systolic'] ?? null,
            'bp_diastolic' => $_POST['bp_diastolic'] ?? null,
            'spo2' => $_POST['spo2'] ?? null,
            'advice' => trim((string) ($_POST['advice'] ?? '')),
            'followup_date' => trim((string) ($_POST['followup_date'] ?? '')),
        ];

        $shouldReceivePayment = $finishMode === 'receive_payment';
        $shouldWaitPayment = in_array($finishMode, ['payment', 'waiting_payment'], true);

        if ($input['cc'] === '' || $input['dx'] === '') {
            flash('error', 'กรุณากรอก CC และ Dx ก่อนบันทึกและจบเคส');
            redirect('queue-exam', ['id' => $visitId]);
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();
            $billingTotals = $this->billingTotals($pdo, $visitId);

            $this->saveQuickClinical($pdo, $visitId, $visit, [], $input);

            $vitalExistsStmt = $pdo->prepare('SELECT id FROM visit_vitals WHERE visit_id = :visit_id LIMIT 1');
            $vitalExistsStmt->execute(['visit_id' => $visitId]);
            $vitalId = $vitalExistsStmt->fetchColumn();

            $vitalPayload = [
                'visit_id' => $visitId,
                'bp_systolic' => $input['bp_systolic'] !== '' ? (int) $input['bp_systolic'] : null,
                'bp_diastolic' => $input['bp_diastolic'] !== '' ? (int) $input['bp_diastolic'] : null,
                'temp_c' => $input['temp_c'] !== '' ? (float) $input['temp_c'] : null,
                'pulse_rate' => $input['pulse_rate'] !== '' ? (int) $input['pulse_rate'] : null,
                'resp_rate' => $input['resp_rate'] !== '' ? (int) $input['resp_rate'] : null,
                'spo2' => $input['spo2'] !== '' ? (int) $input['spo2'] : null,
                'weight_kg' => $input['weight_kg'] !== '' ? (float) $input['weight_kg'] : null,
                'recorded_by' => (int) current_user()['id'],
            ];

            if ($vitalId) {
                $pdo->prepare(
                    'UPDATE visit_vitals SET
                        bp_systolic = :bp_systolic,
                        bp_diastolic = :bp_diastolic,
                        temp_c = :temp_c,
                        pulse_rate = :pulse_rate,
                        resp_rate = :resp_rate,
                        spo2 = :spo2,
                        weight_kg = :weight_kg,
                        recorded_by = :recorded_by,
                        recorded_at = NOW(),
                        updated_at = NOW()
                     WHERE visit_id = :visit_id'
                )->execute($vitalPayload);
            } else {
                $pdo->prepare(
                    'INSERT INTO visit_vitals (
                        visit_id, bp_systolic, bp_diastolic, temp_c, pulse_rate, resp_rate, spo2, weight_kg,
                        recorded_by, recorded_at, created_at, updated_at
                     ) VALUES (
                        :visit_id, :bp_systolic, :bp_diastolic, :temp_c, :pulse_rate, :resp_rate, :spo2, :weight_kg,
                        :recorded_by, NOW(), NOW(), NOW()
                     )'
                )->execute($vitalPayload);
            }

            $this->syncFollowupAppointment($pdo, $visitId);

            $targetStatus = ($finishMode === 'no_charge' || $shouldReceivePayment) ? 'COMPLETED' : 'WAITING_PAYMENT';
            $this->assertTransitionAllowed($visit, $targetStatus);

            if (($shouldReceivePayment || $shouldWaitPayment) && !$this->visitHasBillableItems($visitId)) {
                throw new RuntimeException('ยังไม่มีรายการคิดเงิน กรุณาเพิ่มบริการหรืออุปกรณ์ก่อนส่งชำระเงิน');
            }

            $paymentResult = null;
            if ($shouldReceivePayment) {
                $paymentResult = $this->recordSmartPayment($pdo, $visitId, $billingTotals);
            }

            $pdo->prepare(
                'UPDATE queue_entries
                 SET status = :status,
                     finished_at = CASE WHEN :finished_status = "COMPLETED" THEN NOW() ELSE finished_at END,
                     updated_at = NOW()
                 WHERE visit_id = :visit_id'
            )->execute([
                'status' => $targetStatus,
                'finished_status' => $targetStatus,
                'visit_id' => $visitId,
            ]);

            $pdo->commit();

            if ($shouldReceivePayment) {
                flash('success', 'รับเงินและปิดเคสเรียบร้อยแล้ว เลขที่ใบเสร็จ ' . ($paymentResult['receipt_no'] ?? '-'));
                redirect('receipt', [
                    'id' => (int) ($paymentResult['payment_id'] ?? 0),
                    'source' => 'smart_exam',
                ]);
            }

            if ($targetStatus === 'WAITING_PAYMENT') {
                flash('success', 'บันทึกเคสเรียบร้อย และส่งต่อไปชำระเงินแล้ว');
                if (has_role(['ADMIN', 'CASHIER'])) {
                    redirect('payments');
                }

                redirect('queue');
            }

            flash('success', 'บันทึกเคสและปิดแบบไม่มีค่าใช้จ่ายเรียบร้อยแล้ว');
            redirect('queue');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถบันทึกและจบเคสได้: ' . $throwable->getMessage());
            redirect('queue-exam', ['id' => $visitId]);
        }
    }

    public function quickComplete(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        if ($visitId <= 0) {
            flash('error', 'ไม่พบเคสที่ต้องการปิด');
            redirect('queue');
        }

        $_POST['finish_mode'] = 'no_charge';
        $this->smartFinish();
    }

    public function updateStatus(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $queueId = (int) ($_POST['queue_id'] ?? 0);
        $targetStatus = trim((string) ($_POST['status'] ?? ''));
        $redirectToVisit = (int) ($_POST['redirect_to_visit'] ?? 0) === 1;

        try {
            $queue = $this->findQueue($queueId);
            if (!$queue) {
                throw new RuntimeException('ไม่พบคิวที่ต้องการอัปเดต');
            }

            $this->assertTransitionAllowed($queue, $targetStatus);

            db()->prepare(
                'UPDATE queue_entries
                 SET status = :status,
                     called_at = CASE WHEN :called_status = "IN_SERVICE" THEN COALESCE(called_at, NOW()) ELSE called_at END,
                     finished_at = CASE WHEN :finished_status = "COMPLETED" THEN NOW() ELSE finished_at END,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'status' => $targetStatus,
                'called_status' => $targetStatus,
                'finished_status' => $targetStatus,
                'id' => $queueId,
            ]);

            flash('success', 'อัปเดตสถานะคิวเรียบร้อยแล้ว');

            if ($redirectToVisit && $targetStatus === 'IN_SERVICE') {
                redirect('queue-exam', ['id' => $queue['visit_id']]);
            }
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถอัปเดตสถานะคิวได้: ' . $throwable->getMessage());
        }

        redirect('queue');
    }

    private function todayQueues(): array
    {
        return db()->query(
            'SELECT queue_entries.*, visits.id AS visit_id, visits.patient_id, visits.visit_no, visits.chief_complaint,
                    patients.hn, patients.first_name, patients.last_name, patients.phone
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             INNER JOIN patients ON patients.id = visits.patient_id
             WHERE queue_entries.queue_date = CURDATE()
             ORDER BY
                CASE queue_entries.status
                    WHEN "IN_SERVICE" THEN 1
                    WHEN "WAITING" THEN 2
                    WHEN "WAITING_PAYMENT" THEN 3
                    WHEN "COMPLETED" THEN 4
                    WHEN "CANCELLED" THEN 5
                    ELSE 6
                END,
                queue_entries.queue_no ASC'
        )->fetchAll();
    }

    private function patientsForQueue(): array
    {
        return db()->query(
            'SELECT patients.id, patients.hn, patients.first_name, patients.last_name, patients.phone,
                    (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                    (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
             FROM patients
             WHERE is_active = 1
             ORDER BY patients.id DESC
             LIMIT 12'
        )->fetchAll();
    }

    private function todayAppointments(): array
    {
        return db()->query(
            'SELECT appointments.id, appointments.patient_id, appointments.appointment_date, appointments.appointment_time,
                    appointments.purpose, appointments.note, patients.hn, patients.first_name, patients.last_name, patients.phone,
                    active_queue.visit_id AS active_visit_id,
                    active_queue.queue_no AS active_queue_no,
                    active_queue.status AS active_queue_status
             FROM appointments
             INNER JOIN patients ON patients.id = appointments.patient_id
             LEFT JOIN (
                SELECT visits.patient_id, queue_entries.visit_id, queue_entries.queue_no, queue_entries.status
                FROM queue_entries
                INNER JOIN visits ON visits.id = queue_entries.visit_id
                WHERE queue_entries.queue_date = CURDATE()
                  AND queue_entries.status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")
             ) AS active_queue ON active_queue.patient_id = appointments.patient_id
             WHERE appointments.status = "SCHEDULED"
               AND appointments.appointment_date <= CURDATE()
             ORDER BY appointments.appointment_date ASC, appointments.appointment_time ASC, appointments.id ASC
             LIMIT 8'
        )->fetchAll();
    }

    private function activeQueueForPatientToday(int $patientId): ?array
    {
        $stmt = db()->prepare(
            'SELECT queue_entries.visit_id, queue_entries.queue_no, queue_entries.status
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             WHERE visits.patient_id = :patient_id
               AND queue_entries.queue_date = CURDATE()
               AND queue_entries.status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")
             ORDER BY queue_entries.id DESC
             LIMIT 1'
        );
        $stmt->execute(['patient_id' => $patientId]);

        $queue = $stmt->fetch();
        return $queue ?: null;
    }

    private function nextWaitingFrom(array $todayQueues): ?array
    {
        foreach ($todayQueues as $queue) {
            if ($queue['status'] === 'WAITING') {
                return $queue;
            }
        }

        return null;
    }

    private function currentServiceFrom(array $todayQueues): ?array
    {
        foreach ($todayQueues as $queue) {
            if ($queue['status'] === 'IN_SERVICE') {
                return $queue;
            }
        }

        return null;
    }

    private function resolveActiveVisit(array $todayQueues): ?array
    {
        $requestedVisitId = (int) ($_GET['visit_id'] ?? 0);
        if ($requestedVisitId > 0) {
            return $this->findWorkflowVisit($requestedVisitId);
        }

        $currentQueue = $this->currentServiceFrom($todayQueues);
        if ($currentQueue) {
            return $this->findWorkflowVisit((int) $currentQueue['visit_id']);
        }

        return null;
    }

    private function findWorkflowVisit(int $visitId): ?array
    {
        $stmt = db()->prepare(
            'SELECT visits.*, queue_entries.id AS queue_id, queue_entries.queue_no, queue_entries.status, queue_entries.called_at, queue_entries.finished_at,
                    patients.hn, patients.first_name, patients.last_name, patients.phone, patients.gender, patients.drug_allergy,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c, visit_vitals.pulse_rate, visit_vitals.resp_rate, visit_vitals.spo2, visit_vitals.weight_kg,
                    COALESCE(service_totals.total_service, 0) AS service_total,
                    COALESCE(item_totals.total_item, 0) AS item_total,
                    COALESCE(service_totals.service_count, 0) AS service_count,
                    COALESCE(item_totals.item_count, 0) AS item_count
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             INNER JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
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
             WHERE visits.id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);
        $visit = $stmt->fetch();

        if (!$visit) {
            return null;
        }

        $serviceLinesStmt = db()->prepare(
            'SELECT visit_services.id, services.service_name, visit_services.qty, visit_services.line_total
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visit_services.visit_id = :visit_id
             ORDER BY visit_services.id DESC'
        );
        $serviceLinesStmt->execute(['visit_id' => $visitId]);
        $visit['service_lines'] = $serviceLinesStmt->fetchAll();

        $itemLinesStmt = db()->prepare(
            'SELECT visit_item_usages.id, inventory_items.item_name, inventory_items.unit_name, visit_item_usages.qty, visit_item_usages.line_total
             FROM visit_item_usages
             INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
             WHERE visit_item_usages.visit_id = :visit_id
             ORDER BY visit_item_usages.id DESC'
        );
        $itemLinesStmt->execute(['visit_id' => $visitId]);
        $visit['item_lines'] = $itemLinesStmt->fetchAll();

        return $visit;
    }

    private function patientSnapshot(int $patientId, int $currentVisitId): array
    {
        if ($patientId <= 0) {
            return [
                'profile' => null,
                'recent_visits' => [],
                'latest_vital' => null,
                'upcoming_appointments' => [],
                'unpaid_count' => 0,
                'last_paid_at' => null,
            ];
        }

        $profileStmt = db()->prepare(
            'SELECT patients.*,
                    (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                    (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id AND visits.id <> :current_visit_id) AS last_visit_at
             FROM patients
             WHERE patients.id = :patient_id
             LIMIT 1'
        );
        $profileStmt->execute([
            'patient_id' => $patientId,
            'current_visit_id' => $currentVisitId,
        ]);

        $recentStmt = db()->prepare(
            'SELECT visits.id, visits.visit_no, visits.visit_datetime, visits.chief_complaint, visits.diagnosis, visits.advice,
                    queue_entries.status, queue_entries.queue_no,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c, visit_vitals.pulse_rate, visit_vitals.spo2, visit_vitals.weight_kg,
                    payments.receipt_no, payments.total_amount, payments.paid_at,
                    COALESCE(service_summary.services_summary, "-") AS services_summary,
                    COALESCE(item_summary.items_summary, "-") AS items_summary
             FROM visits
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_services.visit_id, GROUP_CONCAT(services.service_name ORDER BY visit_services.id SEPARATOR ", ") AS services_summary
                FROM visit_services
                INNER JOIN services ON services.id = visit_services.service_id
                GROUP BY visit_services.visit_id
             ) AS service_summary ON service_summary.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_item_usages.visit_id, GROUP_CONCAT(inventory_items.item_name ORDER BY visit_item_usages.id SEPARATOR ", ") AS items_summary
                FROM visit_item_usages
                INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
                GROUP BY visit_item_usages.visit_id
             ) AS item_summary ON item_summary.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
               AND visits.id <> :current_visit_id
             ORDER BY visits.visit_datetime DESC
             LIMIT 3'
        );
        $recentStmt->execute([
            'patient_id' => $patientId,
            'current_visit_id' => $currentVisitId,
        ]);

        $latestVitalStmt = db()->prepare(
            'SELECT visit_vitals.*, visits.visit_datetime
             FROM visit_vitals
             INNER JOIN visits ON visits.id = visit_vitals.visit_id
             WHERE visits.patient_id = :patient_id
               AND visits.id <> :current_visit_id
             ORDER BY visits.visit_datetime DESC
             LIMIT 1'
        );
        $latestVitalStmt->execute([
            'patient_id' => $patientId,
            'current_visit_id' => $currentVisitId,
        ]);

        $appointmentStmt = db()->prepare(
            'SELECT appointment_date, appointment_time, purpose, status
             FROM appointments
             WHERE patient_id = :patient_id
               AND status = "SCHEDULED"
               AND appointment_date >= CURDATE()
             ORDER BY appointment_date ASC, appointment_time ASC
             LIMIT 3'
        );
        $appointmentStmt->execute(['patient_id' => $patientId]);

        $unpaidStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM visits
             INNER JOIN queue_entries ON queue_entries.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
               AND visits.id <> :current_visit_id
               AND queue_entries.status = "WAITING_PAYMENT"'
        );
        $unpaidStmt->execute([
            'patient_id' => $patientId,
            'current_visit_id' => $currentVisitId,
        ]);

        $lastPaidStmt = db()->prepare(
            'SELECT payments.paid_at
             FROM payments
             INNER JOIN visits ON visits.id = payments.visit_id
             WHERE visits.patient_id = :patient_id
             ORDER BY payments.paid_at DESC
             LIMIT 1'
        );
        $lastPaidStmt->execute(['patient_id' => $patientId]);

        $lastPaidAt = $lastPaidStmt->fetchColumn();

        return [
            'profile' => $profileStmt->fetch() ?: null,
            'recent_visits' => $recentStmt->fetchAll(),
            'latest_vital' => $latestVitalStmt->fetch() ?: null,
            'upcoming_appointments' => $appointmentStmt->fetchAll(),
            'unpaid_count' => (int) $unpaidStmt->fetchColumn(),
            'last_paid_at' => $lastPaidAt ?: null,
        ];
    }

    private function findQueue(int $queueId): ?array
    {
        $stmt = db()->prepare(
            'SELECT queue_entries.*, visits.id AS visit_id
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             WHERE queue_entries.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $queueId]);

        $queue = $stmt->fetch();
        return $queue ?: null;
    }

    private function quickPresets(): array
    {
        $databasePresets = $this->databaseQuickPresets();
        if ($databasePresets !== []) {
            return $databasePresets;
        }

        return [
            'wound_dressing' => [
                'label' => 'ทำแผล',
                'description' => 'เพิ่มค่าทำแผล พร้อมน้ำเกลือ ผ้าก๊อซ และคำแนะนำดูแลแผล',
                'theme' => 'preset-wound',
                'services' => ['SRV002'],
                'items' => [
                    ['code' => 'MED002', 'qty' => 1],
                    ['code' => 'MED003', 'qty' => 2],
                ],
                'cc' => 'มีแผล',
                'pi' => 'มีแผลจากอุบัติเหตุ ไม่มีเลือดออกมาก',
                'pe' => 'Wound clean, no active bleeding',
                'dx' => 'Wound',
                'advice' => 'ดูแลแผลให้แห้ง ทำความสะอาดตามคำแนะนำ และกลับมาพบเจ้าหน้าที่หากปวดบวมแดงมากขึ้น',
                'followup_days' => 2,
            ],
            'injection' => [
                'label' => 'ฉีดยา',
                'description' => 'เพิ่มค่าฉีดยา พร้อมบันทึกคำแนะนำหลังฉีดเพื่อสรุปเคสได้เร็วขึ้น',
                'theme' => 'preset-injection',
                'services' => ['SRV003'],
                'items' => [],
                'cc' => 'รับบริการฉีดยา',
                'pi' => 'มารับบริการฉีดยาตามแผนการรักษา ไม่มีอาการผิดปกติระหว่างรอรับบริการ',
                'pe' => 'General appearance good, no acute distress',
                'dx' => 'Injection service',
                'advice' => 'สังเกตอาการปวด บวม แดง หรือผื่นหลังฉีด หากมีอาการผิดปกติให้กลับมาพบเจ้าหน้าที่ทันที',
                'followup_days' => null,
            ],
            'vital_signs' => [
                'label' => 'วัดสัญญาณชีพ',
                'description' => 'เพิ่มค่าบริการวัดสัญญาณชีพและช่วยเตรียมแบบฟอร์มสำหรับบันทึก vital signs',
                'theme' => 'preset-vitals',
                'services' => ['SRV004'],
                'items' => [],
                'cc' => 'ติดตามอาการ',
                'pi' => 'มาประเมินอาการและตรวจวัดสัญญาณชีพเบื้องต้น',
                'pe' => 'General appearance fair',
                'dx' => 'Observation',
                'advice' => 'ติดตามอาการต่อเนื่องตามนัด และบันทึกสัญญาณชีพหากมีอาการเปลี่ยนแปลง',
                'followup_days' => null,
            ],
            'followup' => [
                'label' => 'ติดตามอาการ',
                'description' => 'เพิ่มค่าตรวจทั่วไป พร้อมข้อความตัวอย่างสำหรับเคสนัดติดตามอาการ',
                'theme' => 'preset-followup',
                'services' => ['SRV001'],
                'items' => [],
                'cc' => 'ติดตามอาการ',
                'pi' => 'มาติดตามอาการหลังรับบริการครั้งก่อน อาการโดยรวมคงที่',
                'pe' => 'General appearance stable',
                'dx' => 'Follow up',
                'advice' => 'รับประทานยาหรือปฏิบัติตามคำแนะนำเดิมต่อเนื่อง และกลับมาตามนัดครั้งถัดไป',
                'followup_days' => 7,
            ],
        ];
    }

    private function splitQuickPatientName(string $fullName): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';
        if ($normalized === '') {
            return ['', ''];
        }

        $parts = explode(' ', $normalized, 2);
        return [
            trim($parts[0] ?? ''),
            trim($parts[1] ?? ''),
        ];
    }

    private function databaseQuickPresets(): array
    {
        try {
            $this->ensureSmartPresetSchema();
            $this->seedDefaultSmartPresets();

            $rows = db()->query(
                'SELECT *
                 FROM smart_exam_presets
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }

        $presets = [];
        foreach ($rows as $row) {
            $services = array_values(array_filter(array_map(
                static fn(string $code): string => strtoupper(trim($code)),
                preg_split('/[\s,]+/', (string) ($row['service_codes'] ?? '')) ?: []
            )));
            $items = json_decode((string) ($row['item_codes_json'] ?? '[]'), true);
            $items = is_array($items) ? $items : [];

            $presets[(string) $row['preset_key']] = [
                'label' => (string) $row['label'],
                'description' => (string) ($row['description'] ?? ''),
                'theme' => (string) ($row['theme'] ?: 'preset-custom'),
                'services' => $services,
                'items' => array_map(static fn(array $item): array => [
                    'code' => strtoupper((string) ($item['code'] ?? '')),
                    'qty' => max(0.01, (float) ($item['qty'] ?? 1)),
                ], $items),
                'cc' => (string) ($row['cc'] ?? ''),
                'pi' => (string) ($row['pi'] ?? ''),
                'pe' => (string) ($row['pe'] ?? ''),
                'dx' => (string) ($row['dx'] ?? ''),
                'advice' => (string) ($row['advice'] ?? ''),
                'followup_days' => $row['followup_days'] !== null ? (int) $row['followup_days'] : null,
            ];
        }

        return $presets;
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

    private function quickRegisterDuplicates(string $firstName, string $lastName, string $phone): array
    {
        $conditions = [];
        $params = [];

        if ($phone !== '') {
            $conditions[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        if ($firstName !== '' && $lastName !== '' && $lastName !== '-') {
            $conditions[] = '(first_name = :first_name AND last_name = :last_name)';
            $params['first_name'] = $firstName;
            $params['last_name'] = $lastName;
        } elseif ($firstName !== '') {
            $conditions[] = 'first_name = :first_name_only';
            $params['first_name_only'] = $firstName;
        }

        if ($conditions === []) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT id, hn, first_name, last_name, phone
             FROM patients
             WHERE is_active = 1 AND (' . implode(' OR ', $conditions) . ')
             ORDER BY id DESC
             LIMIT 5'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function presetAlreadyApplied(PDO $pdo, int $visitId, string $presetKey): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM visit_services
             WHERE visit_id = :visit_id AND remark = :remark'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'remark' => 'QUICK_PRESET:' . $presetKey,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function saveQuickClinical(PDO $pdo, int $visitId, array $visit, array $preset = [], array $input = []): void
    {
        $chiefComplaint = trim((string) ($input['cc'] ?? $visit['chief_complaint'] ?? ''));
        $presentIllness = trim((string) ($input['pi'] ?? $visit['present_illness'] ?? ''));
        $physicalExam = trim((string) ($input['pe'] ?? $visit['physical_exam'] ?? ''));
        $diagnosis = trim((string) ($input['dx'] ?? $visit['diagnosis'] ?? ''));
        $advice = trim((string) ($visit['advice'] ?? ''));
        $followupDate = $visit['followup_date'] ?? null;

        if (array_key_exists('advice', $input)) {
            $advice = trim((string) $input['advice']);
        }

        if (array_key_exists('followup_date', $input)) {
            $inputFollowupDate = trim((string) $input['followup_date']);
            $followupDate = $inputFollowupDate !== '' ? $inputFollowupDate : null;
        }

        if ($preset !== []) {
            if (!empty($preset['cc']) && !str_contains($chiefComplaint, $preset['cc'])) {
                $chiefComplaint = $chiefComplaint === '' ? $preset['cc'] : $chiefComplaint . ', ' . $preset['cc'];
            }
            if ($presentIllness === '' && !empty($preset['pi'])) {
                $presentIllness = $preset['pi'];
            }
            if ($physicalExam === '' && !empty($preset['pe'])) {
                $physicalExam = $preset['pe'];
            }
            if ($diagnosis === '' && !empty($preset['dx'])) {
                $diagnosis = $preset['dx'];
            }
            if (!empty($preset['advice']) && !str_contains($advice, $preset['advice'])) {
                $advice = $advice === '' ? $preset['advice'] : $advice . PHP_EOL . $preset['advice'];
            }
            if (!$followupDate && !empty($preset['followup_days'])) {
                $followupDate = date('Y-m-d', strtotime('+' . (int) $preset['followup_days'] . ' day'));
            }
        }

        $nursingNote = $this->composeLegacyNursingNote($presentIllness, $physicalExam, $diagnosis);

        $pdo->prepare(
            'UPDATE visits
             SET chief_complaint = :chief_complaint,
                 present_illness = :present_illness,
                 physical_exam = :physical_exam,
                 diagnosis = :diagnosis,
                 nursing_note = :nursing_note,
                 advice = :advice,
                 followup_date = :followup_date,
                 updated_at = NOW()
             WHERE id = :visit_id'
        )->execute([
            'chief_complaint' => $chiefComplaint !== '' ? $chiefComplaint : null,
            'present_illness' => $presentIllness !== '' ? $presentIllness : null,
            'physical_exam' => $physicalExam !== '' ? $physicalExam : null,
            'diagnosis' => $diagnosis !== '' ? $diagnosis : null,
            'nursing_note' => $nursingNote !== '' ? $nursingNote : null,
            'advice' => $advice !== '' ? $advice : null,
            'followup_date' => $followupDate ?: null,
            'visit_id' => $visitId,
        ]);
    }

    private function syncFollowupAppointment(PDO $pdo, int $visitId): void
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

    private function insertPresetService(PDO $pdo, int $visitId, string $serviceCode, string $presetKey): void
    {
        $serviceStmt = $pdo->prepare('SELECT id, price FROM services WHERE service_code = :service_code AND is_active = 1 LIMIT 1');
        $serviceStmt->execute(['service_code' => $serviceCode]);
        $service = $serviceStmt->fetch();

        if (!$service) {
            throw new RuntimeException('ไม่พบบริการที่กำหนดไว้สำหรับ preset');
        }

        $pdo->prepare(
            'INSERT INTO visit_services (visit_id, service_id, qty, unit_price, line_total, remark, created_at, updated_at)
             VALUES (:visit_id, :service_id, 1, :unit_price, :line_total, :remark, NOW(), NOW())'
        )->execute([
            'visit_id' => $visitId,
            'service_id' => $service['id'],
            'unit_price' => $service['price'],
            'line_total' => $service['price'],
            'remark' => 'QUICK_PRESET:' . $presetKey,
        ]);
    }

    private function insertPresetItemUsage(PDO $pdo, int $visitId, string $itemCode, float $qty, string $presetKey, string $presetLabel): void
    {
        $itemStmt = $pdo->prepare('SELECT id, item_name, default_cost, default_price FROM inventory_items WHERE item_code = :item_code AND is_active = 1 LIMIT 1');
        $itemStmt->execute(['item_code' => $itemCode]);
        $item = $itemStmt->fetch();

        if (!$item) {
            throw new RuntimeException('ไม่พบรายการอุปกรณ์หรือเวชภัณฑ์สำหรับ preset');
        }

        $batchStmt = $pdo->prepare(
            'SELECT id, qty_balance, cost_per_unit
             FROM inventory_batches
             WHERE item_id = :item_id AND qty_balance >= :qty
             ORDER BY expiry_date ASC, id ASC
             LIMIT 1'
        );
        $batchStmt->execute([
            'item_id' => $item['id'],
            'qty' => $qty,
        ]);
        $batch = $batchStmt->fetch();

        if (!$batch) {
            throw new RuntimeException('อุปกรณ์หรือเวชภัณฑ์คงเหลือไม่พอสำหรับ preset ' . $presetLabel);
        }

        $pdo->prepare(
            'UPDATE inventory_batches
             SET qty_balance = qty_balance - :qty
             WHERE id = :id'
        )->execute([
            'qty' => $qty,
            'id' => $batch['id'],
        ]);

        $lineTotal = (float) $item['default_price'] * $qty;

        $pdo->prepare(
            'INSERT INTO visit_item_usages (visit_id, item_id, qty, unit_price, line_total, usage_note, created_at, updated_at)
             VALUES (:visit_id, :item_id, :qty, :unit_price, :line_total, :usage_note, NOW(), NOW())'
        )->execute([
            'visit_id' => $visitId,
            'item_id' => $item['id'],
            'qty' => $qty,
            'unit_price' => $item['default_price'],
            'line_total' => $lineTotal,
            'usage_note' => 'QUICK_PRESET:' . $presetKey . ':' . $presetLabel,
        ]);

        $pdo->prepare(
            'INSERT INTO stock_movements (batch_id, item_id, movement_type, qty, unit_cost, reference_type, reference_id, note, movement_datetime, created_by, created_at, updated_at)
             VALUES (:batch_id, :item_id, "OUT", :qty, :unit_cost, "VISIT", :reference_id, :note, NOW(), :created_by, NOW(), NOW())'
        )->execute([
            'batch_id' => $batch['id'],
            'item_id' => $item['id'],
            'qty' => $qty,
            'unit_cost' => $batch['cost_per_unit'] ?? $item['default_cost'],
            'reference_id' => $visitId,
            'note' => 'ตัดสต็อกจาก preset ' . $presetLabel,
            'created_by' => (int) current_user()['id'],
        ]);
    }

    private function billingTotals(PDO $pdo, int $visitId): array
    {
        $serviceStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) AS total_amount FROM visit_services WHERE visit_id = :visit_id');
        $serviceStmt->execute(['visit_id' => $visitId]);
        $serviceTotal = (float) ($serviceStmt->fetch()['total_amount'] ?? 0);

        $itemStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) AS total_amount FROM visit_item_usages WHERE visit_id = :visit_id');
        $itemStmt->execute(['visit_id' => $visitId]);
        $itemTotal = (float) ($itemStmt->fetch()['total_amount'] ?? 0);

        return [
            'service_total' => $serviceTotal,
            'item_total' => $itemTotal,
            'grand_total' => $serviceTotal + $itemTotal,
        ];
    }

    private function recordSmartPayment(PDO $pdo, int $visitId, array $billingTotals): array
    {
        $grandTotal = (float) ($billingTotals['grand_total'] ?? 0);
        if ($grandTotal <= 0) {
            throw new RuntimeException('ยังไม่มีรายการคิดเงิน กรุณาเพิ่มบริการหรือยาก่อนรับเงิน');
        }

        $discountAmount = max(0, (float) ($_POST['discount_amount'] ?? 0));
        $totalAmount = max(0, $grandTotal - $discountAmount);
        $paidAmount = max(0, (float) ($_POST['paid_amount'] ?? 0));
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'CASH'));
        $allowedMethods = ['CASH', 'TRANSFER', 'QR'];

        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'CASH';
        }

        if ($paidAmount < $totalAmount) {
            throw new RuntimeException('ยอดรับชำระน้อยกว่ายอดสุทธิ กรุณาตรวจสอบอีกครั้ง');
        }

        if ($this->visitHasPayment($visitId)) {
            throw new RuntimeException('เคสนี้มีรายการชำระเงินแล้ว');
        }

        $receiptNo = NumberGenerator::nextReceiptNo();
        $pdo->prepare(
            'INSERT INTO payments (
                visit_id, receipt_no, subtotal_service, subtotal_item, discount_amount, total_amount,
                paid_amount, change_amount, payment_method, payment_status, paid_at, paid_by, created_at, updated_at
             ) VALUES (
                :visit_id, :receipt_no, :subtotal_service, :subtotal_item, :discount_amount, :total_amount,
                :paid_amount, :change_amount, :payment_method, "PAID", NOW(), :paid_by, NOW(), NOW()
             )'
        )->execute([
            'visit_id' => $visitId,
            'receipt_no' => $receiptNo,
            'subtotal_service' => $billingTotals['service_total'],
            'subtotal_item' => $billingTotals['item_total'],
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'change_amount' => max(0, $paidAmount - $totalAmount),
            'payment_method' => $paymentMethod,
            'paid_by' => current_user()['id'],
        ]);

        return [
            'receipt_no' => $receiptNo,
            'payment_id' => (int) $pdo->lastInsertId(),
        ];
    }

    private function assertTransitionAllowed(array $queue, string $targetStatus): void
    {
        $fromStatus = (string) ($queue['status'] ?? 'WAITING');
        if (!can_transition_queue_status($fromStatus, $targetStatus)) {
            throw new RuntimeException('ไม่สามารถเปลี่ยนสถานะจาก ' . $fromStatus . ' ไปเป็น ' . $targetStatus . ' ได้');
        }
    }

    private function visitHasBillableItems(int $visitId): bool
    {
        $stmt = db()->prepare(
            'SELECT (
                (SELECT COUNT(*) FROM visit_services WHERE visit_id = :service_visit_id) +
                (SELECT COUNT(*) FROM visit_item_usages WHERE visit_id = :item_visit_id)
             ) AS total_rows'
        );
        $stmt->execute([
            'service_visit_id' => $visitId,
            'item_visit_id' => $visitId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function visitHasPayment(int $visitId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM payments WHERE visit_id = :visit_id');
        $stmt->execute(['visit_id' => $visitId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function composeLegacyNursingNote(string $presentIllness, string $physicalExam, string $diagnosis): string
    {
        $sections = [];

        if ($presentIllness !== '') {
            $sections[] = 'PI: ' . $presentIllness;
        }
        if ($physicalExam !== '') {
            $sections[] = 'PE: ' . $physicalExam;
        }
        if ($diagnosis !== '') {
            $sections[] = 'Dx: ' . $diagnosis;
        }

        return implode(PHP_EOL, $sections);
    }

    private function ensureSmartExamSchema(): void
    {
        $checks = [
            ['table' => 'visits', 'column' => 'present_illness', 'definition' => 'TEXT NULL'],
            ['table' => 'visits', 'column' => 'physical_exam', 'definition' => 'TEXT NULL'],
            ['table' => 'visits', 'column' => 'diagnosis', 'definition' => 'VARCHAR(255) NULL'],
            ['table' => 'visit_vitals', 'column' => 'resp_rate', 'definition' => 'INT NULL'],
        ];

        foreach ($checks as $check) {
            $stmt = db()->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => $check['table'],
                'column_name' => $check['column'],
            ]);

            if ((int) $stmt->fetchColumn() === 0) {
                db()->exec(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                    $check['table'],
                    $check['column'],
                    $check['definition']
                ));
            }
        }
    }
}
