<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class NumberGenerator
{
    public static function next(string $type, ?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');
        $pdo = db();
        $startedTransaction = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            $stmt = $pdo->prepare(
                'SELECT id, last_no
                 FROM running_numbers
                 WHERE number_type = :number_type AND running_date = :running_date
                 FOR UPDATE'
            );
            $stmt->execute([
                'number_type' => $type,
                'running_date' => $date,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $next = (int) $row['last_no'] + 1;
                $pdo->prepare('UPDATE running_numbers SET last_no = :last_no, updated_at = NOW() WHERE id = :id')->execute([
                    'last_no' => $next,
                    'id' => $row['id'],
                ]);
            } else {
                $next = 1;
                $pdo->prepare(
                    'INSERT INTO running_numbers (number_type, running_date, last_no, created_at, updated_at)
                     VALUES (:number_type, :running_date, :last_no, NOW(), NOW())'
                )->execute([
                    'number_type' => $type,
                    'running_date' => $date,
                    'last_no' => $next,
                ]);
            }

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $next;
        } catch (Throwable $throwable) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public static function nextHn(): string
    {
        $prefix = (string) system_setting('hn_prefix', 'HN');

        return $prefix . str_pad((string) self::next('HN', '2000-01-01'), 6, '0', STR_PAD_LEFT);
    }

    public static function nextVisitNo(): string
    {
        $today = date('Y-m-d');
        $running = self::next('VN', $today);

        return 'VN' . date('Ymd') . '-' . str_pad((string) $running, 3, '0', STR_PAD_LEFT);
    }

    public static function nextQueueNo(?string $date = null): int
    {
        return self::next('QUEUE', $date ?? date('Y-m-d'));
    }

    public static function nextReceiptNo(): string
    {
        $today = date('Y-m-d');
        $running = self::next('RECEIPT', $today);
        $prefix = (string) system_setting('receipt_prefix', 'RC');

        return $prefix . date('Ymd') . '-' . str_pad((string) $running, 4, '0', STR_PAD_LEFT);
    }
}
