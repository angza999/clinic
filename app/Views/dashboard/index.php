<?php
$canAccessPayments = has_role(['ADMIN', 'CASHIER', 'NURSE']);
$canDownloadBackup = has_role('ADMIN');
$dailyClose = $dailyClose ?? [];
$dailyPaymentMethods = $dailyPaymentMethods ?? [];
$latestReceipts = $latestReceipts ?? [];
$latestBackup = $latestBackup ?? null;
$backupFile = $latestBackup['latest'] ?? null;
$visitToday = (int) ($todayStats['visit_count_today'] ?? 0);
$waitingCount = (int) ($todayStats['waiting_count'] ?? 0);
$inServiceCount = (int) ($todayStats['in_service_count'] ?? 0);
$waitingPaymentCount = (int) ($todayStats['waiting_payment_count'] ?? 0);
$followupToday = (int) ($todayStats['followup_today'] ?? 0);
$revenueToday = (float) ($todayStats['revenue_today'] ?? 0);
$openWorkCount = $waitingCount + $inServiceCount + $waitingPaymentCount;
$lowStockCount = count($lowStocks ?? []);
$expiryCount = count($expiryAlerts ?? []);
$visitTarget = 20;
$revenueTarget = 5000;
$visitProgress = min(100, (int) round(($visitToday / max(1, $visitTarget)) * 100));
$revenueProgress = min(100, (int) round(($revenueToday / max(1, $revenueTarget)) * 100));
$notifications = [];
if ($inServiceCount > 0) {
    $notifications[] = ['class' => 'is-blue', 'icon' => 'bi-heart-pulse-fill', 'text' => 'กำลังตรวจ ' . $inServiceCount . ' ราย'];
}
if ($followupToday === 0) {
    $notifications[] = ['class' => 'is-green', 'icon' => 'bi-calendar2-check-fill', 'text' => 'ไม่มีนัดติดตามวันนี้'];
} else {
    $notifications[] = ['class' => 'is-amber', 'icon' => 'bi-calendar-event-fill', 'text' => 'นัดติดตามวันนี้ ' . $followupToday . ' ราย'];
}
if ($lowStockCount > 0) {
    $notifications[] = ['class' => 'is-amber', 'icon' => 'bi-capsule-pill', 'text' => 'ยาใกล้หมด ' . $lowStockCount . ' รายการ'];
}
if ($waitingPaymentCount > 0) {
    $notifications[] = ['class' => 'is-purple', 'icon' => 'bi-credit-card-fill', 'text' => 'รอชำระเงิน ' . $waitingPaymentCount . ' ราย'];
}
$notifications = array_slice($notifications, 0, 3);
$paymentMethodTotals = [];
foreach ($dailyPaymentMethods as $method) {
    $paymentMethodTotals[(string) $method['payment_method']] = (float) $method['total_amount'];
}
$cashToday = $paymentMethodTotals['CASH'] ?? $paymentMethodTotals['เงินสด'] ?? 0;
$qrToday = $paymentMethodTotals['QR'] ?? $paymentMethodTotals['QR_PAYMENT'] ?? $paymentMethodTotals['โอน/QR'] ?? 0;
$transferToday = $paymentMethodTotals['TRANSFER'] ?? $paymentMethodTotals['โอน'] ?? 0;
$maxServiceQty = 0;
foreach ($popularServices as $service) {
    $maxServiceQty = max($maxServiceQty, (float) ($service['total_qty'] ?? 0));
}
$maxRevenue = 0;
foreach ($monthlyRevenue as $row) {
    $maxRevenue = max($maxRevenue, (float) ($row['total_amount'] ?? 0));
}
$currentUser = current_user();
$displayName = trim((string) ($currentUser['full_name'] ?? 'ผู้ดูแลระบบ'));
if ($displayName === '') {
    $displayName = 'ผู้ดูแลระบบ';
}
?>

