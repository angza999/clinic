<?php
$queueUser = current_user() ?? ['role_code' => '', 'full_name' => '', 'role_name' => ''];
$canManageQueue = in_array($queueUser['role_code'], ['ADMIN', 'NURSE'], true);
$showCreateQueuePanel = $canManageQueue;

$visibleBoards = ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT', 'COMPLETED'];

$statusBuckets = [
    'WAITING' => [],
    'IN_SERVICE' => [],
    'WAITING_PAYMENT' => [],
    'COMPLETED' => [],
    'CANCELLED' => [],
];

foreach (($todayQueues ?? []) as $queue) {
    $status = $queue['status'] ?? 'WAITING';
    if (!array_key_exists($status, $statusBuckets)) {
        $statusBuckets[$status] = [];
    }
    $queue['patient_name'] = trim(($queue['first_name'] ?? '') . ' ' . ($queue['last_name'] ?? ''));
    $statusBuckets[$status][] = $queue;
}

$counts = [];
foreach ($statusBuckets as $status => $items) {
    $counts[$status] = count($items);
}

$nextWaiting = $statusBuckets['WAITING'][0] ?? null;
$paymentQueue = $statusBuckets['WAITING_PAYMENT'][0] ?? null;
$hasActiveVisit = !empty($activeVisit);
$activeStatus = (string) ($activeVisit['status'] ?? 'WAITING');
$fromReceipt = (int) ($_GET['from_receipt'] ?? 0) === 1;
$todayAppointments = $todayAppointments ?? [];
$dailyMetrics = $dailyMetrics ?? [];
$assistantAlerts = $assistantAlerts ?? [];
$todayPatientCount = (int) ($dailyMetrics['patient_count'] ?? 0);
$todayExamDoneCount = (int) ($dailyMetrics['exam_done_count'] ?? 0);
$todayRevenue = (float) ($dailyMetrics['revenue_today'] ?? 0);
$avgCaseMinutes = (int) ($dailyMetrics['avg_case_minutes'] ?? 0);
$financialToday = $dailyMetrics['financial_today'] ?? ['CASH' => 0, 'QR' => 0, 'TRANSFER' => 0];
$pendingCases = (int) ($assistantAlerts['pending_cases'] ?? 0);
$overdueCases = (int) ($assistantAlerts['overdue_cases'] ?? 0);
$smartCardOnline = (bool) ($assistantAlerts['smart_card_online'] ?? false);
$printerReady = (bool) ($assistantAlerts['printer_ready'] ?? false);
$recentActivity = $recentActivity ?? [];
$activitySummary = $activitySummary ?? [];

$queueWaitMinutes = static function (array $queue): int {
    $source = $queue['checked_in_at'] ?? $queue['created_at'] ?? null;
    return $source ? max(0, (int) floor((time() - strtotime((string) $source)) / 60)) : 0;
};

$queueAgeState = static function (int $waitMinutes, string $status = 'WAITING'): array {
    if ($status === 'COMPLETED') {
        return ['class' => 'done', 'label' => 'DONE', 'text' => 'เสร็จแล้ว', 'icon' => '●'];
    }

    if ($waitMinutes > 60) {
        return ['class' => 'critical', 'label' => 'URGENT', 'text' => 'รอ ' . $waitMinutes . ' นาที', 'icon' => '🔴'];
    }

    if ($waitMinutes >= 30) {
        return ['class' => 'warning', 'label' => 'WARNING', 'text' => 'รอ ' . $waitMinutes . ' นาที', 'icon' => '🟠'];
    }

    if ($waitMinutes >= 15) {
        return ['class' => 'caution', 'label' => 'WATCH', 'text' => 'รอ ' . $waitMinutes . ' นาที', 'icon' => '🟡'];
    }

    return ['class' => 'normal', 'label' => 'NORMAL', 'text' => 'รอ ' . $waitMinutes . ' นาที', 'icon' => '🟢'];
};

$caseTimelineLabel = static function (string $label): string {
    return match ($label) {
        'REGISTERED' => 'ลงทะเบียน',
        'OPENED_SMART_EXAM' => 'เปิด Smart Exam',
        'ADDED_SERVICE' => 'เพิ่มบริการ',
        'DISPENSED_MEDICATION' => 'จ่ายยา/เวชภัณฑ์',
        'PAID' => 'รับชำระเงิน',
        'CLOSED_CASE' => 'ปิดเคส',
        'WAITING_SMART_EXAM' => 'รอเปิด Smart Exam',
        'RECORDING_SERVICE' => 'กำลังบันทึกบริการ',
        'CONTINUE_SERVICE' => 'บันทึกบริการต่อ',
        'WAITING_PAYMENT' => 'รอชำระเงิน',
        'COMPLETED' => 'เสร็จสิ้น',
        'CONTINUE_WORKFLOW' => 'ดำเนินการต่อ',
        default => $label !== '' ? $label : '-',
    };
};

$caseTimelineTime = static function (string $time): string {
    return $time === 'now' ? 'ตอนนี้' : ($time !== '' ? $time : '-');
};

