<?php
$statusMeta = queue_status_meta($visit['status'] ?? 'WAITING');
$fullName = trim((string) (($visit['first_name'] ?? '') . ' ' . ($visit['last_name'] ?? '')));
$birthDate = !empty($visit['birth_date']) ? new DateTimeImmutable((string) $visit['birth_date']) : null;
$ageText = $birthDate ? (string) $birthDate->diff(new DateTimeImmutable('today'))->y . ' ปี' : '-';
$drugAllergyText = trim((string) ($visit['drug_allergy'] ?? ''));
$chronicText = trim((string) ($visit['underlying_disease'] ?? ''));
$hasDrugAllergy = $drugAllergyText !== '' && $drugAllergyText !== '-';
$hasChronic = $chronicText !== '' && $chronicText !== '-';
$clinicalFacts = [
    'CC' => (string) ($visit['chief_complaint'] ?? ''),
    'PI' => (string) ($visit['present_illness'] ?? ''),
    'PE' => (string) ($visit['physical_exam'] ?? ''),
    'Dx' => (string) ($visit['diagnosis'] ?? ''),
    'Nursing note' => (string) ($visit['nursing_note'] ?? ''),
    'Advice' => (string) ($visit['advice'] ?? ''),
];
$vitalPairs = [
    'BP' => ($visit['bp_systolic'] || $visit['bp_diastolic']) ? trim((string) ($visit['bp_systolic'] ?? '-')) . '/' . trim((string) ($visit['bp_diastolic'] ?? '-')) : '-',
    'Temp' => $visit['temp_c'] !== null ? (string) $visit['temp_c'] . ' °C' : '-',
    'Pulse' => $visit['pulse_rate'] !== null ? (string) $visit['pulse_rate'] . '/min' : '-',
    'Resp' => $visit['resp_rate'] !== null ? (string) $visit['resp_rate'] . '/min' : '-',
    'SpO2' => $visit['spo2'] !== null ? (string) $visit['spo2'] . '%' : '-',
    'Weight' => $visit['weight_kg'] !== null ? (string) $visit['weight_kg'] . ' kg' : '-',
];
?>

