<?php
$canAccessPayments = has_role(['ADMIN', 'CASHIER']);
$canDownloadBackup = has_role('ADMIN');
$dailyClose = $dailyClose ?? [];
$dailyPaymentMethods = $dailyPaymentMethods ?? [];
$latestReceipts = $latestReceipts ?? [];
$latestBackup = $latestBackup ?? null;
$backupFile = $latestBackup['latest'] ?? null;
$openWorkCount = (int) ($todayStats['waiting_count'] ?? 0) + (int) ($todayStats['in_service_count'] ?? 0) + (int) ($todayStats['waiting_payment_count'] ?? 0);
?>

<div class="dashboard-page d-grid gap-4">
    <section class="page-hero-card dashboard-hero-card">
        <div class="page-hero-layout">
            <div>
                <div class="page-hero-eyebrow">Dashboard</div>
                <h1 class="page-hero-title">ภาพรวมงานวันนี้ของดงมหาวันคลินิก</h1>
                <p class="page-hero-text">ใช้ดูสถานะคิว รายได้ งานค้าง และรายการเตือนสำคัญก่อนเริ่มงานหรือก่อนปิดวัน</p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
                <a href="<?= e(route_url('patients')) ?>" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1"></i>ลงทะเบียนผู้รับบริการ</a>
                <a href="<?= e(route_url('queue')) ?>" class="btn btn-outline-primary"><i class="bi bi-grid-1x2-fill me-1"></i>ไปหน้าคิว</a>
                <?php if ($canAccessPayments): ?>
                    <a href="<?= e(route_url('payments')) ?>" class="btn btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>ไปหน้าการเงิน</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-stat-grid">
        <article class="queue-stat queue-stat-waiting">
            <div class="queue-stat-label">รอรับบริการ</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['waiting_count'] ?? 0)) ?></div>
        </article>
        <article class="queue-stat queue-stat-service">
            <div class="queue-stat-label">กำลังตรวจ</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['in_service_count'] ?? 0)) ?></div>
        </article>
        <article class="queue-stat queue-stat-payment">
            <div class="queue-stat-label">รอชำระเงิน</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['waiting_payment_count'] ?? 0)) ?></div>
        </article>
        <article class="queue-stat queue-stat-complete">
            <div class="queue-stat-label">รายได้วันนี้</div>
            <div class="queue-stat-value"><?= format_money($todayStats['revenue_today'] ?? 0) ?></div>
        </article>
    </section>

    <section class="card section-card dashboard-close-panel">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <div class="panel-heading">
                <div>
                    <h2 class="h5 mb-1">สรุปปิดวัน</h2>
                    <p class="text-muted mb-0">ดูยอดรับเงินวันนี้ แยกวิธีชำระ และสิ่งที่ต้องเคลียร์ก่อนปิดคลินิก</p>
                </div>
                <div class="dashboard-close-status <?= $openWorkCount > 0 ? 'has-pending' : 'is-clear' ?>">
                    <?= $openWorkCount > 0 ? 'ยังมีงานค้าง ' . e((string) $openWorkCount) . ' รายการ' : 'พร้อมปิดวัน' ?>
                </div>
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="dashboard-close-grid">
                <div class="dashboard-close-total">
                    <span>ยอดรับสุทธิวันนี้</span>
                    <strong><?= format_money($dailyClose['total_amount'] ?? 0) ?></strong>
                    <small><?= e((string) ($dailyClose['receipt_count'] ?? 0)) ?> ใบเสร็จ / ส่วนลด <?= format_money($dailyClose['discount_amount'] ?? 0) ?></small>
                </div>

                <div class="dashboard-close-methods">
                    <div class="dashboard-close-title">แยกตามวิธีชำระ</div>
                    <?php foreach ($dailyPaymentMethods as $method): ?>
                        <div class="dashboard-close-line">
                            <span><?= e($method['payment_method']) ?> <small><?= e((string) $method['receipt_count']) ?> ใบ</small></span>
                            <strong><?= format_money($method['total_amount']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$dailyPaymentMethods): ?>
                        <div class="queue-empty-state">ยังไม่มีรายการรับเงินวันนี้</div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-close-checklist">
                    <div class="dashboard-close-title">Checklist ก่อนปิดวัน</div>
                    <div class="dashboard-check-item <?= (int) ($todayStats['waiting_count'] ?? 0) > 0 ? 'is-pending' : 'is-done' ?>">
                        <span>คิวรอรับบริการ</span><strong><?= e((string) ($todayStats['waiting_count'] ?? 0)) ?></strong>
                    </div>
                    <div class="dashboard-check-item <?= (int) ($todayStats['in_service_count'] ?? 0) > 0 ? 'is-pending' : 'is-done' ?>">
                        <span>กำลังตรวจ</span><strong><?= e((string) ($todayStats['in_service_count'] ?? 0)) ?></strong>
                    </div>
                    <div class="dashboard-check-item <?= (int) ($todayStats['waiting_payment_count'] ?? 0) > 0 ? 'is-pending' : 'is-done' ?>">
                        <span>รอชำระ</span><strong><?= e((string) ($todayStats['waiting_payment_count'] ?? 0)) ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($latestReceipts): ?>
                <div class="dashboard-receipt-strip">
                    <div class="dashboard-close-title">ใบเสร็จล่าสุดวันนี้</div>
                    <div class="dashboard-receipt-list">
                        <?php foreach ($latestReceipts as $receipt): ?>
                            <div class="dashboard-receipt-item">
                                <div>
                                    <strong><?= e($receipt['receipt_no']) ?></strong>
                                    <span><?= e($receipt['hn']) ?> <?= e($receipt['first_name'] . ' ' . $receipt['last_name']) ?></span>
                                </div>
                                <div class="text-end">
                                    <strong><?= format_money($receipt['total_amount']) ?></strong>
                                    <span><?= e($receipt['payment_method']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-close-actions">
                <div>
                    <div class="dashboard-close-title mb-1">ขั้นตอนสุดท้ายก่อนออกจากคลินิก</div>
                    <p class="text-muted mb-0">
                        <?php if ($openWorkCount > 0): ?>
                            ยังไม่ควรสำรองข้อมูลปิดวันจนกว่าจะเคลียร์คิว กำลังตรวจ และรายการรอชำระให้หมดก่อน
                        <?php else: ?>
                            งานค้างเป็นศูนย์แล้ว สามารถสำรองฐานข้อมูลประจำวันก่อนปิดเครื่องได้
                        <?php endif; ?>
                    </p>
                    <div class="dashboard-backup-status <?= ($backupFile['is_today'] ?? false) ? 'is-current' : 'is-stale' ?>">
                        <span><?= ($backupFile['is_today'] ?? false) ? 'สำรองข้อมูลวันนี้แล้ว' : 'ยังไม่พบ backup วันนี้' ?></span>
                        <strong>
                            <?= $backupFile ? e(thai_date($backupFile['generated_at'])) : 'ยังไม่มีประวัติ backup' ?>
                        </strong>
                        <?php if ($backupFile): ?>
                            <small><?= e($backupFile['filename']) ?> / <?= format_money($backupFile['size_kb']) ?> KB</small>
                        <?php endif; ?>
                        <small>เก็บ <?= e((string) ($latestBackup['total_count'] ?? 0)) ?> / <?= e((string) ($latestBackup['retention_limit'] ?? 30)) ?> ไฟล์ล่าสุด</small>
                    </div>
                </div>
                <div class="dashboard-close-action-buttons">
                    <?php if ($openWorkCount > 0): ?>
                        <a href="<?= e(route_url('queue')) ?>" class="btn btn-warning">
                            เคลียร์งานค้างก่อน
                        </a>
                    <?php elseif ($canDownloadBackup): ?>
                        <a
                            href="<?= e(route_url('backup')) ?>"
                            class="btn btn-primary"
                            onclick="return confirm('ยืนยันสำรองฐานข้อมูลหลังปิดวันแล้วใช่ไหม? ควรเก็บไฟล์นี้ไว้ในที่ปลอดภัย');"
                        >
                            สำรองข้อมูลปิดวัน
                        </a>
                    <?php endif; ?>
                    <a href="<?= e(route_url('reports')) ?>" class="btn btn-outline-primary">
                        เปิดรายงานประจำวัน
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="dashboard-main-grid">
        <section class="card section-card dashboard-primary-panel">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <div class="panel-heading">
                    <div>
                        <h2 class="h5 mb-1">งานที่ต้องทำตอนนี้</h2>
                        <p class="text-muted mb-0">ใช้ดูคิวที่ต้องหยิบทำต่อทันที ก่อนเข้าสู่หน้าคิวหรือหน้าการเงิน</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= e(route_url('queue')) ?>" class="btn btn-primary btn-sm">ไปหน้าคิว</a>
                        <?php if ($canAccessPayments): ?>
                            <a href="<?= e(route_url('payments')) ?>" class="btn btn-outline-primary btn-sm">ไปหน้าการเงิน</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="workflow-list compact-workflow-list">
                    <?php foreach ($workQueues as $queueRow): ?>
                        <?php $meta = queue_status_meta($queueRow['status']); ?>
                        <div class="workflow-list-item">
                            <div>
                                <div class="fw-semibold">คิว <?= e((string) $queueRow['queue_no']) ?> - <?= e($queueRow['first_name'] . ' ' . $queueRow['last_name']) ?></div>
                                <div class="small text-muted"><?= e($queueRow['hn']) ?> / <?= e($queueRow['visit_no']) ?></div>
                                <div class="small"><?= e($queueRow['chief_complaint'] ?: 'ยังไม่ได้ระบุอาการสำคัญ') ?></div>
                            </div>
                            <div class="text-end">
                                <div class="mb-2"><span class="badge bg-<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></div>
                                <?php if ($queueRow['status'] === 'WAITING_PAYMENT' && $canAccessPayments): ?>
                                    <a href="<?= e(route_url('payments')) ?>" class="btn btn-sm btn-outline-primary">ไปหน้าการเงิน</a>
                                <?php elseif ($queueRow['status'] === 'WAITING_PAYMENT'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">รอการเงิน</span>
                                <?php else: ?>
                                    <a href="<?= e(route_url('queue-exam', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-sm btn-outline-primary">เปิด Smart Exam</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$workQueues): ?>
                        <div class="queue-empty-state">ยังไม่มีงานค้างในคิววันนี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="dashboard-side-stack">
            <section class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h2 class="h5 mb-1">นัดติดตามวันนี้</h2>
                    <p class="small text-muted mb-0">ช่วยเตือนเคสที่ต้องติดตามหรือมาซ้ำในวันนี้</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list compact-workflow-list">
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></div>
                                    <div class="small text-muted"><?= e($appointment['hn']) ?></div>
                                </div>
                                <div class="text-end small"><?= e($appointment['purpose'] ?: 'นัดติดตาม') ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$todayAppointments): ?>
                            <div class="queue-empty-state">ไม่มีนัดติดตามในวันนี้</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h2 class="h5 mb-1">สรุปวันนี้</h2>
                    <p class="small text-muted mb-0">ดูตัวเลขภาพรวมก่อนสรุปปิดงานประจำวัน</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list compact-workflow-list">
                        <div class="workflow-list-item">
                            <div>ผู้รับบริการวันนี้</div>
                            <div class="fw-semibold"><?= e((string) ($todayStats['visit_count_today'] ?? 0)) ?></div>
                        </div>
                        <div class="workflow-list-item">
                            <div>นัดติดตามวันนี้</div>
                            <div class="fw-semibold"><?= e((string) ($todayStats['followup_today'] ?? 0)) ?></div>
                        </div>
                        <div class="workflow-list-item">
                            <div>รายได้วันนี้</div>
                            <div class="fw-semibold"><?= format_money($todayStats['revenue_today'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <div class="dashboard-bottom-grid">
        <section class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h2 class="h5 mb-1">บริการยอดนิยมเดือนนี้</h2>
                <p class="small text-muted mb-0">ใช้ดูรายการที่ควรวางเป็นปุ่มลัดใน Smart Exam</p>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>บริการ</th>
                            <th class="text-end">จำนวนครั้ง</th>
                            <th class="text-end">รายได้</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($popularServices as $service): ?>
                            <tr>
                                <td><?= e($service['service_name']) ?></td>
                                <td class="text-end"><?= e((string) $service['total_qty']) ?></td>
                                <td class="text-end"><?= format_money($service['total_income']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$popularServices): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">ยังไม่มีข้อมูลบริการ</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h2 class="h5 mb-1">รายได้ย้อนหลัง 6 เดือน</h2>
                <p class="small text-muted mb-0">ใช้ดูแนวโน้มรายรับแบบย่อของคลินิก</p>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>เดือน</th>
                            <th class="text-end">รายได้</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($monthlyRevenue as $row): ?>
                            <tr>
                                <td><?= e($row['month_label']) ?></td>
                                <td class="text-end"><?= format_money($row['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$monthlyRevenue): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">ยังไม่มีข้อมูลรายได้</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h2 class="h5 mb-1">แจ้งเตือนคลังยา</h2>
                <p class="small text-muted mb-0">เน้นรายการที่ต้องรีบสั่งซื้อหรือใกล้หมดอายุ</p>
            </div>
            <div class="card-body px-4 pb-4">
                <h3 class="h6">สต๊อกต่ำ</h3>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr><th>รายการ</th><th class="text-end">คงเหลือ</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStocks as $stock): ?>
                            <tr>
                                <td><?= e($stock['item_name']) ?></td>
                                <td class="text-end text-danger fw-semibold"><?= format_money($stock['qty_balance']) ?> <?= e($stock['unit_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lowStocks): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">ไม่มีรายการต่ำกว่าเกณฑ์</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="h6">ใกล้หมดอายุภายใน 30 วัน</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr><th>รายการ</th><th>Lot</th><th>หมดอายุ</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($expiryAlerts as $alert): ?>
                            <tr>
                                <td><?= e($alert['item_name']) ?></td>
                                <td><?= e($alert['lot_no']) ?></td>
                                <td class="text-warning fw-semibold"><?= thai_date_only($alert['expiry_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$expiryAlerts): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">ไม่มีรายการใกล้หมดอายุ</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
