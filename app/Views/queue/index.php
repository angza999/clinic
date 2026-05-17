<?php
$queueUser = current_user() ?? ['role_code' => '', 'full_name' => '', 'role_name' => ''];
$canManageQueue = in_array($queueUser['role_code'], ['ADMIN', 'NURSE'], true);
$showCreateQueuePanel = $canManageQueue;
$visibleBoards = [];

if ($queueUser['role_code'] === 'CASHIER') {
    $visibleBoards = ['WAITING_PAYMENT', 'COMPLETED'];
} elseif ($queueUser['role_code'] === 'NURSE') {
    $visibleBoards = ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT'];
} else {
    $visibleBoards = ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT', 'COMPLETED'];
}

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
$hasActiveVisit = !empty($activeVisit);
$activeStatus = $activeVisit['status'] ?? 'WAITING';
$fromReceipt = (int) ($_GET['from_receipt'] ?? 0) === 1;
$todayAppointments = $todayAppointments ?? [];
$queueBoards = [
    'WAITING' => ['title' => 'รอรับบริการ', 'class' => 'status-waiting', 'empty' => 'ยังไม่มีคิวรอรับบริการ'],
    'IN_SERVICE' => ['title' => 'กำลังตรวจ', 'class' => 'status-in-service', 'empty' => 'ยังไม่มีคิวที่กำลังตรวจ'],
    'WAITING_PAYMENT' => ['title' => 'รอชำระเงิน', 'class' => 'status-payment', 'empty' => 'ยังไม่มีคิวรอชำระเงิน'],
    'COMPLETED' => ['title' => 'เสร็จสิ้น', 'class' => 'status-completed', 'empty' => 'ยังไม่มีคิวที่ปิดเคสแล้ววันนี้'],
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
?>

<div class="queue-workspace smart-exam-shell">
    <section class="card queue-command-bar">
        <div class="queue-command-main">
            <div class="queue-command-kicker">Queue Station</div>
            <div class="queue-command-title">คิววันนี้</div>
            <div class="queue-command-metrics" aria-label="Queue status">
                <span class="waiting">รอ <?= (int) ($counts['WAITING'] ?? 0) ?></span>
                <span class="in-service">ตรวจ <?= (int) ($counts['IN_SERVICE'] ?? 0) ?></span>
                <span class="payment">ชำระ <?= (int) ($counts['WAITING_PAYMENT'] ?? 0) ?></span>
                <span class="completed">เสร็จ <?= (int) ($counts['COMPLETED'] ?? 0) ?></span>
            </div>
        </div>

        <div class="queue-command-focus">
            <?php if ($nextWaiting): ?>
                <span>คิวถัดไป</span>
                <strong>คิว <?= e((string) $nextWaiting['queue_no']) ?> · <?= e($nextWaiting['patient_name']) ?></strong>
            <?php elseif ($hasActiveVisit): ?>
                <span>กำลังทำงาน</span>
                <strong>คิว <?= e((string) ($activeVisit['queue_no'] ?? '')) ?> · <?= e(($activeVisit['first_name'] ?? '') . ' ' . ($activeVisit['last_name'] ?? '')) ?></strong>
            <?php else: ?>
                <span>สถานะ</span>
                <strong>พร้อมรับเคส</strong>
            <?php endif; ?>
        </div>

        <div class="queue-command-actions">
            <?php if ($nextWaiting && $canManageQueue): ?>
                <form method="post" action="<?= e(route_url('queue-status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int) $nextWaiting['id'] ?>">
                    <input type="hidden" name="status" value="IN_SERVICE">
                    <input type="hidden" name="redirect_to_visit" value="1">
                    <button class="btn btn-primary btn-sm" id="queueCommandNextCase">
                        <i class="bi bi-heart-pulse-fill me-1"></i>เรียกตรวจ
                    </button>
                </form>
            <?php elseif ($hasActiveVisit): ?>
                <a href="<?= e(route_url('queue-exam', ['id' => (int) $activeVisit['id']])) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-heart-pulse-fill me-1"></i>เปิด Smart Exam
                </a>
            <?php endif; ?>
            <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-primary btn-sm">เพิ่มผู้รับบริการ</a>
        </div>
    </section>

    <section class="card smart-exam-hero">
        <div>
            <div class="eyebrow">ภาพรวมการทำงานวันนี้</div>
            <h2>ระบบคิวพร้อมเชื่อม Smart Exam</h2>
            <p>รับคิว เปิดหน้าตรวจ และดูสรุปเคสจากจุดเดียว โดยลดพื้นที่ว่างและทำให้ลำดับงานชัดขึ้นสำหรับพยาบาลหน้างาน</p>
        </div>
    </section>

    <section class="queue-status-strip smart-exam-status-strip">
        <article class="queue-status-mini waiting"><span>รอรับบริการ</span><strong><?= (int) ($counts['WAITING'] ?? 0) ?></strong></article>
        <article class="queue-status-mini in-service"><span>กำลังตรวจ</span><strong><?= (int) ($counts['IN_SERVICE'] ?? 0) ?></strong></article>
        <article class="queue-status-mini payment"><span>รอชำระเงิน</span><strong><?= (int) ($counts['WAITING_PAYMENT'] ?? 0) ?></strong></article>
        <article class="queue-status-mini completed"><span>เสร็จสิ้น</span><strong><?= (int) ($counts['COMPLETED'] ?? 0) ?></strong></article>
    </section>

    <?php if ($fromReceipt && $canManageQueue): ?>
        <section class="queue-continuation-card" data-queue-continuation>
            <div>
                <div class="eyebrow">Next Case Flow</div>
                <h3>รับเคสถัดไปได้ทันที</h3>
                <?php if ($nextWaiting): ?>
                    <p>มีคิวรออยู่: คิว <?= e((string) $nextWaiting['queue_no']) ?> - <?= e($nextWaiting['patient_name']) ?> กดปุ่มเดียวเพื่อเรียกและเปิด Smart Exam</p>
                <?php else: ?>
                    <p>ปิดเคสก่อนหน้าเรียบร้อยแล้ว ตอนนี้ยังไม่มีคิวรอรับบริการ สามารถค้นหาคนไข้หรือรับเคสใหม่ได้จากแผงซ้าย</p>
                <?php endif; ?>
            </div>
            <?php if ($nextWaiting): ?>
                <form method="post" action="<?= e(route_url('queue-status')) ?>" class="queue-continuation-action">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int) $nextWaiting['id'] ?>">
                    <input type="hidden" name="status" value="IN_SERVICE">
                    <input type="hidden" name="redirect_to_visit" value="1">
                    <button class="btn btn-primary btn-lg" id="queueContinueNextCase">
                        <i class="bi bi-arrow-right-circle-fill me-1"></i>เรียกและเปิด Smart Exam
                    </button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="smart-exam-layout">
        <aside class="smart-panel smart-panel-left">
            <article class="card smart-panel-card">
                <div class="smart-panel-head">
                    <div class="eyebrow">ขั้นตอนที่ 1</div>
                    <h3>เริ่มเคส</h3>
                    <p>เลือกคิวถัดไป ค้นหาคนไข้เดิม หรือรับเคสใหม่ให้พร้อมก่อนเข้าสู่หน้าตรวจ</p>
                </div>

                <?php if ($nextWaiting): ?>
                    <div class="smart-next-queue">
                        <div class="smart-next-queue-top">
                            <span class="soft-label">คิวถัดไป</span>
                            <span class="queue-inline-chip waiting">คิว <?= (int) $nextWaiting['queue_no'] ?></span>
                        </div>
                        <div class="smart-next-queue-name"><?= e($nextWaiting['patient_name']) ?></div>
                        <div class="smart-next-queue-meta">HN <?= e($nextWaiting['hn']) ?> / VN <?= e($nextWaiting['visit_no']) ?></div>
                        <div class="smart-next-queue-note"><?= e($nextWaiting['chief_complaint'] ?: 'ยังไม่ได้ระบุอาการสำคัญ') ?></div>
                        <?php if ($canManageQueue): ?>
                            <form method="post" action="<?= e(route_url('queue-status')) ?>" class="mt-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="queue_id" value="<?= (int) $nextWaiting['id'] ?>">
                                <input type="hidden" name="status" value="IN_SERVICE">
                                <input type="hidden" name="redirect_to_visit" value="1">
                                <button class="btn btn-primary w-100"><i class="bi bi-megaphone-fill me-1"></i>เรียกเข้าตรวจ</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canManageQueue): ?>
                    <div class="smart-subsection queue-appointment-panel mt-3">
                        <div class="smart-subsection-title">นัดติดตามวันนี้ / ค้างรับบริการ</div>
                        <div class="queue-appointment-list">
                            <?php foreach ($todayAppointments as $appointment): ?>
                                <?php
                                $appointmentName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
                                $hasActiveAppointmentQueue = !empty($appointment['active_visit_id']);
                                ?>
                                <div class="queue-appointment-card">
                                    <div>
                                        <strong><?= e($appointmentName) ?></strong>
                                        <span>HN <?= e((string) ($appointment['hn'] ?? '-')) ?> / <?= thai_date_only($appointment['appointment_date'] ?? null) ?></span>
                                        <small><?= e((string) (($appointment['purpose'] ?? '') ?: 'นัดติดตามอาการ')) ?></small>
                                    </div>
                                    <?php if ($hasActiveAppointmentQueue): ?>
                                        <a href="<?= e(route_url('queue-exam', ['id' => (int) $appointment['active_visit_id']])) ?>" class="btn btn-outline-primary btn-sm">เปิดคิวเดิม</a>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(route_url('appointment-checkin')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                            <button class="btn btn-primary btn-sm">รับนัดเข้าคิว</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$todayAppointments): ?>
                                <div class="queue-appointment-empty">ยังไม่มีนัดติดตามที่ต้องรับบริการวันนี้</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($showCreateQueuePanel): ?>
                    <div class="smart-subsection">
                        <div class="smart-subsection-title">ค้นหาคนไข้เดิม</div>
                        <form method="post" action="<?= e(route_url('queue-store')) ?>" class="stack-form mt-3" id="queueCreateForm">
                            <?= csrf_field() ?>
                            <div class="queue-patient-picker" data-queue-patient-picker>
                                <label class="form-label" for="queuePatientSearch">ค้นหาจาก HN / ชื่อ / เบอร์โทร</label>
                                <input type="text" id="queuePatientSearch" class="form-control" placeholder="เช่น HN000001, อัง, 0857" autocomplete="off" data-queue-patient-search>
                                <input type="hidden" name="patient_id" id="queuePatientId" value="<?= (int) ($prefillPatientId ?? 0) ?>" required data-queue-patient-id>
                                <div class="queue-patient-selected d-none" data-queue-patient-selected></div>
                                <div class="queue-patient-results" data-queue-patient-results></div>
                                <div class="form-text">พิมพ์บางส่วนแล้วคลิกเลือกรายชื่อก่อนกดรับเคส</div>
                            </div>
                            <div>
                                <label class="form-label">อาการเบื้องต้น</label>
                                <textarea name="chief_complaint" class="form-control" rows="2" placeholder="เช่น ไข้ ไอ เจ็บคอ"></textarea>
                            </div>
                            <button class="btn btn-primary"><i class="bi bi-person-check-fill me-1"></i>รับเคสจากคนไข้เดิม</button>
                        </form>
                    </div>

                    <div class="smart-subsection mt-3 queue-register-redirect">
                        <div class="smart-subsection-title">คนไข้ใหม่</div>
                        <p class="mb-3">ลงทะเบียนผู้รับบริการใหม่ให้ทำที่หน้า “ผู้รับบริการ” เพื่อเก็บข้อมูลครบและลดข้อมูลซ้ำในหน้าคิว</p>
                        <form method="post" action="<?= e(route_url('queue-quick-register')) ?>" class="queue-quick-register-form" id="queueQuickRegisterForm">
                            <?= csrf_field() ?>
                            <div class="queue-quick-register-grid">
                                <div>
                                    <label class="form-label" for="quickFullName">ชื่อ-สกุล</label>
                                    <input type="text" name="quick_full_name" id="quickFullName" class="form-control" value="<?= e((string) old('quick_full_name')) ?>" placeholder="เช่น สมชาย ใจดี" required data-quick-name>
                                </div>
                                <div>
                                    <label class="form-label" for="quickPhone">เบอร์โทร</label>
                                    <input type="text" name="quick_phone" id="quickPhone" class="form-control" value="<?= e((string) old('quick_phone')) ?>" placeholder="ใช้เช็คข้อมูลซ้ำ" data-quick-phone>
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
                            <label class="form-label mt-2" for="quickChiefComplaint">อาการสำคัญ</label>
                            <textarea name="quick_chief_complaint" id="quickChiefComplaint" class="form-control" rows="2" placeholder="เช่น ไข้ ไอ เจ็บคอ"><?= e((string) old('quick_chief_complaint')) ?></textarea>
                            <div class="queue-duplicate-warning d-none" data-quick-duplicate-warning></div>
                            <label class="queue-confirm-duplicate">
                                <input type="checkbox" name="confirm_duplicate" value="1" <?= checked((string) old('confirm_duplicate'), '1') ?>>
                                ยืนยันสร้างคนไข้ใหม่ แม้ระบบแจ้งว่าอาจซ้ำ
                            </label>
                            <button class="btn btn-primary w-100 mt-2">
                                <i class="bi bi-lightning-charge-fill me-1"></i>ลงทะเบียนด่วนและเปิด Smart Exam
                            </button>
                        </form>
                        <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-primary w-100 mt-2">
                            <i class="bi bi-person-plus-fill me-1"></i>ไปหน้าลงทะเบียนผู้รับบริการ
                        </a>
                    </div>
                <?php endif; ?>

                <div class="smart-subsection mt-4">
                    <div class="smart-subsection-title">คนไข้ล่าสุด</div>
                    <div class="smart-recent-list mt-3">
                        <?php foreach (array_slice($patients ?? [], 0, 5) as $patient): ?>
                            <form method="post" action="<?= e(route_url('queue-store')) ?>" class="smart-recent-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                                <input type="hidden" name="chief_complaint" value="">
                                <button type="submit" class="smart-recent-card">
                                    <strong><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></strong>
                                    <span><?= e($patient['hn']) ?> / <?= e($patient['phone'] ?: '-') ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        </aside>

        <main class="smart-panel smart-panel-center">
            <article class="card smart-panel-card smart-main-card">
                <div class="smart-panel-head">
                    <div class="eyebrow">ขั้นตอนที่ 2</div>
                    <h3>เปิดหน้าตรวจ</h3>
                    <p>เมื่อเลือกคนไข้หรือเรียกคิวเข้าตรวจแล้ว ให้เปิดหน้า Smart Exam เพื่อบันทึกประวัติ ตรวจร่างกาย และจบเคสแบบแยกหน้าชัดเจน</p>
                </div>

                <?php if ($hasActiveVisit): ?>
                    <div class="smart-active-case">
                        <div>
                            <div class="smart-active-name"><?= e($activeVisit['first_name'] . ' ' . $activeVisit['last_name']) ?></div>
                            <div class="smart-active-meta">คิว <?= e((string) $activeVisit['queue_no']) ?> / HN <?= e($activeVisit['hn']) ?> / VN <?= e($activeVisit['visit_no']) ?></div>
                        </div>
                        <span class="active-case-status <?= e(strtolower((string) $activeVisit['status'])) ?>"><?= e(queue_status_meta((string) $activeVisit['status'])['label']) ?></span>
                    </div>
                    <div class="smart-secondary-link">
                        <a href="<?= e(route_url('visit-edit', ['id' => $activeVisit['id']])) ?>">เปิดรายละเอียดขั้นสูง</a>
                    </div>

                    <div class="smart-open-exam-card">
                        <h4>พร้อมสำหรับ Smart Exam</h4>
                        <p>เมื่อเริ่มคิวแล้ว ให้เปิด Smart Exam เพื่อซักประวัติ บันทึกการรักษา และจบเคสโดยไม่รบกวนหน้าคิวหลัก</p>
                        <div class="smart-open-exam-actions">
                            <a href="<?= e(route_url('queue-exam', ['id' => $activeVisit['id']])) ?>" class="btn btn-primary btn-lg"><i class="bi bi-heart-pulse-fill me-1"></i>เปิดหน้า Smart Exam</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state queue-empty-compact">
                        <h3>ยังไม่มีเคสที่กำลังทำ</h3>
                        <p>เลือกคนไข้จากด้านซ้ายหรือเรียกคิวถัดไปก่อน แล้วระบบจะแสดงปุ่มเปิดหน้า Smart Exam ให้ทันที</p>
                    </div>
                <?php endif; ?>
            </article>
        </main>

        <aside class="smart-panel smart-panel-right">
            <article class="card smart-panel-card smart-summary-card">
                <div class="smart-panel-head">
                    <div class="eyebrow">ขั้นตอนที่ 3</div>
                    <h3>สรุปเคส</h3>
                    <p>ดูยอดบริการและอุปกรณ์ที่ใช้จากหน้าคิวได้ทันที ส่วนการบันทึกและจบเคสให้ทำจากหน้า Smart Exam</p>
                </div>

                <?php if ($hasActiveVisit): ?>
                    <div class="smart-summary-patient">
                        <div class="smart-summary-name"><?= e($activeVisit['first_name'] . ' ' . $activeVisit['last_name']) ?></div>
                        <div class="smart-summary-meta">แพ้ยา: <?= e($activeVisit['drug_allergy'] ?: '-') ?></div>
                    </div>

                    <div class="smart-summary-section">
                        <div class="smart-summary-title">บริการ</div>
                        <?php foreach (($activeVisit['service_lines'] ?? []) as $line): ?>
                            <div class="smart-summary-line">
                                <span><?= e($line['service_name']) ?> x<?= e((string) $line['qty']) ?></span>
                                <strong><?= format_money($line['line_total']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activeVisit['service_lines'])): ?>
                            <div class="smart-summary-empty">ยังไม่มีบริการ</div>
                        <?php endif; ?>
                    </div>

                    <div class="smart-summary-section">
                        <div class="smart-summary-title">อุปกรณ์ที่ใช้</div>
                        <?php foreach (($activeVisit['item_lines'] ?? []) as $line): ?>
                            <div class="smart-summary-line">
                                <span><?= e($line['item_name']) ?> x<?= format_money($line['qty']) ?></span>
                                <strong><?= format_money($line['line_total']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activeVisit['item_lines'])): ?>
                            <div class="smart-summary-empty">ยังไม่มีอุปกรณ์ที่ใช้</div>
                        <?php endif; ?>
                    </div>

                    <div class="smart-summary-total">
                        <div class="smart-summary-line"><span>ค่าบริการ</span><strong><?= format_money($activeVisit['service_total'] ?? 0) ?></strong></div>
                        <div class="smart-summary-line"><span>ค่าเวชภัณฑ์/อุปกรณ์</span><strong><?= format_money($activeVisit['item_total'] ?? 0) ?></strong></div>
                        <div class="smart-summary-line grand"><span>รวมสุทธิ</span><strong><?= format_money(($activeVisit['service_total'] ?? 0) + ($activeVisit['item_total'] ?? 0)) ?></strong></div>
                    </div>

                    <div class="smart-summary-actions">
                        <div class="smart-summary-hint">ต้องจบเคสใน Smart Exam ก่อนส่งชำระเงิน</div>
                    </div>
                <?php else: ?>
                    <div class="empty-state queue-empty-compact">
                        <h3>ยังไม่มีรายการสรุป</h3>
                        <p>เมื่อเลือกคนไข้และเริ่มตรวจ ระบบจะแสดงข้อมูลสรุปไว้ที่แผงนี้ทันที</p>
                    </div>
                <?php endif; ?>
            </article>
        </aside>
    </section>

    <section class="smart-board-grid">
        <?php foreach ($queueBoards as $statusCode => $meta): ?>
            <?php if (!in_array($statusCode, $visibleBoards, true)) { continue; } ?>
            <article class="card smart-board-card <?= e($meta['class']) ?>">
                <div class="smart-board-head">
                    <h4><?= e($meta['title']) ?></h4>
                    <span class="smart-board-count"><?= (int) ($counts[$statusCode] ?? 0) ?></span>
                </div>
                <div class="smart-board-list">
                    <?php foreach (array_slice($statusBuckets[$statusCode] ?? [], 0, 4) as $queue): ?>
                        <div class="smart-board-item">
                            <strong><?= e($queue['patient_name']) ?></strong>
                            <span>คิว <?= e((string) $queue['queue_no']) ?> / HN <?= e($queue['hn']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($statusBuckets[$statusCode])): ?>
                        <div class="smart-board-empty"><?= e($meta['empty']) ?></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<script type="application/json" id="queuePatientData"><?= json_encode($patientOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
