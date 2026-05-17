<?php
$statusOptions = [
    '' => 'ทั้งหมด',
    'SCHEDULED' => 'รอรับบริการ',
    'COMPLETED' => 'รับบริการแล้ว',
    'CANCELLED' => 'ยกเลิก',
];

$statusMeta = static function (string $value): array {
    return match ($value) {
        'SCHEDULED' => ['label' => 'รอรับบริการ', 'class' => 'primary'],
        'COMPLETED' => ['label' => 'รับบริการแล้ว', 'class' => 'success'],
        'CANCELLED' => ['label' => 'ยกเลิก', 'class' => 'secondary'],
        default => ['label' => $value, 'class' => 'light text-dark'],
    };
};
?>

<div class="workspace-stack appointment-page">
    <section class="appointment-toolbar">
        <form method="get" class="appointment-filter-card">
            <input type="hidden" name="page" value="appointments">
            <div>
                <label class="form-label">จากวันที่</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div>
                <label class="form-label">ถึงวันที่</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div>
                <label class="form-label">สถานะ</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusOptions as $optionValue => $optionLabel): ?>
                        <option value="<?= e($optionValue) ?>" <?= selected($status, $optionValue) ?>><?= e($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="appointment-filter-keyword">
                <label class="form-label">ค้นหา</label>
                <input type="text" name="keyword" class="form-control" placeholder="HN ชื่อ เบอร์โทร หรือจุดประสงค์" value="<?= e($keyword) ?>">
            </div>
            <div class="appointment-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>ค้นหา</button>
                <a href="<?= e(route_url('appointments')) ?>" class="btn btn-outline-secondary">ล้าง</a>
            </div>
        </form>

        <div class="appointment-summary-grid">
            <div><span>รอรับบริการ</span><strong><?= e((string) (int) ($summary['scheduled_count'] ?? 0)) ?></strong></div>
            <div><span>เกินนัด</span><strong><?= e((string) (int) ($summary['overdue_count'] ?? 0)) ?></strong></div>
            <div><span>รับบริการแล้ว</span><strong><?= e((string) (int) ($summary['completed_count'] ?? 0)) ?></strong></div>
            <div><span>ยกเลิก</span><strong><?= e((string) (int) ($summary['cancelled_count'] ?? 0)) ?></strong></div>
        </div>
    </section>

    <div class="appointment-workspace-grid">
        <section class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <div class="panel-heading appointment-panel-heading">
                    <div>
                        <div class="eyebrow">Appointment Agenda</div>
                        <h2 class="h5 mb-1">ตารางนัดหมาย</h2>
                        <p class="text-muted mb-0">ดูนัดที่ต้องติดตาม เลื่อนนัด ยกเลิก หรือรับเข้าคิวจากหน้าจอเดียว</p>
                    </div>
                    <span class="soft-badge"><?= e((string) count($appointments)) ?> รายการ</span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="appointment-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $meta = $statusMeta((string) $appointment['status']);
                        $patientName = trim(($appointment['title_name'] ?? '') . ' ' . ($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
                        $isScheduled = (string) $appointment['status'] === 'SCHEDULED';
                        $isOverdue = $isScheduled && (string) $appointment['appointment_date'] < date('Y-m-d');
                        $hasActiveQueue = !empty($appointment['active_visit_id']);
                        ?>
                        <article class="appointment-card <?= $isOverdue ? 'is-overdue' : '' ?>">
                            <div class="appointment-card-main">
                                <div class="appointment-date-block">
                                    <strong><?= thai_date_only($appointment['appointment_date']) ?></strong>
                                    <span><?= e(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5) ?: 'ไม่ระบุเวลา') ?></span>
                                </div>
                                <div class="appointment-patient-block">
                                    <div class="appointment-title-row">
                                        <h3><?= e($patientName ?: '-') ?></h3>
                                        <span class="badge text-bg-<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge text-bg-danger">เกินนัด</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-meta-row">
                                        <span>HN <?= e((string) ($appointment['hn'] ?? '-')) ?></span>
                                        <span>โทร <?= e((string) (($appointment['phone'] ?? '') ?: '-')) ?></span>
                                        <?php if ($hasActiveQueue): ?>
                                            <span>คิววันนี้ #<?= e((string) $appointment['active_queue_no']) ?> / <?= e((string) $appointment['active_queue_status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-purpose"><?= e((string) (($appointment['purpose'] ?? '') ?: 'นัดติดตามอาการ')) ?></div>
                                    <?php if (!empty($appointment['note'])): ?>
                                        <div class="appointment-note"><?= e((string) $appointment['note']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="appointment-actions">
                                <?php if ($isScheduled): ?>
                                    <?php if ($hasActiveQueue): ?>
                                        <a href="<?= e(route_url('queue-exam', ['id' => (int) $appointment['active_visit_id']])) ?>" class="btn btn-primary btn-sm"><i class="bi bi-folder2-open me-1"></i>เปิดคิวเดิม</a>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(route_url('appointment-checkin')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>รับเข้าคิว</button>
                                        </form>
                                    <?php endif; ?>
                                    <details class="appointment-edit-details">
                                        <summary class="btn btn-outline-secondary btn-sm">เลื่อน/แก้ไข</summary>
                                        <form method="post" action="<?= e(route_url('appointments-update')) ?>" class="appointment-edit-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                            <label class="form-label">วันที่</label>
                                            <input type="date" name="appointment_date" class="form-control form-control-sm" value="<?= e((string) $appointment['appointment_date']) ?>" required>
                                            <label class="form-label">เวลา</label>
                                            <input type="time" name="appointment_time" class="form-control form-control-sm" value="<?= e(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?>">
                                            <label class="form-label">จุดประสงค์</label>
                                            <input type="text" name="purpose" class="form-control form-control-sm" value="<?= e((string) ($appointment['purpose'] ?? '')) ?>">
                                            <label class="form-label">หมายเหตุ</label>
                                            <textarea name="note" class="form-control form-control-sm" rows="2"><?= e((string) ($appointment['note'] ?? '')) ?></textarea>
                                            <button type="submit" class="btn btn-primary btn-sm w-100">บันทึก</button>
                                        </form>
                                    </details>
                                    <form method="post" action="<?= e(route_url('appointments-cancel')) ?>" onsubmit="return confirm('ยืนยันยกเลิกนัดหมายนี้?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <input type="hidden" name="return_date" value="<?= e((string) $appointment['appointment_date']) ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">ยกเลิก</button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= e(route_url('patient-show', ['id' => (int) $appointment['patient_id']])) ?>" class="btn btn-outline-secondary btn-sm">เปิดแฟ้ม</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if (!$appointments): ?>
                        <div class="queue-empty-state">ไม่พบนัดหมายในช่วงวันที่นี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="card section-card appointment-create-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <div class="eyebrow">Create Appointment</div>
                <h2 class="h5 mb-1">เพิ่มนัดหมาย</h2>
                <p class="text-muted mb-0">เลือกผู้รับบริการเดิม แล้วกำหนดวัน เวลา และจุดประสงค์นัด</p>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if ($patients): ?>
                    <form method="post" action="<?= e(route_url('appointments-store')) ?>" class="appointment-create-form">
                        <?= csrf_field() ?>
                        <label class="form-label">ผู้รับบริการ</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">เลือกผู้รับบริการ</option>
                            <?php foreach ($patients as $patient): ?>
                                <?php $patientName = trim(($patient['title_name'] ?? '') . ' ' . ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?>
                                <option value="<?= (int) $patient['id'] ?>"><?= e($patient['hn'] . ' - ' . $patientName . (($patient['phone'] ?? '') ? ' / ' . $patient['phone'] : '')) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">วันที่นัด</label>
                                <input type="date" name="appointment_date" class="form-control" value="<?= e($dateFrom) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">เวลา</label>
                                <input type="time" name="appointment_time" class="form-control">
                            </div>
                        </div>

                        <label class="form-label">จุดประสงค์</label>
                        <input type="text" name="purpose" class="form-control" placeholder="นัดติดตามอาการ">

                        <label class="form-label">หมายเหตุ</label>
                        <textarea name="note" class="form-control compact-textarea" rows="3"></textarea>

                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-calendar-plus me-1"></i>บันทึกนัดหมาย</button>
                    </form>
                <?php else: ?>
                    <div class="queue-empty-state">ยังไม่มีรายชื่อผู้รับบริการให้เลือก ลองค้นหาด้วยชื่อหรือ HN ก่อน</div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