$waitingOver30 = 0;
$waitingOver60 = 0;
foreach ($statusBuckets['WAITING'] as $waitingQueue) {
    $waitMinutes = $queueWaitMinutes($waitingQueue);
    if ($waitMinutes >= 30) {
        $waitingOver30++;
    }
    if ($waitMinutes > 60) {
        $waitingOver60++;
    }
}

$nextPatientWait = $nextWaiting ? $queueWaitMinutes($nextWaiting) : 0;
$nextPatientAge = $queueAgeState($nextPatientWait, 'WAITING');

$queueBoards = [
    'WAITING' => ['title' => 'รอรับบริการ', 'class' => 'status-waiting', 'icon' => 'bi-hourglass-split', 'empty' => 'ยังไม่มีคิวรอรับบริการ'],
    'IN_SERVICE' => ['title' => 'กำลังตรวจ', 'class' => 'status-in-service', 'icon' => 'bi-heart-pulse', 'empty' => 'ยังไม่มีคิวที่กำลังตรวจ'],
    'WAITING_PAYMENT' => ['title' => 'รอชำระเงิน', 'class' => 'status-payment', 'icon' => 'bi-wallet2', 'empty' => 'ยังไม่มีคิวรอชำระเงิน'],
    'COMPLETED' => ['title' => 'เสร็จสิ้น', 'class' => 'status-completed', 'icon' => 'bi-check-circle', 'empty' => 'ยังไม่มีคิวที่ปิดเคสแล้ววันนี้'],
];

$patientOptions = array_map(static function (array $patient): array {
    $name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

    return [
        'id' => (int) ($patient['id'] ?? 0),
        'hn' => (string) ($patient['hn'] ?? ''),
        'name' => $name,
        'phone' => (string) ($patient['phone'] ?? ''),
    ];
}, $patients ?? []);

$activePatientName = $hasActiveVisit
    ? trim(($activeVisit['first_name'] ?? '') . ' ' . ($activeVisit['last_name'] ?? ''))
    : '';
$activeGrandTotal = $hasActiveVisit ? (float) (($activeVisit['service_total'] ?? 0) + ($activeVisit['item_total'] ?? 0)) : 0.0;
$activeHasPayment = $hasActiveVisit && !empty($activeVisit['payment_id']);
$activeItemCount = $hasActiveVisit ? (int) ($activeVisit['item_count'] ?? 0) : 0;
$activeHistoryLines = $hasActiveVisit ? (array) ($activeVisit['history_lines'] ?? []) : [];
$activeCaseTimeline = $hasActiveVisit ? (array) ($activeVisit['case_timeline'] ?? []) : [];
$activeCanCloseAndNext = $hasActiveVisit
    && $activeStatus !== 'WAITING'
    && ($activeStatus === 'COMPLETED' || $activeHasPayment || $activeGrandTotal <= 0);
$activeNeedsPaymentBeforeClose = $hasActiveVisit
    && $activeStatus !== 'COMPLETED'
    && !$activeHasPayment
    && $activeGrandTotal > 0;
$activeStartedAt = $hasActiveVisit
    ? ($activeVisit['called_at'] ?? $activeVisit['checked_in_at'] ?? $activeVisit['visit_datetime'] ?? null)
    : null;
$activeMinutes = $activeStartedAt ? max(0, (int) floor((time() - strtotime((string) $activeStartedAt)) / 60)) : 0;
$activeWaitText = $activeMinutes > 0 ? $activeMinutes . ' นาที' : '-';
$activeAgeText = '-';
if ($hasActiveVisit && !empty($activeVisit['birth_date'])) {
    try {
        $birthDate = new DateTime((string) $activeVisit['birth_date']);
        $activeAgeText = $birthDate->diff(new DateTime('today'))->y . ' ปี';
    } catch (Throwable $exception) {
        $activeAgeText = '-';
    }
}
$activeAllergyText = $hasActiveVisit ? trim((string) (($activeVisit['drug_allergy'] ?? '') ?: '-')) : '-';
$activeChronicText = $hasActiveVisit ? trim((string) (($activeVisit['underlying_disease'] ?? '') ?: '-')) : '-';
$activeHasAllergyAlert = $hasActiveVisit && $activeAllergyText !== '' && $activeAllergyText !== '-';
$activeHasChronicAlert = $hasActiveVisit && $activeChronicText !== '' && $activeChronicText !== '-';
$activeSafetyClass = $activeHasAllergyAlert ? 'critical' : ($activeHasChronicAlert ? 'warning' : 'normal');

$latestCase = (array) ($activitySummary['latest_case'] ?? []);
$latestReceipt = (array) ($activitySummary['latest_receipt'] ?? []);
$latestSticker = (array) ($activitySummary['latest_sticker'] ?? []);
$latestCaseName = trim((string) ($latestCase['first_name'] ?? '') . ' ' . (string) ($latestCase['last_name'] ?? ''));
$latestReceiptName = trim((string) ($latestReceipt['first_name'] ?? '') . ' ' . (string) ($latestReceipt['last_name'] ?? ''));
$latestStickerName = trim((string) ($latestSticker['first_name'] ?? '') . ' ' . (string) ($latestSticker['last_name'] ?? ''));