<div class="visit-review-page">
    <section class="visit-review-hero">
        <div>
            <div class="visit-review-eyebrow">Case History</div>
            <h1>ประวัติเคส</h1>
            <p>หน้านี้เป็นพื้นที่อ่านทวนเคส ประวัติ การเงิน และ audit เท่านั้น งานรักษาหลักให้ทำผ่าน Smart Exam</p>
        </div>
        <div class="visit-review-actions">
            <span class="visit-review-status bg-<?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
            <?php if (in_array($visit['status'] ?? '', ['WAITING', 'IN_SERVICE'], true)): ?>
                <a href="<?= e(route_url('queue-exam', ['id' => (int) $visit['id']])) ?>" class="btn btn-primary btn-sm">กลับไป Smart Exam</a>
            <?php endif; ?>
            <a href="<?= e(route_url('queue')) ?>" class="btn btn-outline-secondary btn-sm">กลับคิววันนี้</a>
        </div>
    </section>

    <section class="visit-review-section visit-review-patient">
        <div class="visit-review-section-head">
            <div>
                <div class="visit-review-eyebrow">SECTION 1</div>
                <h2>Patient Summary</h2>
            </div>
            <?php if ($isAdminReview): ?>
                <span class="visit-review-admin-note">Admin case history mode</span>
            <?php endif; ?>
        </div>

        <div class="visit-review-patient-grid">
            <div class="visit-review-identity">
                <div class="visit-review-avatar"><i class="bi bi-person-badge"></i></div>
                <div>
                    <h3><?= e($fullName !== '' ? $fullName : '-') ?></h3>
                    <p>HN <?= e((string) ($visit['hn'] ?? '-')) ?> / VN <?= e((string) ($visit['visit_no'] ?? '-')) ?></p>
                    <span>คิว <?= e((string) ($visit['queue_no'] ?? '-')) ?> · <?= e(thai_date($visit['visit_datetime'] ?? null)) ?></span>
                </div>
            </div>

            <div class="visit-review-fact-grid">
                <div><span>อายุ</span><strong><?= e($ageText) ?></strong></div>
                <div><span>เพศ</span><strong><?= e((string) (($visit['gender'] ?? '') ?: '-')) ?></strong></div>
                <div><span>โทรศัพท์</span><strong><?= e((string) (($visit['phone'] ?? '') ?: '-')) ?></strong></div>
                <div><span>ยอดเคสนี้</span><strong><?= format_money($grandTotal) ?></strong></div>
            </div>
        </div>

        <div class="visit-review-safety-grid">
            <div class="<?= $hasDrugAllergy ? 'is-danger' : 'is-clear' ?>">
                <span>แพ้ยา</span>
                <strong><?= nl2br(e($hasDrugAllergy ? $drugAllergyText : 'ไม่พบประวัติแพ้ยา')) ?></strong>
            </div>
            <div class="<?= $hasChronic ? 'is-warning' : 'is-clear' ?>">
                <span>โรคประจำตัว</span>
                <strong><?= nl2br(e($hasChronic ? $chronicText : 'ไม่ระบุ')) ?></strong>
            </div>
        </div>

        <details class="visit-review-details" open>
            <summary>ข้อมูลเคสปัจจุบัน</summary>
            <div class="visit-review-clinical-grid">
                <?php foreach ($clinicalFacts as $label => $value): ?>
                    <div>
                        <span><?= e($label) ?></span>
                        <strong><?= nl2br(e(trim($value) !== '' ? $value : '-')) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="visit-review-vitals">
                <?php foreach ($vitalPairs as $label => $value): ?>
                    <div><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
                <?php endforeach; ?>
            </div>
        </details>
    </section>

    <div class="visit-review-grid">
        <section class="visit-review-section visit-review-timeline-section">
            <div class="visit-review-section-head">
                <div>
                    <div class="visit-review-eyebrow">SECTION 2</div>
                    <h2>Visit Timeline</h2>
                </div>
                <span><?= e((string) count($visitTimeline)) ?> visits</span>
            </div>

            <?php if ($visitTimeline): ?>
                <div class="visit-review-timeline">
                    <?php foreach ($visitTimeline as $timelineVisit): ?>
                        <?php $isCurrent = (int) $timelineVisit['id'] === (int) $visit['id']; ?>
                        <details class="visit-review-timeline-item<?= $isCurrent ? ' is-current' : '' ?>" <?= $isCurrent ? 'open' : '' ?>>
                            <summary>
                                <div>
                                    <strong><?= e(thai_date($timelineVisit['visit_datetime'] ?? null)) ?></strong>
                                    <span><?= e((string) (($timelineVisit['chief_complaint'] ?? '') ?: '-')) ?></span>
                                </div>
                                <div class="visit-review-timeline-meta">
                                    <?php if ($isCurrent): ?><em>เคสปัจจุบัน</em><?php endif; ?>
                                    <b><?= e((string) (($timelineVisit['diagnosis'] ?? '') ?: 'ยังไม่มี Dx')) ?></b>
                                    <small><?= format_money($timelineVisit['paid_total'] ?? 0) ?></small>
                                </div>
                            </summary>
                            <div class="visit-review-timeline-body">
                                <div>VN: <?= e((string) ($timelineVisit['visit_no'] ?? '-')) ?></div>
                                <div>คิว: <?= e((string) ($timelineVisit['queue_no'] ?? '-')) ?></div>
                                <div>ใบเสร็จ: <?= e((string) (($timelineVisit['receipt_no'] ?? '') ?: '-')) ?></div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="visit-review-empty">ยังไม่มีประวัติ visit</div>
            <?php endif; ?>
        </section>

        <section class="visit-review-section">
            <div class="visit-review-section-head">
                <div>
                    <div class="visit-review-eyebrow">SECTION 3</div>
                    <h2>Service History</h2>
                </div>
                <span><?= e((string) count($serviceHistory)) ?> rows</span>
            </div>
            <div class="visit-review-table-wrap">
                <table class="visit-review-table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>รายการบริการ</th>
                            <th class="text-end">ราคา</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceHistory as $serviceLine): ?>
                            <tr>
                                <td><?= e(thai_date_only($serviceLine['visit_datetime'] ?? null)) ?></td>
                                <td>
                                    <strong><?= e((string) $serviceLine['service_name']) ?></strong>
                                    <span>VN <?= e((string) $serviceLine['visit_no']) ?> · จำนวน <?= e((string) $serviceLine['qty']) ?></span>
                                </td>
                                <td class="text-end"><?= format_money($serviceLine['line_total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceHistory): ?>
                            <tr><td colspan="3" class="visit-review-empty-cell">ยังไม่มีประวัติบริการ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="visit-review-section">
            <div class="visit-review-section-head">
                <div>
                    <div class="visit-review-eyebrow">SECTION 4</div>
                    <h2>Drug History</h2>
                </div>
                <span><?= e((string) count($drugHistory)) ?> rows</span>
            </div>
            <div class="visit-review-table-wrap">
                <table class="visit-review-table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>รายการ</th>
                            <th class="text-end">จำนวน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drugHistory as $drugLine): ?>
                            <tr>
                                <td><?= e(thai_date_only($drugLine['visit_datetime'] ?? null)) ?></td>
                                <td>
                                    <strong><?= e((string) $drugLine['item_name']) ?></strong>
                                    <span>VN <?= e((string) $drugLine['visit_no']) ?><?= $drugLine['usage_note'] ? ' · ' . e((string) $drugLine['usage_note']) : '' ?></span>
                                </td>
                                <td class="text-end"><?= format_money($drugLine['qty'] ?? 0) ?> <?= e((string) ($drugLine['unit_name'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$drugHistory): ?>
                            <tr><td colspan="3" class="visit-review-empty-cell">ยังไม่มีประวัติยา/เวชภัณฑ์</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="visit-review-section">
            <div class="visit-review-section-head">
                <div>
                    <div class="visit-review-eyebrow">SECTION 5</div>
                    <h2>Payment History</h2>
                </div>
                <span><?= e((string) count($paymentHistory)) ?> receipts</span>
            </div>
            <div class="visit-review-table-wrap">
                <table class="visit-review-table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>ใบเสร็จ</th>
                            <th>ผู้บันทึก</th>
                            <th class="text-end">ยอดชำระ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $payment): ?>
                            <tr>
                                <td><?= e(thai_date($payment['paid_at'] ?? $payment['created_at'] ?? null)) ?></td>
                                <td>
                                    <?php if (!empty($payment['id'])): ?>
                                        <a href="<?= e(route_url('receipt', ['id' => (int) $payment['id'], 'source' => 'visit-review'])) ?>"><?= e((string) $payment['receipt_no']) ?></a>
                                    <?php else: ?>
                                        <?= e((string) (($payment['receipt_no'] ?? '') ?: '-')) ?>
                                    <?php endif; ?>
                                    <span><?= e((string) ($payment['payment_method'] ?? '-')) ?> · VN <?= e((string) ($payment['visit_no'] ?? '-')) ?></span>
                                </td>
                                <td><?= e((string) (($payment['cashier_name'] ?? '') ?: '-')) ?></td>
                                <td class="text-end"><?= format_money($payment['total_amount'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$paymentHistory): ?>
                            <tr><td colspan="4" class="visit-review-empty-cell">ยังไม่มีประวัติรับชำระ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="visit-review-section">
            <div class="visit-review-section-head">
                <div>
                    <div class="visit-review-eyebrow">SECTION 6</div>
                    <h2>Audit Log</h2>
                </div>
                <span><?= e((string) count($auditLogs)) ?> logs</span>
            </div>
            <div class="visit-review-audit-list">
                <?php foreach ($auditLogs as $audit): ?>
                    <div class="visit-review-audit-row">
                        <time><?= e(thai_date($audit['created_at'] ?? null)) ?></time>
                        <div>
                            <strong><?= e((string) ($audit['action'] ?? '-')) ?></strong>
                            <span><?= e((string) (($audit['actor_name'] ?? '') ?: 'System')) ?> · <?= e((string) (($audit['table_name'] ?? '') ?: '-')) ?> #<?= e((string) (($audit['record_id'] ?? '') ?: '-')) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$auditLogs): ?>
                    <div class="visit-review-empty">ยังไม่มี audit log สำหรับเคสนี้</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
