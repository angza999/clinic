<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClinicWorkflow;
use App\Core\Controller;
use Throwable;

class QueueController extends Controller
{
    public function index(): void
    {
        require_login();

        $todayQueues = $this->todayQueues();
        $patients = $this->patientsForQueue();
        $nextWaiting = $this->nextWaitingFrom($todayQueues);

        $this->render('queue/index', [
            'pageTitle' => 'ระบบคิว',
            'todayQueues' => $todayQueues,
            'patients' => $patients,
            'nextWaiting' => $nextWaiting,
            'prefillPatientId' => (int) ($_GET['patient_id'] ?? 0),
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
        require_roles(['ADMIN']);

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));

        if ($patientId <= 0) {
            flash('error', 'กรุณาเลือกผู้รับบริการ');
            redirect('queue');
        }

        try {
            $workflow = ClinicWorkflow::createVisitAndQueue(
                $patientId,
                $chiefComplaint,
                (int) current_user()['id']
            );

            flash('success', "สร้างคิวสำเร็จ หมายเลขคิว {$workflow['queue_no']}");
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถสร้างคิวได้: ' . $throwable->getMessage());
        }

        redirect('queue');
    }

    public function updateStatus(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);

        $queueId = (int) ($_POST['queue_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($queueId <= 0 || !in_array($status, ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT', 'COMPLETED', 'CANCELLED'], true)) {
            flash('error', 'ข้อมูลสถานะไม่ถูกต้อง');
            redirect('queue');
        }

        $fields = ['status = :status', 'updated_at = NOW()'];
        $params = ['status' => $status, 'id' => $queueId];

        if ($status === 'IN_SERVICE') {
            $fields[] = 'called_at = NOW()';
        }

        if (in_array($status, ['COMPLETED', 'CANCELLED'], true)) {
            $fields[] = 'finished_at = NOW()';
        }

        db()->prepare('UPDATE queue_entries SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);

        flash('success', 'อัปเดตสถานะคิวเรียบร้อย');
        redirect('queue');
    }

    private function todayQueues(): array
    {
        return db()->query(
            'SELECT queue_entries.*, visits.id AS visit_id, visits.visit_no, visits.chief_complaint,
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
                    ELSE 5
                END,
                queue_entries.queue_no ASC'
        )->fetchAll();
    }

    private function patientsForQueue(): array
    {
        return db()->query(
            'SELECT id, hn, first_name, last_name, phone
             FROM patients
             WHERE is_active = 1
             ORDER BY id DESC
             LIMIT 100'
        )->fetchAll();
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
}