$primaryAction = null;
$nextStepForm = null;
if ($hasActiveVisit) {
    if ($activeStatus === 'WAITING') {
        $primaryAction = [
            'label' => 'เรียกคิวและเปิด Smart Exam',
            'url' => route_url('queue-exam', ['id' => (int) $activeVisit['id']]),
            'icon' => 'bi-heart-pulse-fill',
            'class' => 'btn-primary',
            'shortcut' => 'F3',
        ];
    } elseif ($activeStatus === 'IN_SERVICE') {
        $primaryAction = [
            'label' => 'บันทึกบริการ',
            'url' => route_url('queue-exam', ['id' => (int) $activeVisit['id']]),
            'icon' => 'bi-clipboard2-pulse',
            'class' => 'btn-primary',
            'shortcut' => 'F4',
        ];
    } elseif ($activeStatus === 'WAITING_PAYMENT') {
        $primaryAction = [
            'label' => 'เปิดหน้าการเงิน',
            'url' => route_url('payments'),
            'icon' => 'bi-wallet2',
            'class' => 'btn-primary',
            'shortcut' => 'F9',
        ];
    } else {
        $completedUrl = !empty($activeVisit['payment_id'])
            ? route_url('receipt', ['id' => (int) $activeVisit['payment_id'], 'source' => 'queue'])
            : route_url('payments');
        $primaryAction = [
            'label' => 'พิมพ์ใบเสร็จและจบเคส',
            'url' => $completedUrl,
            'icon' => 'bi-receipt-cutoff',
            'class' => 'btn-primary',
            'shortcut' => null,
        ];
    }
}

$nextStepLabel = $primaryAction['label'] ?? 'เริ่มรับผู้รับบริการ';
$nextStepIcon = $primaryAction['icon'] ?? 'bi-person-vcard';
$nextStepShortcut = $primaryAction['shortcut'] ?? null;
$nextStepUrl = $primaryAction['url'] ?? route_url('patients');
if ($activeCanCloseAndNext) {
    $nextStepLabel = 'ปิดเคสและเรียกคิวถัดไป';
    $nextStepIcon = 'bi-arrow-right-circle-fill';
    $nextStepShortcut = 'F5';
    $nextStepForm = [
        'action' => route_url('queue-close-next'),
        'visit_id' => (int) ($activeVisit['id'] ?? 0),
    ];
} elseif ($activeNeedsPaymentBeforeClose) {
    $nextStepLabel = 'ส่งชำระเงินก่อนปิดเคส';
    $nextStepIcon = 'bi-wallet2';
    $nextStepShortcut = 'F9';
    $nextStepUrl = route_url('payments');
}

$workflowTimeline = [
    ['label' => 'ลงทะเบียน', 'state' => $hasActiveVisit ? 'done' : 'todo'],
    ['label' => 'เปิด Smart Exam', 'state' => 'todo'],
    ['label' => 'กำลังบันทึกบริการ', 'state' => 'todo'],
    ['label' => 'รอชำระเงิน', 'state' => 'todo'],
    ['label' => 'เสร็จสิ้น', 'state' => 'todo'],
];
if ($hasActiveVisit) {
    if ($activeStatus === 'WAITING') {
        $workflowTimeline[1]['state'] = 'current';
    } elseif ($activeStatus === 'IN_SERVICE') {
        $workflowTimeline[1]['state'] = 'done';
        $workflowTimeline[2]['state'] = 'current';
    } elseif ($activeStatus === 'WAITING_PAYMENT') {
        $workflowTimeline[1]['state'] = 'done';
        $workflowTimeline[2]['state'] = 'done';
        $workflowTimeline[3]['state'] = 'current';
    } elseif ($activeStatus === 'COMPLETED') {
        foreach ($workflowTimeline as $index => $step) {
            $workflowTimeline[$index]['state'] = 'done';
        }
    }
}

$nextActionText = 'ยังไม่มีเคส active - เริ่มจากอ่านบัตรหรือค้นหาคนไข้';
$nextActionUrl = route_url('patients');
$nextActionIcon = 'bi-person-vcard';
$nextActionButtonLabel = 'เริ่มรับผู้รับบริการ';
if ($hasActiveVisit && $primaryAction) {
    $nextActionText = $activePatientName . ' ' . queue_status_meta($activeStatus)['label'] . ' - ' . $primaryAction['label'];
    $nextActionUrl = $primaryAction['url'];
    $nextActionIcon = $primaryAction['icon'];
    $nextActionButtonLabel = $primaryAction['label'];
} elseif ($paymentQueue) {
    $nextActionText = 'มี ' . (int) ($counts['WAITING_PAYMENT'] ?? 0) . ' เคสรอชำระ - ไปหน้าการเงิน';
    $nextActionUrl = route_url('payments');
    $nextActionIcon = 'bi-wallet2';
    $nextActionButtonLabel = 'เปิดหน้าการเงิน';
} elseif ($nextWaiting) {
    $nextActionText = 'คิว ' . (string) $nextWaiting['queue_no'] . ' รอเปิดตรวจ - เรียกและเปิด Smart Exam';
    $nextActionUrl = route_url('queue-exam', ['id' => (int) $nextWaiting['visit_id']]);
    $nextActionIcon = 'bi-heart-pulse-fill';
    $nextActionButtonLabel = 'เรียกคิวและเปิด Smart Exam';
}
?>

