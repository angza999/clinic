<?php $clinicName = (string) system_setting('clinic_name', config('app.name')); ?>

<div class="row justify-content-center receipt-sheet-wrap">
    <div class="col-xl-10">
        <div class="card border-0 shadow-sm receipt-sheet">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                    <div>
                        <div class="small text-uppercase text-muted">Printable Daily Report</div>
                        <h1 class="h3 mb-1"><?= e($clinicName) ?></h1>
                        <div class="text-muted">รายงานประจำวัน วันที่ <?= e(thai_date_only($dailyDate)) ?></div>
                    </div>
                    <div class="no-print d-flex gap-2">
                        <a href="<?= e(route_url('reports', ['date' => $dailyDate])) ?>" class="btn btn-outline-secondary">กลับหน้ารายงาน</a>
                        <button class="btn btn-primary" onclick="window.print()">พิมพ์รายงาน</button>
                    </div>
                </div>

                <div class="report-summary-grid mb-4">
                    <div class="report-summary-card"><span>ผู้รับบริการ</span><strong><?= e((string) ($daily['summary']['visit_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>รอรับบริการ</span><strong><?= e((string) ($daily['summary']['waiting_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>กำลังตรวจ</span><strong><?= e((string) ($daily['summary']['in_service_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>รอชำระเงิน</span><strong><?= e((string) ($daily['summary']['waiting_payment_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>เสร็จสิ้น</span><strong><?= e((string) ($daily['summary']['completed_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card report-summary-card-accent"><span>รายได้รวม</span><strong><?= format_money($daily['summary']['revenue_total'] ?? 0) ?></strong></div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table align-middle receipt-table">
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
                        <?php foreach ($daily['visits'] as $visit): ?>
                            <?php $meta = queue_status_meta((string) ($visit['status'] ?? 'WAITING')); ?>
                            <tr>
                                <td><?= e(date('H:i', strtotime((string) $visit['visit_datetime']))) ?></td>
                                <td>
                                    <div class="fw-semibold">คิว <?= e((string) ($visit['queue_no'] ?? '-')) ?></div>
                                    <div class="small text-muted"><?= e($visit['hn']) ?> / <?= e($visit['visit_no']) ?></div>
                                </td>
                                <td><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></td>
                                <td><?= e($visit['chief_complaint'] ?: '-') ?></td>
                                <td><?= e($meta['label']) ?></td>
                                <td class="text-end"><?= format_money($visit['total_amount'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$daily['visits']): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีข้อมูลผู้รับบริการในวันที่เลือก</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="fw-semibold mb-3">วิธีชำระเงิน</div>
                            <div class="workflow-list">
                                <?php foreach ($daily['payment_methods'] as $paymentMethod): ?>
                                    <div class="workflow-list-item">
                                        <div>
                                            <div class="fw-semibold"><?= e($paymentMethod['payment_method']) ?></div>
                                            <div class="small text-muted">จำนวน <?= e((string) $paymentMethod['payment_count']) ?> รายการ</div>
                                        </div>
                                        <div class="fw-semibold"><?= format_money($paymentMethod['total_amount']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$daily['payment_methods']): ?>
                                    <div class="queue-empty-state">ยังไม่มีข้อมูลการชำระเงินในวันที่เลือก</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="fw-semibold mb-3">บริการที่ใช้บ่อย</div>
                            <div class="workflow-list">
                                <?php foreach ($daily['popular_services'] as $service): ?>
                                    <div class="workflow-list-item">
                                        <div>
                                            <div class="fw-semibold"><?= e($service['service_name']) ?></div>
                                            <div class="small text-muted">ใช้ <?= format_money($service['total_qty']) ?> ครั้ง</div>
                                        </div>
                                        <div class="fw-semibold"><?= format_money($service['total_income']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$daily['popular_services']): ?>
                                    <div class="queue-empty-state">ยังไม่มีข้อมูลบริการในวันที่เลือก</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
