<?php

namespace App\Controllers;

use App\Core\ClinicWorkflow;
use App\Core\Controller;
use App\Core\NumberGenerator;
use Throwable;

class PatientController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);

        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $params = [];
        $sql = 'SELECT patients.*,
                       (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                       (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
                FROM patients
                WHERE patients.is_active = 1';

        if ($keyword !== '') {
            $sql .= ' AND (
                        patients.hn LIKE :keyword
                        OR patients.first_name LIKE :keyword
                        OR patients.last_name LIKE :keyword
                        OR patients.phone LIKE :keyword
                        OR patients.citizen_id LIKE :keyword
                    )';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= ' ORDER BY patients.id DESC LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();

        $this->render('patients/index', [
            'pageTitle' => 'ผู้รับบริการ',
            'patients' => $patients,
            'keyword' => $keyword,
            'recentPatients' => array_slice($patients, 0, 8),
            'pageStyles' => [app_url('assets/css/patients.css')],
        ]);
    }

    public function show(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);

        $patientId = (int) ($_GET['id'] ?? 0);
        $patientStmt = db()->prepare(
            'SELECT patients.*,
                    (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                    (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
             FROM patients
             WHERE patients.id = :id
             LIMIT 1'
        );
        $patientStmt->execute(['id' => $patientId]);
        $patient = $patientStmt->fetch();

        if (!$patient) {
            http_response_code(404);
            exit('Patient not found');
        }

        $visitsStmt = db()->prepare(
            'SELECT visits.id, visits.visit_no, visits.visit_datetime, visits.chief_complaint, visits.nursing_note, visits.advice, visits.followup_date,
                    queue_entries.queue_no, queue_entries.status,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c, visit_vitals.pulse_rate, visit_vitals.spo2, visit_vitals.weight_kg,
                    payments.total_amount, payments.receipt_no, payments.paid_at,
                    COALESCE(service_summary.services, "-") AS services_summary,
                    COALESCE(item_summary.items, "-") AS items_summary
             FROM visits
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_services.visit_id,
                       GROUP_CONCAT(CONCAT(services.service_name, " x", visit_services.qty) ORDER BY visit_services.id SEPARATOR ", ") AS services
                FROM visit_services
                INNER JOIN services ON services.id = visit_services.service_id
                GROUP BY visit_services.visit_id
             ) AS service_summary ON service_summary.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_item_usages.visit_id,
                       GROUP_CONCAT(CONCAT(inventory_items.item_name, " x", visit_item_usages.qty) ORDER BY visit_item_usages.id SEPARATOR ", ") AS items
                FROM visit_item_usages
                INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
                GROUP BY visit_item_usages.visit_id
             ) AS item_summary ON item_summary.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
             ORDER BY visits.visit_datetime DESC'
        );
        $visitsStmt->execute(['patient_id' => $patientId]);
        $visits = $visitsStmt->fetchAll();

        $appointmentsStmt = db()->prepare(
            'SELECT *
             FROM appointments
             WHERE patient_id = :patient_id
             ORDER BY appointment_date DESC, appointment_time DESC, id DESC
             LIMIT 10'
        );
        $appointmentsStmt->execute(['patient_id' => $patientId]);

        $this->render('patients/show', [
            'pageTitle' => 'แฟ้มประวัติผู้รับบริการ',
            'patient' => $patient,
            'visits' => $visits,
            'appointments' => $appointmentsStmt->fetchAll(),
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $input = [
            'title_name' => trim((string) ($_POST['title_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'citizen_id' => trim((string) ($_POST['citizen_id'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'emergency_contact_name' => trim((string) ($_POST['emergency_contact_name'] ?? '')),
            'emergency_contact_phone' => trim((string) ($_POST['emergency_contact_phone'] ?? '')),
            'underlying_disease' => trim((string) ($_POST['underlying_disease'] ?? '')),
            'drug_allergy' => trim((string) ($_POST['drug_allergy'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];

        if ($input['first_name'] === '' || $input['last_name'] === '') {
            flash('error', 'กรุณากรอกชื่อและนามสกุลผู้รับบริการ');
            redirect('patients');
        }

        try {
            $hn = NumberGenerator::nextHn();
            $workflowAction = (string) ($_POST['workflow_action'] ?? 'save');
            $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));
            $pdo = db();

            $pdo->prepare(
                'INSERT INTO patients (
                    hn, citizen_id, title_name, first_name, last_name, gender, birth_date, phone, address,
                    emergency_contact_name, emergency_contact_phone, underlying_disease, drug_allergy, note,
                    is_active, created_at, updated_at
                ) VALUES (
                    :hn, :citizen_id, :title_name, :first_name, :last_name, :gender, :birth_date, :phone, :address,
                    :emergency_contact_name, :emergency_contact_phone, :underlying_disease, :drug_allergy, :note,
                    1, NOW(), NOW()
                )'
            )->execute([
                'hn' => $hn,
                'citizen_id' => $input['citizen_id'] ?: null,
                'title_name' => $input['title_name'] ?: null,
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'gender' => $input['gender'] ?: null,
                'birth_date' => $input['birth_date'] ?: null,
                'phone' => $input['phone'] ?: null,
                'address' => $input['address'] ?: null,
                'emergency_contact_name' => $input['emergency_contact_name'] ?: null,
                'emergency_contact_phone' => $input['emergency_contact_phone'] ?: null,
                'underlying_disease' => $input['underlying_disease'] ?: null,
                'drug_allergy' => $input['drug_allergy'] ?: null,
                'note' => $input['note'] ?: null,
            ]);

            $patientId = (int) $pdo->lastInsertId();

            if (in_array($workflowAction, ['save_and_treat', 'save_and_queue'], true)) {
                $workflow = ClinicWorkflow::createVisitAndQueue(
                    $patientId,
                    $chiefComplaint,
                    (int) current_user()['id']
                );

                flash('success', "ลงทะเบียนเรียบร้อย HN: {$hn} และเปิด Smart Exam ให้แล้ว");
                redirect('queue-exam', ['id' => $workflow['visit_id']]);
            }

            flash('success', "บันทึกผู้รับบริการเรียบร้อย HN: {$hn}");
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกข้อมูลผู้รับบริการได้: ' . $throwable->getMessage());
        }

        redirect('patients');
    }

    public function startTreatment(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));

        if ($patientId <= 0) {
            flash('error', 'ไม่พบข้อมูลผู้รับบริการที่ต้องการเริ่มรักษา');
            redirect('patients');
        }

        try {
            $patientStmt = db()->prepare('SELECT id, hn, first_name, last_name FROM patients WHERE id = :id AND is_active = 1 LIMIT 1');
            $patientStmt->execute(['id' => $patientId]);
            $patient = $patientStmt->fetch();

            if (!$patient) {
                flash('error', 'ไม่พบข้อมูลผู้รับบริการที่เลือก');
                redirect('patients');
            }

            $workflow = ClinicWorkflow::createVisitAndQueue(
                $patientId,
                $chiefComplaint,
                (int) current_user()['id']
            );

            flash('success', 'เปิด Smart Exam ให้ ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' เรียบร้อยแล้ว');
            redirect('queue-exam', ['id' => $workflow['visit_id']]);
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถเริ่มรักษาได้: ' . $throwable->getMessage());
            redirect('patients');
        }
    }
}
