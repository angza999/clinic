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
    'patient_trend' => $monthly['patient_trend'] ?? [],
    'popular_services' => $monthly['popular_services'] ?? [],
    'payment_methods' => $monthly['payment_methods'] ?? [],
    'recent_visits' => $monthly['recent_visits'] ?? [],
    'start_date' => $monthly['start_date'] ?? null,
    'end_date' => $monthly['end_date'] ?? null,
    'month_label' => $monthly['month_label'] ?? date('m/Y'),
];

$backup = $backupStats ?? [];
$dailySummary = $normalizedDaily['summary'];
$dailyRevenue = (float) ($dailySummary['revenue_total'] ?? 0);
$dailyReceiptCount = array_sum(array_map(static fn(array $row): int => (int) ($row['payment_count'] ?? 0), $normalizedDaily['payment_methods']));
$dailyAverageReceipt = $dailyReceiptCount > 0 ? $dailyRevenue / $dailyReceiptCount : 0;
$monthlyRevenue = (float) ($normalizedMonthly['summary']['revenue_total'] ?? 0);
$reportCount = 8;

$maxRevenue = 0.0;
foreach ($normalizedMonthly['daily_revenue'] as $row) {
    $maxRevenue = max($maxRevenue, (float) ($row['total_amount'] ?? 0));
}

$maxPatients = 0;
foreach ($normalizedMonthly['patient_trend'] as $row) {
    $maxPatients = max($maxPatients, (int) ($row['visit_count'] ?? 0));
}

$maxServiceIncome = 0.0;
foreach ($normalizedMonthly['popular_services'] as $service) {
    $maxServiceIncome = max($maxServiceIncome, (float) ($service['total_income'] ?? 0));
}

$paymentTotal = 0.0;
foreach ($normalizedMonthly['payment_methods'] as $method) {
    $paymentTotal += (float) ($method['total_amount'] ?? 0);
}

$paymentColors = ['#0f766e', '#2563eb', '#7c3aed', '#f59e0b', '#64748b'];
$donutSegments = [];
$cursor = 0.0;
foreach ($normalizedMonthly['payment_methods'] as $index => $method) {
    $amount = (float) ($method['total_amount'] ?? 0);
    $percent = $paymentTotal > 0 ? ($amount / $paymentTotal) * 100 : 0;
    if ($percent <= 0) {
        continue;
    }
    $color = $paymentColors[$index % count($paymentColors)];
    $donutSegments[] = $color . ' ' . round($cursor, 2) . '% ' . round($cursor + $percent, 2) . '%';
    $cursor += $percent;
}
$donutStyle = $donutSegments ? 'conic-gradient(' . implode(', ', $donutSegments) . ')' : 'conic-gradient(#e2e8f0 0 100%)';

$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes) . ' B';
};
?>

