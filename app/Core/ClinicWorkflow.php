<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class ClinicWorkflow
{
    public static function createVisitAndQueue(int $patientId, ?string $chiefComplaint, int $userId): array
    {
        $pdo = db();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            } else {
                $startedTransaction = false;
            }

            $visitNo = NumberGenerator::nextVisitNo();
            $queueNo = NumberGenerator::nextQueueNo();

            $stmt = $pdo->prepare(
                'INSERT INTO visits (visit_no, patient_id, visit_datetime, chief_complaint, created_by, created_at, updated_at)
                 VALUES (:visit_no, :patient_id, NOW(), :chief_complaint, :created_by, NOW(), NOW())'
            );
            $stmt->execute([
                'visit_no' => $visitNo,
                'patient_id' => $patientId,
                'chief_complaint' => $chiefComplaint ?: null,
                'created_by' => $userId,
            ]);

            $visitId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO queue_entries (visit_id, queue_date, queue_no, status, checked_in_at, created_at, updated_at)
                 VALUES (:visit_id, CURDATE(), :queue_no, "WAITING", NOW(), NOW(), NOW())'
            )->execute([
                'visit_id' => $visitId,
                'queue_no' => $queueNo,
            ]);

            if (($startedTransaction ?? false) && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'visit_id' => $visitId,
                'visit_no' => $visitNo,
                'queue_no' => $queueNo,
            ];
        } catch (Throwable $throwable) {
            if (($startedTransaction ?? false) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }
}

