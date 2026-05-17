<?php

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

class AppointmentController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $dateFrom = trim((string) ($_GET['date_from'] ?? date('Y-m-d')));
        $dateTo = trim((string) ($_GET['date_to'] ?? date('Y-m-d', strtotime('+14 days'))));
        $status = trim((string) ($_GET['status'] ?? 'SCHEDULED'));
        $keyword = trim((string) ($_GET['keyword'] ?? ''));

        if (!$this->isDate($dateFrom)) {
            $dateFrom = date('Y-m-d');
        }

        if (!$this->isDate($dateTo)) {
            $dateTo = $dateFrom;
        }

        if ($dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $appointments = $this->appointments($dateFrom, $dateTo, $status, $keyword);
        $patients = $this->patientOptions($keyword);
        $summary = $this->summary($dateFrom, $dateTo);

        $this->render('appointments/index', [
            'pageTitle' => 'นัดหมาย',
            'appointments' => $appointments,
            'patients' => $patients,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'keyword' => $keyword,
            'pageStyles' => [app_url('assets/css/appointments.css')],
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $appointmentDate = trim((string) ($_POST['appointment_date'] ?? ''));
        $appointmentTime = trim((string) ($_POST['appointment_time'] ?? ''));
        $purpose = trim((string) ($_POST['purpose'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($patientId <= 0 || !$this->isDate($appointmentDate)) {
            flash('error', 'กรุณาเลือกผู้รับบริการและวันที่นัดหมาย');
            redirect('appointments');
        }

        try {
            $patient = $this->findActivePatient($patientId);
            if (!$patient) {
                flash('error', 'ไม่พบผู้รับบริการที่ต้องการนัดหมาย');
                redirect('appointments');
            }

            db()->prepare(
                'INSERT INTO appointments (
                    patient_id, appointment_date, appointment_time, purpose, status, note, created_at, updated_at
                ) VALUES (
                    :patient_id, :appointment_date, :appointment_time, :purpose, "SCHEDULED", :note, NOW(), NOW()
                )'
            )->execute([
                'patient_id' => $patientId,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime !== '' ? $appointmentTime : null,
                'purpose' => $purpose !== '' ? $purpose : 'นัดติดตามอาการ',
                'note' => $note !== '' ? $note : null,
            ]);

            flash('success', 'บันทึกนัดหมายเรียบร้อย');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกนัดหมายได้: ' . $throwable->getMessage());
        }

        redirect('appointments', ['date_from' => $appointmentDate, 'date_to' => $appointmentDate]);
    }

    public function update(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $appointmentDate = trim((string) ($_POST['appointment_date'] ?? ''));
        $appointmentTime = trim((string) ($_POST['appointment_time'] ?? ''));
        $purpose = trim((string) ($_POST['purpose'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($appointmentId <= 0 || !$this->isDate($appointmentDate)) {
            flash('error', 'ข้อมูลนัดหมายไม่ครบถ้วน');
            redirect('appointments');
        }

        try {
            $appointment = $this->findAppointment($appointmentId);
            if (!$appointment) {
                flash('error', 'ไม่พบนัดหมายที่ต้องการแก้ไข');
                redirect('appointments');
            }

            if ($appointment['status'] !== 'SCHEDULED') {
                flash('error', 'แก้ไขได้เฉพาะนัดหมายที่ยังรอรับบริการ');
                redirect('appointments');
            }

            db()->prepare(
                'UPDATE appointments
                 SET appointment_date = :appointment_date,
                     appointment_time = :appointment_time,
                     purpose = :purpose,
                     note = :note,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime !== '' ? $appointmentTime : null,
                'purpose' => $purpose !== '' ? $purpose : 'นัดติดตามอาการ',
                'note' => $note !== '' ? $note : null,
                'id' => $appointmentId,
            ]);

            flash('success', 'อัปเดตนัดหมายเรียบร้อย');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถอัปเดตนัดหมายได้: ' . $throwable->getMessage());
        }

        redirect('appointments', ['date_from' => $appointmentDate, 'date_to' => $appointmentDate]);
    }

    public function cancel(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $returnDate = trim((string) ($_POST['return_date'] ?? date('Y-m-d')));

        if ($appointmentId <= 0) {
            flash('error', 'ไม่พบนัดหมายที่ต้องการยกเลิก');
            redirect('appointments');
        }

        try {
            $appointment = $this->findAppointment($appointmentId);
            if (!$appointment) {
                flash('error', 'ไม่พบนัดหมายที่ต้องการยกเลิก');
                redirect('appointments');
            }

            if ($appointment['status'] !== 'SCHEDULED') {
                flash('error', 'ยกเลิกได้เฉพาะนัดหมายที่ยังรอรับบริการ');
                redirect('appointments');
            }

            db()->prepare(
                'UPDATE appointments
                 SET status = "CANCELLED", updated_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $appointmentId]);

            flash('success', 'ยกเลิกนัดหมายเรียบร้อย');
            $returnDate = (string) $appointment['appointment_date'];
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถยกเลิกนัดหมายได้: ' . $throwable->getMessage());
        }

        redirect('appointments', ['date_from' => $returnDate, 'date_to' => $returnDate, 'status' => '']);
    }

    private function appointments(string $dateFrom, string $dateTo, string $status, string $keyword): array
    {
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $sql = 'SELECT appointments.*,
                       patients.hn, patients.title_name, patients.first_name, patients.last_name, patients.phone,
                       active_queue.visit_id AS active_visit_id,
                       active_queue.queue_no AS active_queue_no,
                       active_queue.status AS active_queue_status
                FROM appointments
                INNER JOIN patients ON patients.id = appointments.patient_id
                LEFT JOIN (
                    SELECT visits.patient_id, visits.id AS visit_id, queue_entries.queue_no, queue_entries.status
                    FROM queue_entries
                    INNER JOIN visits ON visits.id = queue_entries.visit_id
                    WHERE queue_entries.queue_date = CURDATE()
                      AND queue_entries.status IN ("WAITING", "IN_SERVICE", "WAITING_PAYMENT")
                ) AS active_queue ON active_queue.patient_id = appointments.patient_id
                WHERE appointments.appointment_date BETWEEN :date_from AND :date_to';

        if ($status !== '') {
            $sql .= ' AND appointments.status = :status';
            $params['status'] = $status;
        }

        if ($keyword !== '') {
            $sql .= ' AND (
                        patients.hn LIKE :keyword
                        OR patients.first_name LIKE :keyword
                        OR patients.last_name LIKE :keyword
                        OR patients.phone LIKE :keyword
                        OR appointments.purpose LIKE :keyword
                    )';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= ' ORDER BY appointments.appointment_date ASC, appointments.appointment_time ASC, appointments.id ASC LIMIT 200';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function patientOptions(string $keyword): array
    {
        $params = [];
        $sql = 'SELECT id, hn, title_name, first_name, last_name, phone
                FROM patients
                WHERE is_active = 1';

        if ($keyword !== '') {
            $sql .= ' AND (
                        hn LIKE :keyword
                        OR first_name LIKE :keyword
                        OR last_name LIKE :keyword
                        OR phone LIKE :keyword
                    )';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= ' ORDER BY id DESC LIMIT 80';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function summary(string $dateFrom, string $dateTo): array
    {
        $stmt = db()->prepare(
            'SELECT
                SUM(CASE WHEN status = "SCHEDULED" THEN 1 ELSE 0 END) AS scheduled_count,
                SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = "CANCELLED" THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status = "SCHEDULED" AND appointment_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
             FROM appointments
             WHERE appointment_date BETWEEN :date_from AND :date_to'
        );
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return $stmt->fetch() ?: [];
    }

    private function findActivePatient(int $patientId): ?array
    {
        $stmt = db()->prepare('SELECT id FROM patients WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $patientId]);
        $patient = $stmt->fetch();

        return $patient ?: null;
    }

    private function findAppointment(int $appointmentId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM appointments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $appointmentId]);
        $appointment = $stmt->fetch();

        return $appointment ?: null;
    }

    private function isDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
