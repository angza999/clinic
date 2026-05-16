<?php $clinicName = (string) system_setting('clinic_name', config('app.name')); ?>

<div class="row justify-content-center receipt-sheet-wrap">
    <div class="col-xl-10">
        <div class="card border-0 shadow-sm receipt-sheet">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                    <div>
                        <div class="small text-uppercase text-muted">Printable Monthly Report</div>
                        <h1 class="h3 mb-1"><?= e($clinicName) ?></h1>
                        <div class="text-muted">รายงานประจำเดือน <?= e($monthly['month_label']) ?> (<?= e(thai_date_only($monthly['start_date'])) ?> - <?= e(thai_date_only($monthly['end_date'])) ?>)</div>
                    </div>
                    <div class="no-print d-flex gap-2">
                        <a href="<?= e(route_url('reports', ['month' => $monthValue])) ?>" class="btn btn-outline-secondary">กลับหน้ารายงาน</a>
                        <button class="btn btn-primary" onclick="window.print()">พิมพ์รายงาน</button>
                    </div>
                </div>

                <div class="report-summary-grid mb-4">
                    <div class="report-summary-card"><span>ผู้รับบริการ</span><strong><?= e((string) ($monthly['summary']['visit_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>ชำระแล้ว</span><strong><?= e((string) ($monthly['summary']['paid_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card"><span>นัดติดตาม</span><strong><?= e((string) ($monthly['summary']['appointment_count'] ?? 0)) ?></strong></div>
                    <div class="report-summary-card report-summary-card-accent"><span>รายได้รวม</span><strong><?= format_money($monthly['summary']['revenue_total'] ?? 0) ?></strong></div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table align-middle receipt-table">
                                <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th class="text-end">จำนวนใบเสร็จ</th>
                                    <th class="text-end">รายได้รวม</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($monthly['daily_revenue'] as $row): ?>
                                    <tr>
                                        <td><?= e(thai_date_only($row['paid_date'])) ?></td>
                                        <td class="text-end"><?= e((string) $row['receipt_count']) ?></td>
                                        <td class="text-end"><?= format_money($row['total_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$monthly['daily_revenue']): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">ยังไม่มีข้อมูลรายได้ในเดือนที่เลือก</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="template-panel h-100">
                            <div class="fw-semibold mb-3">วิธีชำระเงิน</div>
                            <div class="workflow-list">
                                <?php foreach ($monthly['payment_methods'] as $paymentMethod): ?>
                                    <div class="workflow-list-item">
                                        <div>
                                            <div class="fw-semibold"><?= e($paymentMethod['payment_method']) ?></div>
                                            <div class="small text-muted">จำนวน <?= e((string) $paymentMethod['payment_count']) ?> รายการ</div>
                                        </div>
                                        <div class="fw-semibold"><?= format_money($paymentMethod['total_amount']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$monthly['payment_methods']): ?>
                                    <div class="queue-empty-state">ยังไม่มีข้อมูลการชำระเงินในเดือนที่เลือก</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="fw-semibold mb-3">บริการยอดนิยม</div>
                            <div class="workflow-list">
                                <?php foreach ($monthly['popular_services'] as $service): ?>
                                    <div class="workflow-list-item">
                                        <div>
                                            <div class="fw-semibold"><?= e($service['service_name']) ?></div>
                                            <div class="small text-muted">ใช้ <?= format_money($service['total_qty']) ?> ครั้ง</div>
                                        </div>
                                        <div class="fw-semibold"><?= format_money($service['total_income']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$monthly['popular_services']): ?>
                                    <div class="queue-empty-state">ยังไม่มีข้อมูลบริการในเดือนที่เลือก</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="fw-semibold mb-3">รายการผู้รับบริการล่าสุด</div>
                            <div class="workflow-list">
                                <?php foreach ($monthly['recent_visits'] as $visit): ?>
                                    <div class="workflow-list-item">
                                        <div>
                                            <div class="fw-semibold"><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></div>
                                            <div class="small text-muted"><?= e($visit['hn']) ?> / <?= e($visit['visit_no']) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted"><?= e(thai_date($visit['visit_datetime'])) ?></div>
                                            <div class="fw-semibold"><?= format_money($visit['total_amount'] ?? 0) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$monthly['recent_visits']): ?>
                                    <div class="queue-empty-state">ยังไม่มีข้อมูลผู้รับบริการในเดือนที่เลือก</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