<div class="clinic-dashboard">
    <section class="dashboard-header-panel">
        <div class="dashboard-greeting">
            <span class="dashboard-eyebrow">Clinic Command Center</span>
            <h1>สวัสดี, <?= e($displayName) ?> 👋</h1>
            <p><?= thai_date(date('Y-m-d H:i:s')) ?> <span class="dashboard-clock"><?= date('H:i') ?> น.</span></p>
        </div>
        <div class="dashboard-status-strip" aria-label="สถานะคลินิกวันนี้">
            <div>
                <span>ผู้รับบริการวันนี้</span>
                <strong><?= e((string) $visitToday) ?> ราย</strong>
            </div>
            <div>
                <span>กำลังตรวจ</span>
                <strong><?= e((string) $inServiceCount) ?> ราย</strong>
            </div>
            <div>
                <span>รอชำระเงิน</span>
                <strong><?= e((string) $waitingPaymentCount) ?> ราย</strong>
            </div>
            <div>
                <span>รายได้วันนี้</span>
                <strong><?= format_money($revenueToday) ?> บาท</strong>
            </div>
        </div>
    </section>

    <section class="dashboard-notification-strip" aria-label="แจ้งเตือนสำคัญวันนี้">
        <?php foreach ($notifications as $notification): ?>
            <a class="dashboard-notification <?= e($notification['class']) ?>" href="<?= e(route_url('queue')) ?>">
                <i class="bi <?= e($notification['icon']) ?>"></i>
                <span><?= e($notification['text']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if (!$notifications): ?>
            <div class="dashboard-notification is-green">
                <i class="bi bi-check-circle-fill"></i>
                <span>วันนี้ระบบพร้อมใช้งาน ไม่มีรายการเร่งด่วน</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-kpi-grid" aria-label="ตัวชี้วัดสำคัญ">
        <a class="dashboard-kpi-card kpi-patient" href="<?= e(route_url('queue')) ?>">
            <span class="kpi-icon"><i class="bi bi-people-fill"></i></span>
            <span class="kpi-label">ผู้รับบริการวันนี้</span>
            <strong><?= e((string) $visitToday) ?></strong>
            <span class="kpi-status"><?= e((string) $visitProgress) ?>% ของเป้าหมายวันนี้</span>
        </a>
        <a class="dashboard-kpi-card kpi-service" href="<?= e(route_url('queue')) ?>">
            <span class="kpi-icon"><i class="bi bi-heart-pulse-fill"></i></span>
            <span class="kpi-label">กำลังตรวจ</span>
            <strong><?= e((string) $inServiceCount) ?></strong>
            <span class="kpi-status"><?= $inServiceCount > 0 ? 'มีเคสที่ต้องดูต่อทันที' : 'ไม่มีเคส active ตอนนี้' ?></span>
        </a>
        <a class="dashboard-kpi-card kpi-payment" href="<?= e(route_url('payments')) ?>">
            <span class="kpi-icon"><i class="bi bi-credit-card-2-front-fill"></i></span>
            <span class="kpi-label">รอชำระเงิน</span>
            <strong><?= e((string) $waitingPaymentCount) ?></strong>
            <span class="kpi-status"><?= $waitingPaymentCount > 0 ? 'ควรเคลียร์ก่อนปิดวัน' : 'ไม่มีคิวรอชำระ' ?></span>
        </a>
        <a class="dashboard-kpi-card kpi-revenue" href="<?= e(route_url('payments')) ?>">
            <span class="kpi-icon"><i class="bi bi-cash-stack"></i></span>
            <span class="kpi-label">รายได้วันนี้</span>
            <strong><?= format_money($revenueToday) ?></strong>
            <span class="kpi-status"><?= e((string) $revenueProgress) ?>% ของเป้าหมาย <?= format_money($revenueTarget) ?></span>
        </a>
        <a class="dashboard-kpi-card kpi-followup" href="<?= e(route_url('appointments')) ?>">
            <span class="kpi-icon"><i class="bi bi-calendar2-check-fill"></i></span>
            <span class="kpi-label">นัดติดตามวันนี้</span>
            <strong><?= e((string) $followupToday) ?></strong>
            <span class="kpi-status"><?= $followupToday > 0 ? 'มีเคสต้องติดตาม' : 'รับ Walk-in ได้เต็มที่' ?></span>
        </a>
        <a class="dashboard-kpi-card kpi-stock" href="<?= e(route_url('inventory')) ?>">
            <span class="kpi-icon"><i class="bi bi-capsule-pill"></i></span>
            <span class="kpi-label">ยาใกล้หมด</span>
            <strong><?= e((string) $lowStockCount) ?></strong>
            <span class="kpi-status"><?= $expiryCount > 0 ? 'ใกล้หมดอายุ ' . e((string) $expiryCount) . ' lot' : 'คลังยาพร้อมใช้งาน' ?></span>
        </a>
    </section>

    <section class="dashboard-section dashboard-task-board">
        <div class="dashboard-section-header">
            <div>
                <span class="dashboard-eyebrow">Priority Task Board</span>
                <h2><i class="bi bi-lightning-charge-fill"></i> งานที่ต้องทำตอนนี้</h2>
            </div>
            <a href="<?= e(route_url('queue')) ?>" class="btn btn-sm btn-primary">ไปหน้าคิววันนี้</a>
        </div>
        <div class="task-board-grid">
            <?php
            $taskGroups = [
                'IN_SERVICE' => ['label' => 'คิวกำลังตรวจ', 'icon' => 'bi-heart-pulse-fill', 'class' => 'task-blue'],
                'WAITING_PAYMENT' => ['label' => 'คิวรอชำระ', 'icon' => 'bi-credit-card-fill', 'class' => 'task-purple'],
                'WAITING' => ['label' => 'รอรับบริการ', 'icon' => 'bi-hourglass-split', 'class' => 'task-orange'],
            ];
            foreach ($taskGroups as $status => $taskMeta):
                $items = array_values(array_filter($workQueues, static fn(array $queueRow): bool => $queueRow['status'] === $status));
            ?>
                <article class="task-column <?= e($taskMeta['class']) ?>">
                    <header>
                        <span><i class="bi <?= e($taskMeta['icon']) ?>"></i> <?= e($taskMeta['label']) ?></span>
                        <strong><?= e((string) count($items)) ?></strong>
                    </header>
                    <div class="task-list">
                        <?php foreach (array_slice($items, 0, 4) as $queueRow): ?>
                            <a class="task-row" href="<?= e($queueRow['status'] === 'WAITING_PAYMENT' && $canAccessPayments ? route_url('payments') : route_url('queue-exam', ['id' => $queueRow['visit_id']])) ?>">
                                <span class="task-queue">Q<?= e((string) $queueRow['queue_no']) ?></span>
                                <span>
                                    <strong><?= e($queueRow['first_name'] . ' ' . $queueRow['last_name']) ?></strong>
                                    <small><?= e($queueRow['chief_complaint'] ?: $queueRow['hn']) ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <div class="dashboard-empty-mini">ไม่มีรายการ</div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <article class="task-column task-green">
                <header>
                    <span><i class="bi bi-calendar-heart-fill"></i> นัดติดตามวันนี้</span>
                    <strong><?= e((string) $followupToday) ?></strong>
                </header>
                <div class="task-list">
                    <?php foreach (array_slice($todayAppointments, 0, 4) as $appointment): ?>
                        <a class="task-row" href="<?= e(route_url('appointments')) ?>">
                            <span class="task-queue"><i class="bi bi-calendar2"></i></span>
                            <span>
                                <strong><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong>
                                <small><?= e($appointment['purpose'] ?: 'นัดติดตาม') ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$todayAppointments): ?>
                        <div class="dashboard-empty-mini">ไม่มีนัดวันนี้</div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>

    <div class="dashboard-two-column">
        <section class="dashboard-section">
            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-eyebrow">Follow Up</span>
                    <h2><i class="bi bi-calendar-check-fill"></i> นัดติดตามวันนี้</h2>
                </div>
                <a href="<?= e(route_url('appointments')) ?>" class="btn btn-sm btn-outline-primary">ดูนัดหมาย</a>
            </div>
            <div class="followup-list">
                <?php foreach ($todayAppointments as $appointment): ?>
                    <a class="followup-row" href="<?= e(route_url('appointments')) ?>">
                        <span class="followup-avatar"><i class="bi bi-person-fill"></i></span>
                        <span>
                            <strong><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong>
                            <small><?= e($appointment['hn']) ?> · <?= e($appointment['purpose'] ?: 'นัดติดตาม') ?></small>
                        </span>
                        <em>วันนี้</em>
                    </a>
                <?php endforeach; ?>
                <?php if (!$todayAppointments): ?>
                    <div class="dashboard-empty-state">
                        <i class="bi bi-calendar2-check"></i>
                        <strong>ไม่มีนัดติดตามวันนี้</strong>
                        <span>งานติดตามว่าง สามารถโฟกัสคิวหน้างานได้เต็มที่</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section finance-card">
            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-eyebrow">Finance Today</span>
                    <h2><i class="bi bi-wallet2"></i> การเงินวันนี้</h2>
                </div>
                <a href="<?= e(route_url('payments')) ?>" class="btn btn-sm btn-outline-primary">เปิดการเงิน</a>
            </div>
            <div class="finance-total">
                <span>รายได้รวม</span>
                <strong><?= format_money($dailyClose['total_amount'] ?? 0) ?></strong>
                <small>เป้าหมาย <?= format_money($revenueTarget) ?> · <?= e((string) $revenueProgress) ?>%</small>
                <div class="finance-progress" aria-label="ความคืบหน้าเป้าหมายรายได้">
                    <span style="width: <?= e((string) $revenueProgress) ?>%"></span>
                </div>
                <small>ส่วนลด <?= format_money($dailyClose['discount_amount'] ?? 0) ?> · <?= e((string) ($dailyClose['receipt_count'] ?? 0)) ?> ใบเสร็จ</small>
            </div>
            <div class="finance-breakdown">
                <div><span>เงินสด</span><strong><?= format_money($cashToday) ?></strong></div>
                <div><span>QR Payment</span><strong><?= format_money($qrToday) ?></strong></div>
                <div><span>โอน</span><strong><?= format_money($transferToday) ?></strong></div>
                <div><span>ส่วนลด</span><strong><?= format_money($dailyClose['discount_amount'] ?? 0) ?></strong></div>
            </div>
        </section>
    </div>

    <div class="dashboard-analytics-grid" id="dashboardAnalytics">
        <section class="dashboard-section">
            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-eyebrow">Revenue Trend</span>
                    <h2><i class="bi bi-graph-up-arrow"></i> รายได้ย้อนหลัง</h2>
                </div>
            </div>
            <div class="line-chart" aria-label="กราฟรายได้ย้อนหลัง">
                <?php foreach ($monthlyRevenue as $row): ?>
                    <?php $height = $maxRevenue > 0 ? max(8, ((float) $row['total_amount'] / $maxRevenue) * 100) : 8; ?>
                    <div class="line-chart-point">
                        <span style="height: <?= e((string) round($height, 2)) ?>%"></span>
                        <small><?= e($row['month_label']) ?></small>
                        <em><?= format_money($row['total_amount']) ?></em>
                    </div>
                <?php endforeach; ?>
                <?php if (!$monthlyRevenue): ?>
                    <div class="dashboard-empty-state">
                        <i class="bi bi-graph-up"></i>
                        <strong>ยังไม่มีข้อมูลรายได้</strong>
                        <span>เมื่อมีการรับชำระ ระบบจะแสดงแนวโน้มที่นี่</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-eyebrow">Popular Services</span>
                    <h2><i class="bi bi-bar-chart-fill"></i> บริการยอดนิยม</h2>
                </div>
            </div>
            <div class="bar-chart-list">
                <?php foreach ($popularServices as $service): ?>
                    <?php $width = $maxServiceQty > 0 ? max(6, ((float) $service['total_qty'] / $maxServiceQty) * 100) : 6; ?>
                    <div class="bar-chart-row">
                        <div class="bar-chart-meta">
                            <strong><?= e($service['service_name']) ?></strong>
                            <span><?= e((string) $service['total_qty']) ?> ครั้ง · <?= format_money($service['total_income']) ?></span>
                        </div>
                        <div class="bar-track"><span style="width: <?= e((string) round($width, 2)) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$popularServices): ?>
                    <div class="dashboard-empty-state">
                        <i class="bi bi-bar-chart"></i>
                        <strong>ยังไม่มีข้อมูลบริการ</strong>
                        <span>เมื่อมีการบันทึกบริการ ระบบจะแสดงอันดับยอดนิยม</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="dashboard-section">
        <div class="dashboard-section-header">
            <div>
                <span class="dashboard-eyebrow">Inventory Watch</span>
                <h2><i class="bi bi-capsule-pill"></i> คลังยา</h2>
            </div>
            <a href="<?= e(route_url('inventory')) ?>" class="btn btn-sm btn-outline-primary">เปิดคลังยา</a>
        </div>
        <div class="inventory-watch-grid">
            <div class="inventory-watch-card">
                <h3><i class="bi bi-exclamation-triangle-fill"></i> ยาใกล้หมด</h3>
                <?php foreach ($lowStocks as $stock): ?>
                    <div class="inventory-row">
                        <span><?= e($stock['item_name']) ?></span>
                        <strong><?= format_money($stock['qty_balance']) ?> <?= e($stock['unit_name']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$lowStocks): ?>
                    <div class="dashboard-empty-mini">
                        <i class="bi bi-capsule-pill"></i>
                        <strong>ไม่มีรายการยาใกล้หมด</strong>
                        <span>คลังยาพร้อมใช้งาน</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="inventory-watch-card">
                <h3><i class="bi bi-clock-history"></i> ยาใกล้หมดอายุ</h3>
                <?php foreach ($expiryAlerts as $alert): ?>
                    <div class="inventory-row">
                        <span><?= e($alert['item_name']) ?> <small>Lot <?= e($alert['lot_no']) ?></small></span>
                        <strong><?= thai_date_only($alert['expiry_date']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$expiryAlerts): ?>
                    <div class="dashboard-empty-mini">
                        <i class="bi bi-check-circle"></i>
                        <strong>ไม่มี lot ใกล้หมดอายุ</strong>
                        <span>รายการยาปลอดภัยสำหรับวันนี้</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-section system-health-section">
        <div class="dashboard-section-header">
            <div>
                <span class="dashboard-eyebrow">System Health</span>
                <h2><i class="bi bi-shield-check"></i> ความพร้อมระบบ</h2>
            </div>
            <a href="<?= e(route_url('production')) ?>" class="btn btn-sm btn-outline-primary">ตรวจระบบ</a>
        </div>
        <div class="system-health-grid">
            <div class="system-health-card">
                <span><i class="bi bi-database-check"></i> Backup ล่าสุด</span>
                <strong><?= ($backupFile['is_today'] ?? false) ? 'สำรองวันนี้แล้ว' : 'ยังไม่พบ Backup วันนี้' ?></strong>
                <small><?= $backupFile ? e(thai_date($backupFile['generated_at'])) : 'ยังไม่มีประวัติ backup' ?></small>
            </div>
            <div class="system-health-card">
                <span><i class="bi bi-receipt"></i> ใบเสร็จวันนี้</span>
                <strong><?= e((string) ($dailyClose['receipt_count'] ?? 0)) ?> ใบ</strong>
                <small>ยอดรับชำระ <?= format_money($dailyClose['total_amount'] ?? 0) ?></small>
            </div>
            <div class="system-health-card">
                <span><i class="bi bi-list-check"></i> งานค้าง</span>
                <strong><?= e((string) $openWorkCount) ?> รายการ</strong>
                <small><?= $openWorkCount > 0 ? 'ควรเคลียร์ก่อนปิดวัน' : 'พร้อมปิดวันได้' ?></small>
            </div>
            <?php if ($canDownloadBackup): ?>
                <a href="<?= e(route_url('backup')) ?>" class="system-health-action">
                    <i class="bi bi-download"></i>
                    <span>สำรองข้อมูลตอนนี้</span>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
