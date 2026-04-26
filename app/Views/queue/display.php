<?php
$waitingCount = count(array_filter($todayQueues, static fn(array $queue): bool => $queue['status'] === 'WAITING'));
$completedCount = count(array_filter($todayQueues, static fn(array $queue): bool => $queue['status'] === 'COMPLETED'));
?>

<div class="queue-display-shell">
    <div class="queue-display-topbar">
        <div>
            <div class="queue-display-brand"><?= e((string) system_setting('clinic_name', config('app.name'))) ?></div>
            <div class="queue-display-subtitle">หน้าจอเรียกคิวสำหรับ Reception และหน้าห้องตรวจ</div>
        </div>
        <div class="queue-display-time">อัปเดตอัตโนมัติทุก 15 วินาที</div>
    </div>

    <div class="row g-4 align-items-stretch mb-4">
        <div class="col-xl-7">
            <div class="queue-display-current h-100">
                <div class="small text-uppercase opacity-75 mb-3">กำลังให้บริการ</div>
                <?php if ($currentQueue): ?>
                    <div class="queue-display-number">คิว <?= e((string) $currentQueue['queue_no']) ?></div>
                    <div class="queue-display-name mt-3"><?= e($currentQueue['first_name'] . ' ' . $currentQueue['last_name']) ?></div>
                    <div class="queue-display-meta mt-4">
                        <span>HN <?= e($currentQueue['hn']) ?></span>
                        <span>VN <?= e($currentQueue['visit_no']) ?></span>
                    </div>
                    <div class="queue-display-note mt-4"><?= e($currentQueue['chief_complaint'] ?: 'กำลังอยู่ระหว่างรับบริการ') ?></div>
                <?php else: ?>
                    <div class="queue-display-number queue-display-number-empty">ยังไม่มีคิวกำลังตรวจ</div>
                    <div class="queue-display-note mt-3">เมื่อเจ้าหน้าที่เรียกคิวเข้าตรวจ คิวปัจจุบันจะแสดงที่นี่ทันที</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="queue-display-next h-100">
                <div class="small text-uppercase text-muted mb-3">คิวถัดไป</div>
                <?php if ($nextWaiting): ?>
                    <div class="queue-display-next-number">คิว <?= e((string) $nextWaiting['queue_no']) ?></div>
                    <div class="queue-display-next-name mt-3"><?= e($nextWaiting['first_name'] . ' ' . $nextWaiting['last_name']) ?></div>
                    <div class="queue-display-next-meta mt-3">HN <?= e($nextWaiting['hn']) ?> / VN <?= e($nextWaiting['visit_no']) ?></div>
                    <div class="queue-display-next-note mt-3"><?= e($nextWaiting['chief_complaint'] ?: 'รอเรียกคิวถัดไป') ?></div>
                <?php else: ?>
                    <div class="queue-empty-state queue-empty-state-compact">ยังไม่มีคิวรอรับบริการ</div>
                <?php endif; ?>

                <div class="queue-display-stats mt-4">
                    <div class="queue-display-stat-card">
                        <span>รอรับบริการ</span>
                        <strong><?= e((string) $waitingCount) ?></strong>
                    </div>
                    <div class="queue-display-stat-card">
                        <span>เสร็จสิ้นแล้ว</span>
                        <strong><?= e((string) $completedCount) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="queue-display-list-card">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4 flex-wrap">
            <div>
                <div class="h4 mb-1">รายการคิวรอ</div>
                <div class="text-muted">แสดงคิวที่กำลังรอรับบริการลำดับถัด ๆ ไป</div>
            </div>
            <div class="queue-display-chip">ทั้งหมด <?= e((string) $waitingCount) ?> คิว</div>
        </div>

        <div class="queue-display-list">
            <?php foreach ($waitingList as $queue): ?>
                <div class="queue-display-list-item">
                    <div class="queue-display-list-queue">คิว <?= e((string) $queue['queue_no']) ?></div>
                    <div class="queue-display-list-name"><?= e($queue['first_name'] . ' ' . $queue['last_name']) ?></div>
                    <div class="queue-display-list-note"><?= e($queue['chief_complaint'] ?: 'รอรับบริการ') ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$waitingList): ?>
                <div class="queue-empty-state">ยังไม่มีรายการคิวรอในขณะนี้</div>
            <?php endif; ?>
        </div>
    </div>
</div>
