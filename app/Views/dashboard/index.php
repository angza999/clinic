<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="queue-stat queue-stat-waiting">
            <div class="queue-stat-label">รอรับบริการ</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['waiting_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="queue-stat queue-stat-service">
            <div class="queue-stat-label">กำลังตรวจ</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['in_service_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="queue-stat queue-stat-payment">
            <div class="queue-stat-label">รอชำระเงิน</div>
            <div class="queue-stat-value"><?= e((string) ($todayStats['waiting_payment_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="queue-stat queue-stat-complete">
            <div class="queue-stat-label">รายได้วันนี้</div>
            <div class="queue-stat-value"><?= format_money($todayStats['revenue_today'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card section-card h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 mb-1">งานที่ต้องทำตอนนี้</h2>
                    <div class="small text-muted">ใช้เป็นภาพรวมหน้างานก่อนเข้าหน้าคิวหรือการเงิน</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= e(route_url('queue')) ?>" class="btn btn-primary btn-sm">ไปหน้าคิว</a>
                    <a href="<?= e(route_url('payments')) ?>" class="btn btn-outline-primary btn-sm">ไปหน้าการเงิน</a>
                </div>
            </div>
            <div class="card-body px-4">
                <div class="workflow-list">
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
                                <a href="<?= e(route_url('visit-edit', ['id' => $queueRow['visit_id']])) ?>" class="btn btn-sm btn-outline-primary">เปิดแฟ้ม</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$workQueues): ?>
                        <div class="queue-empty-state">ยังไม่มีงานค้างในคิววันนี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card section-card h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">นัดติดตามวันนี้</h2>
                <div class="small text-muted">ช่วยเตือนเคสที่ต้องติดตามหรือมาซ้ำในวันนี้</div>
            </div>
            <div class="card-body px-4">
                <div class="workflow-list">
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
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">บริการยอดนิยมเดือนนี้</h2>
                <div class="small text-muted">ใช้ดูรายการที่ควรวางเป็นปุ่มลัดในห้องตรวจ</div>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table align-middle">
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
        </div>

        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">รายได้ย้อนหลัง 6 เดือน</h2>
                <div class="small text-muted">ใช้ดูแนวโน้มรายรับแบบย่อ</div>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table align-middle">
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
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">แจ้งเตือนคลังยา</h2>
                <div class="small text-muted">เน้นรายการที่ต้องรีบสั่งซื้อหรือใกล้หมดอายุ</div>
            </div>
            <div class="card-body px-4">
                <h3 class="h6">Stock ต่ำ</h3>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">
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
                    <table class="table table-sm align-middle">
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
        </div>

        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">งานสรุปวันนี้</h2>
                <div class="small text-muted">ดูตัวเลขภาพรวมก่อนปิดวัน</div>
            </div>
            <div class="card-body px-4">
                <div class="workflow-list">
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
        </div>
    </div>
</div>