<div class="single-nurse-queue">
    <section class="snq-command">
        <div class="snq-command-title">
            <span>Single Nurse Workstation</span>
            <strong>คิววันนี้</strong>
        </div>
        <div class="snq-status-strip" aria-label="Today status">
            <div class="snq-status-pill waiting">
                <span>รอรับบริการ</span>
                <strong><?= (int) ($counts['WAITING'] ?? 0) ?></strong>
            </div>
            <div class="snq-status-pill in-service">
                <span>กำลังตรวจ</span>
                <strong><?= (int) ($counts['IN_SERVICE'] ?? 0) ?></strong>
            </div>
            <div class="snq-status-pill payment">
                <span>รอชำระ</span>
                <strong><?= (int) ($counts['WAITING_PAYMENT'] ?? 0) ?></strong>
            </div>
        </div>
    </section>

    <?php if ($waitingOver30 > 0 || $waitingOver60 > 0): ?>
        <section class="snq-aging-alert <?= $waitingOver60 > 0 ? 'is-critical' : 'is-warning' ?>" aria-live="polite">
            <?php if ($waitingOver60 > 0): ?>
                <strong>🚨 มีคิวรอเกิน 60 นาที <?= $waitingOver60 ?> ราย</strong>
            <?php endif; ?>
            <?php if ($waitingOver30 > 0): ?>
                <span>⚠ มีคิวรอเกิน 30 นาที <?= $waitingOver30 ?> ราย</span>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($fromReceipt && $canManageQueue && $nextWaiting): ?>
        <section class="snq-continuation" data-queue-continuation>
            <i class="bi bi-arrow-right-circle-fill"></i>
            <div>
                <strong>รับเคสถัดไปได้ทันที</strong>
                <span>คิว <?= e((string) $nextWaiting['queue_no']) ?> - <?= e($nextWaiting['patient_name']) ?></span>
            </div>
            <form method="post" action="<?= e(route_url('queue-status')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="queue_id" value="<?= (int) $nextWaiting['id'] ?>">
                <input type="hidden" name="status" value="IN_SERVICE">
                <input type="hidden" name="redirect_to_visit" value="1">
                <button class="btn btn-primary btn-sm" id="queueContinueNextCase">เรียกและเปิดตรวจ</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="snq-grid">
        <aside class="snq-start-panel">
            <div class="snq-panel-head">
                <span>Start Case</span>
                <strong>เริ่มเคส</strong>
            </div>

            <?php if ($showCreateQueuePanel): ?>
                <div class="snq-intake-actions">
                    <a href="<?= e(route_url('patients')) ?>" class="snq-tool-btn" data-queue-new-patient>
                        <i class="bi bi-person-vcard"></i>
                        <span>อ่านบัตร</span>
                    </a>
                    <a href="<?= e(route_url('patients')) ?>" class="snq-tool-btn">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>คนไข้ใหม่</span>
                    </a>
                    <a href="<?= e(route_url('import', ['type' => 'patients'])) ?>" class="snq-tool-btn secondary">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span>Import</span>
                    </a>
                </div>

                <form method="post" action="<?= e(route_url('queue-store')) ?>" class="snq-search-card" id="queueCreateForm">
                    <?= csrf_field() ?>
                    <div class="queue-patient-picker" data-queue-patient-picker>
                        <label class="form-label" for="queuePatientSearch">ค้นหาคนไข้เดิม</label>
                        <input type="text" id="queuePatientSearch" class="form-control" placeholder="HN, ชื่อ, เบอร์โทร" autocomplete="off" data-queue-patient-search data-queue-search>
                        <input type="hidden" name="patient_id" id="queuePatientId" value="<?= (int) ($prefillPatientId ?? 0) ?>" required data-queue-patient-id>
                        <div class="queue-patient-selected d-none" data-queue-patient-selected></div>
                        <div class="queue-patient-results" data-queue-patient-results></div>
                    </div>
                    <textarea name="chief_complaint" class="form-control" rows="2" placeholder="อาการเบื้องต้น เช่น ไข้ ไอ เจ็บคอ"></textarea>
                    <button class="btn btn-primary w-100">
                        <i class="bi bi-person-check-fill me-1"></i>รับเคสจากคนไข้เดิม
                    </button>
                </form>

                <details class="snq-quick-register" id="snqQuickRegister">
                    <summary>
                        <span>ลงทะเบียนด่วน</span>
                        <small>กรอกเท่าที่จำเป็น</small>
                    </summary>
                    <form method="post" action="<?= e(route_url('queue-quick-register')) ?>" class="queue-quick-register-form" id="queueQuickRegisterForm">
                        <?= csrf_field() ?>
                        <div class="queue-quick-register-grid">
                            <div>
                                <label class="form-label" for="quickFullName">ชื่อ-สกุล</label>
                                <input type="text" name="quick_full_name" id="quickFullName" class="form-control" value="<?= e((string) old('quick_full_name')) ?>" placeholder="เช่น สมชาย ใจดี" required data-quick-name>
                            </div>
                            <div>
                                <label class="form-label" for="quickPhone">เบอร์โทร</label>
                                <input type="text" name="quick_phone" id="quickPhone" class="form-control" value="<?= e((string) old('quick_phone')) ?>" placeholder="ใช้เช็คซ้ำ" data-quick-phone>
                            </div>
                            <div>
                                <label class="form-label" for="quickGender">เพศ</label>
                                <select name="quick_gender" id="quickGender" class="form-select">
                                    <option value="">ไม่ระบุ</option>
                                    <option value="M" <?= selected((string) old('quick_gender'), 'M') ?>>ชาย</option>
                                    <option value="F" <?= selected((string) old('quick_gender'), 'F') ?>>หญิง</option>
                                    <option value="O" <?= selected((string) old('quick_gender'), 'O') ?>>อื่น ๆ</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="quickAllergy">แพ้ยา</label>
                                <input type="text" name="quick_drug_allergy" id="quickAllergy" class="form-control" value="<?= e((string) old('quick_drug_allergy')) ?>" placeholder="ถ้าไม่มีปล่อยว่าง">
                            </div>
                        </div>
                        <textarea name="quick_chief_complaint" id="quickChiefComplaint" class="form-control" rows="2" placeholder="อาการสำคัญ"><?= e((string) old('quick_chief_complaint')) ?></textarea>
                        <div class="queue-duplicate-warning d-none" data-quick-duplicate-warning></div>
                        <label class="queue-confirm-duplicate">
                            <input type="checkbox" name="confirm_duplicate" value="1" <?= checked((string) old('confirm_duplicate'), '1') ?>>
                            ยืนยันสร้างคนไข้ใหม่ แม้ระบบแจ้งว่าอาจซ้ำ
                        </label>
                        <button class="btn btn-primary w-100">
                            <i class="bi bi-lightning-charge-fill me-1"></i>ลงทะเบียนและเปิด Smart Exam
                        </button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if ($todayAppointments): ?>
                <div class="snq-mini-section">
                    <div class="snq-mini-title">นัดติดตามวันนี้</div>
                    <div class="snq-appointment-list">
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <?php
                            $appointmentName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
                            $hasActiveAppointmentQueue = !empty($appointment['active_visit_id']);
                            ?>
                            <div class="snq-appointment-row">
                                <div>
                                    <strong><?= e($appointmentName) ?></strong>
                                    <span>HN <?= e((string) ($appointment['hn'] ?? '-')) ?> · <?= thai_date_only($appointment['appointment_date'] ?? null) ?></span>
                                </div>
                                <?php if ($hasActiveAppointmentQueue): ?>
                                    <a href="<?= e(route_url('queue-exam', ['id' => (int) $appointment['active_visit_id']])) ?>" class="btn btn-outline-primary btn-sm">เปิด</a>
                                <?php else: ?>
                                    <form method="post" action="<?= e(route_url('appointment-checkin')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <button class="btn btn-primary btn-sm">รับคิว</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="snq-mini-section">
                <div class="snq-mini-title">Recent Search</div>
                <div class="snq-recent-list">
                    <?php foreach (array_slice($patients ?? [], 0, 5) as $patient): ?>
                        <form method="post" action="<?= e(route_url('queue-store')) ?>" class="smart-recent-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                            <input type="hidden" name="chief_complaint" value="">
                            <button type="submit" class="snq-recent-row">
                                <span><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
                                <small><?= e($patient['hn']) ?> · <?= e($patient['phone'] ?: '-') ?></small>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <main class="snq-main-panel">
            <section class="snq-next-alert-bar <?= $nextWaiting ? 'has-next ' . e((string) $nextPatientAge['class']) : 'is-empty' ?>" aria-live="polite">
                <div class="snq-next-alert-copy">
                    <?php if ($nextWaiting): ?>
                        <span><?= $nextPatientAge['class'] === 'critical' ? 'คิวด่วน' : 'คิวถัดไป' ?></span>
                        <strong>Q<?= e((string) $nextWaiting['queue_no']) ?> · <?= e((string) $nextWaiting['patient_name']) ?></strong>
                        <small><?= e((string) $nextPatientAge['icon']) ?> <?= e((string) $nextPatientAge['text']) ?> · <?= e((string) $nextPatientAge['label']) ?></small>
                    <?php else: ?>
                        <span>คิวถัดไป</span>
                        <strong>ยังไม่มีคิวรอรับบริการ</strong>
                        <small>รับผู้รับบริการใหม่ หรือคลิกคิวใน Queue Board เพื่อเปิด Smart Exam</small>
                    <?php endif; ?>
                </div>
                <?php if ($nextWaiting && $canManageQueue): ?>
                    <form method="post" action="<?= e(route_url('queue-status')) ?>" class="snq-next-alert-action">
                        <?= csrf_field() ?>
                        <input type="hidden" name="queue_id" value="<?= (int) $nextWaiting['id'] ?>">
                        <input type="hidden" name="status" value="IN_SERVICE">
                        <input type="hidden" name="redirect_to_visit" value="1">
                        <button class="btn btn-primary btn-sm" data-queue-call-next>
                            <i class="bi bi-megaphone-fill me-1"></i>เรียกคิว
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <article class="snq-active-card <?= $hasActiveVisit ? 'has-case' : 'is-empty' ?>">
                <div class="snq-panel-head">
                    <span>Active Case</span>
                    <strong>เคสที่กำลังทำ</strong>
                </div>

                <?php if ($hasActiveVisit): ?>
                    <div class="snq-safety-banner <?= e($activeSafetyClass) ?>">
                        <div class="snq-safety-main">
                            <span>Patient Safety</span>
                            <strong><?= e($activePatientName) ?></strong>
                            <small>HN <?= e((string) $activeVisit['hn']) ?> · <?= e($activeAgeText) ?></small>
                        </div>
                        <div class="snq-safety-flags">
                            <span class="<?= $activeHasAllergyAlert ? 'is-critical' : 'is-muted' ?>">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                แพ้ยา: <?= e($activeAllergyText ?: '-') ?>
                            </span>
                            <span class="<?= $activeHasChronicAlert ? 'is-warning' : 'is-muted' ?>">
                                <i class="bi bi-heart-pulse-fill"></i>
                                โรคประจำตัว: <?= e($activeChronicText ?: '-') ?>
                            </span>
                        </div>
                    </div>

                    <div class="snq-active-case-line">
                        <span>คิว <?= e((string) $activeVisit['queue_no']) ?></span>
                        <span>VN <?= e((string) $activeVisit['visit_no']) ?></span>
                        <strong><?= e((string) (($activeVisit['chief_complaint'] ?? '') ?: 'ยังไม่ได้ระบุอาการสำคัญ')) ?></strong>
                        <em class="active-case-status <?= e(strtolower($activeStatus)) ?>">
                            <?= e(queue_status_meta($activeStatus)['label']) ?>
                        </em>
                    </div>

                    <div class="snq-patient-preview">
                        <div>
                            <span>เคยมาแล้ว</span>
                            <strong><?= (int) ($activeVisit['visit_count'] ?? 0) ?> ครั้ง</strong>
                        </div>
                        <div>
                            <span>ระยะเวลาในคิว</span>
                            <strong><?= e($activeWaitText) ?></strong>
                        </div>
                        <div>
                            <span>บริการ</span>
                            <strong><?= (int) ($activeVisit['service_count'] ?? 0) ?></strong>
                        </div>
                        <div>
                            <span>ยา/อุปกรณ์</span>
                            <strong><?= (int) ($activeVisit['item_count'] ?? 0) ?></strong>
                        </div>
                        <div>
                            <span>ยอดสุทธิ</span>
                            <strong><?= format_money($activeGrandTotal) ?></strong>
                        </div>
                    </div>

                    <div class="snq-workflow-timeline" aria-label="Workflow timeline">
                        <?php foreach ($workflowTimeline as $step): ?>
                            <div class="snq-timeline-step <?= e($step['state']) ?>">
                                <span><?= $step['state'] === 'done' ? '✓' : ($step['state'] === 'current' ? '●' : '○') ?></span>
                                <strong><?= e($step['label']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="snq-case-events" aria-label="Case timeline">
                        <div class="snq-case-events-title">
                            <span>Timeline</span>
                            <strong>ประวัติเคสปัจจุบัน</strong>
                        </div>
                        <div class="snq-case-event-list">
                            <?php foreach (array_slice($activeCaseTimeline, 0, 5) as $event): ?>
                                <div class="snq-case-event <?= e((string) ($event['state'] ?? 'done')) ?>">
                                    <time><?= e($caseTimelineTime((string) ($event['time'] ?? ''))) ?></time>
                                    <span><?= e($caseTimelineLabel((string) ($event['label'] ?? ''))) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$activeCaseTimeline): ?>
                                <div class="snq-case-event empty">
                                    <time>--:--</time>
                                    <span>ยังไม่มี timeline เพิ่มเติม</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="snq-primary-action-row">
                        <?php if ($nextStepForm): ?>
                            <form method="post" action="<?= e($nextStepForm['action']) ?>" class="snq-main-step-form" data-queue-close-next-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="visit_id" value="<?= (int) $nextStepForm['visit_id'] ?>">
                                <button class="btn btn-primary snq-primary-action" type="submit" data-queue-primary-action data-queue-close-next data-queue-primary-status="<?= e($activeStatus) ?>">
                                    <i class="bi <?= e($nextStepIcon) ?> me-1"></i>
                                    <span>ดำเนินการขั้นถัดไป</span>
                                    <small><?= e($nextStepLabel) ?></small>
                                    <?php if ($nextStepShortcut): ?><kbd><?= e($nextStepShortcut) ?></kbd><?php endif; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= e($nextStepUrl) ?>" class="btn btn-primary snq-primary-action" data-queue-primary-action data-queue-primary-status="<?= e($activeStatus) ?>" <?= $activeNeedsPaymentBeforeClose ? 'data-queue-payment-action' : '' ?>>
                                <i class="bi <?= e($nextStepIcon) ?> me-1"></i>
                                <span>ดำเนินการขั้นถัดไป</span>
                                <small><?= e($nextStepLabel) ?></small>
                                <?php if ($nextStepShortcut): ?><kbd><?= e($nextStepShortcut) ?></kbd><?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <div class="snq-secondary-actions">
                            <a href="<?= e(route_url('visit-edit', ['id' => (int) $activeVisit['id']])) ?>">
                                <i class="bi bi-clock-history"></i> ประวัติเคส
                            </a>
                            <?php if ($activeItemCount > 0): ?>
                                <a href="<?= e(route_url('pharmacy-labels', ['visit_id' => (int) $activeVisit['id']])) ?>" data-queue-label-action>
                                    <i class="bi bi-printer-fill"></i> สติ๊กเกอร์ยา
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <details class="snq-history-drawer">
                        <summary>
                            <span><i class="bi bi-clock-history me-1"></i>ประวัติย้อนหลัง</span>
                            <em><?= count($activeHistoryLines) ?> รายการ</em>
                        </summary>
                        <div class="snq-history-list">
                            <?php foreach ($activeHistoryLines as $historyLine): ?>
                                <div class="snq-history-row">
                                    <time><?= e((string) ($historyLine['date'] ?? '-')) ?></time>
                                    <strong><?= e((string) (($historyLine['diagnosis'] ?? '-') ?: '-')) ?></strong>
                                    <span><?= e((string) (($historyLine['chief_complaint'] ?? '-') ?: '-')) ?></span>
                                    <small>บริการ: <?= e((string) ($historyLine['services_summary'] ?? '-')) ?></small>
                                    <small>ยา: <?= e((string) ($historyLine['items_summary'] ?? '-')) ?></small>
                                    <em><?= e((string) ($historyLine['paid_total_text'] ?? '0.00')) ?></em>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$activeHistoryLines): ?>
                                <div class="snq-history-empty">ยังไม่มีประวัติย้อนหลังของผู้รับบริการรายนี้</div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="snq-empty-active">
                        <strong>ยังไม่มีเคสที่กำลังทำ</strong>
                        <a href="<?= e(route_url('patients')) ?>" class="btn btn-primary btn-sm">เริ่มรับผู้รับบริการ</a>
                    </div>
                <?php endif; ?>
            </article>

            <section class="snq-board-shell">
                <div class="snq-board-title">
                    <div>
                        <span>Queue Board</span>
                        <strong>คิวที่ต้องดำเนินการ</strong>
                    </div>
                    <small>คลิกคิวเพื่อเปิด Smart Exam ทันที</small>
                </div>
                <div class="snq-board-grid">
                        <?php foreach ($queueBoards as $statusCode => $meta): ?>
                            <?php if (!in_array($statusCode, $visibleBoards, true)) { continue; } ?>
                            <article class="snq-board-column <?= e($meta['class']) ?> <?= empty($statusBuckets[$statusCode]) ? 'is-empty' : '' ?>">
                                <div class="snq-board-head">
                                    <i class="bi <?= e($meta['icon']) ?>"></i>
                                    <span><?= e($meta['title']) ?></span>
                                    <strong><?= (int) ($counts[$statusCode] ?? 0) ?></strong>
                                </div>
                                <div class="snq-board-list">
                                    <?php foreach (($statusBuckets[$statusCode] ?? []) as $queue): ?>
                                        <?php
                                        $waitMinutes = $queueWaitMinutes($queue);
                                        $ageState = $queueAgeState($waitMinutes, $statusCode);
                                        $queueUrl = route_url('queue-exam', ['id' => (int) $queue['visit_id']]);
                                        $isSelectedQueue = (int) ($activeVisit['queue_id'] ?? 0) === (int) $queue['id'];
                                        ?>
                                        <a class="snq-queue-row age-<?= e((string) $ageState['class']) ?> <?= $isSelectedQueue ? 'is-active is-selected' : '' ?>" href="<?= e($queueUrl) ?>" data-queue-card aria-selected="<?= $isSelectedQueue ? 'true' : 'false' ?>">
                                            <span class="snq-qno">Q<?= e((string) $queue['queue_no']) ?></span>
                                            <span class="snq-qmain">
                                                <strong><?= e($queue['patient_name']) ?></strong>
                                                <small><?= e((string) ($queue['hn'] ?? '-')) ?></small>
                                                <small class="snq-qwait"><span><?= e((string) $ageState['icon']) ?></span><span><?= e((string) $ageState['text']) ?></span></small>
                                            </span>
                                            <span class="snq-qflags">
                                                <em class="priority <?= e((string) $ageState['class']) ?>"><?= e((string) $ageState['label']) ?></em>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if (empty($statusBuckets[$statusCode])): ?>
                                        <div class="snq-board-empty"><?= e($meta['empty']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                </div>
            </section>
        </main>

        <aside class="snq-assistant-rail">
            <article class="snq-rail-card snq-today-status">
                <div class="snq-status-head">
                    <div>
                        <span>Today Status</span>
                        <strong>สถานะวันนี้</strong>
                    </div>
                    <small>สำหรับพยาบาล 1 คน</small>
                </div>

                <section class="snq-status-block">
                    <div class="snq-status-block-title">
                        <i class="bi bi-activity"></i>
                        <span>ภาพรวมวันนี้</span>
                    </div>
                    <div class="snq-status-metrics">
                        <div><span>ทั้งหมด</span><strong><?= $todayPatientCount ?></strong></div>
                        <div><span>กำลังตรวจ</span><strong><?= (int) ($counts['IN_SERVICE'] ?? 0) ?></strong></div>
                        <div><span>รอรับ</span><strong><?= (int) ($counts['WAITING'] ?? 0) ?></strong></div>
                        <div><span>รอชำระ</span><strong><?= (int) ($counts['WAITING_PAYMENT'] ?? 0) ?></strong></div>
                        <div><span>เสร็จสิ้น</span><strong><?= (int) ($counts['COMPLETED'] ?? 0) ?></strong></div>
                    </div>
                </section>

                <section class="snq-status-block">
                    <div class="snq-status-block-title">
                        <i class="bi bi-cash-stack"></i>
                        <span>การเงินวันนี้</span>
                    </div>
                    <div class="snq-money-list">
                        <div class="grand"><span>รายได้รวม</span><strong><?= format_money($todayRevenue) ?></strong></div>
                        <div><span>เงินสด</span><strong><?= format_money((float) ($financialToday['CASH'] ?? 0)) ?></strong></div>
                        <div><span>QR</span><strong><?= format_money((float) ($financialToday['QR'] ?? 0)) ?></strong></div>
                        <div><span>โอน</span><strong><?= format_money((float) ($financialToday['TRANSFER'] ?? 0)) ?></strong></div>
                    </div>
                </section>

                <section class="snq-status-block">
                    <div class="snq-status-block-title">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>แจ้งเตือนสำคัญ</span>
                    </div>
                    <div class="snq-alert-list compact">
                        <div class="snq-alert-row <?= $overdueCases > 0 ? 'is-warning' : 'is-ok' ?>">
                            <i class="bi bi-hourglass-split"></i><span>คิวค้างเกิน 30 นาที</span><strong><?= $overdueCases ?></strong>
                        </div>
                        <div class="snq-alert-row <?= $printerReady ? 'is-ok' : 'is-warning' ?>">
                            <i class="bi bi-printer"></i><span>เครื่องพิมพ์</span><strong><?= $printerReady ? 'พร้อม' : 'ไม่พร้อม' ?></strong>
                        </div>
                        <div class="snq-alert-row <?= $smartCardOnline ? 'is-ok' : 'is-warning' ?>">
                            <i class="bi bi-person-vcard"></i><span>Smart Card</span><strong><?= $smartCardOnline ? 'Online' : 'Offline' ?></strong>
                        </div>
                        <?php if ($overdueCases <= 0 && $printerReady && $smartCardOnline): ?>
                            <div class="snq-alert-row is-ok">
                                <i class="bi bi-check-circle"></i><span>Critical Alert</span><strong>ปกติ</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="snq-status-block">
                    <div class="snq-status-block-title">
                        <i class="bi bi-list-check"></i>
                        <span>สรุปล่าสุด</span>
                    </div>
                    <div class="snq-latest-list">
                        <div>
                            <span>เคสล่าสุด</span>
                            <strong><?= e($latestCaseName !== '' ? $latestCaseName : '-') ?></strong>
                            <small><?= !empty($latestCase['updated_at']) ? e(date('H:i', strtotime((string) $latestCase['updated_at']))) : '-' ?></small>
                        </div>
                        <div>
                            <span>ใบเสร็จล่าสุด</span>
                            <strong><?= e((string) (($latestReceipt['receipt_no'] ?? '') ?: '-')) ?></strong>
                            <small><?= $latestReceiptName !== '' ? e($latestReceiptName) : '-' ?></small>
                        </div>
                        <div>
                            <span>สติ๊กเกอร์ล่าสุด</span>
                            <strong><?= e((string) (($latestSticker['drug_name_snapshot'] ?? '') ?: '-')) ?></strong>
                            <small><?= $latestStickerName !== '' ? e($latestStickerName) : '-' ?></small>
                        </div>
                    </div>
                </section>

                <section class="snq-status-block">
                    <div class="snq-status-block-title">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Activity Summary</span>
                    </div>
                    <div class="snq-activity-summary">
                        <div><span>เปิด Smart Exam</span><strong><?= (int) ($activitySummary['smart_exam_count'] ?? 0) ?></strong></div>
                        <div><span>รับชำระ</span><strong><?= (int) ($activitySummary['payment_count'] ?? 0) ?></strong></div>
                        <div><span>ใบเสร็จ</span><strong><?= (int) ($activitySummary['receipt_count'] ?? 0) ?></strong></div>
                        <div><span>สติ๊กเกอร์ยา</span><strong><?= (int) ($activitySummary['sticker_print_count'] ?? 0) ?></strong></div>
                    </div>
                </section>

                <section class="snq-status-block shortcuts">
                    <div class="snq-shortcut-grid">
                        <span><kbd>F1</kbd> ค้นหา</span>
                        <span><kbd>F2</kbd> คนไข้ใหม่</span>
                        <span><kbd>F3</kbd> Smart Exam</span>
                        <span><kbd>F4</kbd> บริการ</span>
                        <span><kbd>F5</kbd> ปิด/เรียกคิว</span>
                        <span><kbd>F9</kbd> ชำระเงิน</span>
                    </div>
                </section>
            </article>
        </aside>
    </section>
</div>

<script type="application/json" id="queuePatientData"><?= json_encode($patientOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
