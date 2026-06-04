<?php
$readiness = $readiness ?? [];
$summary = $readiness['summary'] ?? ['passed' => 0, 'warning' => 0, 'failed' => 0];
$checks = $readiness['checks'] ?? [];
$backupLogs = $readiness['backup_logs'] ?? [];
$auditLogs = $readiness['audit_logs'] ?? [];
$statusMeta = static function (string $status): array {
    return match ($status) {
        'passed' => ['label' => 'พร้อม', 'icon' => 'bi-check-circle-fill', 'class' => 'is-passed'],
        'warning' => ['label' => 'ควรตรวจ', 'icon' => 'bi-exclamation-triangle-fill', 'class' => 'is-warning'],
        'failed' => ['label' => 'ต้องแก้', 'icon' => 'bi-x-circle-fill', 'class' => 'is-failed'],
        default => ['label' => $status, 'icon' => 'bi-info-circle-fill', 'class' => 'is-warning'],
    };
};
?>

<div class="production-page">
    <section class="production-hero">
        <div>
            <div class="production-kicker">Production Readiness</div>
            <h1>ตรวจความพร้อมก่อนใช้จริง</h1>
            <p>รวมสถานะ backup, schema, permission, smart card, printer, reports และ privacy เพื่อให้ผู้ดูแลรู้ว่าระบบพร้อมใช้งานจริงแค่ไหน</p>
        </div>
        <div class="production-score-grid">
            <div class="production-score is-passed">
                <span>ผ่าน</span>
                <strong><?= e((string) ($summary['passed'] ?? 0)) ?></strong>
            </div>
            <div class="production-score is-warning">
                <span>ควรตรวจ</span>
                <strong><?= e((string) ($summary['warning'] ?? 0)) ?></strong>
            </div>
            <div class="production-score is-failed">
                <span>ต้องแก้</span>
                <strong><?= e((string) ($summary['failed'] ?? 0)) ?></strong>
            </div>
        </div>
    </section>

    <section class="production-command">
        <a href="<?= e(route_url('production-smoke')) ?>" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-activity"></i> เปิด Smoke JSON
        </a>
        <a href="<?= e(route_url('backup')) ?>" class="btn btn-primary">
            <i class="bi bi-download"></i> สำรองข้อมูลตอนนี้
        </a>
        <a href="<?= e(route_url('reports')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-bar-chart"></i> รายงาน
        </a>
        <a href="<?= e(route_url('settings')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-sliders"></i> ตั้งค่าคลินิก
        </a>
    </section>

    <div class="production-grid">
        <main class="production-panel">
            <div class="production-panel-head">
                <div>
                    <div class="production-kicker">Readiness Checklist</div>
                    <h2>รายการตรวจระบบ</h2>
                </div>
                <span><?= e((string) count($checks)) ?> checks</span>
            </div>

            <div class="production-check-list">
                <?php foreach ($checks as $check): ?>
                    <?php $meta = $statusMeta((string) ($check['status'] ?? 'warning')); ?>
                    <article class="production-check <?= e($meta['class']) ?>">
                        <i class="bi <?= e($meta['icon']) ?>"></i>
                        <div>
                            <div class="production-check-title">
                                <strong><?= e((string) ($check['title'] ?? '-')) ?></strong>
                                <span><?= e((string) ($check['group'] ?? '-')) ?></span>
                            </div>
                            <p><?= e((string) ($check['message'] ?? '-')) ?></p>
                        </div>
                        <em><?= e($meta['label']) ?></em>
                    </article>
                <?php endforeach; ?>
            </div>
        </main>

        <aside class="production-rail">
            <section class="production-panel">
                <div class="production-panel-head">
                    <div>
                        <div class="production-kicker">Backup History</div>
                        <h2>สำรองข้อมูลล่าสุด</h2>
                    </div>
                </div>
                <div class="production-mini-list">
                    <?php foreach ($backupLogs as $log): ?>
                        <div class="production-mini-row">
                            <strong><?= e((string) ($log['file_name'] ?? '-')) ?></strong>
                            <span><?= thai_date($log['created_at'] ?? null) ?> · <?= format_money(((float) ($log['file_size_bytes'] ?? 0)) / 1024) ?> KB</span>
                            <small>งานค้าง <?= e((string) ($log['pending_work_count'] ?? 0)) ?> · โดย <?= e((string) (($log['created_by_name'] ?? '') ?: '-')) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$backupLogs): ?>
                        <div class="production-empty">ยังไม่มี backup log ในฐานข้อมูล</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="production-panel">
                <div class="production-panel-head">
                    <div>
                        <div class="production-kicker">Audit Activity</div>
                        <h2>กิจกรรมล่าสุด</h2>
                    </div>
                </div>
                <div class="production-mini-list">
                    <?php foreach ($auditLogs as $log): ?>
                        <div class="production-mini-row">
                            <strong><?= e((string) ($log['action'] ?? '-')) ?></strong>
                            <span><?= thai_date($log['created_at'] ?? null) ?> · <?= e((string) (($log['actor_name'] ?? '') ?: '-')) ?></span>
                            <small><?= e((string) (($log['table_name'] ?? '') ?: '-')) ?> #<?= e((string) (($log['record_id'] ?? '') ?: '-')) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$auditLogs): ?>
                        <div class="production-empty">ยังไม่มี audit log</div>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</div>
