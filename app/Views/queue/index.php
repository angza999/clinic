<?php
$isAdmin = has_role('ADMIN');
$isNurse = has_role('NURSE');
$isCashier = has_role('CASHIER');

$statusGroups = [
    'WAITING' => [],
    'IN_SERVICE' => [],
    'WAITING_PAYMENT' => [],
    'COMPLETED' => [],
];

foreach ($todayQueues as $queueRow) {
    if (isset($statusGroups[$queueRow['status']])) {
        $statusGroups[$queueRow['status']][] = $queueRow;
    }
}

$displayGroups = $statusGroups;
if ($nextWaiting && !empty($displayGroups['WAITING'])) {
    array_shift($displayGroups['WAITING']);
}

if ($isCashier && !$isAdmin && !$isNurse) {
    $visibleStatuses = ['WAITING_PAYMENT', 'COMPLETED'];
} elseif ($isNurse && !$isAdmin) {
    $visibleStatuses = ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT'];
} else {
    $visibleStatuses = ['WAITING', 'IN_SERVICE', 'WAITING_PAYMENT', 'COMPLETED'];
}

$columnClass = match (count($visibleStatuses)) {
    2 => 'col-md-6',
    3 => 'col-md-6 col-xl-4',
    default => 'col-md-6 col-xl-3',
};
?>

