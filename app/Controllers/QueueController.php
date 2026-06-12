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
            'pageTitle' => 'คิววันนี้',
            'todayQueues' => $todayQueues,
            'patients' => $patients,
            'todayAppointments' => $todayAppointments,
            'nextWaiting' => $nextWaiting,
            'currentQueue' => $currentQueue,
            'activeVisit' => $activeVisit,
            'dailyMetrics' => $this->dailyQueueMetrics($todayQueues),
            'assistantAlerts' => $this->queueAssistantAlerts($todayQueues),
            'activitySummary' => $this->todayActivitySummary(),
            'recentActivity' => $this->recentQueueActivity(),
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
        $this->ensureTreatmentPresetSchema();

        $visitId = (int) ($_GET['id'] ?? ($_GET['visit_id'] ?? 0));
        if ($visitId <= 0) {
            flash('error', 'ไม่พบเคสที่ต้องการเปิดตรวจ');
            redirect('queue');
        }

        $visit = $this->findWorkflowVisit($visitId);
        if (!$visit) {
            flash('error', 'ไม่พบข้อมูลเคสนี้ กรุณากลับไปเลือกจากคิววันนี้อีกครั้ง');
            redirect('queue');
        }

        if (!in_array($visit['status'], ['WAITING', 'IN_SERVICE'], true)) {
            flash('error', 'เคสนี้จบแล้วหรืออยู่ขั้นตอนชำระเงิน จึงไม่สามารถเปิด Smart Exam ได้');
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
            'treatmentPresets' => $this->treatmentPresetsForExam(),
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

    public function updatePatientProfile(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        if ($visitId <= 0 || $patientId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'ไม่พบเคสหรือผู้รับบริการที่ต้องการแก้ไข'], 422);
        }

        $visit = $this->findWorkflowVisit($visitId);
        if (!$visit || (int) ($visit['patient_id'] ?? 0) !== $patientId) {
            $this->jsonResponse(['success' => false, 'message' => 'ข้อมูลเคสไม่ตรงกับผู้รับบริการ กรุณาโหลดหน้าใหม่'], 404);
        }

        $currentStmt = db()->prepare(
            'SELECT id, hn, citizen_id, title_name, first_name, last_name, gender, birth_date, phone, address, underlying_disease, drug_allergy, note
             FROM patients
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $currentStmt->execute(['id' => $patientId]);
        $current = $currentStmt->fetch();
        if (!$current) {
            $this->jsonResponse(['success' => false, 'message' => 'ไม่พบแฟ้มผู้รับบริการที่จะแก้ไข'], 404);
        }

        $roleCode = (string) (current_user()['role_code'] ?? '');
        $adminFields = ['title_name', 'first_name', 'last_name', 'citizen_id', 'birth_date', 'gender', 'phone', 'address', 'underlying_disease', 'drug_allergy', 'note'];
        $nurseFields = ['phone', 'address', 'underlying_disease', 'drug_allergy', 'note'];
        $allowedFields = $roleCode === 'ADMIN' ? $adminFields : $nurseFields;

        $input = [];
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }

            $input[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        if ($roleCode === 'ADMIN') {
            $input['first_name'] = $input['first_name'] ?? (string) ($current['first_name'] ?? '');
            $input['last_name'] = $input['last_name'] ?? (string) ($current['last_name'] ?? '');
            if ($input['first_name'] === '' || $input['last_name'] === '') {
                $this->jsonResponse(['success' => false, 'message' => 'กรุณากรอกชื่อและนามสกุลผู้รับบริการ'], 422);
            }
        }

        if (array_key_exists('gender', $input) && !in_array($input['gender'], ['', 'M', 'F', 'O'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'รูปแบบเพศไม่ถูกต้อง'], 422);
        }

        if (array_key_exists('birth_date', $input)) {
            $birthRaw = $input['birth_date'];
            $input['birth_date'] = $this->normalizePatientBirthDate($birthRaw);
            if ($birthRaw !== '' && $input['birth_date'] === '') {
                $this->jsonResponse(['success' => false, 'message' => 'วันเกิดไม่ถูกต้อง กรุณากรอกเช่น 28/10/2549'], 422);
            }
        }

        if (array_key_exists('citizen_id', $input)) {
            $input['citizen_id'] = preg_replace('/\D+/', '', $input['citizen_id']) ?? '';
            if ($input['citizen_id'] !== '' && strlen($input['citizen_id']) !== 13) {
                $this->jsonResponse(['success' => false, 'message' => 'เลขบัตรประชาชนต้องมี 13 หลัก'], 422);
            }

            if ($input['citizen_id'] !== '') {
                $dupStmt = db()->prepare('SELECT id FROM patients WHERE citizen_id = :citizen_id AND id <> :id LIMIT 1');
                $dupStmt->execute([
                    'citizen_id' => $input['citizen_id'],
                    'id' => $patientId,
                ]);
                if ($dupStmt->fetch()) {
                    $this->jsonResponse(['success' => false, 'message' => 'เลขบัตรประชาชนนี้มีในระบบแล้ว'], 422);
                }
            }
        }

        if (array_key_exists('phone', $input) && $input['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,30}$/', $input['phone'])) {
            $this->jsonResponse(['success' => false, 'message' => 'รูปแบบเบอร์โทรไม่ถูกต้อง'], 422);
        }

        $changes = [];
        foreach ($input as $field => $value) {
            $normalizedValue = $value === '' ? null : $value;
            $currentValue = $current[$field] ?? null;
            $currentValue = $currentValue === '' ? null : $currentValue;
            if ((string) ($currentValue ?? '') !== (string) ($normalizedValue ?? '')) {
                $changes[$field] = [
                    'before' => $currentValue,
                    'after' => $normalizedValue,
                ];
            }
        }

        if ($changes !== []) {
            $setParts = [];
            $params = ['id' => $patientId];
            foreach ($changes as $field => $change) {
                $setParts[] = "`{$field}` = :{$field}";
                $params[$field] = $change['after'];
            }
            $setParts[] = 'updated_at = NOW()';

            db()->prepare(
                'UPDATE patients SET ' . implode(', ', $setParts) . ' WHERE id = :id'
            )->execute($params);

            $this->writePatientAudit('UPDATE_PATIENT_PROFILE', $patientId, [
                'visit_id' => $visitId,
                'role_code' => $roleCode,
                'changes' => $changes,
            ]);
        }

        $profile = $this->patientProfileForResponse($patientId);
        $this->jsonResponse([
            'success' => true,
            'message' => $changes === [] ? 'ไม่มีข้อมูลที่เปลี่ยนแปลง' : 'บันทึกข้อมูลผู้รับบริการเรียบร้อย',
            'profile' => $profile,
            'changed' => array_keys($changes),
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
            flash('error', 'กรุณาเลือกผู้รับบริการก่อนรับเคส');
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
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'เนเธกเนเธเธเธเนเธญเธกเธนเธฅ preset เธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเนเธเนเธเธฒเธ',
                ], 422);
            }
            flash('error', 'เนเธกเนเธเธเธเนเธญเธกเธนเธฅ preset เธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเนเธเนเธเธฒเธ');
            redirect('queue');
        }

        $visit = $this->findWorkflowVisit($visitId);
        if (!$visit) {
            flash('error', 'เนเธกเนเธเธเน€เธเธชเธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเนเธเน preset');
            redirect('queue');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();
            $alreadyApplied = $this->presetAlreadyApplied($pdo, $visitId, $presetKey);

            if ($visit['status'] === 'WAITING') {
                $this->assertTransitionAllowed($visit, 'IN_SERVICE');
                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "IN_SERVICE", called_at = COALESCE(called_at, NOW()), updated_at = NOW()
                     WHERE visit_id = :visit_id'
                )->execute(['visit_id' => $visitId]);
                $this->writeQueueAudit('QUEUE_STATUS_CHANGED', (int) ($visit['queue_id'] ?? 0), [
                    'visit_id' => $visitId,
                    'from_status' => 'WAITING',
                    'to_status' => 'IN_SERVICE',
                    'source' => 'smart_exam_preset',
                ], $pdo);
                $visit['status'] = 'IN_SERVICE';
            }

            if (!$alreadyApplied) {
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

            $clinicalInput = [
                'cc' => trim((string) ($_POST['cc'] ?? $visit['chief_complaint'] ?? '')),
                'pi' => trim((string) ($_POST['pi'] ?? $visit['present_illness'] ?? '')),
                'pe' => trim((string) ($_POST['pe'] ?? $visit['physical_exam'] ?? '')),
                'dx' => trim((string) ($_POST['dx'] ?? $visit['diagnosis'] ?? '')),
                'advice' => trim((string) ($_POST['advice'] ?? $visit['advice'] ?? '')),
                'followup_date' => trim((string) ($_POST['followup_date'] ?? $visit['followup_date'] ?? '')),
            ];

            $this->saveQuickClinical($pdo, $visitId, $visit, $preset, $clinicalInput);
            $clinicalSummary = $this->visitClinicalSummary($pdo, $visitId);
            $orderSummary = $this->visitOrderSummary($pdo, $visitId);

            $pdo->commit();
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'ok' => true,
                    'message' => $alreadyApplied
                        ? 'Preset เธเธตเนเน€เธเธขเน€เธเธดเนเธกเธฃเธฒเธขเธเธฒเธฃเนเธฅเนเธง เธฃเธฐเธเธเธญเธฑเธเน€เธ”เธ•เธเนเธญเธกเธนเธฅเธ•เธฃเธงเธเนเธซเน'
                        : 'เน€เธเธดเนเธก preset ' . $preset['label'] . ' เน€เธฃเธตเธขเธเธฃเนเธญเธข',
                    'preset' => [
                        'key' => $presetKey,
                        'label' => (string) $preset['label'],
                        'alreadyApplied' => $alreadyApplied,
                    ],
                    'clinical' => $clinicalSummary,
                    'summary' => $orderSummary,
                ]);
            }
            flash('success', 'เน€เธเธดเนเธก preset ' . $preset['label'] . ' เน€เธฃเธตเธขเธเธฃเนเธญเธขเนเธฅเนเธง');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'เนเธกเนเธชเธฒเธกเธฒเธฃเธ–เน€เธเธดเนเธก preset เนเธ”เน: ' . $throwable->getMessage());
        }

        redirect('queue-exam', ['id' => $visitId, 'preset' => $presetKey]);
    }

    public function applyTreatmentPreset(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();
        $this->ensureTreatmentPresetSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $presetId = (int) ($_POST['treatment_preset_id'] ?? 0);

        if ($visitId <= 0 || $presetId <= 0) {
            flash('error', 'Please select a Treatment Preset and visit.');
            redirect('queue');
        }

        $visit = $this->findWorkflowVisit($visitId);
        $preset = $this->treatmentPresetDetail($presetId);

        if (!$visit || !$preset) {
            flash('error', 'Visit or Treatment Preset was not found.');
            redirect('queue');
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();

            if ($visit['status'] === 'WAITING') {
                $this->assertTransitionAllowed($visit, 'IN_SERVICE');
                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "IN_SERVICE", called_at = COALESCE(called_at, NOW()), updated_at = NOW()
                     WHERE visit_id = :visit_id'
                )->execute(['visit_id' => $visitId]);
                $this->writeQueueAudit('QUEUE_STATUS_CHANGED', (int) ($visit['queue_id'] ?? 0), [
                    'visit_id' => $visitId,
                    'from_status' => 'WAITING',
                    'to_status' => 'IN_SERVICE',
                    'source' => 'treatment_preset',
                    'preset_id' => $presetId,
                ], $pdo);
            }

            if ($this->treatmentPresetAlreadyApplied($pdo, $visitId, $presetId)) {
                $pdo->commit();
                flash('warning', 'This Treatment Preset is already applied to this visit. Add extra services or medicines manually if needed.');
                redirect('queue-exam', ['id' => $visitId]);
            }

            foreach ($preset['services'] as $service) {
                $this->insertTreatmentPresetService($pdo, $visitId, $service, $presetId);
            }

            foreach ($preset['medications'] as $medication) {
                $this->insertTreatmentPresetItemUsage($pdo, $visitId, $medication, $presetId, (string) $preset['preset_name']);
            }

            foreach ($preset['supplies'] as $supply) {
                $this->insertTreatmentPresetItemUsage($pdo, $visitId, $supply, $presetId, (string) $preset['preset_name']);
            }

            $this->writeQueueAudit('TREATMENT_PRESET_APPLIED', (int) ($visit['queue_id'] ?? 0), [
                'visit_id' => $visitId,
                'preset_id' => $presetId,
                'preset_name' => $preset['preset_name'],
                'services' => count($preset['services']),
                'medications' => count($preset['medications']),
                'supplies' => count($preset['supplies']),
            ], $pdo);

            $pdo->commit();
            flash('success', 'Treatment Preset "' . $preset['preset_name'] . '" was applied successfully.');
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Unable to apply Treatment Preset: ' . $throwable->getMessage());
        }

        redirect('queue-exam', ['id' => $visitId]);
    }

    public function smartFinish(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensureSmartExamSchema();

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $finishMode = trim((string) ($_POST['finish_mode'] ?? 'payment'));
        $visit = $this->findWorkflowVisit($visitId);

        if (!$visit) {
            flash('error', 'เนเธกเนเธเธเน€เธเธชเธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเธเธฑเธเธ—เธถเธ');
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
            flash('error', 'เธเธฃเธธเธ“เธฒเธเธฃเธญเธ CC เนเธฅเธฐ Dx เธเนเธญเธเธเธฑเธเธ—เธถเธเนเธฅเธฐเธเธเน€เธเธช');
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
                throw new RuntimeException('เธขเธฑเธเนเธกเนเธกเธตเธฃเธฒเธขเธเธฒเธฃเธเธดเธ”เน€เธเธดเธ เธเธฃเธธเธ“เธฒเน€เธเธดเนเธกเธเธฃเธดเธเธฒเธฃเธซเธฃเธทเธญเธญเธธเธเธเธฃเธ“เนเธเนเธญเธเธชเนเธเธเธณเธฃเธฐเน€เธเธดเธ');
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

            $this->writeQueueAudit('QUEUE_SMART_FINISH', (int) ($visit['queue_id'] ?? 0), [
                'visit_id' => $visitId,
                'from_status' => (string) ($visit['status'] ?? ''),
                'to_status' => $targetStatus,
                'finish_mode' => $finishMode,
                'service_total' => (float) ($billingTotals['service_total'] ?? 0),
                'item_total' => (float) ($billingTotals['item_total'] ?? 0),
                'grand_total' => (float) ($billingTotals['grand_total'] ?? 0),
                'payment_id' => (int) ($paymentResult['payment_id'] ?? 0),
                'source' => 'smart_exam',
            ], $pdo);

            $pdo->commit();

            if ($shouldReceivePayment) {
                flash('success', 'เธฃเธฑเธเน€เธเธดเธเนเธฅเธฐเธเธดเธ”เน€เธเธชเน€เธฃเธตเธขเธเธฃเนเธญเธขเนเธฅเนเธง เน€เธฅเธเธ—เธตเนเนเธเน€เธชเธฃเนเธ ' . ($paymentResult['receipt_no'] ?? '-'));
                redirect('receipt', [
                    'id' => (int) ($paymentResult['payment_id'] ?? 0),
                    'source' => 'smart_exam',
                ]);
            }

            if ($targetStatus === 'WAITING_PAYMENT') {
                flash('success', 'เธเธฑเธเธ—เธถเธเน€เธเธชเน€เธฃเธตเธขเธเธฃเนเธญเธข เนเธฅเธฐเธชเนเธเธ•เนเธญเนเธเธเธณเธฃเธฐเน€เธเธดเธเนเธฅเนเธง');
                if (has_role(['ADMIN', 'CASHIER'])) {
                    redirect('payments');
                }

                redirect('queue');
            }

            flash('success', 'เธเธฑเธเธ—เธถเธเน€เธเธชเนเธฅเธฐเธเธดเธ”เนเธเธเนเธกเนเธกเธตเธเนเธฒเนเธเนเธเนเธฒเธขเน€เธฃเธตเธขเธเธฃเนเธญเธขเนเธฅเนเธง');
            redirect('queue');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'เนเธกเนเธชเธฒเธกเธฒเธฃเธ–เธเธฑเธเธ—เธถเธเนเธฅเธฐเธเธเน€เธเธชเนเธ”เน: ' . $throwable->getMessage());
            redirect('queue-exam', ['id' => $visitId]);
        }
    }

    public function quickComplete(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        if ($visitId <= 0) {
            flash('error', 'เนเธกเนเธเธเน€เธเธชเธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเธเธดเธ”');
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
                throw new RuntimeException('เนเธกเนเธเธเธเธดเธงเธ—เธตเนเธ•เนเธญเธเธเธฒเธฃเธญเธฑเธเน€เธ”เธ•');
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

            $this->writeQueueAudit('QUEUE_STATUS_CHANGED', $queueId, [
                'from_status' => (string) ($queue['status'] ?? ''),
                'to_status' => $targetStatus,
                'visit_id' => (int) ($queue['visit_id'] ?? 0),
                'source' => 'queue_workstation',
            ]);

            flash('success', 'เธญเธฑเธเน€เธ”เธ•เธชเธ–เธฒเธเธฐเธเธดเธงเน€เธฃเธตเธขเธเธฃเนเธญเธขเนเธฅเนเธง');

            if ($redirectToVisit && $targetStatus === 'IN_SERVICE') {
                redirect('queue-exam', ['id' => $queue['visit_id']]);
            }
        } catch (Throwable $throwable) {
            flash('error', 'เนเธกเนเธชเธฒเธกเธฒเธฃเธ–เธญเธฑเธเน€เธ”เธ•เธชเธ–เธฒเธเธฐเธเธดเธงเนเธ”เน: ' . $throwable->getMessage());
        }

        redirect('queue');
    }

    public function closeAndNext(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        if ($visitId <= 0) {
            flash('error', 'ไม่พบเคสที่ต้องการปิด');
            redirect('queue');
        }

        $nextVisitId = 0;

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $queue = $this->findQueueByVisitForUpdate($pdo, $visitId);
            if (!$queue) {
                throw new RuntimeException('ไม่พบคิวของเคสนี้');
            }

            $status = (string) ($queue['status'] ?? '');
            $billingTotals = $this->billingTotals($pdo, $visitId);
            $hasPaidPayment = $this->visitHasPaidPayment($pdo, $visitId);
            $grandTotal = (float) ($billingTotals['grand_total'] ?? 0);

            if ($status === 'WAITING') {
                throw new RuntimeException('เคสนี้ยังไม่ได้เริ่มตรวจ กรุณาเปิด Smart Exam ก่อนปิดเคส');
            }

            if ($status !== 'COMPLETED' && !$hasPaidPayment && $grandTotal > 0) {
                $pdo->rollBack();
                flash('warning', 'ยังมียอดค้างชำระ กรุณาส่งชำระเงินก่อนปิดเคส');
                redirect('payments');
            }

            if ($status !== 'COMPLETED') {
                $this->assertTransitionAllowed($queue, 'COMPLETED');
                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "COMPLETED", finished_at = COALESCE(finished_at, NOW()), updated_at = NOW()
                     WHERE id = :id'
                )->execute(['id' => (int) $queue['id']]);

                $this->writeQueueAudit('QUEUE_CASE_CLOSED', (int) $queue['id'], [
                    'visit_id' => $visitId,
                    'from_status' => $status,
                    'to_status' => 'COMPLETED',
                    'grand_total' => $grandTotal,
                    'has_paid_payment' => $hasPaidPayment,
                    'source' => 'close_and_next',
                ], $pdo);
            }

            $otherActive = $this->findOtherInServiceQueue($pdo, (int) $queue['id']);
            if ($otherActive) {
                $pdo->commit();
                flash('warning', 'ยังมีเคสที่กำลังตรวจอยู่ ระบบจึงไม่เรียกคิวใหม่ซ้อน');
                redirect('queue', ['visit_id' => (int) $otherActive['visit_id']]);
            }

            $nextQueue = $this->nextWaitingQueueForUpdate($pdo);
            if ($nextQueue) {
                $this->assertTransitionAllowed($nextQueue, 'IN_SERVICE');
                $pdo->prepare(
                    'UPDATE queue_entries
                     SET status = "IN_SERVICE", called_at = COALESCE(called_at, NOW()), updated_at = NOW()
                     WHERE id = :id'
                )->execute(['id' => (int) $nextQueue['id']]);

                $nextVisitId = (int) $nextQueue['visit_id'];
                $this->writeQueueAudit('QUEUE_NEXT_CALLED', (int) $nextQueue['id'], [
                    'visit_id' => $nextVisitId,
                    'from_status' => (string) ($nextQueue['status'] ?? ''),
                    'to_status' => 'IN_SERVICE',
                    'closed_visit_id' => $visitId,
                    'source' => 'close_and_next',
                ], $pdo);
            }

            $pdo->commit();

            if ($nextVisitId > 0) {
                flash('success', 'ปิดเคสแล้ว และเรียกคิวถัดไปขึ้นทำงานต่อ');
                redirect('queue', ['visit_id' => $nextVisitId]);
            }

            flash('success', 'ปิดเคสแล้ว วันนี้ยังไม่มีคิวถัดไป');
            redirect('queue');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            flash('error', 'ปิดเคสและเรียกคิวถัดไปไม่สำเร็จ: ' . $throwable->getMessage());
            redirect('queue', ['visit_id' => $visitId]);
        }
    }

    private function todayQueues(): array
    {
        return db()->query(
            'SELECT queue_entries.*, visits.id AS visit_id, visits.patient_id, visits.visit_no, visits.chief_complaint,
                    patients.hn, patients.first_name, patients.last_name, patients.phone, patients.drug_allergy
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

    private function dailyQueueMetrics(array $todayQueues): array
    {
        $patientIds = [];
        foreach ($todayQueues as $queue) {
            $patientId = (int) ($queue['patient_id'] ?? 0);
            if ($patientId > 0) {
                $patientIds[$patientId] = true;
            }
        }

        $revenueToday = 0.0;
        $financialToday = [
            'CASH' => 0.0,
            'QR' => 0.0,
            'TRANSFER' => 0.0,
        ];
        if ($this->tableExists('payments')) {
            $revenueToday = (float) db()->query(
                'SELECT COALESCE(SUM(total_amount), 0)
                 FROM payments
                 WHERE payment_status = "PAID"
                   AND DATE(paid_at) = CURDATE()'
            )->fetchColumn();

            $methodStmt = db()->query(
                'SELECT payment_method, COALESCE(SUM(total_amount), 0) AS total_amount
                 FROM payments
                 WHERE payment_status = "PAID"
                   AND DATE(paid_at) = CURDATE()
                 GROUP BY payment_method'
            );
            foreach ($methodStmt->fetchAll() as $row) {
                $method = (string) ($row['payment_method'] ?? '');
                if (array_key_exists($method, $financialToday)) {
                    $financialToday[$method] = (float) ($row['total_amount'] ?? 0);
                }
            }
        }

        $avgCaseMinutes = 0;
        if ($this->tableExists('queue_entries')) {
            $avgCaseMinutes = (int) round((float) db()->query(
                'SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, COALESCE(called_at, checked_in_at, created_at), COALESCE(finished_at, updated_at))), 0)
                 FROM queue_entries
                 WHERE queue_date = CURDATE()
                   AND status = "COMPLETED"
                   AND COALESCE(finished_at, updated_at) IS NOT NULL'
            )->fetchColumn());
        }

        return [
            'patient_count' => count($patientIds),
            'exam_done_count' => count(array_filter(
                $todayQueues,
                static fn(array $queue): bool => in_array((string) ($queue['status'] ?? ''), ['WAITING_PAYMENT', 'COMPLETED'], true)
            )),
            'payment_waiting_count' => count(array_filter(
                $todayQueues,
                static fn(array $queue): bool => (string) ($queue['status'] ?? '') === 'WAITING_PAYMENT'
            )),
            'revenue_today' => $revenueToday,
            'avg_case_minutes' => $avgCaseMinutes,
            'financial_today' => $financialToday,
        ];
    }

    private function queueAssistantAlerts(array $todayQueues): array
    {
        $lowStockCount = 0;
        if ($this->tableExists('inventory_items') && $this->tableExists('inventory_batches')) {
            $lowStockCount = (int) db()->query(
                'SELECT COUNT(*)
                 FROM inventory_items
                 LEFT JOIN (
                    SELECT item_id, SUM(qty_balance) AS qty_balance
                    FROM inventory_batches
                    GROUP BY item_id
                 ) stock_totals ON stock_totals.item_id = inventory_items.id
                 WHERE inventory_items.is_active = 1
                   AND COALESCE(stock_totals.qty_balance, 0) <= inventory_items.reorder_level'
            )->fetchColumn();
        }

        $latestBackup = null;
        if ($this->tableExists('backup_logs')) {
            $latestBackup = db()->query(
                'SELECT status, created_at
                 FROM backup_logs
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1'
            )->fetch() ?: null;
        }

        $pendingCases = count(array_filter(
            $todayQueues,
            static fn(array $queue): bool => in_array((string) ($queue['status'] ?? ''), ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT'], true)
        ));

        $overdueCases = 0;
        foreach ($todayQueues as $queue) {
            $status = (string) ($queue['status'] ?? '');
            if (!in_array($status, ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT'], true)) {
                continue;
            }

            $startedAt = $queue['called_at'] ?? $queue['checked_in_at'] ?? $queue['created_at'] ?? null;
            if ($startedAt && (time() - strtotime((string) $startedAt)) >= 1800) {
                $overdueCases++;
            }
        }

        $backupDoneToday = false;
        if (!empty($latestBackup['created_at'])) {
            $backupDoneToday = date('Y-m-d', strtotime((string) $latestBackup['created_at'])) === date('Y-m-d')
                && (string) ($latestBackup['status'] ?? '') !== 'FAILED';
        }

        return [
            'low_stock_count' => $lowStockCount,
            'urgent_count' => 0,
            'pending_cases' => $pendingCases,
            'overdue_cases' => $overdueCases,
            'latest_backup' => $latestBackup,
            'backup_done_today' => $backupDoneToday,
            'smart_card_online' => $this->smartCardBridgeOnline(),
            'printer_ready' => false,
        ];
    }

    private function smartCardBridgeOnline(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 0.35,
                'ignore_errors' => true,
            ],
        ]);
        $payload = @file_get_contents('http://127.0.0.1:8189/health', false, $context);
        if (!$payload) {
            return false;
        }

        $health = json_decode($payload, true);
        return is_array($health) && !empty($health['ok']);
    }

    private function todayActivitySummary(): array
    {
        $pdo = db();
        $paymentCount = 0;
        $receiptCount = 0;
        $stickerPrintCount = 0;
        $smartExamCount = 0;
        $latestReceipt = null;
        $latestSticker = null;
        $latestCase = null;

        if ($this->tableExists('queue_entries')) {
            $smartExamCount = (int) $pdo->query(
                'SELECT COUNT(*)
                 FROM queue_entries
                 WHERE queue_date = CURDATE()
                   AND called_at IS NOT NULL'
            )->fetchColumn();

            $latestCase = $pdo->query(
                'SELECT queue_entries.queue_no, queue_entries.status, queue_entries.updated_at,
                        patients.first_name, patients.last_name
                 FROM queue_entries
                 INNER JOIN visits ON visits.id = queue_entries.visit_id
                 INNER JOIN patients ON patients.id = visits.patient_id
                 WHERE queue_entries.queue_date = CURDATE()
                 ORDER BY queue_entries.updated_at DESC, queue_entries.id DESC
                 LIMIT 1'
            )->fetch() ?: null;
        }

        if ($this->tableExists('payments')) {
            $paymentCount = (int) $pdo->query(
                'SELECT COUNT(*)
                 FROM payments
                 WHERE payment_status = "PAID"
                   AND DATE(paid_at) = CURDATE()'
            )->fetchColumn();
            $receiptCount = $paymentCount;

            $latestReceipt = $pdo->query(
                'SELECT payments.receipt_no, payments.total_amount, payments.paid_at,
                        patients.first_name, patients.last_name
                 FROM payments
                 INNER JOIN visits ON visits.id = payments.visit_id
                 INNER JOIN patients ON patients.id = visits.patient_id
                 WHERE payments.payment_status = "PAID"
                   AND DATE(payments.paid_at) = CURDATE()
                 ORDER BY payments.paid_at DESC, payments.id DESC
                 LIMIT 1'
            )->fetch() ?: null;
        }

        if ($this->tableExists('medication_print_logs')) {
            $stickerPrintCount = (int) $pdo->query(
                'SELECT COUNT(*)
                 FROM medication_print_logs
                 WHERE DATE(printed_at) = CURDATE()'
            )->fetchColumn();

            $latestSticker = $pdo->query(
                'SELECT medication_print_logs.printed_at, prescription_items.drug_name_snapshot,
                        patients.first_name, patients.last_name
                 FROM medication_print_logs
                 INNER JOIN prescription_items ON prescription_items.id = medication_print_logs.prescription_item_id
                 INNER JOIN patients ON patients.id = medication_print_logs.patient_id
                 WHERE DATE(medication_print_logs.printed_at) = CURDATE()
                 ORDER BY medication_print_logs.printed_at DESC, medication_print_logs.id DESC
                 LIMIT 1'
            )->fetch() ?: null;
        }

        return [
            'smart_exam_count' => $smartExamCount,
            'payment_count' => $paymentCount,
            'receipt_count' => $receiptCount,
            'sticker_print_count' => $stickerPrintCount,
            'latest_case' => $latestCase,
            'latest_receipt' => $latestReceipt,
            'latest_sticker' => $latestSticker,
        ];
    }

    private function recentQueueActivity(): array
    {
        $events = [];
        $stmt = db()->query(
            'SELECT queue_entries.queue_no, queue_entries.status, queue_entries.created_at, queue_entries.called_at, queue_entries.updated_at, queue_entries.finished_at,
                    visits.id AS visit_id, visits.visit_no, patients.first_name, patients.last_name,
                    payments.receipt_no, payments.total_amount, payments.paid_at,
                    service_events.latest_service_at, service_events.latest_service_name
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             INNER JOIN patients ON patients.id = visits.patient_id
             LEFT JOIN payments ON payments.visit_id = visits.id AND payments.payment_status = "PAID"
             LEFT JOIN (
                SELECT visit_services.visit_id, MAX(visit_services.created_at) AS latest_service_at, MAX(services.service_name) AS latest_service_name
                FROM visit_services
                INNER JOIN services ON services.id = visit_services.service_id
                GROUP BY visit_services.visit_id
             ) AS service_events ON service_events.visit_id = visits.id
             WHERE queue_entries.queue_date = CURDATE()
             ORDER BY queue_entries.updated_at DESC, queue_entries.id DESC
             LIMIT 20'
        );

        foreach ($stmt->fetchAll() as $row) {
            $patientName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $patientName = $patientName !== '' ? $patientName : 'ไม่ระบุชื่อ';

            $this->pushQueueActivity($events, $row['created_at'] ?? null, 'เปิดเคส ' . $patientName, 'bi-person-check');
            $this->pushQueueActivity($events, $row['called_at'] ?? null, 'เปิด Smart Exam ' . $patientName, 'bi-heart-pulse');
            if (!empty($row['latest_service_at'])) {
                $this->pushQueueActivity($events, $row['latest_service_at'], 'เพิ่มบริการ ' . (string) ($row['latest_service_name'] ?? ''), 'bi-plus-circle');
            }
            if ((string) ($row['status'] ?? '') === 'WAITING_PAYMENT') {
                $this->pushQueueActivity($events, $row['updated_at'] ?? null, 'ส่งชำระเงิน ' . $patientName, 'bi-wallet2');
            }
            $this->pushQueueActivity($events, $row['paid_at'] ?? null, 'รับชำระเงิน ' . $patientName, 'bi-cash-stack');
            $this->pushQueueActivity($events, $row['finished_at'] ?? null, 'จบเคส ' . $patientName, 'bi-check-circle');
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return array_slice($events, 0, 10);
    }

    private function pushQueueActivity(array &$events, mixed $at, string $text, string $icon): void
    {
        if (empty($at)) {
            return;
        }

        $events[] = [
            'at' => (string) $at,
            'time' => date('H:i', strtotime((string) $at)),
            'text' => trim($text),
            'icon' => $icon,
        ];
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findWorkflowVisit(int $visitId): ?array
    {
        $stmt = db()->prepare(
            'SELECT visits.*, queue_entries.id AS queue_id, queue_entries.queue_no, queue_entries.status, queue_entries.checked_in_at, queue_entries.called_at, queue_entries.finished_at,
                    patients.hn, patients.citizen_id, patients.title_name, patients.first_name, patients.last_name, patients.phone, patients.gender, patients.birth_date, patients.address, patients.drug_allergy, patients.underlying_disease, patients.note, patients.photo_path,
                    (SELECT COUNT(*) FROM visits AS patient_visits WHERE patient_visits.patient_id = patients.id) AS visit_count,
                    payments.id AS payment_id, payments.receipt_no, payments.paid_at,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c, visit_vitals.pulse_rate, visit_vitals.resp_rate, visit_vitals.spo2, visit_vitals.weight_kg,
                    COALESCE(service_totals.total_service, 0) AS service_total,
                    COALESCE(item_totals.total_item, 0) AS item_total,
                    COALESCE(service_totals.service_count, 0) AS service_count,
                    COALESCE(item_totals.item_count, 0) AS item_count
             FROM visits
             INNER JOIN patients ON patients.id = visits.patient_id
             INNER JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
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
        $visit['history_lines'] = $this->patientHistoryLines((int) ($visit['patient_id'] ?? 0), $visitId);
        $visit['case_timeline'] = $this->caseTimeline($visitId, $visit);

        return $visit;
    }

    private function caseTimeline(int $visitId, array $visit): array
    {
        $events = [];
        $this->pushCaseTimeline($events, $visit['checked_in_at'] ?? $visit['visit_datetime'] ?? $visit['created_at'] ?? null, 'REGISTERED', 'done');
        $this->pushCaseTimeline($events, $visit['called_at'] ?? null, 'OPENED_SMART_EXAM', 'done');

        $serviceAt = $this->firstVisitEventAt('visit_services', $visitId);
        $itemAt = $this->firstVisitEventAt('visit_item_usages', $visitId);
        $this->pushCaseTimeline($events, $serviceAt, 'ADDED_SERVICE', 'done');
        $this->pushCaseTimeline($events, $itemAt, 'DISPENSED_MEDICATION', 'done');
        $this->pushCaseTimeline($events, $visit['paid_at'] ?? null, 'PAID', 'done');
        $this->pushCaseTimeline($events, $visit['finished_at'] ?? null, 'CLOSED_CASE', 'done');

        if ($events === []) {
            return [];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        $status = (string) ($visit['status'] ?? 'WAITING');
        $pendingLabel = match ($status) {
            'WAITING' => 'WAITING_SMART_EXAM',
            'IN_SERVICE' => ((int) ($visit['service_count'] ?? 0) + (int) ($visit['item_count'] ?? 0)) > 0 ? 'CONTINUE_SERVICE' : 'RECORDING_SERVICE',
            'WAITING_PAYMENT' => 'WAITING_PAYMENT',
            'COMPLETED' => 'COMPLETED',
            default => 'CONTINUE_WORKFLOW',
        };

        if ($status !== 'COMPLETED') {
            $events[] = [
                'at' => date('Y-m-d H:i:s'),
                'time' => 'now',
                'label' => $pendingLabel,
                'state' => 'current',
            ];
        }

        return array_slice($events, -6);
    }

    private function firstVisitEventAt(string $tableName, int $visitId): ?string
    {
        if (!in_array($tableName, ['visit_services', 'visit_item_usages'], true)) {
            return null;
        }

        try {
            $stmt = db()->prepare("SELECT MIN(created_at) FROM {$tableName} WHERE visit_id = :visit_id");
            $stmt->execute(['visit_id' => $visitId]);
            $value = $stmt->fetchColumn();

            return $value ? (string) $value : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function pushCaseTimeline(array &$events, mixed $at, string $label, string $state): void
    {
        if (empty($at)) {
            return;
        }

        $events[] = [
            'at' => (string) $at,
            'time' => date('H:i', strtotime((string) $at)),
            'label' => $label,
            'state' => $state,
        ];
    }

    private function patientHistoryLines(int $patientId, int $currentVisitId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT visits.id, visits.visit_datetime, visits.chief_complaint, visits.diagnosis,
                    COALESCE(service_summary.services_summary, "-") AS services_summary,
                    COALESCE(item_summary.items_summary, "-") AS items_summary,
                    COALESCE(payments.total_amount, 0) AS paid_total,
                    payments.receipt_no
             FROM visits
             LEFT JOIN payments ON payments.visit_id = visits.id AND payments.payment_status = "PAID"
             LEFT JOIN (
                SELECT visit_services.visit_id,
                       GROUP_CONCAT(services.service_name ORDER BY visit_services.id SEPARATOR ", ") AS services_summary
                FROM visit_services
                INNER JOIN services ON services.id = visit_services.service_id
                GROUP BY visit_services.visit_id
             ) AS service_summary ON service_summary.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_item_usages.visit_id,
                       GROUP_CONCAT(inventory_items.item_name ORDER BY visit_item_usages.id SEPARATOR ", ") AS items_summary
                FROM visit_item_usages
                INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
                GROUP BY visit_item_usages.visit_id
             ) AS item_summary ON item_summary.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
               AND visits.id <> :current_visit_id
             ORDER BY visits.visit_datetime DESC, visits.id DESC
             LIMIT 5'
        );
        $stmt->execute([
            'patient_id' => $patientId,
            'current_visit_id' => $currentVisitId,
        ]);

        return array_map(static function (array $row): array {
            $dateText = !empty($row['visit_datetime'])
                ? date('d/m/Y H:i', strtotime((string) $row['visit_datetime']))
                : '-';

            return [
                'id' => (int) ($row['id'] ?? 0),
                'date' => $dateText,
                'chief_complaint' => (string) (($row['chief_complaint'] ?? '') ?: '-'),
                'diagnosis' => (string) (($row['diagnosis'] ?? '') ?: '-'),
                'services_summary' => (string) (($row['services_summary'] ?? '') ?: '-'),
                'items_summary' => (string) (($row['items_summary'] ?? '') ?: '-'),
                'paid_total' => (float) ($row['paid_total'] ?? 0),
                'paid_total_text' => format_money((float) ($row['paid_total'] ?? 0)),
                'receipt_no' => (string) (($row['receipt_no'] ?? '') ?: '-'),
            ];
        }, $stmt->fetchAll());
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

    private function findQueueByVisitForUpdate(PDO $pdo, int $visitId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT queue_entries.*, visits.id AS visit_id
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             WHERE queue_entries.visit_id = :visit_id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['visit_id' => $visitId]);

        $queue = $stmt->fetch();
        return $queue ?: null;
    }

    private function nextWaitingQueueForUpdate(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT queue_entries.*, visits.id AS visit_id
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             WHERE queue_entries.queue_date = CURDATE()
               AND queue_entries.status = "WAITING"
             ORDER BY queue_entries.queue_no ASC, queue_entries.id ASC
             LIMIT 1
             FOR UPDATE'
        );

        $queue = $stmt->fetch();
        return $queue ?: null;
    }

    private function findOtherInServiceQueue(PDO $pdo, int $excludeQueueId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT queue_entries.*, visits.id AS visit_id
             FROM queue_entries
             INNER JOIN visits ON visits.id = queue_entries.visit_id
             WHERE queue_entries.queue_date = CURDATE()
               AND queue_entries.status = "IN_SERVICE"
               AND queue_entries.id <> :exclude_id
             ORDER BY queue_entries.called_at ASC, queue_entries.id ASC
             LIMIT 1'
        );
        $stmt->execute(['exclude_id' => $excludeQueueId]);

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
            'uri' => [
                'label' => 'เนเธเนเธซเธงเธฑเธ” / URI',
                'description' => 'เน€เธ•เธดเธก CC / PI / PE / Dx เธชเธณเธซเธฃเธฑเธเนเธเนเธซเธงเธฑเธ”เนเธฅเธฐเธญเธฒเธเธฒเธฃเธ—เธฒเธเน€เธ”เธดเธเธซเธฒเธขเนเธเธชเนเธงเธเธเธ',
                'theme' => 'preset-uri',
                'services' => ['SRV001'],
                'items' => [],
                'cc' => 'เนเธเน เนเธญ เธกเธตเธเนเธณเธกเธนเธ',
                'pi' => 'เธกเธตเนเธเน เนเธญ เธกเธตเธเนเธณเธกเธนเธ เน€เธเนเธเธเธญเน€เธฅเนเธเธเนเธญเธข เนเธกเนเธกเธตเธซเธญเธเน€เธซเธเธทเนเธญเธข',
                'pe' => 'Throat mildly injected, chest clear',
                'dx' => 'URI',
                'advice' => 'เธเธฑเธเธเนเธญเธเนเธซเนเน€เธเธตเธขเธเธเธญ เธ”เธทเนเธกเธเนเธณเธกเธฒเธเธเธถเนเธ เธชเธฑเธเน€เธเธ•เธญเธฒเธเธฒเธฃเธซเธญเธเน€เธซเธเธทเนเธญเธขเธซเธฃเธทเธญเนเธเนเธชเธนเธ เธซเธฒเธเธญเธฒเธเธฒเธฃเนเธกเนเธ”เธตเธเธถเนเธเนเธซเนเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเน',
                'followup_days' => null,
            ],
            'wound_dressing' => [
                'label' => 'เธ—เธณเนเธเธฅ',
                'description' => 'เน€เธเธดเนเธกเธเนเธฒเธ—เธณเนเธเธฅ เธเธฃเนเธญเธกเธเนเธณเน€เธเธฅเธทเธญ เธเนเธฒเธเนเธญเธ เนเธฅเธฐเธเธณเนเธเธฐเธเธณเธ”เธนเนเธฅเนเธเธฅ',
                'theme' => 'preset-wound',
                'services' => ['SRV002'],
                'items' => [
                    ['code' => 'MED002', 'qty' => 1],
                    ['code' => 'MED003', 'qty' => 2],
                ],
                'cc' => 'เธกเธตเนเธเธฅ',
                'pi' => 'เธกเธตเนเธเธฅเธเธฒเธเธญเธธเธเธฑเธ•เธดเน€เธซเธ•เธธ เนเธกเนเธกเธตเน€เธฅเธทเธญเธ”เธญเธญเธเธกเธฒเธ',
                'pe' => 'Wound clean, no active bleeding',
                'dx' => 'Wound',
                'advice' => 'เธ”เธนเนเธฅเนเธเธฅเนเธซเนเนเธซเนเธ เธ—เธณเธเธงเธฒเธกเธชเธฐเธญเธฒเธ”เธ•เธฒเธกเธเธณเนเธเธฐเธเธณ เนเธฅเธฐเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเนเธซเธฒเธเธเธงเธ”เธเธงเธกเนเธ”เธเธกเธฒเธเธเธถเนเธ',
                'followup_days' => 2,
            ],
            'injection' => [
                'label' => 'เธเธตเธ”เธขเธฒ',
                'description' => 'เน€เธเธดเนเธกเธเนเธฒเธเธตเธ”เธขเธฒ เธเธฃเนเธญเธกเธเธฑเธเธ—เธถเธเธเธณเนเธเธฐเธเธณเธซเธฅเธฑเธเธเธตเธ”เน€เธเธทเนเธญเธชเธฃเธธเธเน€เธเธชเนเธ”เนเน€เธฃเนเธงเธเธถเนเธ',
                'theme' => 'preset-injection',
                'services' => ['SRV003'],
                'items' => [],
                'cc' => 'เธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธตเธ”เธขเธฒ',
                'pi' => 'เธกเธฒเธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธตเธ”เธขเธฒเธ•เธฒเธกเนเธเธเธเธฒเธฃเธฃเธฑเธเธฉเธฒ เนเธกเนเธกเธตเธญเธฒเธเธฒเธฃเธเธดเธ”เธเธเธ•เธดเธฃเธฐเธซเธงเนเธฒเธเธฃเธญเธฃเธฑเธเธเธฃเธดเธเธฒเธฃ',
                'pe' => 'General appearance good, no acute distress',
                'dx' => 'Injection service',
                'advice' => 'เธชเธฑเธเน€เธเธ•เธญเธฒเธเธฒเธฃเธเธงเธ” เธเธงเธก เนเธ”เธ เธซเธฃเธทเธญเธเธทเนเธเธซเธฅเธฑเธเธเธตเธ” เธซเธฒเธเธกเธตเธญเธฒเธเธฒเธฃเธเธดเธ”เธเธเธ•เธดเนเธซเนเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเนเธ—เธฑเธเธ—เธต',
                'followup_days' => null,
            ],
            'vital_signs' => [
                'label' => 'เธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธ',
                'description' => 'เน€เธเธดเนเธกเธเนเธฒเธเธฃเธดเธเธฒเธฃเธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธเนเธฅเธฐเธเนเธงเธขเน€เธ•เธฃเธตเธขเธกเนเธเธเธเธญเธฃเนเธกเธชเธณเธซเธฃเธฑเธเธเธฑเธเธ—เธถเธ vital signs',
                'theme' => 'preset-vitals',
                'services' => ['SRV004'],
                'items' => [],
                'cc' => 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ',
                'pi' => 'เธกเธฒเธเธฃเธฐเน€เธกเธดเธเธญเธฒเธเธฒเธฃเนเธฅเธฐเธ•เธฃเธงเธเธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธเน€เธเธทเนเธญเธเธ•เนเธ',
                'pe' => 'General appearance fair',
                'dx' => 'Observation',
                'advice' => 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃเธ•เนเธญเน€เธเธทเนเธญเธเธ•เธฒเธกเธเธฑเธ” เนเธฅเธฐเธเธฑเธเธ—เธถเธเธชเธฑเธเธเธฒเธ“เธเธตเธเธซเธฒเธเธกเธตเธญเธฒเธเธฒเธฃเน€เธเธฅเธตเนเธขเธเนเธเธฅเธ',
                'followup_days' => null,
            ],
            'followup' => [
                'label' => 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ',
                'description' => 'เน€เธเธดเนเธกเธเนเธฒเธ•เธฃเธงเธเธ—เธฑเนเธงเนเธ เธเธฃเนเธญเธกเธเนเธญเธเธงเธฒเธกเธ•เธฑเธงเธญเธขเนเธฒเธเธชเธณเธซเธฃเธฑเธเน€เธเธชเธเธฑเธ”เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ',
                'theme' => 'preset-followup',
                'services' => ['SRV001'],
                'items' => [],
                'cc' => 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ',
                'pi' => 'เธกเธฒเธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃเธซเธฅเธฑเธเธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธฃเธฑเนเธเธเนเธญเธ เธญเธฒเธเธฒเธฃเนเธ”เธขเธฃเธงเธกเธเธเธ—เธตเน',
                'pe' => 'General appearance stable',
                'dx' => 'Follow up',
                'advice' => 'เธฃเธฑเธเธเธฃเธฐเธ—เธฒเธเธขเธฒเธซเธฃเธทเธญเธเธเธดเธเธฑเธ•เธดเธ•เธฒเธกเธเธณเนเธเธฐเธเธณเน€เธ”เธดเธกเธ•เนเธญเน€เธเธทเนเธญเธ เนเธฅเธฐเธเธฅเธฑเธเธกเธฒเธ•เธฒเธกเธเธฑเธ”เธเธฃเธฑเนเธเธ–เธฑเธ”เนเธ',
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
        $defaults = [
            ['uri', 'เนเธเนเธซเธงเธฑเธ” / URI', 'เน€เธ•เธดเธก CC / PI / PE / Dx เธชเธณเธซเธฃเธฑเธเนเธเนเธซเธงเธฑเธ”เนเธฅเธฐเธญเธฒเธเธฒเธฃเธ—เธฒเธเน€เธ”เธดเธเธซเธฒเธขเนเธเธชเนเธงเธเธเธ', 'preset-uri', 'SRV001', '[]', 'เนเธเน เนเธญ เธกเธตเธเนเธณเธกเธนเธ', 'เธกเธตเนเธเน เนเธญ เธกเธตเธเนเธณเธกเธนเธ เน€เธเนเธเธเธญเน€เธฅเนเธเธเนเธญเธข เนเธกเนเธกเธตเธซเธญเธเน€เธซเธเธทเนเธญเธข', 'Throat mildly injected, chest clear', 'URI', 'เธเธฑเธเธเนเธญเธเนเธซเนเน€เธเธตเธขเธเธเธญ เธ”เธทเนเธกเธเนเธณเธกเธฒเธเธเธถเนเธ เธชเธฑเธเน€เธเธ•เธญเธฒเธเธฒเธฃเธซเธญเธเน€เธซเธเธทเนเธญเธขเธซเธฃเธทเธญเนเธเนเธชเธนเธ เธซเธฒเธเธญเธฒเธเธฒเธฃเนเธกเนเธ”เธตเธเธถเนเธเนเธซเนเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเน', null, 5],
            ['wound_dressing', 'เธ—เธณเนเธเธฅ', 'เน€เธเธดเนเธกเธเนเธฒเธ—เธณเนเธเธฅ เธเธฃเนเธญเธกเธเนเธณเน€เธเธฅเธทเธญ เธเนเธฒเธเนเธญเธ เนเธฅเธฐเธเธณเนเธเธฐเธเธณเธ”เธนเนเธฅเนเธเธฅ', 'preset-wound', 'SRV002', '[{"code":"MED002","qty":1},{"code":"MED003","qty":2}]', 'เธกเธตเนเธเธฅ', 'เธกเธตเนเธเธฅเธเธฒเธเธญเธธเธเธฑเธ•เธดเน€เธซเธ•เธธ เนเธกเนเธกเธตเน€เธฅเธทเธญเธ”เธญเธญเธเธกเธฒเธ', 'Wound clean, no active bleeding', 'Wound', 'เธ”เธนเนเธฅเนเธเธฅเนเธซเนเนเธซเนเธ เธ—เธณเธเธงเธฒเธกเธชเธฐเธญเธฒเธ”เธ•เธฒเธกเธเธณเนเธเธฐเธเธณ เนเธฅเธฐเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเนเธซเธฒเธเธเธงเธ”เธเธงเธกเนเธ”เธเธกเธฒเธเธเธถเนเธ', 2, 10],
            ['injection', 'เธเธตเธ”เธขเธฒ', 'เน€เธเธดเนเธกเธเนเธฒเธเธตเธ”เธขเธฒ เธเธฃเนเธญเธกเธเธฑเธเธ—เธถเธเธเธณเนเธเธฐเธเธณเธซเธฅเธฑเธเธเธตเธ”เน€เธเธทเนเธญเธชเธฃเธธเธเน€เธเธชเนเธ”เนเน€เธฃเนเธงเธเธถเนเธ', 'preset-injection', 'SRV003', '[]', 'เธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธตเธ”เธขเธฒ', 'เธกเธฒเธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธตเธ”เธขเธฒเธ•เธฒเธกเนเธเธเธเธฒเธฃเธฃเธฑเธเธฉเธฒ เนเธกเนเธกเธตเธญเธฒเธเธฒเธฃเธเธดเธ”เธเธเธ•เธดเธฃเธฐเธซเธงเนเธฒเธเธฃเธญเธฃเธฑเธเธเธฃเธดเธเธฒเธฃ', 'General appearance good, no acute distress', 'Injection service', 'เธชเธฑเธเน€เธเธ•เธญเธฒเธเธฒเธฃเธเธงเธ” เธเธงเธก เนเธ”เธ เธซเธฃเธทเธญเธเธทเนเธเธซเธฅเธฑเธเธเธตเธ” เธซเธฒเธเธกเธตเธญเธฒเธเธฒเธฃเธเธดเธ”เธเธเธ•เธดเนเธซเนเธเธฅเธฑเธเธกเธฒเธเธเน€เธเนเธฒเธซเธเนเธฒเธ—เธตเนเธ—เธฑเธเธ—เธต', null, 20],
            ['vital_signs', 'เธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธ', 'เน€เธเธดเนเธกเธเนเธฒเธเธฃเธดเธเธฒเธฃเธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธเนเธฅเธฐเธเนเธงเธขเน€เธ•เธฃเธตเธขเธกเนเธเธเธเธญเธฃเนเธกเธชเธณเธซเธฃเธฑเธเธเธฑเธเธ—เธถเธ vital signs', 'preset-vitals', 'SRV004', '[]', 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ', 'เธกเธฒเธเธฃเธฐเน€เธกเธดเธเธญเธฒเธเธฒเธฃเนเธฅเธฐเธ•เธฃเธงเธเธงเธฑเธ”เธชเธฑเธเธเธฒเธ“เธเธตเธเน€เธเธทเนเธญเธเธ•เนเธ', 'General appearance fair', 'Observation', 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃเธ•เนเธญเน€เธเธทเนเธญเธเธ•เธฒเธกเธเธฑเธ” เนเธฅเธฐเธเธฑเธเธ—เธถเธเธชเธฑเธเธเธฒเธ“เธเธตเธเธซเธฒเธเธกเธตเธญเธฒเธเธฒเธฃเน€เธเธฅเธตเนเธขเธเนเธเธฅเธ', null, 30],
            ['followup', 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ', 'เน€เธเธดเนเธกเธเนเธฒเธ•เธฃเธงเธเธ—เธฑเนเธงเนเธ เธเธฃเนเธญเธกเธเนเธญเธเธงเธฒเธกเธ•เธฑเธงเธญเธขเนเธฒเธเธชเธณเธซเธฃเธฑเธเน€เธเธชเธเธฑเธ”เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ', 'preset-followup', 'SRV001', '[]', 'เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ', 'เธกเธฒเธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃเธซเธฅเธฑเธเธฃเธฑเธเธเธฃเธดเธเธฒเธฃเธเธฃเธฑเนเธเธเนเธญเธ เธญเธฒเธเธฒเธฃเนเธ”เธขเธฃเธงเธกเธเธเธ—เธตเน', 'General appearance stable', 'Follow up', 'เธฃเธฑเธเธเธฃเธฐเธ—เธฒเธเธขเธฒเธซเธฃเธทเธญเธเธเธดเธเธฑเธ•เธดเธ•เธฒเธกเธเธณเนเธเธฐเธเธณเน€เธ”เธดเธกเธ•เนเธญเน€เธเธทเนเธญเธ เนเธฅเธฐเธเธฅเธฑเธเธกเธฒเธ•เธฒเธกเธเธฑเธ”เธเธฃเธฑเนเธเธ–เธฑเธ”เนเธ', 7, 40],
        ];

        $stmt = db()->prepare(
            'INSERT INTO smart_exam_presets (
                preset_key, label, description, theme, service_codes, item_codes_json, cc, pi, pe, dx,
                advice, followup_days, sort_order, is_active, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE preset_key = preset_key'
        );

        foreach ($defaults as $preset) {
            $stmt->execute($preset);
        }
    }

    private function ensureTreatmentPresetSchema(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_master (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_master_active (is_active, preset_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_services (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                service_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_services_preset_id (preset_id),
                KEY idx_preset_services_service_id (service_id),
                CONSTRAINT fk_preset_services_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_services_service_id FOREIGN KEY (service_id) REFERENCES services (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_medications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                medicine_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                instruction TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_medications_preset_id (preset_id),
                KEY idx_preset_medications_medicine_id (medicine_id),
                CONSTRAINT fk_preset_medications_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_medications_medicine_id FOREIGN KEY (medicine_id) REFERENCES inventory_items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        db()->exec(
            'CREATE TABLE IF NOT EXISTS preset_supplies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                preset_id BIGINT UNSIGNED NOT NULL,
                supply_id BIGINT UNSIGNED NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_preset_supplies_preset_id (preset_id),
                KEY idx_preset_supplies_supply_id (supply_id),
                CONSTRAINT fk_preset_supplies_preset_id FOREIGN KEY (preset_id) REFERENCES preset_master (id) ON DELETE CASCADE,
                CONSTRAINT fk_preset_supplies_supply_id FOREIGN KEY (supply_id) REFERENCES inventory_items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedTreatmentPresets();
    }

    private function seedTreatmentPresets(): void
    {
        $count = (int) db()->query('SELECT COUNT(*) FROM preset_master')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->seedTreatmentPreset('URI', 'ตรวจ URI แบบเร็ว พร้อมยาเบื้องต้น', ['SRV001'], ['Paracetamol' => 10], []);
        $this->seedTreatmentPreset('ทำแผล', 'เพิ่มบริการทำแผลและเวชภัณฑ์พื้นฐาน', ['SRV002'], [], ['Normal Saline' => 1, 'ผ้าก๊อซ' => 2]);
        $this->seedTreatmentPreset('ฉีดยา', 'เพิ่มค่าบริการฉีดยาเป็น bundle เริ่มต้น', ['SRV003'], [], []);
    }

    private function seedTreatmentPreset(string $name, string $description, array $serviceCodes, array $drugNames, array $supplyNames): void
    {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO preset_master (preset_name, description, is_active, created_at, updated_at)
             VALUES (:preset_name, :description, 1, NOW(), NOW())'
        )->execute(['preset_name' => $name, 'description' => $description]);
        $presetId = (int) $pdo->lastInsertId();

        foreach ($serviceCodes as $serviceCode) {
            $serviceId = $this->serviceIdByCode($serviceCode);
            if ($serviceId > 0) {
                $pdo->prepare('INSERT INTO preset_services (preset_id, service_id, qty, created_at, updated_at) VALUES (:preset_id, :service_id, 1, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'service_id' => $serviceId]);
            }
        }

        foreach ($drugNames as $drugName => $qty) {
            $itemId = $this->itemIdLike((string) $drugName, 'DRUG');
            if ($itemId > 0) {
                $pdo->prepare('INSERT INTO preset_medications (preset_id, medicine_id, qty, instruction, created_at, updated_at) VALUES (:preset_id, :medicine_id, :qty, NULL, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'medicine_id' => $itemId, 'qty' => (float) $qty]);
            }
        }

        foreach ($supplyNames as $supplyName => $qty) {
            $itemId = $this->itemIdLike((string) $supplyName, 'SUPPLY');
            if ($itemId > 0) {
                $pdo->prepare('INSERT INTO preset_supplies (preset_id, supply_id, qty, created_at, updated_at) VALUES (:preset_id, :supply_id, :qty, NOW(), NOW())')
                    ->execute(['preset_id' => $presetId, 'supply_id' => $itemId, 'qty' => (float) $qty]);
            }
        }
    }

    private function serviceIdByCode(string $serviceCode): int
    {
        $stmt = db()->prepare('SELECT id FROM services WHERE service_code = :service_code LIMIT 1');
        $stmt->execute(['service_code' => $serviceCode]);
        return (int) $stmt->fetchColumn();
    }

    private function itemIdLike(string $itemName, string $itemType): int
    {
        $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_type = :item_type AND item_name LIKE :item_name LIMIT 1');
        $stmt->execute(['item_type' => $itemType, 'item_name' => '%' . $itemName . '%']);
        return (int) $stmt->fetchColumn();
    }

    private function treatmentPresetsForExam(): array
    {
        $rows = db()->query(
            'SELECT id
             FROM preset_master
             WHERE is_active = 1
             ORDER BY preset_name ASC'
        )->fetchAll();

        $presets = [];
        foreach ($rows as $row) {
            $preset = $this->treatmentPresetDetail((int) $row['id']);
            if ($preset) {
                $presets[] = $preset;
            }
        }

        return $presets;
    }

    private function treatmentPresetDetail(int $presetId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM preset_master WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $presetId]);
        $preset = $stmt->fetch();
        if (!$preset) {
            return null;
        }

        $preset['services'] = $this->treatmentPresetServices($presetId);
        $preset['medications'] = $this->treatmentPresetItems($presetId, 'preset_medications', 'medicine_id');
        $preset['supplies'] = $this->treatmentPresetItems($presetId, 'preset_supplies', 'supply_id');

        return $preset;
    }

    private function treatmentPresetServices(int $presetId): array
    {
        $stmt = db()->prepare(
            'SELECT preset_services.qty,
                    services.id AS service_id,
                    services.service_code,
                    services.service_name,
                    services.price
             FROM preset_services
             INNER JOIN services ON services.id = preset_services.service_id
             WHERE preset_services.preset_id = :preset_id
               AND services.is_active = 1
             ORDER BY preset_services.id ASC'
        );
        $stmt->execute(['preset_id' => $presetId]);
        return $stmt->fetchAll();
    }

    private function treatmentPresetItems(int $presetId, string $tableName, string $itemField): array
    {
        $instructionSelect = $tableName === 'preset_medications' ? "{$tableName}.instruction," : "NULL AS instruction,";
        $stmt = db()->prepare(
            "SELECT {$tableName}.qty,
                    {$instructionSelect}
                    inventory_items.id AS item_id,
                    inventory_items.item_code,
                    inventory_items.item_name,
                    inventory_items.item_type,
                    inventory_items.unit_name,
                    inventory_items.default_cost,
                    inventory_items.default_price,
                    COALESCE(stock_totals.qty_balance, 0) AS qty_balance
             FROM {$tableName}
             INNER JOIN inventory_items ON inventory_items.id = {$tableName}.{$itemField}
             LEFT JOIN (
                SELECT item_id, SUM(qty_balance) AS qty_balance
                FROM inventory_batches
                GROUP BY item_id
             ) AS stock_totals ON stock_totals.item_id = inventory_items.id
             WHERE {$tableName}.preset_id = :preset_id
               AND inventory_items.is_active = 1
             ORDER BY {$tableName}.id ASC"
        );
        $stmt->execute(['preset_id' => $presetId]);
        return $stmt->fetchAll();
    }

    private function treatmentPresetAlreadyApplied(PDO $pdo, int $visitId, int $presetId): bool
    {
        $serviceStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM visit_services
             WHERE visit_id = :visit_id
               AND remark = :remark'
        );
        $serviceStmt->execute([
            'visit_id' => $visitId,
            'remark' => 'TREATMENT_PRESET:' . $presetId,
        ]);

        if ((int) $serviceStmt->fetchColumn() > 0) {
            return true;
        }

        $itemStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM visit_item_usages
             WHERE visit_id = :visit_id
               AND usage_note LIKE :usage_note'
        );
        $itemStmt->execute([
            'visit_id' => $visitId,
            'usage_note' => 'TREATMENT_PRESET:' . $presetId . ':%',
        ]);

        return (int) $itemStmt->fetchColumn() > 0;
    }

    private function insertTreatmentPresetService(PDO $pdo, int $visitId, array $service, int $presetId): void
    {
        $qty = max(1, (int) round((float) ($service['qty'] ?? 1)));
        $unitPrice = (float) ($service['price'] ?? 0);

        $pdo->prepare(
            'INSERT INTO visit_services (visit_id, service_id, qty, unit_price, line_total, remark, created_at, updated_at)
             VALUES (:visit_id, :service_id, :qty, :unit_price, :line_total, :remark, NOW(), NOW())'
        )->execute([
            'visit_id' => $visitId,
            'service_id' => (int) $service['service_id'],
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $qty,
            'remark' => 'TREATMENT_PRESET:' . $presetId,
        ]);
    }

    private function insertTreatmentPresetItemUsage(PDO $pdo, int $visitId, array $item, int $presetId, string $presetName): void
    {
        $qty = max(0.01, (float) ($item['qty'] ?? 1));
        $itemId = (int) ($item['item_id'] ?? 0);
        $itemName = (string) ($item['item_name'] ?? 'Item');

        if ($itemId <= 0) {
            throw new RuntimeException('Treatment Preset item was not found.');
        }

        $batchStmt = $pdo->prepare(
            'SELECT id, qty_balance, cost_per_unit
             FROM inventory_batches
             WHERE item_id = :item_id
               AND qty_balance > 0
             ORDER BY expiry_date IS NULL ASC, expiry_date ASC, received_date ASC, id ASC
             FOR UPDATE'
        );
        $batchStmt->execute(['item_id' => $itemId]);
        $batches = $batchStmt->fetchAll();
        $availableQty = array_sum(array_map(static fn(array $batch): float => (float) $batch['qty_balance'], $batches));

        if ($availableQty < $qty) {
            throw new RuntimeException('Insufficient stock for ' . $itemName . '. Available ' . format_money($availableQty) . ', required ' . format_money($qty) . '.');
        }

        $lineTotal = (float) ($item['default_price'] ?? 0) * $qty;
        $usageNoteParts = ['TREATMENT_PRESET:' . $presetId, $presetName];
        $instruction = trim((string) ($item['instruction'] ?? ''));
        if ($instruction !== '') {
            $usageNoteParts[] = $instruction;
        }

        $pdo->prepare(
            'INSERT INTO visit_item_usages (visit_id, item_id, qty, unit_price, line_total, usage_note, created_at, updated_at)
             VALUES (:visit_id, :item_id, :qty, :unit_price, :line_total, :usage_note, NOW(), NOW())'
        )->execute([
            'visit_id' => $visitId,
            'item_id' => $itemId,
            'qty' => $qty,
            'unit_price' => (float) ($item['default_price'] ?? 0),
            'line_total' => $lineTotal,
            'usage_note' => implode(':', $usageNoteParts),
        ]);
        $usageId = (int) $pdo->lastInsertId();

        $remainingQty = $qty;
        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $takeQty = min($remainingQty, (float) $batch['qty_balance']);
            $newBalance = (float) $batch['qty_balance'] - $takeQty;

            $pdo->prepare(
                'UPDATE inventory_batches
                 SET qty_balance = :qty_balance,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'qty_balance' => $newBalance,
                'id' => (int) $batch['id'],
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
                'batch_id' => (int) $batch['id'],
                'item_id' => $itemId,
                'qty' => $takeQty,
                'unit_cost' => (float) ($batch['cost_per_unit'] ?? $item['default_cost'] ?? 0),
                'reference_id' => $usageId,
                'note' => 'Stock deducted by Treatment Preset: ' . $presetName,
                'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
            ]);

            $remainingQty -= $takeQty;
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
            $chiefComplaint = $this->mergeClinicalText($chiefComplaint, (string) ($preset['cc'] ?? ''), ', ');
            $presentIllness = $this->mergeClinicalText($presentIllness, (string) ($preset['pi'] ?? ''), PHP_EOL);
            $physicalExam = $this->mergeClinicalText($physicalExam, (string) ($preset['pe'] ?? ''), PHP_EOL);
            $diagnosis = $this->mergeClinicalText($diagnosis, (string) ($preset['dx'] ?? ''), ' / ');
            $advice = $this->mergeClinicalText($advice, (string) ($preset['advice'] ?? ''), PHP_EOL);
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

    private function mergeClinicalText(string $current, string $incoming, string $separator): string
    {
        $current = trim($current);
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $current;
        }

        if ($current === '') {
            return $incoming;
        }

        if (str_contains($current, $incoming)) {
            return $current;
        }

        return $current . $separator . $incoming;
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
            'purpose' => 'เธเธฑเธ”เธ•เธดเธ”เธ•เธฒเธกเธญเธฒเธเธฒเธฃ',
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
            throw new RuntimeException('เนเธกเนเธเธเธเธฃเธดเธเธฒเธฃเธ—เธตเนเธเธณเธซเธเธ”เนเธงเนเธชเธณเธซเธฃเธฑเธ preset');
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
            throw new RuntimeException('เนเธกเนเธเธเธฃเธฒเธขเธเธฒเธฃเธญเธธเธเธเธฃเธ“เนเธซเธฃเธทเธญเน€เธงเธเธ เธฑเธ“เธ‘เนเธชเธณเธซเธฃเธฑเธ preset');
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
            throw new RuntimeException('เธญเธธเธเธเธฃเธ“เนเธซเธฃเธทเธญเน€เธงเธเธ เธฑเธ“เธ‘เนเธเธเน€เธซเธฅเธทเธญเนเธกเนเธเธญเธชเธณเธซเธฃเธฑเธ preset ' . $presetLabel);
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

        $usageId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO stock_movements (batch_id, item_id, movement_type, qty, unit_cost, reference_type, reference_id, note, movement_datetime, created_by, created_at, updated_at)
             VALUES (:batch_id, :item_id, "OUT", :qty, :unit_cost, "VISIT_USAGE", :reference_id, :note, NOW(), :created_by, NOW(), NOW())'
        )->execute([
            'batch_id' => $batch['id'],
            'item_id' => $item['id'],
            'qty' => $qty,
            'unit_cost' => $batch['cost_per_unit'] ?? $item['default_cost'],
            'reference_id' => $usageId,
            'note' => 'เธ•เธฑเธ”เธชเธ•เนเธญเธเธเธฒเธ preset ' . $presetLabel,
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

    private function visitClinicalSummary(PDO $pdo, int $visitId): array
    {
        $stmt = $pdo->prepare(
            'SELECT chief_complaint, present_illness, physical_exam, diagnosis, advice, followup_date
             FROM visits
             WHERE id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);
        $visit = $stmt->fetch() ?: [];

        return [
            'cc' => (string) ($visit['chief_complaint'] ?? ''),
            'pi' => (string) ($visit['present_illness'] ?? ''),
            'pe' => (string) ($visit['physical_exam'] ?? ''),
            'dx' => (string) ($visit['diagnosis'] ?? ''),
            'advice' => (string) ($visit['advice'] ?? ''),
            'followup_date' => (string) ($visit['followup_date'] ?? ''),
        ];
    }

    private function visitOrderSummary(PDO $pdo, int $visitId): array
    {
        $serviceStmt = $pdo->prepare(
            'SELECT visit_services.id, services.service_name, visit_services.qty, visit_services.line_total
             FROM visit_services
             INNER JOIN services ON services.id = visit_services.service_id
             WHERE visit_services.visit_id = :visit_id
             ORDER BY visit_services.id DESC'
        );
        $serviceStmt->execute(['visit_id' => $visitId]);
        $serviceLines = $serviceStmt->fetchAll();

        $itemStmt = $pdo->prepare(
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

    private function recordSmartPayment(PDO $pdo, int $visitId, array $billingTotals): array
    {
        $grandTotal = (float) ($billingTotals['grand_total'] ?? 0);
        if ($grandTotal <= 0) {
            throw new RuntimeException('เธขเธฑเธเนเธกเนเธกเธตเธฃเธฒเธขเธเธฒเธฃเธเธดเธ”เน€เธเธดเธ เธเธฃเธธเธ“เธฒเน€เธเธดเนเธกเธเธฃเธดเธเธฒเธฃเธซเธฃเธทเธญเธขเธฒเธเนเธญเธเธฃเธฑเธเน€เธเธดเธ');
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
            throw new RuntimeException('เธขเธญเธ”เธฃเธฑเธเธเธณเธฃเธฐเธเนเธญเธขเธเธงเนเธฒเธขเธญเธ”เธชเธธเธ—เธเธด เธเธฃเธธเธ“เธฒเธ•เธฃเธงเธเธชเธญเธเธญเธตเธเธเธฃเธฑเนเธ');
        }

        if ($this->visitHasPayment($visitId)) {
            throw new RuntimeException('เน€เธเธชเธเธตเนเธกเธตเธฃเธฒเธขเธเธฒเธฃเธเธณเธฃเธฐเน€เธเธดเธเนเธฅเนเธง');
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
            throw new RuntimeException('เนเธกเนเธชเธฒเธกเธฒเธฃเธ–เน€เธเธฅเธตเนเธขเธเธชเธ–เธฒเธเธฐเธเธฒเธ ' . $fromStatus . ' เนเธเน€เธเนเธ ' . $targetStatus . ' เนเธ”เน');
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

    private function visitHasPaidPayment(PDO $pdo, int $visitId): bool
    {
        if (!$this->tableExists('payments')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM payments
             WHERE visit_id = :visit_id
               AND payment_status = "PAID"'
        );
        $stmt->execute(['visit_id' => $visitId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function patientProfileForResponse(int $patientId): array
    {
        $stmt = db()->prepare(
            'SELECT patients.*,
                    (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                    (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
             FROM patients
             WHERE patients.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $patientId]);
        $patient = $stmt->fetch() ?: [];

        $birthDate = (string) ($patient['birth_date'] ?? '');
        $firstName = (string) ($patient['first_name'] ?? '');
        $lastName = (string) ($patient['last_name'] ?? '');
        $drugAllergy = trim((string) ($patient['drug_allergy'] ?? ''));
        $chronic = trim((string) ($patient['underlying_disease'] ?? ''));

        return [
            'id' => $patientId,
            'hn' => (string) ($patient['hn'] ?? ''),
            'citizen_id' => (string) ($patient['citizen_id'] ?? ''),
            'title_name' => (string) ($patient['title_name'] ?? ''),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim($firstName . ' ' . $lastName),
            'gender' => (string) ($patient['gender'] ?? ''),
            'gender_text' => $this->patientGenderText((string) ($patient['gender'] ?? '')),
            'birth_date' => $birthDate,
            'birth_date_text' => $this->patientBirthDateText($birthDate),
            'age_text' => $this->patientAgeText($birthDate),
            'phone' => (string) ($patient['phone'] ?? ''),
            'phone_text' => trim((string) ($patient['phone'] ?? '')) !== '' ? (string) $patient['phone'] : '-',
            'address' => (string) ($patient['address'] ?? ''),
            'underlying_disease' => (string) ($patient['underlying_disease'] ?? ''),
            'underlying_disease_text' => $chronic !== '' && $chronic !== '-' ? $chronic : 'ไม่มีโรคประจำตัว',
            'drug_allergy' => (string) ($patient['drug_allergy'] ?? ''),
            'drug_allergy_text' => $drugAllergy !== '' && $drugAllergy !== '-' ? $drugAllergy : 'ไม่มีประวัติแพ้ยา',
            'note' => (string) ($patient['note'] ?? ''),
            'visit_count' => (int) ($patient['visit_count'] ?? 0),
            'last_visit_at' => (string) ($patient['last_visit_at'] ?? ''),
            'last_visit_text' => !empty($patient['last_visit_at']) ? thai_date((string) $patient['last_visit_at']) : '-',
            'has_drug_allergy' => $drugAllergy !== '' && $drugAllergy !== '-',
            'has_chronic' => $chronic !== '' && $chronic !== '-',
        ];
    }

    private function patientGenderText(string $gender): string
    {
        return match ($gender) {
            'M' => 'ชาย',
            'F' => 'หญิง',
            'O' => 'อื่นๆ',
            default => '-',
        };
    }

    private function patientAgeText(string $birthDate): string
    {
        if ($birthDate === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($birthDate))->diff(new \DateTimeImmutable('today'))->y . ' ปี';
        } catch (Throwable $throwable) {
            return '-';
        }
    }

    private function patientBirthDateText(string $birthDate): string
    {
        if ($birthDate === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($birthDate);
            return $date->format('d/m/') . ((int) $date->format('Y') + 543);
        } catch (Throwable $throwable) {
            return '';
        }
    }

    private function normalizePatientBirthDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $day = 0;
        $month = 0;
        $year = 0;
        $normalized = preg_replace('/\s+/', '', $value) ?? '';

        if (preg_match('/^(\d{1,4})[\/.-](\d{1,2})[\/.-](\d{1,4})$/', $normalized, $matches)) {
            if (strlen($matches[1]) === 4) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } else {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
            }
        } else {
            $digits = preg_replace('/\D+/', '', $normalized) ?? '';
            if (strlen($digits) !== 8) {
                return '';
            }

            $firstFour = (int) substr($digits, 0, 4);
            if ($firstFour >= 1900) {
                $year = $firstFour;
                $month = (int) substr($digits, 4, 2);
                $day = (int) substr($digits, 6, 2);
            } else {
                $day = (int) substr($digits, 0, 2);
                $month = (int) substr($digits, 2, 2);
                $year = (int) substr($digits, 4, 4);
            }
        }

        if ($year > 2400) {
            $year -= 543;
        }

        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function writePatientAudit(string $action, int $recordId, array $detail): void
    {
        try {
            $user = current_user();
            db()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, detail_json, created_at)
                 VALUES (:user_id, :action, "patients", :record_id, :detail_json, NOW())'
            )->execute([
                'user_id' => $user['id'] ?? null,
                'action' => $action,
                'record_id' => $recordId,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $throwable) {
            // Patient audit is important, but it must not block Smart Exam work.
        }
    }

    private function writeQueueAudit(string $action, int $recordId, array $detail, ?PDO $pdo = null): void
    {
        try {
            $database = $pdo ?? db();
            $user = current_user();
            $database->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, detail_json, created_at)
                 VALUES (:user_id, :action, "queue_entries", :record_id, :detail_json, NOW())'
            )->execute([
                'user_id' => $user['id'] ?? null,
                'action' => $action,
                'record_id' => $recordId,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $throwable) {
            // Audit must not block one-nurse clinic operation.
        }
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
            ['table' => 'patients', 'column' => 'photo_path', 'definition' => 'VARCHAR(255) NULL'],
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