<div class="report-center">
    <section class="report-hero">
        <div class="report-hero-main">
            <span class="report-eyebrow"><i class="bi bi-bar-chart-line-fill"></i> Report Center</span>
            <h1>ศูนย์วิเคราะห์ข้อมูลคลินิก</h1>
            <p>วิเคราะห์รายได้ ผู้รับบริการ และแนวโน้มการให้บริการย้อนหลัง พร้อม Export และสำรองข้อมูลโดยไม่ซ้ำบทบาทกับ Dashboard</p>
        </div>
        <div class="report-hero-metrics">
            <div>
                <span>วันที่ปัจจุบัน</span>
                <strong><?= e(thai_date_only(date('Y-m-d'))) ?></strong>
            </div>
            <div>
                <span>เดือนที่เลือก</span>
                <strong><?= e($normalizedMonthly['month_label']) ?></strong>
            </div>
            <div>
                <span>รายงานพร้อมใช้</span>
                <strong><?= e((string) $reportCount) ?></strong>
            </div>
            <div class="<?= ($backup['directory_ready'] ?? false) ? 'is-ok' : 'is-warning' ?>">
                <span>Database Status</span>
                <strong><?= ($backup['directory_ready'] ?? false) ? 'พร้อมใช้งาน' : 'ตรวจสอบพื้นที่' ?></strong>
            </div>
        </div>
    </section>

    <section class="report-control-panel">
        <form method="get" action="<?= e(route_url('reports')) ?>" class="report-filter-form">
            <input type="hidden" name="page" value="reports">
            <label>
                <span>วันที่รายงาน</span>
                <input type="date" name="date" value="<?= e($dailyDate) ?>">
            </label>
            <label>
                <span>เดือนวิเคราะห์</span>
                <input type="month" name="month" value="<?= e($monthValue) ?>">
            </label>
            <button type="submit" class="report-primary-btn">
                <i class="bi bi-funnel-fill"></i>
                แสดงรายงาน
            </button>
            <a class="report-secondary-btn" href="<?= e(route_url('report-print', ['type' => 'daily', 'date' => $dailyDate])) ?>" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i>
                PDF รายวัน
            </a>
            <a class="report-secondary-btn" href="<?= e(route_url('report-print', ['type' => 'monthly', 'month' => $monthValue])) ?>" target="_blank">
                <i class="bi bi-calendar3"></i>
                PDF รายเดือน
            </a>
        </form>
    </section>

    <section class="report-kpi-grid" aria-label="Daily analytics">
        <article class="report-kpi-card kpi-blue">
            <span class="kpi-icon"><i class="bi bi-people-fill"></i></span>
            <div>
                <span>ผู้รับบริการ</span>
                <strong><?= e((string) ($dailySummary['visit_count'] ?? 0)) ?></strong>
                <small>วันที่เลือก</small>
            </div>
        </article>
        <article class="report-kpi-card kpi-green">
            <span class="kpi-icon"><i class="bi bi-cash-stack"></i></span>
            <div>
                <span>รายได้รวม</span>
                <strong><?= format_money($dailyRevenue) ?></strong>
                <small>จากรายการที่ชำระแล้ว</small>
            </div>
        </article>
        <article class="report-kpi-card kpi-purple">
            <span class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></span>
            <div>
                <span>จำนวนใบเสร็จ</span>
                <strong><?= e((string) $dailyReceiptCount) ?></strong>
                <small>รวมทุกช่องทางชำระ</small>
            </div>
        </article>
        <article class="report-kpi-card kpi-orange">
            <span class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></span>
            <div>
                <span>เฉลี่ยต่อใบเสร็จ</span>
                <strong><?= format_money($dailyAverageReceipt) ?></strong>
                <small>ช่วยดูคุณภาพรายรับ</small>
            </div>
        </article>
    </section>

    <section class="report-section patient-report">
        <div class="report-section-header">
            <div>
                <span class="report-eyebrow"><i class="bi bi-clipboard2-pulse-fill"></i> Patient Report</span>
                <h2>ผู้รับบริการประจำวัน</h2>
                <p>ค้นหา ตรวจสอบสถานะ และส่งออกข้อมูลผู้รับบริการของวันที่เลือก</p>
            </div>
            <div class="report-actions">
                <a href="<?= e(route_url('export', ['type' => 'visits_today', 'date' => $dailyDate])) ?>" class="report-secondary-btn" title="Export ตารางผู้รับบริการของวันที่เลือกเป็น CSV">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Export Excel
                </a>
                <a href="<?= e(route_url('report-print', ['type' => 'daily', 'date' => $dailyDate])) ?>" target="_blank" class="report-secondary-btn" title="เปิดรายงานรายวันสำหรับพิมพ์หรือบันทึกเป็น PDF">
                    <i class="bi bi-filetype-pdf"></i>
                    Export PDF
                </a>
            </div>
        </div>
        <div class="report-table-toolbar">
            <label class="report-search">
                <i class="bi bi-search"></i>
                <input type="search" data-report-search placeholder="ค้นหาเวลา คิว HN ชื่อ อาการ หรือสถานะ">
            </label>
            <label class="report-sort">
                <span>Sort</span>
                <select data-report-sort>
                    <option value="time">เวลา</option>
                    <option value="name">ชื่อผู้รับบริการ</option>
                    <option value="status">สถานะ</option>
                    <option value="amount-desc">ยอดชำระมากไปน้อย</option>
                </select>
            </label>
        </div>
        <div class="report-table-shell">
            <table class="report-data-table" id="patientReportTable">
                <thead>
                <tr>
                    <th>เวลา</th>
                    <th>คิว</th>
                    <th>HN</th>
                    <th>ชื่อผู้รับบริการ</th>
                    <th>อาการสำคัญ</th>
                    <th>สถานะ</th>
                    <th class="text-end">ยอดชำระ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($normalizedDaily['visits'] as $visit): ?>
                    <?php
                    $meta = queue_status_meta((string) ($visit['status'] ?? 'WAITING'));
                    $patientName = trim((string) ($visit['first_name'] ?? '') . ' ' . (string) ($visit['last_name'] ?? ''));
                    $visitTime = (string) ($visit['visit_datetime'] ?? '');
                    $amount = (float) ($visit['total_amount'] ?? 0);
                    $search = implode(' ', [
                        date('H:i', strtotime($visitTime)),
                        $visit['queue_no'] ?? '',
                        $visit['hn'] ?? '',
                        $patientName,
                        $visit['chief_complaint'] ?? '',
                        $meta['label'] ?? '',
                    ]);
                    ?>
                    <tr
                        data-search="<?= e(mb_strtolower($search)) ?>"
                        data-time="<?= e($visitTime) ?>"
                        data-name="<?= e(mb_strtolower($patientName)) ?>"
                        data-status="<?= e((string) ($meta['label'] ?? '')) ?>"
                        data-amount="<?= e((string) $amount) ?>"
                    >
                        <td><?= e(date('H:i', strtotime($visitTime))) ?></td>
                        <td><span class="report-queue-pill">Q<?= e((string) ($visit['queue_no'] ?? '-')) ?></span></td>
                        <td><?= e((string) ($visit['hn'] ?? '-')) ?></td>
                        <td class="fw-semibold"><?= e($patientName ?: '-') ?></td>
                        <td><?= e((string) (($visit['chief_complaint'] ?? '') ?: '-')) ?></td>
                        <td><span class="report-status-badge"><?= e((string) ($meta['label'] ?? '-')) ?></span></td>
                        <td class="text-end fw-semibold"><?= format_money($amount) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$normalizedDaily['visits']): ?>
                    <tr>
                        <td colspan="7">
                            <div class="report-empty-state">
                                <i class="bi bi-clipboard2-check"></i>
                                <strong>ยังไม่มีผู้รับบริการในวันที่เลือก</strong>
                                <span>เมื่อมีการเปิดคิวหรือ Smart Exam รายการจะปรากฏในตารางนี้</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-analytics-grid">
        <article class="report-section">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-bar-chart-fill"></i> Revenue Analytics</span>
                    <h2>Revenue Trend</h2>
                </div>
                <span class="report-period-pill"><?= e($normalizedMonthly['month_label']) ?></span>
            </div>
            <div class="report-chart report-bar-chart">
                <?php foreach ($normalizedMonthly['daily_revenue'] as $row): ?>
                    <?php
                    $value = (float) ($row['total_amount'] ?? 0);
                    $height = $maxRevenue > 0 ? max(8, ($value / $maxRevenue) * 100) : 0;
                    ?>
                    <div class="chart-bar-item" title="<?= e(thai_date_only($row['paid_date'])) ?>: <?= e(format_money($value)) ?>">
                        <div class="chart-bar-track">
                            <span style="height: <?= e((string) $height) ?>%"></span>
                        </div>
                        <small><?= e(date('d', strtotime((string) $row['paid_date']))) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$normalizedMonthly['daily_revenue']): ?>
                    <div class="report-empty-state">
                        <i class="bi bi-graph-up"></i>
                        <strong>ยังไม่มีรายได้ในเดือนนี้</strong>
                        <span>ข้อมูลจะแสดงเมื่อมีการรับชำระเงิน</span>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="report-section">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-person-lines-fill"></i> Patient Analytics</span>
                    <h2>Patient Trend</h2>
                </div>
                <span class="report-period-pill"><?= e((string) ($normalizedMonthly['summary']['visit_count'] ?? 0)) ?> ราย</span>
            </div>
            <div class="report-chart report-line-like">
                <?php foreach ($normalizedMonthly['patient_trend'] as $row): ?>
                    <?php
                    $count = (int) ($row['visit_count'] ?? 0);
                    $height = $maxPatients > 0 ? max(10, ($count / $maxPatients) * 100) : 0;
                    ?>
                    <div class="patient-trend-dot" title="<?= e(thai_date_only($row['visit_date'])) ?>: <?= e((string) $count) ?> ราย">
                        <span style="height: <?= e((string) $height) ?>%"></span>
                        <small><?= e(date('d', strtotime((string) $row['visit_date']))) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$normalizedMonthly['patient_trend']): ?>
                    <div class="report-empty-state">
                        <i class="bi bi-people"></i>
                        <strong>ยังไม่มีข้อมูลผู้รับบริการ</strong>
                        <span>เลือกเดือนอื่นหรือเริ่มบันทึกเคสเพื่อดูแนวโน้ม</span>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="report-analytics-grid">
        <article class="report-section">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-trophy-fill"></i> Service Analytics</span>
                    <h2>Top 10 บริการยอดนิยม</h2>
                </div>
            </div>
            <div class="report-ranking-list">
                <?php foreach ($normalizedMonthly['popular_services'] as $index => $service): ?>
                    <?php
                    $income = (float) ($service['total_income'] ?? 0);
                    $width = $maxServiceIncome > 0 ? max(6, ($income / $maxServiceIncome) * 100) : 0;
                    ?>
                    <div class="ranking-row">
                        <span class="ranking-index"><?= e((string) ($index + 1)) ?></span>
                        <div class="ranking-main">
                            <div class="ranking-title">
                                <strong><?= e((string) ($service['service_name'] ?? '-')) ?></strong>
                                <span><?= e((string) ($service['total_qty'] ?? 0)) ?> ครั้ง · <?= format_money($income) ?></span>
                            </div>
                            <div class="ranking-bar"><span style="width: <?= e((string) $width) ?>%"></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$normalizedMonthly['popular_services']): ?>
                    <div class="report-empty-state">
                        <i class="bi bi-stars"></i>
                        <strong>ยังไม่มีบริการยอดนิยม</strong>
                        <span>ข้อมูลจะจัดอันดับหลังมีการบันทึกบริการใน Smart Exam</span>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="report-section">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-pie-chart-fill"></i> Payment Analytics</span>
                    <h2>ช่องทางชำระเงิน</h2>
                </div>
            </div>
            <div class="payment-analytics">
                <div class="payment-donut" style="--donut: <?= e($donutStyle) ?>">
                    <strong><?= format_money($paymentTotal) ?></strong>
                    <span>รวมเดือนนี้</span>
                </div>
                <div class="payment-method-list">
                    <?php foreach ($normalizedMonthly['payment_methods'] as $index => $method): ?>
                        <?php
                        $amount = (float) ($method['total_amount'] ?? 0);
                        $percent = $paymentTotal > 0 ? round(($amount / $paymentTotal) * 100) : 0;
                        ?>
                        <div>
                            <i style="background: <?= e($paymentColors[$index % count($paymentColors)]) ?>"></i>
                            <span><?= e((string) ($method['payment_method'] ?? '-')) ?></span>
                            <strong><?= e((string) $percent) ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$normalizedMonthly['payment_methods']): ?>
                        <div class="report-empty-state compact-empty">
                            <i class="bi bi-credit-card"></i>
                            <strong>ยังไม่มีข้อมูลการชำระเงิน</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </section>

    <section class="report-export-backup-grid">
        <article class="report-section">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-box-arrow-up-right"></i> Export Center</span>
                    <h2>ส่งออกข้อมูลใช้งานต่อ</h2>
                    <p>แยกจาก Backup เพื่อให้เข้าใจว่าเป็นการนำข้อมูลไปวิเคราะห์ ไม่ใช่การสำรองฐานข้อมูล</p>
                </div>
            </div>
            <div class="export-action-grid">
                <a href="<?= e(route_url('export', ['type' => 'patients'])) ?>" title="Export รายชื่อผู้รับบริการทั้งหมด">
                    <i class="bi bi-people-fill"></i>
                    <span>Export ผู้รับบริการ</span>
                </a>
                <a href="<?= e(route_url('export', ['type' => 'revenue_month', 'month' => $monthValue])) ?>" title="Export รายรับของเดือนที่เลือก">
                    <i class="bi bi-cash-coin"></i>
                    <span>Export รายได้</span>
                </a>
                <a href="<?= e(route_url('export', ['type' => 'inventory_alerts'])) ?>" title="Export รายการยาและเวชภัณฑ์พร้อมจำนวนคงเหลือ">
                    <i class="bi bi-capsule-pill"></i>
                    <span>Export รายการยา</span>
                </a>
                <a href="<?= e(route_url('export', ['type' => 'appointments', 'month' => $monthValue])) ?>" title="Export นัดหมายของเดือนที่เลือก">
                    <i class="bi bi-calendar-check"></i>
                    <span>Export นัดหมาย</span>
                </a>
                <a href="<?= e(route_url('export', ['type' => 'monthly_report', 'month' => $monthValue])) ?>" title="Export สรุปรายงานประจำเดือน">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Export รายงานประจำเดือน</span>
                </a>
            </div>
        </article>

        <article class="report-section backup-center">
            <div class="report-section-header compact">
                <div>
                    <span class="report-eyebrow"><i class="bi bi-database-fill-check"></i> Backup Center</span>
                    <h2>จัดการข้อมูลสำรอง</h2>
                    <p>ใช้สำหรับความปลอดภัยของข้อมูล ไม่ปะปนกับรายงานวิเคราะห์</p>
                </div>
                <span class="backup-status <?= ($backup['is_today'] ?? false) ? 'is-current' : 'is-stale' ?>">
                    <?= ($backup['is_today'] ?? false) ? 'สำรองแล้ววันนี้' : 'ยังไม่ได้สำรองวันนี้' ?>
                </span>
            </div>
            <div class="backup-summary-grid">
                <div>
                    <span>Backup ล่าสุด</span>
                    <strong><?= !empty($backup['latest_at']) ? e(thai_date((string) $backup['latest_at'])) : 'ยังไม่มีไฟล์' ?></strong>
                </div>
                <div>
                    <span>ขนาดไฟล์ล่าสุด</span>
                    <strong><?= $formatBytes((int) ($backup['latest_size_bytes'] ?? 0)) ?></strong>
                </div>
                <div>
                    <span>จำนวนไฟล์</span>
                    <strong><?= e((string) ($backup['file_count'] ?? 0)) ?> ไฟล์</strong>
                </div>
                <div>
                    <span>พื้นที่ใช้งาน</span>
                    <strong><?= $formatBytes((int) ($backup['total_size_bytes'] ?? 0)) ?></strong>
                </div>
            </div>
            <div class="backup-actions">
                <a href="<?= e(route_url('backup')) ?>" class="report-primary-btn">
                    <i class="bi bi-download"></i>
                    สำรองข้อมูลทันที
                </a>
                <button type="button" class="report-secondary-btn" disabled title="ไฟล์ backup ถูกเก็บใน storage/exports และการดาวน์โหลดล่าสุดจะเพิ่มเป็น route แยกในเฟสถัดไป">
                    <i class="bi bi-file-earmark-arrow-down"></i>
                    ดาวน์โหลดไฟล์
                </button>
                <button type="button" class="report-danger-btn" disabled title="Restore Database ต้องมีขั้นตอนยืนยันและสิทธิ์ Admin เฉพาะทางก่อนเปิดใช้งานจริง">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Restore Database
                </button>
            </div>
        </article>
    </section>
</div>
