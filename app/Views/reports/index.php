<?php

$normalizedDaily = [
    'summary' => $daily['summary'] ?? [],
    'visits' => $daily['visits'] ?? [],
    'popular_services' => $daily['popular_services'] ?? [],
    'payment_methods' => $daily['payment_methods'] ?? [],
];

$normalizedMonthly = [
    'summary' => $monthly['summary'] ?? [],
    'daily_revenue' => $monthly['daily_revenue'] ?? [],
    'popular_services' => $monthly['popular_services'] ?? [],
    'payment_methods' => $monthly['payment_methods'] ?? [],
    'recent_visits' => $monthly['recent_visits'] ?? [],
    'start_date' => $monthly['start_date'] ?? null,
    'end_date' => $monthly['end_date'] ?? null,
    'month_label' => $monthly['month_label'] ?? date('m/Y'),
];
?>

<div class="d-grid gap-4">
    <section class="page-hero-card">
        <div class="page-hero-layout">
            <div>
                <div class="page-hero-eyebrow">รายงานและสำรองข้อมูล</div>
                <h1 class="page-hero-title">ดูภาพรวมรายรับ ผู้รับบริการ และสำรองข้อมูลจากหน้าจอเดียว</h1>
                <p class="page-hero-text">เลือกช่วงวันที่ที่ต้องการ ดูสรุปรายวันและรายเดือน พิมพ์รายงาน หรือส่งออกข้อมูลที่ใช้บ่อยได้ทันที</p>
            </div>
            <div class="report-summary-grid">
                <div class="report-summary-card">
                    <span>ผู้รับบริการวันนี้</span>
                    <strong><?= e((string) ($normalizedDaily['summary']['visit_count'] ?? 0)) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>รายได้วันนี้</span>
                    <strong><?= format_money($normalizedDaily['summary']['revenue_total'] ?? 0) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>ผู้รับบริการเดือนนี้</span>
                    <strong><?= e((string) ($normalizedMonthly['summary']['visit_count'] ?? 0)) ?></strong>
                </div>
                <div class="report-summary-card report-summary-card-accent">
                    <span>รายได้เดือนนี้</span>
                    <strong><?= format_money($normalizedMonthly['summary']['revenue_total'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รายงานประจำวัน</h2>
                    <div class="small text-muted">สรุปจำนวนผู้รับบริการ สถานะคิว รายได้ และบริการที่ใช้ในแต่ละวัน</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="get" action="<?= e(route_url('reports')) ?>" class="row g-3">
                        <input type="hidden" name="month" value="<?= e($monthValue) ?>">
                        <div class="col-12">
                            <label class="form-label">เลือกวันที่</label>
                            <input type="date" name="date" class="form-control" value="<?= e($dailyDate) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">แสดงรายงานประจำวัน</button>
                            <a href="<?= e(route_url('report-print', ['type' => 'daily', 'date' => $dailyDate])) ?>" target="_blank" class="btn btn-outline-primary">พิมพ์รายงานวัน</a>
                        </div>
                    </form>

                    <div class="report-summary-grid mt-4">
                        <div class="report-summary-card"><span>ผู้รับบริการ</span><strong><?= e((string) ($normalizedDaily['summary']['visit_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>รอรับบริการ</span><strong><?= e((string) ($normalizedDaily['summary']['waiting_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>กำลังตรวจ</span><strong><?= e((string) ($normalizedDaily['summary']['in_service_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>รอชำระเงิน</span><strong><?= e((string) ($normalizedDaily['summary']['waiting_payment_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>เสร็จสิ้น</span><strong><?= e((string) ($normalizedDaily['summary']['completed_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card report-summary-card-accent"><span>รายได้รวม</span><strong><?= format_money($normalizedDaily['summary']['revenue_total'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รายงานประจำเดือน</h2>
                    <div class="small text-muted">ดูภาพรวมรายได้ รายวันในเดือน และบริการยอดนิยมตลอดช่วงที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="get" action="<?= e(route_url('reports')) ?>" class="row g-3">
                        <input type="hidden" name="date" value="<?= e($dailyDate) ?>">
                        <div class="col-12">
                            <label class="form-label">เลือกเดือน</label>
                            <input type="month" name="month" class="form-control" value="<?= e($monthValue) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">แสดงรายงานประจำเดือน</button>
                            <a href="<?= e(route_url('report-print', ['type' => 'monthly', 'month' => $monthValue])) ?>" target="_blank" class="btn btn-outline-primary">พิมพ์รายงานเดือน</a>
                        </div>
                    </form>

                    <div class="report-summary-grid mt-4">
                        <div class="report-summary-card"><span>ผู้รับบริการ</span><strong><?= e((string) ($normalizedMonthly['summary']['visit_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>ชำระแล้ว</span><strong><?= e((string) ($normalizedMonthly['summary']['paid_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card"><span>นัดติดตาม</span><strong><?= e((string) ($normalizedMonthly['summary']['appointment_count'] ?? 0)) ?></strong></div>
                        <div class="report-summary-card report-summary-card-accent"><span>รายได้รวม</span><strong><?= format_money($normalizedMonthly['summary']['revenue_total'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">Export และ Backup</h2>
                    <div class="small text-muted">ดาวน์โหลดข้อมูลที่ใช้บ่อยและสำรองฐานข้อมูลจากหน้าเดียว</div>
                </div>
                <div class="card-body px-4 pb-4 d-grid gap-2 report-export-list">
                    <a href="<?= e(route_url('export', ['type' => 'patients'])) ?>" class="btn btn-outline-secondary text-start">Export รายชื่อผู้รับบริการ</a>
                    <a href="<?= e(route_url('export', ['type' => 'visits_today'])) ?>" class="btn btn-outline-secondary text-start">Export ผู้รับบริการวันนี้</a>
                    <a href="<?= e(route_url('export', ['type' => 'revenue_month'])) ?>" class="btn btn-outline-secondary text-start">Export รายได้เดือนนี้</a>
                    <a href="<?= e(route_url('export', ['type' => 'inventory_alerts'])) ?>" class="btn btn-outline-secondary text-start">Export แจ้งเตือนคลังยา</a>
                    <hr>
                    <a href="<?= e(route_url('backup')) ?>" class="btn btn-primary">สำรองฐานข้อมูลตอนนี้</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">ผู้รับบริการประจำวัน</h2>
                    <div class="small text-muted">ตรวจสอบคิว อาการสำคัญ และยอดชำระของวันที่เลือกได้ในตารางเดียว</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>เวลา</th>
                                <th>คิว/HN/VN</th>
                                <th>ผู้รับบริการ</th>
                                <th>อาการสำคัญ</th>
                                <th>สถานะ</th>
                                <th class="text-end">ยอดชำระ</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($normalizedDaily['visits'] as $visit): ?>
                                <?php $meta = queue_status_meta((string) ($visit['status'] ?? 'WAITING')); ?>
                                <tr>
                                    <td><?= e(date('H:i', strtotime((string) $visit['visit_datetime']))) ?></td>
                                    <td>
                                        <div class="fw-semibold">คิว <?= e((string) ($visit['queue_no'] ?? '-')) ?></div>
                                        <div class="small text-muted"><?= e($visit['hn']) ?> / <?= e($visit['visit_no']) ?></div>
                                    </td>
                                    <td><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></td>
                                    <td><?= e($visit['chief_complaint'] ?: '-') ?></td>
                                    <td><span class="badge text-bg-light"><?= e($meta['label']) ?></span></td>
                                    <td class="text-end"><?= format_money($visit['total_amount'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$normalizedDaily['visits']): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีข้อมูลผู้รับบริการในวันที่เลือก</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card section-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">บริการยอดนิยมวันนี้</h2>
                    <div class="small text-muted">ดูว่าบริการใดถูกใช้งานบ่อยที่สุดในวันที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list">
                        <?php foreach ($normalizedDaily['popular_services'] as $service): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($service['service_name']) ?></div>
                                    <div class="small text-muted">ใช้ <?= format_money($service['total_qty']) ?> ครั้ง</div>
                                </div>
                                <div class="fw-semibold"><?= format_money($service['total_income']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$normalizedDaily['popular_services']): ?>
                            <div class="queue-empty-state">ยังไม่มีข้อมูลบริการสำหรับวันที่เลือก</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">วิธีชำระเงินวันนี้</h2>
                    <div class="small text-muted">สรุปยอดรับชำระตามช่องทางการชำระเงินของวันที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list">
                        <?php foreach ($normalizedDaily['payment_methods'] as $paymentMethod): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($paymentMethod['payment_method']) ?></div>
                                    <div class="small text-muted">จำนวน <?= e((string) $paymentMethod['payment_count']) ?> รายการ</div>
                                </div>
                                <div class="fw-semibold"><?= format_money($paymentMethod['total_amount']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$normalizedDaily['payment_methods']): ?>
                            <div class="queue-empty-state">ยังไม่มีข้อมูลการชำระเงินในวันที่เลือก</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รายได้รายวันของเดือน <?= e($normalizedMonthly['month_label']) ?></h2>
                    <div class="small text-muted">ใช้ดูกระจายรายได้ในแต่ละวันของเดือนที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>วันที่</th>
                                <th class="text-end">จำนวนใบเสร็จ</th>
                                <th class="text-end">รายได้รวม</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($normalizedMonthly['daily_revenue'] as $row): ?>
                                <tr>
                                    <td><?= e(thai_date_only($row['paid_date'])) ?></td>
                                    <td class="text-end"><?= e((string) $row['receipt_count']) ?></td>
                                    <td class="text-end"><?= format_money($row['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$normalizedMonthly['daily_revenue']): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">ยังไม่มีข้อมูลรายได้ในเดือนที่เลือก</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card section-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">บริการยอดนิยมประจำเดือน</h2>
                    <div class="small text-muted">ดูบริการที่ถูกใช้งานบ่อยที่สุดตลอดเดือนที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list">
                        <?php foreach ($normalizedMonthly['popular_services'] as $service): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($service['service_name']) ?></div>
                                    <div class="small text-muted">ใช้ <?= format_money($service['total_qty']) ?> ครั้ง</div>
                                </div>
                                <div class="fw-semibold"><?= format_money($service['total_income']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$normalizedMonthly['popular_services']): ?>
                            <div class="queue-empty-state">ยังไม่มีข้อมูลบริการในเดือนที่เลือก</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">วิธีชำระเงินประจำเดือน</h2>
                    <div class="small text-muted">สรุปยอดรับชำระตามวิธีชำระเงินในเดือนที่เลือก</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="workflow-list">
                        <?php foreach ($normalizedMonthly['payment_methods'] as $paymentMethod): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($paymentMethod['payment_method']) ?></div>
                                    <div class="small text-muted">จำนวน <?= e((string) $paymentMethod['payment_count']) ?> รายการ</div>
                                </div>
                                <div class="fw-semibold"><?= format_money($paymentMethod['total_amount']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$normalizedMonthly['payment_methods']): ?>
                            <div class="queue-empty-state">ยังไม่มีข้อมูลการชำระเงินในเดือนที่เลือก</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