<div class="row g-4 mb-4">
    <?php if ($isAdmin): ?>
        <div class="col-xl-4">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รับคิวใหม่</h2>
                    <div class="small text-muted">ค้นหาผู้รับบริการได้เร็วกว่า dropdown แบบเดิม</div>
                </div>
                <div class="card-body px-4">
                    <form method="post" action="<?= e(route_url('queue-store')) ?>" id="queue-create-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="patient_id" id="queue-patient-id" value="<?= e((string) $prefillPatientId) ?>">
                        <div class="mb-3">
                            <label class="form-label">ค้นหาผู้รับบริการ</label>
                            <input
                                type="text"
                                id="queue-patient-search"
                                class="form-control form-control-lg"
                                list="queue-patient-options"
                                placeholder="พิมพ์ HN ชื่อ หรือเบอร์โทร"
                                autocomplete="off"
                            >
                            <datalist id="queue-patient-options">
                                <?php foreach ($patients as $patient): ?>
                                    <option
                                        value="<?= e($patient['hn'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']) ?>"
                                        data-id="<?= e((string) $patient['id']) ?>"
                                        data-phone="<?= e($patient['phone'] ?: '') ?>"
                                    ></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="small text-muted mt-2">เลือกจากรายการที่ขึ้นระหว่างพิมพ์</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">อาการสำคัญเบื้องต้น</label>
                            <textarea name="chief_complaint" class="form-control" rows="3" placeholder="เช่น มีไข้ ไอ เจ็บคอ"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">สร้างคิววันนี้</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= $isAdmin ? 'col-xl-8' : 'col-12' ?>">
        <div class="card section-card h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <h2 class="h5 mb-1">ภาพรวมคิววันนี้</h2>
                    <div class="small text-muted">มองเห็นคิวถัดไปและจำนวนแต่ละสถานะในหน้าเดียว</div>
                </div>
                <a href="<?= e(route_url('queue-display')) ?>" target="_blank" class="btn btn-outline-primary">เปิดหน้าจอเรียกคิว</a>
            </div>
            <div class="card-body px-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="queue-stat queue-stat-waiting">
                            <div class="queue-stat-label">รอรับบริการ</div>
                            <div class="queue-stat-value"><?= count($statusGroups['WAITING']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="queue-stat queue-stat-service">
                            <div class="queue-stat-label">กำลังตรวจ</div>
                            <div class="queue-stat-value"><?= count($statusGroups['IN_SERVICE']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="queue-stat queue-stat-payment">
                            <div class="queue-stat-label">รอชำระเงิน</div>
                            <div class="queue-stat-value"><?= count($statusGroups['WAITING_PAYMENT']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="queue-stat queue-stat-complete">
                            <div class="queue-stat-label">เสร็จสิ้น</div>
                            <div class="queue-stat-value"><?= count($statusGroups['COMPLETED']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="queue-next-card queue-next-card-featured">
                    <div class="small text-uppercase text-muted mb-2">คิวถัดไป</div>

                    <?php if ($nextWaiting): ?>
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-start">
                            <div class="queue-next-body">
                                <div class="queue-next-title">คิว <?= e((string) $nextWaiting['queue_no']) ?> - <?= e($nextWaiting['first_name'] . ' ' . $nextWaiting['last_name']) ?></div>
                                <div class="queue-next-meta mt-3">
                                    <div class="queue-meta-pill">
                                        <span class="queue-meta-label">HN</span>
                                        <strong><?= e($nextWaiting['hn']) ?></strong>
                                    </div>
                                    <div class="queue-meta-pill">
                                        <span class="queue-meta-label">VN</span>
                                        <strong><?= e($nextWaiting['visit_no']) ?></strong>
                                    </div>
                                </div>
                                <div class="queue-next-complaint mt-3"><?= e($nextWaiting['chief_complaint'] ?: 'ยังไม่ได้ระบุอาการสำคัญ') ?></div>
                                <div class="small text-muted mt-3">
                                    <?php if (count($displayGroups['WAITING']) > 0): ?>
                                        ยังมีคิวรอถัดจากคิวนี้อีก <?= e((string) count($displayGroups['WAITING'])) ?> ราย
                                    <?php else: ?>
                                        คิวนี้เป็นคิวรอรายสุดท้ายในขณะนี้
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="queue-next-actions d-grid gap-2">
                                <form method="post" action="<?= e(route_url('queue-status')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="queue_id" value="<?= e((string) $nextWaiting['id']) ?>">
                                    <input type="hidden" name="status" value="IN_SERVICE">
                                    <button type="submit" class="btn btn-primary btn-lg">เรียกคิวถัดไปอัตโนมัติ</button>
                                </form>
                                <a href="<?= e(route_url('visit-edit', ['id' => $nextWaiting['visit_id']])) ?>" class="btn btn-outline-primary btn-lg">เปิดแฟ้มคิวนี้</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="queue-empty-state queue-empty-state-compact">ยังไม่มีคิวที่กำลังรอเรียก</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($visibleStatuses as $groupKey): ?>
        <?php $groupMeta = queue_status_meta($groupKey); ?>
        <div class="<?= e($columnClass) ?>">
            <div class="card section-card queue-board-card">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h6 mb-0"><?= e($groupMeta['label']) ?></h2>
                        <?php if ($groupKey === 'WAITING' && $nextWaiting): ?>
                            <div class="small text-muted mt-1">รายการด้านล่างไม่รวมคิวถัดไป</div>
                        <?php endif; ?>
                    </div>
                    <span class="badge rounded-pill bg-<?= e($groupMeta['class']) ?>">
                        <?= count($groupKey === 'WAITING' ? $displayGroups[$groupKey] : $statusGroups[$groupKey]) ?>
                    </span>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php $rows = $groupKey === 'WAITING' ? $displayGroups[$groupKey] : $statusGroups[$groupKey]; ?>
                    <div class="queue-board-list">
                        <?php foreach ($rows as $queueRow): ?>
                            <div class="queue-work-card queue-work-card-compact">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold">คิว <?= e((string) $queueRow['queue_no']) ?></div>
                                        <div class="queue-record-meta mt-2">
                                            <div><span>HN</span><strong><?= e($queueRow['hn']) ?></strong></div>
                                            <div><span>VN</span><strong><?= e($queueRow['visit_no']) ?></strong></div>
                                        </div>
                                    </div>
                                    <span class="badge bg-<?= e($groupMeta['class']) ?>"><?= e($groupMeta['label']) ?></span>
                                </div>

                                <div class="fw-semibold mb-1"><?= e($queueRow['first_name'] . ' ' . $queueRow['last_name']) ?></div>
                                <div class="small text-muted mb-3"><?= e($queueRow['chief_complaint'] ?: 'ยังไม่ได้ระบุอาการสำคัญ') ?></div>

                                <div class="d-grid gap-2">
                                    <?php if ($groupKey === 'WAITING'): ?>
                                        <form method="post" action="<?= e(route_url('queue-status')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="queue_id" value="<?= e((string) $queueRow['id']) ?>">
                                            <input type="hidden" name="status" value="IN_SERVICE">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">เรียกเข้าตรวจ</button>
                                        </form>
                                        <a href="<?= e(route_url('visit-edit', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-outline-primary btn-sm">เปิดแฟ้ม</a>
                                    <?php elseif ($groupKey === 'IN_SERVICE'): ?>
                                        <a href="<?= e(route_url('visit-edit', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-primary btn-sm">บันทึกห้องตรวจ</a>
                                        <form method="post" action="<?= e(route_url('queue-status')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="queue_id" value="<?= e((string) $queueRow['id']) ?>">
                                            <input type="hidden" name="status" value="WAITING">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">กลับไปรอคิว</button>
                                        </form>
                                    <?php elseif ($groupKey === 'WAITING_PAYMENT'): ?>
                                        <a href="<?= e(route_url('payments')) ?>" class="btn btn-success btn-sm">ไปหน้าการเงิน</a>
                                        <a href="<?= e(route_url('visit-edit', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-outline-secondary btn-sm">ดูแฟ้ม</a>
                                    <?php else: ?>
                                        <a href="<?= e(route_url('visit-edit', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-outline-secondary btn-sm">ดูรายละเอียด</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$rows): ?>
                        <div class="queue-empty-state queue-empty-state-compact">ไม่มีรายการ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('queue-patient-search');
    const hiddenInput = document.getElementById('queue-patient-id');
    const form = document.getElementById('queue-create-form');
    const options = Array.from(document.querySelectorAll('#queue-patient-options option'));

    const syncSelectedPatient = () => {
        const matched = options.find((option) => option.value === searchInput.value);
        hiddenInput.value = matched ? matched.dataset.id : '';
    };

    searchInput.addEventListener('input', syncSelectedPatient);
    searchInput.addEventListener('change', syncSelectedPatient);

    form.addEventListener('submit', function (event) {
        syncSelectedPatient();
        if (!hiddenInput.value) {
            event.preventDefault();
            searchInput.focus();
            alert('กรุณาเลือกผู้รับบริการจากรายการที่ระบบค้นพบ');
        }
    });
});
</script>
<?php endif; ?>