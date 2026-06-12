<?php
$visit = $activeVisit ?? [];
$services = $services ?? [];
$items = $items ?? [];
$quickPresets = $quickPresets ?? [];
$treatmentPresets = $treatmentPresets ?? [];
$frequentServices = $frequentServices ?? [];
$frequentItems = $frequentItems ?? [];
$expiryAlertDays = (int) ($expiryAlertDays ?? 30);
$patientSnapshot = $patientSnapshot ?? [];
$snapshotProfile = $patientSnapshot['profile'] ?? [];
$recentVisits = $patientSnapshot['recent_visits'] ?? [];
$latestVital = $patientSnapshot['latest_vital'] ?? null;
$upcomingAppointments = $patientSnapshot['upcoming_appointments'] ?? [];
$unpaidCount = (int) ($patientSnapshot['unpaid_count'] ?? 0);
$lastPaidAt = $patientSnapshot['last_paid_at'] ?? null;
$currentPresetKey = trim((string) ($_GET['preset'] ?? ''));
$currentPreset = $quickPresets[$currentPresetKey] ?? null;
$formatQty = static function (float $qty): string {
    $value = number_format($qty, 2, '.', '');
    return rtrim(rtrim($value, '0'), '.') ?: '0';
};
$serviceLines = $visit['service_lines'] ?? [];
$itemLines = $visit['item_lines'] ?? [];
$serviceCount = count($serviceLines);
$itemCount = count($itemLines);
$fullName = trim((string) (($visit['first_name'] ?? '') . ' ' . ($visit['last_name'] ?? '')));
$drugAllergyText = trim((string) ($visit['drug_allergy'] ?? ''));
$hasDrugAllergy = $drugAllergyText !== '' && $drugAllergyText !== '-';
$chronicText = trim((string) (($snapshotProfile['underlying_disease'] ?? $visit['underlying_disease'] ?? '') ?: ''));
$hasChronic = $chronicText !== '' && $chronicText !== '-';
if (!function_exists('smart_exam_birth_date')) {
    function smart_exam_birth_date(?string $birthDate): ?DateTimeImmutable
    {
        $birthDate = trim((string) $birthDate);
        if ($birthDate === '') {
            return null;
        }

        try {
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $birthDate, $matches)) {
                $year = (int) $matches[1];
                if ($year >= 2400) {
                    $year -= 543;
                }

                return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[3]));
            }

            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $birthDate, $matches)) {
                $year = (int) $matches[3];
                if ($year >= 2400) {
                    $year -= 543;
                }

                return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[1]));
            }

            $date = new DateTimeImmutable($birthDate);
            $year = (int) $date->format('Y');
            if ($year >= 2400) {
                $date = new DateTimeImmutable(sprintf('%04d-%s', $year - 543, $date->format('m-d')));
            }

            return $date;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('smart_exam_age_text')) {
    function smart_exam_age_text(?DateTimeImmutable $birthDate): string
    {
        if (!$birthDate) {
            return '-';
        }

        $today = new DateTimeImmutable('today');
        if ($birthDate > $today) {
            return '-';
        }

        $age = $birthDate->diff($today)->y;
        return $age <= 130 ? $age . ' ปี' : '-';
    }
}
$birthDate = smart_exam_birth_date((string) ($visit['birth_date'] ?? ''));
$ageText = smart_exam_age_text($birthDate);
$genderText = trim((string) ($visit['gender'] ?? '')) !== '' ? (string) $visit['gender'] : '-';
$phoneText = trim((string) ($visit['phone'] ?? '')) !== '' ? (string) $visit['phone'] : '-';
$lastVisitAt = $snapshotProfile['last_visit_at'] ?? null;
$smartExamUser = current_user() ?? [];
$smartExamRole = (string) ($smartExamUser['role_code'] ?? '');
$canEditPatientFull = $smartExamRole === 'ADMIN';
$canEditPatientLimited = in_array($smartExamRole, ['ADMIN', 'NURSE'], true);
$birthDateText = '';
if (!empty($visit['birth_date'])) {
    try {
        $birthDateText = (new DateTimeImmutable((string) $visit['birth_date']))->format('d/m/') . ((int) (new DateTimeImmutable((string) $visit['birth_date']))->format('Y') + 543);
    } catch (Throwable $throwable) {
        $birthDateText = '';
    }
}
$birthDateText = $birthDate ? $birthDate->format('d/m/') . ((int) $birthDate->format('Y') + 543) : $birthDateText;
$patientProfile = [
    'id' => (int) ($visit['patient_id'] ?? 0),
    'hn' => (string) ($visit['hn'] ?? ''),
    'vn' => (string) ($visit['visit_no'] ?? ''),
    'citizen_id' => (string) (($snapshotProfile['citizen_id'] ?? $visit['citizen_id'] ?? '') ?: ''),
    'title_name' => (string) (($snapshotProfile['title_name'] ?? $visit['title_name'] ?? '') ?: ''),
    'first_name' => (string) ($visit['first_name'] ?? ''),
    'last_name' => (string) ($visit['last_name'] ?? ''),
    'gender' => (string) ($visit['gender'] ?? ''),
    'gender_text' => $genderText,
    'birth_date_text' => $birthDateText,
    'age_text' => $ageText,
    'phone' => (string) ($visit['phone'] ?? ''),
    'address' => (string) (($snapshotProfile['address'] ?? $visit['address'] ?? '') ?: ''),
    'underlying_disease' => $chronicText,
    'drug_allergy' => $drugAllergyText,
    'note' => (string) (($snapshotProfile['note'] ?? $visit['note'] ?? '') ?: ''),
    'visit_count' => (int) ($snapshotProfile['visit_count'] ?? $visit['visit_count'] ?? 1),
    'last_visit_text' => $lastVisitAt ? thai_date($lastVisitAt) : '-',
];
$historyPreview = array_slice($recentVisits, 0, 3);
$latestMedicineNames = [];
foreach ($historyPreview as $recentVisit) {
    $itemsSummary = trim((string) ($recentVisit['items_summary'] ?? ''));
    if ($itemsSummary === '' || $itemsSummary === '-') {
        continue;
    }

    foreach (preg_split('/,\s*/u', $itemsSummary) ?: [] as $medicineName) {
        $medicineName = trim($medicineName);
        if ($medicineName !== '' && $medicineName !== '-' && !in_array($medicineName, $latestMedicineNames, true)) {
            $latestMedicineNames[] = $medicineName;
        }
    }
}
$latestMedicineNames = array_slice($latestMedicineNames, 0, 3);
$serviceTotal = (float) ($visit['service_total'] ?? 0);
$itemTotal = (float) ($visit['item_total'] ?? 0);
$grandTotal = $serviceTotal + $itemTotal;
$queueStatusMeta = queue_status_meta((string) ($visit['status'] ?? 'IN_SERVICE'));
$queueStatusLabel = (string) ($queueStatusMeta['label'] ?? '');
$patientPhotoUrl = !empty($visit['photo_path'])
    ? route_url('patient-photo', ['id' => (int) ($visit['patient_id'] ?? 0), 'v' => strtotime((string) ($visit['updated_at'] ?? 'now'))])
    : '';
$stockMeta = static function (array $item) use ($expiryAlertDays): array {
    $qtyBalance = (float) ($item['qty_balance'] ?? 0);
    $reorderLevel = (float) ($item['reorder_level'] ?? 0);
    $nearestExpiry = trim((string) ($item['nearest_expiry'] ?? ''));
    $isOut = $qtyBalance <= 0;
    $isLow = !$isOut && $reorderLevel > 0 && $qtyBalance <= $reorderLevel;
    $isExpiring = false;

    if (!$isOut && $nearestExpiry !== '') {
        $today = new DateTimeImmutable('today');
        $expiry = new DateTimeImmutable($nearestExpiry);
        $daysToExpiry = (int) $today->diff($expiry)->format('%r%a');
        $isExpiring = $daysToExpiry >= 0 && $daysToExpiry <= $expiryAlertDays;
    }

    return [
        'is_out' => $isOut,
        'is_low' => $isLow,
        'is_expiring' => $isExpiring,
        'nearest_expiry' => $nearestExpiry,
        'class' => $isOut ? 'is-out' : ($isExpiring ? 'is-expiring' : ($isLow ? 'is-low' : 'is-ok')),
        'label' => $isOut ? 'หมด' : ($isExpiring ? 'ใกล้หมดอายุ' : ($isLow ? 'ใกล้หมด' : 'พร้อมใช้')),
    ];
};
?>

<div class="smart-exam-page-shell">
    <section class="card smart-encounter-header">
        <div class="smart-patient-photo">
            <?php if ($patientPhotoUrl !== ''): ?>
                <img src="<?= e($patientPhotoUrl) ?>" alt="รูปผู้รับบริการ">
            <?php else: ?>
                <i class="bi bi-person-badge"></i>
            <?php endif; ?>
        </div>
        <div class="smart-encounter-patient">
            <div class="smart-encounter-kicker">Smart Exam</div>
            <div class="smart-encounter-name"><?= e($fullName) ?></div>
            <div class="smart-active-chips">
                <span class="smart-meta-chip">คิว <?= e((string) ($visit['queue_no'] ?? '')) ?></span>
                <span class="smart-meta-chip">HN <?= e((string) ($visit['hn'] ?? '')) ?></span>
                <span class="smart-meta-chip">VN <?= e((string) ($visit['visit_no'] ?? '')) ?></span>
                <span class="smart-meta-chip"><?= e($queueStatusLabel) ?></span>
                <?php if ($hasDrugAllergy): ?>
                    <span class="smart-meta-chip smart-meta-chip-alert">แพ้ยา <?= e($drugAllergyText) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="smart-encounter-flow" aria-label="Smart Exam workflow">
            <div class="smart-flow-step active" id="smartExamStepPresetCompact"><strong>1</strong><span>Preset</span></div>
            <div class="smart-flow-step" id="smartExamStepClinicalCompact"><strong>2</strong><span>ตรวจ</span></div>
            <div class="smart-flow-step" id="smartExamStepFinishCompact"><strong>3</strong><span>จบเคส</span></div>
        </div>

        <div class="smart-encounter-actions">
            <a href="<?= e(route_url('queue', ['visit_id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-secondary btn-sm">กลับคิว</a>
            <a href="<?= e(route_url('visit-edit', ['id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-dark btn-sm">ประวัติเคส</a>
        </div>
    </section>

    <section class="card smart-exam-page-hero">
        <div class="smart-exam-page-copy">
            <div class="eyebrow">หน้าตรวจ</div>
            <p>เลือก preset, กรอก CC / Dx และสรุปยอดให้ครบก่อนจบเคสในหน้าเดียว</p>
        </div>
        <div class="smart-exam-page-actions">
            <a href="<?= e(route_url('queue', ['visit_id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-secondary">กลับไปหน้าคิว</a>
            <a href="<?= e(route_url('visit-edit', ['id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-dark">ประวัติเคส</a>
        </div>
    </section>

    <section class="smart-exam-steps" aria-label="Smart Exam workflow">
        <div class="smart-exam-step active" id="smartExamStepPreset"><strong>1</strong><span>เลือก preset</span></div>
        <div class="smart-exam-step" id="smartExamStepClinical"><strong>2</strong><span>ตรวจ CC / Dx</span></div>
        <div class="smart-exam-step" id="smartExamStepFinish"><strong>3</strong><span>จบเคส</span></div>
    </section>

    <section class="smart-exam-page-grid">
        <aside class="smart-patient-context" aria-label="Patient context">
            <article class="card smart-panel-card smart-context-card"
                data-patient-context-card
                data-profile-update-url="<?= e(route_url('queue-patient-profile-update')) ?>">
                <div class="smart-context-identity">
                    <div class="smart-context-photo">
                        <?php if ($patientPhotoUrl !== ''): ?>
                            <img src="<?= e($patientPhotoUrl) ?>" alt="รูปผู้รับบริการ">
                        <?php else: ?>
                            <i class="bi bi-person-badge"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="smart-section-label">Patient Context</div>
                        <h3 data-patient-full-name><?= e($fullName) ?></h3>
                        <p>HN <span data-patient-hn><?= e((string) ($visit['hn'] ?? '')) ?></span> / VN <span data-patient-vn><?= e((string) ($visit['visit_no'] ?? '')) ?></span></p>
                    </div>
                    <div class="smart-context-menu-wrap">
                        <?php if ($canEditPatientLimited): ?>
                            <button type="button" class="smart-context-edit-trigger" data-patient-drawer-open="edit" aria-label="แก้ไขข้อมูลคนไข้">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="smart-context-menu-trigger" data-patient-menu-toggle aria-label="เมนูข้อมูลผู้รับบริการ" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div class="smart-context-menu" data-patient-menu hidden>
                            <button type="button" data-patient-drawer-open="view"><i class="bi bi-person-lines-fill"></i> ดูข้อมูลทั้งหมด</button>
                            <?php if ($canEditPatientLimited): ?>
                                <button type="button" data-patient-drawer-open="edit"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลผู้รับบริการ</button>
                            <?php endif; ?>
                            <a href="<?= e(route_url('visit-edit', ['id' => (int) ($visit['id'] ?? 0)])) ?>"><i class="bi bi-clock-history"></i> ประวัติการรักษาทั้งหมด</a>
                            <button type="button" data-patient-menu-placeholder="พิมพ์บัตรผู้รับบริการ"><i class="bi bi-printer"></i> พิมพ์บัตรผู้รับบริการ</button>
                            <button type="button" data-patient-menu-placeholder="พิมพ์ QR Code"><i class="bi bi-qr-code"></i> พิมพ์ QR Code</button>
                            <button type="button" data-patient-menu-close><i class="bi bi-x-lg"></i> ปิดเมนู</button>
                        </div>
                    </div>
                </div>

                <div class="smart-context-facts">
                    <div><span>อายุ</span><strong data-patient-age><?= e($ageText) ?></strong></div>
                    <div><span>เพศ</span><strong data-patient-gender><?= e($genderText) ?></strong></div>
                    <div><span>โทร</span><strong data-patient-phone><?= e($phoneText) ?></strong></div>
                    <div><span>มาแล้ว</span><strong data-patient-visit-count><?= e((string) ($snapshotProfile['visit_count'] ?? $visit['visit_count'] ?? 1)) ?> ครั้ง</strong></div>
                </div>

                <div class="smart-safety-banner <?= $hasDrugAllergy ? 'is-danger' : ($hasChronic ? 'is-warning' : 'is-clear') ?>" data-patient-safety-banner>
                    <div>
                        <span>แพ้ยา</span>
                        <strong data-patient-allergy><?= e($hasDrugAllergy ? $drugAllergyText : 'ไม่มีประวัติแพ้ยา') ?></strong>
                    </div>
                    <div>
                        <span>โรคประจำตัว</span>
                        <strong data-patient-chronic><?= e($hasChronic ? $chronicText : 'ไม่มีโรคประจำตัว') ?></strong>
                    </div>
                </div>

                <div class="smart-context-block">
                    <div class="smart-context-block-head">
                        <strong>ประวัติย้อนหลัง</strong>
                        <span><?= e((string) count($historyPreview)) ?> รายการ</span>
                    </div>
                    <?php if ($historyPreview): ?>
                        <div class="smart-context-history">
                            <?php foreach ($historyPreview as $recentVisit): ?>
                                <div class="smart-context-history-row">
                                    <span><?= thai_date_only($recentVisit['visit_datetime'] ?? null) ?></span>
                                    <strong><?= e((string) (($recentVisit['chief_complaint'] ?? '') ?: (($recentVisit['diagnosis'] ?? '') ?: '-'))) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <details class="smart-one-click">
                            <summary>ดูรายละเอียดทั้งหมด</summary>
                            <div class="smart-one-click-body">
                                <?php foreach ($recentVisits as $recentVisit): ?>
                                    <div class="smart-one-click-row">
                                        <div><strong><?= thai_date($recentVisit['visit_datetime'] ?? null) ?></strong> · <?= e((string) (($recentVisit['diagnosis'] ?? '') ?: 'ยังไม่มี Dx')) ?></div>
                                        <span>ยา/เวชภัณฑ์: <?= e((string) ($recentVisit['items_summary'] ?? '-')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php else: ?>
                        <div class="smart-context-empty">ยังไม่มีประวัติย้อนหลัง</div>
                    <?php endif; ?>
                </div>

                <div class="smart-context-block">
                    <div class="smart-context-block-head">
                        <strong>ยาที่เคยได้รับล่าสุด</strong>
                        <span>One click</span>
                    </div>
                    <?php if ($latestMedicineNames): ?>
                        <div class="smart-context-pill-list">
                            <?php foreach ($latestMedicineNames as $medicineName): ?>
                                <span><?= e($medicineName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="smart-context-empty">ยังไม่พบรายการยาล่าสุด</div>
                    <?php endif; ?>
                </div>

                <div class="smart-context-footer">
                    <span>มาครั้งล่าสุด <?= $lastVisitAt ? thai_date($lastVisitAt) : '-' ?></span>
                    <button type="button" data-patient-drawer-open="view">ข้อมูลทั้งหมด</button>
                </div>
            </article>
        </aside>

        <main class="smart-exam-main">
            <article class="card smart-panel-card smart-main-card">
                <div class="smart-active-case">
                    <div class="smart-active-identity">
                        <div class="smart-active-name"><?= e($fullName) ?></div>
                        <div class="smart-active-chips">
                            <span class="smart-meta-chip">คิว <?= e((string) ($visit['queue_no'] ?? '')) ?></span>
                            <span class="smart-meta-chip">HN <?= e((string) ($visit['hn'] ?? '')) ?></span>
                            <span class="smart-meta-chip">VN <?= e((string) ($visit['visit_no'] ?? '')) ?></span>
                            <?php if ($hasDrugAllergy): ?>
                                <span class="smart-meta-chip smart-meta-chip-alert">แพ้ยา <?= e($drugAllergyText) ?></span>
                            <?php else: ?>
                                <span class="smart-meta-chip">ไม่พบประวัติแพ้ยา</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="active-case-status <?= e(strtolower((string) ($visit['status'] ?? 'IN_SERVICE'))) ?>"><?= e(queue_status_meta((string) ($visit['status'] ?? 'IN_SERVICE'))['label']) ?></span>
                </div>

                <section class="smart-patient-snapshot" aria-label="Patient snapshot">
                    <div class="smart-snapshot-head">
                        <div>
                            <div class="smart-section-label">Patient Snapshot</div>
                            <h4>ข้อมูลสำคัญก่อนตรวจ</h4>
                        </div>
                        <a href="<?= e(route_url('patient-show', ['id' => (int) ($visit['patient_id'] ?? 0)])) ?>" class="smart-snapshot-link">ดูประวัติเต็ม</a>
                    </div>

                    <div class="smart-snapshot-grid">
                        <div class="smart-snapshot-alert<?= $hasDrugAllergy ? ' is-danger' : '' ?>">
                            <span>แพ้ยา</span>
                            <strong><?= e($hasDrugAllergy ? $drugAllergyText : 'ไม่พบข้อมูลแพ้ยา') ?></strong>
                        </div>
                        <div class="smart-snapshot-alert">
                            <span>โรคประจำตัว</span>
                            <strong><?= e((string) (($snapshotProfile['underlying_disease'] ?? '') ?: '-')) ?></strong>
                        </div>
                        <div class="smart-snapshot-alert<?= $unpaidCount > 0 ? ' is-warning' : '' ?>">
                            <span>สถานะการเงินเดิม</span>
                            <strong><?= $unpaidCount > 0 ? e((string) $unpaidCount) . ' เคสรอชำระ' : 'ไม่มีเคสรอชำระ' ?></strong>
                        </div>
                        <div class="smart-snapshot-alert">
                            <span>มาทั้งหมด</span>
                            <strong><?= e((string) ($snapshotProfile['visit_count'] ?? 1)) ?> ครั้ง</strong>
                        </div>
                    </div>

                    <details class="smart-snapshot-detail">
                        <summary>
                            <span>ข้อมูลเสริมก่อนตรวจ</span>
                            <strong>Vital ล่าสุด / นัด / ประวัติรักษา</strong>
                        </summary>

                    <div class="smart-snapshot-body">
                        <div class="smart-snapshot-panel">
                            <div class="smart-snapshot-title">Vital ล่าสุด</div>
                            <?php if ($latestVital): ?>
                                <div class="smart-snapshot-vitals">
                                    <span>BP <?= e((string) ($latestVital['bp_systolic'] ?? '-')) ?>/<?= e((string) ($latestVital['bp_diastolic'] ?? '-')) ?></span>
                                    <span>T <?= e((string) ($latestVital['temp_c'] ?? '-')) ?></span>
                                    <span>P <?= e((string) ($latestVital['pulse_rate'] ?? '-')) ?></span>
                                    <span>SpO2 <?= e((string) ($latestVital['spo2'] ?? '-')) ?></span>
                                    <span>Wt <?= e((string) ($latestVital['weight_kg'] ?? '-')) ?></span>
                                </div>
                                <div class="smart-snapshot-muted">บันทึกล่าสุด <?= thai_date($latestVital['visit_datetime'] ?? null) ?></div>
                            <?php else: ?>
                                <div class="smart-summary-empty">ยังไม่มี vital เดิม</div>
                            <?php endif; ?>
                        </div>

                        <div class="smart-snapshot-panel">
                            <div class="smart-snapshot-title">นัดหมาย / ชำระล่าสุด</div>
                            <?php if ($upcomingAppointments): ?>
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <div class="smart-snapshot-line">
                                        <span><?= thai_date_only($appointment['appointment_date'] ?? null) ?> <?= e((string) ($appointment['appointment_time'] ?? '')) ?></span>
                                        <strong><?= e((string) (($appointment['purpose'] ?? '') ?: 'นัดติดตาม')) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="smart-snapshot-line">
                                    <span>นัดถัดไป</span>
                                    <strong>ไม่มีนัดค้าง</strong>
                                </div>
                            <?php endif; ?>
                            <div class="smart-snapshot-muted">ชำระล่าสุด <?= $lastPaidAt ? thai_date($lastPaidAt) : '-' ?></div>
                        </div>
                    </div>

                    <div class="smart-snapshot-recent">
                        <div class="smart-snapshot-title">ประวัติรักษาล่าสุด</div>
                        <?php foreach ($recentVisits as $recentVisit): ?>
                            <details class="smart-snapshot-history">
                                <summary>
                                    <span><?= thai_date($recentVisit['visit_datetime'] ?? null) ?></span>
                                    <strong><?= e((string) (($recentVisit['diagnosis'] ?? '') ?: 'ยังไม่มี Dx')) ?></strong>
                                </summary>
                                <div class="smart-snapshot-history-body">
                                    <div><b>CC:</b> <?= nl2br(e((string) (($recentVisit['chief_complaint'] ?? '') ?: '-'))) ?></div>
                                    <div><b>บริการ:</b> <?= e((string) ($recentVisit['services_summary'] ?? '-')) ?></div>
                                    <div><b>ยา/เวชภัณฑ์:</b> <?= e((string) ($recentVisit['items_summary'] ?? '-')) ?></div>
                                    <div><b>ยอดชำระ:</b> <?= format_money($recentVisit['total_amount'] ?? 0) ?> / ใบเสร็จ <?= e((string) (($recentVisit['receipt_no'] ?? '') ?: '-')) ?></div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                        <?php if (!$recentVisits): ?>
                            <div class="smart-summary-empty">ยังไม่มีประวัติรักษาย้อนหลัง</div>
                        <?php endif; ?>
                    </div>
                    </details>
                </section>

                <form method="post" action="<?= e(route_url('queue-smart-finish')) ?>" class="smart-exam-form" id="smartExamForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="visit_id" value="<?= (int) ($visit['id'] ?? 0) ?>">
                    <div
                        id="smartExamRuntime"
                        class="smart-exam-runtime"
                        data-current-preset="<?= e($currentPresetKey) ?>"
                        data-service-count="<?= e((string) $serviceCount) ?>"
                        data-item-count="<?= e((string) $itemCount) ?>"
                        data-grand-total="<?= e((string) $grandTotal) ?>"
                        data-remove-service-url="<?= e(route_url('visit-remove-service')) ?>"
                        data-remove-item-url="<?= e(route_url('visit-remove-item')) ?>"
                    ></div>

                    <div class="smart-exam-top-grid">
                    <div class="smart-service-presets">
                        <div class="smart-service-presets-head">
                            <div class="smart-section-label">Preset บริการและอุปกรณ์ที่ใช้บ่อย</div>
                            <div class="smart-service-presets-hint">กดครั้งเดียวเพื่อเพิ่มรายการที่ใช้ประจำในเคส</div>
                        </div>
                        <?php if ($currentPreset): ?>
                            <div class="smart-preset-feedback" id="smartPresetFeedback">
                                <strong>Preset ล่าสุด:</strong> <?= e($currentPreset['label']) ?> ถูกเพิ่มแล้ว ตรวจ CC / Dx ต่อได้เลย
                            </div>
                        <?php endif; ?>
                        <div class="smart-service-grid smart-compact-preset-row">
                            <?php foreach ($quickPresets as $presetKey => $preset): ?>
                                <button
                                    type="submit"
                                    class="smart-service-card <?= e($preset['theme']) ?><?= $currentPresetKey === $presetKey ? ' is-active' : '' ?>"
                                    formaction="<?= e(route_url('queue-apply-preset')) ?>"
                                    name="preset_key"
                                    data-preset-key="<?= e($presetKey) ?>"
                                    value="<?= e($presetKey) ?>"
                                >
                                    <span class="smart-service-title"><?= e($preset['label']) ?></span>
                                    <span class="smart-service-desc"><?= e($preset['description']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($treatmentPresets): ?>
                            <div class="treatment-preset-block">
                                <div class="smart-service-presets-head">
                                    <div class="smart-section-label">Treatment Preset</div>
                                    <div class="smart-service-presets-hint">เพิ่มบริการ ยา และเวชภัณฑ์เป็นชุด หลังตรวจรายการก่อนยืนยัน</div>
                                </div>
                                <div class="treatment-preset-grid">
                                    <?php foreach ($treatmentPresets as $treatmentPreset): ?>
                                        <?php
                                        $serviceSummary = array_map(
                                            static fn(array $row): string => (string) $row['service_name'] . ' x' . $formatQty((float) $row['qty']),
                                            $treatmentPreset['services'] ?? []
                                        );
                                        $medicationSummary = array_map(
                                            static fn(array $row): string => (string) $row['item_name'] . ' x' . $formatQty((float) $row['qty']) . ' ' . (string) ($row['unit_name'] ?? ''),
                                            $treatmentPreset['medications'] ?? []
                                        );
                                        $supplySummary = array_map(
                                            static fn(array $row): string => (string) $row['item_name'] . ' x' . $formatQty((float) $row['qty']) . ' ' . (string) ($row['unit_name'] ?? ''),
                                            $treatmentPreset['supplies'] ?? []
                                        );
                                        $totalPresetRows = count($serviceSummary) + count($medicationSummary) + count($supplySummary);
                                        ?>
                                        <button
                                            type="button"
                                            class="treatment-preset-card"
                                            data-treatment-preset-trigger
                                            data-preset-id="<?= e((string) $treatmentPreset['id']) ?>"
                                            data-preset-name="<?= e((string) $treatmentPreset['preset_name']) ?>"
                                            data-preset-description="<?= e((string) ($treatmentPreset['description'] ?? '')) ?>"
                                            data-preset-services="<?= e(json_encode($serviceSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                            data-preset-medications="<?= e(json_encode($medicationSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                            data-preset-supplies="<?= e(json_encode($supplySummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                        >
                                            <span class="treatment-preset-name"><?= e((string) $treatmentPreset['preset_name']) ?></span>
                                            <span class="treatment-preset-meta"><?= e((string) $totalPresetRows) ?> รายการในชุด</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="smart-template-launch-card">
                        <button type="button" class="btn btn-outline-primary smart-template-open" data-smart-template-open>
                            <i class="bi bi-magic"></i> เลือก Template
                        </button>
                        <button type="button" class="btn btn-outline-secondary smart-template-more" data-smart-template-open>
                            + เพิ่มเติม
                        </button>

                        <dialog class="smart-template-dialog" data-smart-template-dialog aria-label="เลือก Smart Template">
                            <div class="smart-template-dialog-head">
                                <div>
                                    <div class="smart-section-label">Smart Template</div>
                                    <h3>เลือก Template</h3>
                                </div>
                                <button type="button" class="smart-dialog-close" data-smart-template-close aria-label="ปิด">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <div class="smart-template-picker">
                                <label for="smartTemplateSelect">Template Dropdown</label>
                                <select id="smartTemplateSelect">
                                    <option value="">เลือก template</option>
                                    <option value="uri">ตรวจทั่วไป / URI</option>
                                    <option value="wound">ทำแผล</option>
                                    <option value="gastritis">ปวดท้อง</option>
                                    <option value="iv">ให้น้ำเกลือ</option>
                                </select>
                            </div>

                            <div class="smart-one-click smart-template-advanced">
                            <div class="smart-preset-group">
                                <h6>CC</h6>
                                <div class="smart-preset-buttons compact">
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="ไข้">ไข้</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="ไอ">ไอ</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="เจ็บคอ">เจ็บคอ</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="ปวดท้อง">ปวดท้อง</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="มีแผล">มีแผล</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="cc" data-append-text="เวียนศีรษะ">เวียนศีรษะ</button>
                                </div>
                            </div>

                            <div class="smart-preset-group">
                                <h6>PI Template</h6>
                                <div class="smart-preset-buttons compact">
                                    <button type="button" class="smart-preset-btn" data-template="uri">ไข้หวัด</button>
                                    <button type="button" class="smart-preset-btn" data-template="wound">แผลสด</button>
                                    <button type="button" class="smart-preset-btn" data-template="gastritis">ปวดท้อง</button>
                                    <button type="button" class="smart-preset-btn" data-template="iv">ให้น้ำเกลือ</button>
                                </div>
                            </div>

                            <div class="smart-preset-group">
                                <h6>PE</h6>
                                <div class="smart-preset-buttons compact smart-preset-buttons-pe">
                                    <button type="button" class="smart-preset-btn" data-append-target="pe" data-append-text="General appearance: good">General</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="pe" data-append-text="Chest clear, no wheezing">Chest</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="pe" data-append-text="Abdomen soft, no guarding">Abdomen</button>
                                    <button type="button" class="smart-preset-btn" data-append-target="pe" data-append-text="Wound clean, no active bleeding">Wound</button>
                                </div>
                            </div>
                            </div>
                        </dialog>
                    </div>
                    </div>

                    <dialog class="treatment-preset-dialog" data-treatment-preset-dialog aria-label="ยืนยัน Treatment Preset">
                        <div class="treatment-preset-dialog-head">
                            <div>
                                <div class="smart-section-label">Treatment Preset</div>
                                <h3 data-treatment-preset-title>ยืนยันชุดการรักษา</h3>
                                <p data-treatment-preset-description>ตรวจรายการก่อนเพิ่มเข้าเคส</p>
                            </div>
                            <button type="button" class="smart-dialog-close" data-treatment-preset-close aria-label="ปิด">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <div class="treatment-preset-dialog-body">
                            <section>
                                <h4><i class="bi bi-clipboard2-pulse"></i> บริการ</h4>
                                <ul data-treatment-preset-services></ul>
                            </section>
                            <section>
                                <h4><i class="bi bi-capsule-pill"></i> ยา</h4>
                                <ul data-treatment-preset-medications></ul>
                            </section>
                            <section>
                                <h4><i class="bi bi-box-seam"></i> เวชภัณฑ์</h4>
                                <ul data-treatment-preset-supplies></ul>
                            </section>
                        </div>

                        <div class="treatment-preset-warning">
                            ระบบจะเพิ่มรายการเข้าเคสและตัด Stock ทันที สามารถลบรายการออกจากเคสเพื่อคืน Stock ได้ภายหลัง
                        </div>

                        <div class="treatment-preset-dialog-actions">
                            <button type="button" class="btn btn-outline-secondary" data-treatment-preset-close>ยกเลิก</button>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                formaction="<?= e(route_url('queue-apply-treatment-preset')) ?>"
                                name="treatment_preset_id"
                                value=""
                                data-treatment-preset-confirm
                            >
                                ยืนยันเพิ่มชุดการรักษา
                            </button>
                        </div>
                    </dialog>

                    <div class="smart-exam-work-grid">

                    <div class="smart-exam-card smart-clinical-card">
                        <div class="smart-clinical-head">
                            <div>
                                <h4>ซักประวัติ / ตรวจร่างกาย</h4>
                                <p>กรอกเฉพาะข้อมูลสำคัญ ใช้ Smart Preset ช่วยลดการพิมพ์ และเดินงานต่อด้วยคีย์บอร์ดได้เร็วขึ้น</p>
                            </div>
                        </div>

                        <div class="smart-clinical-grid">
                            <div class="smart-exam-inputs">
                                <label for="cc">CC: อาการสำคัญ</label>
                                <textarea id="cc" name="cc" rows="2" data-auto-expand data-min-rows="2" placeholder="เช่น ไข้ ไอ เจ็บคอ"><?= e((string) ($visit['chief_complaint'] ?? '')) ?></textarea>

                                <label for="pi">PI: ประวัติปัจจุบัน</label>
                                <textarea id="pi" name="pi" rows="3" data-auto-expand data-min-rows="3" placeholder="รายละเอียดอาการ"><?= e((string) ($visit['present_illness'] ?? '')) ?></textarea>

                                <label for="dx">Dx: วินิจฉัยเบื้องต้น</label>
                                <input id="dx" name="dx" value="<?= e((string) ($visit['diagnosis'] ?? '')) ?>" placeholder="เช่น URI, Gastritis, Wound">

                                <label for="pe">PE: ตรวจร่างกาย</label>
                                <textarea id="pe" name="pe" rows="2" data-auto-expand data-min-rows="2" placeholder="ผลการตรวจร่างกาย"><?= e((string) ($visit['physical_exam'] ?? '')) ?></textarea>

                                <div id="dxSuggest" class="smart-dx-suggest" hidden></div>
                                <div class="smart-keyboard-hint">กด Enter เพื่อไปช่องถัดไป และกด Ctrl + Enter ในช่องข้อความเพื่อข้ามไปส่วนถัดไป</div>
                            </div>

                        </div>

                        <div class="smart-vitals-inline">
                            <div class="smart-vitals-inline-head">ข้อมูลสัญญาณชีพ</div>
                            <div class="smart-vital-row">
                                <div class="smart-vital-field smart-vital-bp-up">
                                    <label for="bp_systolic">BP บน</label>
                                    <input type="number" id="bp_systolic" name="bp_systolic" value="<?= e((string) ($visit['bp_systolic'] ?? '')) ?>" placeholder="120">
                                </div>
                                <div class="smart-vital-field smart-vital-bp-down">
                                    <label for="bp_diastolic">BP ล่าง</label>
                                    <input type="number" id="bp_diastolic" name="bp_diastolic" value="<?= e((string) ($visit['bp_diastolic'] ?? '')) ?>" placeholder="80">
                                </div>
                                <div class="smart-vital-field smart-vital-temp">
                                    <label for="temp_c">Temp</label>
                                    <input type="number" step="0.1" id="temp_c" name="temp_c" value="<?= e((string) ($visit['temp_c'] ?? '')) ?>" placeholder="°C">
                                </div>
                                <div class="smart-vital-field smart-vital-pulse">
                                    <label for="pulse_rate">Pulse</label>
                                    <input type="number" id="pulse_rate" name="pulse_rate" value="<?= e((string) ($visit['pulse_rate'] ?? '')) ?>" placeholder="/min">
                                </div>
                                <div class="smart-vital-field smart-vital-resp">
                                    <label for="resp_rate">Resp</label>
                                    <input type="number" id="resp_rate" name="resp_rate" value="<?= e((string) ($visit['resp_rate'] ?? '')) ?>" placeholder="/min">
                                </div>
                                <div class="smart-vital-field smart-vital-spo2">
                                    <label for="spo2">SpO2</label>
                                    <input type="number" id="spo2" name="spo2" value="<?= e((string) ($visit['spo2'] ?? '')) ?>" placeholder="%">
                                </div>
                                <div class="smart-vital-field smart-vital-weight">
                                    <label for="weight_kg">Weight</label>
                                    <input type="number" step="0.1" id="weight_kg" name="weight_kg" value="<?= e((string) ($visit['weight_kg'] ?? '')) ?>" placeholder="kg">
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="smart-exam-card smart-followup-card">
                        <div class="smart-card-head">
                            <h4>คำแนะนำและนัดติดตาม</h4>
                            <p>ใช้เมื่อต้องการบันทึกคำแนะนำและสร้างนัดติดตามอัตโนมัติหลังปิดเคส</p>
                        </div>
                        <div class="smart-followup-grid">
                            <div>
                                <label for="advice">คำแนะนำกลับบ้าน</label>
                                <textarea id="advice" name="advice" placeholder="เช่น ดูแลแผลให้แห้ง สังเกตอาการผิดปกติ หรือกลับมาหากอาการไม่ดีขึ้น"><?= e((string) ($visit['advice'] ?? '')) ?></textarea>
                            </div>
                            <div>
                                <label for="followup_date">วันนัดติดตาม</label>
                                <input type="date" id="followup_date" name="followup_date" value="<?= e((string) ($visit['followup_date'] ?? '')) ?>">
                                <div class="smart-followup-hint">ถ้ามีวันนัด ระบบจะสร้าง/อัปเดต appointment ให้ตอนจบเคส</div>
                            </div>
                        </div>
                    </div>

                    <div class="smart-inline-actions">
                        <button type="button" class="btn btn-clear" id="smartExamClear">ล้างข้อมูล</button>
                        <button type="button" class="btn btn-undo" id="smartExamUndo">ย้อนกลับ</button>
                        <span class="smart-shortcut-hint">Ctrl+K preset/search · F2 service/medicine · F9 finish · Esc close</span>
                    </div>
                </form>

                <section class="smart-billing-workspace" aria-label="บริการและยาในเคส">
                    <div class="smart-exam-card smart-billing-card smart-service-order-card">
                        <div class="smart-billing-head">
                            <div>
                                <div class="smart-section-label">บริการ</div>
                                <h4>เพิ่มบริการที่ทำในเคสนี้</h4>
                            </div>
                            <span id="smartServiceCountLabel"><?= e((string) ($visit['service_count'] ?? 0)) ?> รายการ</span>
                        </div>

                        <div class="smart-order-search">
                            <label for="smartServiceSearch">ค้นหาบริการ</label>
                            <input type="search" id="smartServiceSearch" placeholder="พิมพ์ชื่อบริการ">
                        </div>

                        <?php if ($frequentServices): ?>
                            <div class="smart-shortcut-grid">
                                <?php foreach ($frequentServices as $service): ?>
                                    <form method="post" action="<?= e(route_url('visit-add-service')) ?>" data-smart-filter-text="<?= e(strtolower((string) $service['service_name'])) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="queue-exam">
                                        <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <button type="submit" class="smart-shortcut-btn">
                                            <strong><?= e($service['service_name']) ?></strong>
                                            <span><?= format_money($service['price']) ?></span>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(route_url('visit-add-service')) ?>" class="smart-add-line-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="queue-exam">
                            <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                            <select name="service_id" id="smartServiceSelect" required>
                                <option value="">เลือกบริการ</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e((string) $service['id']) ?>" data-filter-text="<?= e(strtolower((string) $service['service_name'])) ?>"><?= e($service['service_name']) ?> (<?= format_money($service['price']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="qty" id="smartServiceQtyInput" value="1" min="1" aria-label="จำนวนบริการ">
                            <button type="submit" class="btn btn-outline-primary">เพิ่มบริการ</button>
                        </form>

                        <div class="smart-line-list" id="smartServiceLineList">
                            <?php foreach ($serviceLines as $line): ?>
                                <div class="smart-line-item">
                                    <div>
                                        <strong><?= e($line['service_name']) ?></strong>
                                        <span>จำนวน <?= e((string) $line['qty']) ?></span>
                                    </div>
                                    <div class="smart-line-price">
                                        <strong><?= format_money($line['line_total']) ?></strong>
                                        <form method="post" action="<?= e(route_url('visit-remove-service')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="return_to" value="queue-exam">
                                            <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                                            <input type="hidden" name="service_line_id" value="<?= e((string) $line['id']) ?>">
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">ลบ</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($serviceLines)): ?>
                                <div class="smart-summary-empty">ยังไม่มีบริการในเคสนี้</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="smart-exam-card smart-billing-card smart-medication-card">
                        <div class="smart-billing-head">
                            <div>
                                <div class="smart-section-label">ยา / เวชภัณฑ์</div>
                                <h4>สั่งยาและตัดสต็อก</h4>
                            </div>
                            <span id="smartItemCountLabel"><?= e((string) ($visit['item_count'] ?? 0)) ?> รายการ</span>
                        </div>

                        <div class="smart-order-search">
                            <label for="smartItemSearch">ค้นหายา/เวชภัณฑ์</label>
                            <input type="search" id="smartItemSearch" placeholder="พิมพ์ชื่อยา หรืออุปกรณ์">
                        </div>

                        <?php if ($frequentItems): ?>
                            <div class="smart-shortcut-grid">
                                <?php foreach ($frequentItems as $item): ?>
                                    <?php $itemStock = $stockMeta($item); ?>
                                    <form method="post" action="<?= e(route_url('visit-add-item')) ?>" data-smart-filter-text="<?= e(strtolower((string) $item['item_name'])) ?>" data-stock-balance="<?= e((string) ($item['qty_balance'] ?? 0)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="queue-exam">
                                        <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                                        <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <input type="hidden" name="usage_note" value="">
                                        <button type="submit" class="smart-shortcut-btn <?= e($itemStock['class']) ?> <?= $itemStock['is_out'] ? 'is-disabled' : '' ?>" <?= $itemStock['is_out'] ? 'disabled' : '' ?>>
                                            <strong><?= e($item['item_name']) ?></strong>
                                            <span><?= format_money($item['default_price']) ?> / คงเหลือ <?= format_money($item['qty_balance'] ?? 0) ?></span>
                                            <em class="smart-stock-badge <?= e($itemStock['class']) ?>"><?= e($itemStock['label']) ?></em>
                                            <?php if (!$itemStock['is_out'] && $itemStock['nearest_expiry'] !== ''): ?>
                                                <small>หมดอายุใกล้สุด <?= thai_date_only($itemStock['nearest_expiry']) ?></small>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(route_url('visit-add-item')) ?>" class="smart-add-line-form smart-add-line-form-items">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="queue-exam">
                            <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                            <select name="item_id" id="smartItemSelect" required>
                                <option value="">เลือกยา / เวชภัณฑ์ / อุปกรณ์</option>
                                <?php foreach ($items as $item): ?>
                                    <?php $itemStock = $stockMeta($item); ?>
                                    <option value="<?= e((string) $item['id']) ?>" data-filter-text="<?= e(strtolower((string) $item['item_name'])) ?>" data-stock-balance="<?= e((string) ($item['qty_balance'] ?? 0)) ?>" <?= $itemStock['is_out'] ? 'disabled' : '' ?>>
                                        <?= e($item['item_name']) ?> (<?= format_money($item['default_price']) ?> / คงเหลือ <?= format_money($item['qty_balance'] ?? 0) ?> / <?= e($itemStock['label']) ?><?= $itemStock['nearest_expiry'] !== '' ? ' / EXP ' . thai_date_only($itemStock['nearest_expiry']) : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.01" name="qty" id="smartItemQtyInput" value="1" min="0.01" aria-label="จำนวนยา">
                            <input type="hidden" name="usage_note" id="smartItemNoteInput" value="">
                            <div class="smart-med-instruction-builder">
                                <input type="text" id="smartMedDoseQty" value="1" aria-label="ขนาดยาต่อครั้ง">
                                <select id="smartMedDoseUnit" aria-label="หน่วยยา">
                                    <option value="เม็ด">เม็ด</option>
                                    <option value="แคปซูล">แคปซูล</option>
                                    <option value="ช้อนชา">ช้อนชา</option>
                                    <option value="ซอง">ซอง</option>
                                    <option value="ครั้ง">ครั้ง</option>
                                </select>
                                <select id="smartMedFrequency" aria-label="ความถี่">
                                    <option value="วันละ 1 ครั้ง">วันละ 1 ครั้ง</option>
                                    <option value="วันละ 2 ครั้ง">วันละ 2 ครั้ง</option>
                                    <option value="วันละ 3 ครั้ง" selected>วันละ 3 ครั้ง</option>
                                    <option value="วันละ 4 ครั้ง">วันละ 4 ครั้ง</option>
                                    <option value="ทุก 4 ชั่วโมง">ทุก 4 ชั่วโมง</option>
                                    <option value="เมื่อมีอาการ">เมื่อมีอาการ</option>
                                </select>
                                <select id="smartMedTiming" aria-label="เวลารับประทาน">
                                    <option value="หลังอาหาร" selected>หลังอาหาร</option>
                                    <option value="ก่อนอาหาร">ก่อนอาหาร</option>
                                    <option value="ก่อนนอน">ก่อนนอน</option>
                                    <option value="">ไม่ระบุเวลา</option>
                                </select>
                                <input type="text" id="smartMedFreeNote" placeholder="หมายเหตุ / คำเตือนสั้น ๆ">
                            </div>
                            <div class="smart-med-preview" id="smartMedInstructionPreview">รับประทานครั้งละ 1 เม็ด วันละ 3 ครั้ง หลังอาหาร</div>
                            <button type="submit" class="btn btn-outline-success">เพิ่มยา</button>
                        </form>

                        <div class="smart-line-list" id="smartItemLineList">
                            <?php foreach ($itemLines as $line): ?>
                                <div class="smart-line-item">
                                    <div>
                                        <strong><?= e($line['item_name']) ?></strong>
                                        <span>จำนวน <?= format_money($line['qty']) ?> <?= e($line['unit_name'] ?? '') ?></span>
                                    </div>
                                    <div class="smart-line-price">
                                        <strong><?= format_money($line['line_total']) ?></strong>
                                        <form method="post" action="<?= e(route_url('visit-remove-item')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="return_to" value="queue-exam">
                                            <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                                            <input type="hidden" name="usage_id" value="<?= e((string) $line['id']) ?>">
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">ลบ</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($itemLines)): ?>
                                <div class="smart-summary-empty">ยังไม่มียา/เวชภัณฑ์ในเคสนี้</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </article>
        </main>

        <aside class="smart-exam-sidebar">
            <article class="card smart-panel-card smart-summary-card">
                <div class="smart-panel-head smart-summary-head">
                    <div class="eyebrow">สรุปเคส</div>
                    <h3>บันทึกและจบเคส</h3>
                </div>

                <div class="smart-summary-patient">
                    <div class="smart-summary-name"><?= e($fullName) ?></div>
                    <div class="smart-summary-meta">HN <?= e((string) ($visit['hn'] ?? '')) ?> / VN <?= e((string) ($visit['visit_no'] ?? '')) ?></div>
                </div>

                <div class="smart-summary-clinical-flag<?= $hasDrugAllergy ? ' is-alert' : '' ?>">
                    <span><?= $hasDrugAllergy ? 'แพ้ยา' : 'Drug allergy' ?></span>
                    <strong><?= e($hasDrugAllergy ? $drugAllergyText : 'ไม่พบข้อมูลแพ้ยา') ?></strong>
                </div>

                <div class="smart-summary-clinical-flag<?= $hasChronic ? ' is-warning' : '' ?>">
                    <span>โรคประจำตัว</span>
                    <strong><?= e($hasChronic ? $chronicText : 'ไม่มีโรคประจำตัว') ?></strong>
                </div>

                <div class="smart-summary-meta-grid">
                    <div class="smart-summary-metric">
                        <span>บริการ</span>
                        <strong id="smartSummaryServiceCount"><?= e((string) $serviceCount) ?></strong>
                    </div>
                    <div class="smart-summary-metric">
                        <span>ยา/อุปกรณ์</span>
                        <strong id="smartSummaryItemCount"><?= e((string) $itemCount) ?></strong>
                    </div>
                </div>

                <div class="smart-summary-section">
                    <div class="smart-summary-title">บริการ</div>
                    <div class="smart-summary-lines" id="smartSummaryServiceLines">
                        <?php foreach ($serviceLines as $line): ?>
                            <div class="smart-summary-line">
                                <span><?= e($line['service_name']) ?> x<?= e((string) $line['qty']) ?></span>
                                <strong><?= format_money($line['line_total']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($serviceLines)): ?>
                            <div class="smart-summary-empty">ยังไม่มีบริการ</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="smart-summary-section">
                    <div class="smart-summary-title">อุปกรณ์ที่ใช้</div>
                    <div class="smart-summary-lines" id="smartSummaryItemLines">
                        <?php foreach ($itemLines as $line): ?>
                            <div class="smart-summary-line">
                                <span><?= e($line['item_name']) ?> x<?= format_money($line['qty']) ?></span>
                                <strong><?= format_money($line['line_total']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($itemLines)): ?>
                            <div class="smart-summary-empty">ยังไม่มีอุปกรณ์ที่ใช้</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="smart-summary-total">
                    <div class="smart-summary-line"><span>ค่าบริการ</span><strong id="smartSummaryServiceTotal"><?= format_money($visit['service_total'] ?? 0) ?></strong></div>
                    <div class="smart-summary-line"><span>ค่ายา/อุปกรณ์</span><strong id="smartSummaryItemTotal"><?= format_money($visit['item_total'] ?? 0) ?></strong></div>
                    <div class="smart-summary-line grand"><span>รวมสุทธิ</span><strong id="smartSummaryGrandTotal"><?= format_money(($visit['service_total'] ?? 0) + ($visit['item_total'] ?? 0)) ?></strong></div>
                </div>

                <div class="smart-inline-payment" data-base-total="<?= e((string) $grandTotal) ?>">
                    <div class="smart-summary-title">รับเงินในหน้านี้</div>
                    <div class="smart-payment-grid">
                        <div>
                            <label for="smartPaymentMethod">วิธีชำระ</label>
                            <select name="payment_method" id="smartPaymentMethod" form="smartExamForm">
                                <option value="CASH">เงินสด</option>
                                <option value="TRANSFER">โอนเงิน</option>
                                <option value="QR">QR Code</option>
                            </select>
                        </div>
                        <div>
                            <label for="smartPaymentDiscount">ส่วนลด</label>
                            <input type="number" step="0.01" min="0" name="discount_amount" id="smartPaymentDiscount" form="smartExamForm" value="0">
                        </div>
                        <div>
                            <label for="smartPaymentPaid">รับมา</label>
                            <input type="number" step="0.01" min="0" name="paid_amount" id="smartPaymentPaid" form="smartExamForm" value="<?= e((string) $grandTotal) ?>">
                        </div>
                    </div>
                    <div class="smart-payment-preview">
                        <div>
                            <span>ยอดสุทธิ</span>
                            <strong id="smartPaymentNet"><?= format_money($grandTotal) ?></strong>
                        </div>
                        <div>
                            <span>เงินทอน</span>
                            <strong id="smartPaymentChange">0.00</strong>
                        </div>
                    </div>
                    <div class="smart-payment-warning" id="smartPaymentWarning" hidden>ยอดรับชำระน้อยกว่ายอดสุทธิ</div>
                </div>

                <div class="smart-finish-readiness">
                    <div class="smart-summary-title">ความพร้อมก่อนจบเคส</div>
                    <div class="smart-readiness-list">
                        <div class="smart-readiness-item" id="smartReadinessCc">
                            <span>CC</span>
                            <strong>ยังไม่กรอก</strong>
                        </div>
                        <div class="smart-readiness-item" id="smartReadinessDx">
                            <span>Dx</span>
                            <strong>ยังไม่กรอก</strong>
                        </div>
                        <div class="smart-readiness-item" id="smartReadinessBilling">
                            <span>รายการคิดเงิน</span>
                            <strong><?= ($serviceCount + $itemCount) > 0 ? 'พร้อม' : 'ยังไม่มี' ?></strong>
                        </div>
                        <div class="smart-readiness-item" id="smartReadinessStock">
                            <span>Stock</span>
                            <strong>พร้อม</strong>
                        </div>
                    </div>
                    <div class="smart-readiness-note" id="smartFinishNote">กรอก CC และ Dx ให้ครบก่อน จากนั้นตรวจรายการคิดเงินแล้วค่อยจบเคส</div>
                </div>

                <div class="smart-summary-actions">
                    <div class="smart-exam-alert" id="smartExamAlert" hidden></div>
                    <a href="<?= e(route_url('pharmacy-labels', ['visit_id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-success w-100 smart-label-print-action" title="<?= $itemCount > 0 ? 'เปิดหน้า preview สติ๊กเกอร์ยา' : 'เปิดหน้า preview เพื่อตรวจว่ามีรายการยาหรือยัง' ?>">
                        <i class="bi bi-printer-fill me-1"></i> พิมพ์สติ๊กเกอร์ยา
                    </a>
                    <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="receive_payment" class="btn btn-primary btn-lg w-100" id="smartFinishPayment">รับเงินและปิดเคส</button>
                    <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="waiting_payment" class="btn btn-outline-primary w-100" id="smartFinishWaitPayment">บันทึกรอชำระ</button>
                    <details class="smart-secondary-finish">
                        <summary>ตัวเลือกปิดเคสอื่น</summary>
                        <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="no_charge" class="btn btn-outline-secondary w-100" id="smartFinishNoCharge">ปิดเคสแบบไม่มีค่าใช้จ่าย</button>
                    </details>
                </div>
            </article>
        </aside>
    </section>

    <div class="smart-patient-drawer-backdrop" data-patient-drawer-backdrop hidden></div>
    <aside class="smart-patient-drawer" data-patient-drawer aria-hidden="true" aria-label="ข้อมูลผู้รับบริการ">
        <div class="smart-patient-drawer-head">
            <div>
                <div class="smart-section-label">Patient Profile</div>
                <h3 data-patient-drawer-title>ข้อมูลผู้รับบริการ</h3>
                <p>แก้ข้อมูลเท่าที่จำเป็นโดยไม่ออกจาก Smart Exam</p>
            </div>
            <button type="button" class="smart-drawer-close" data-patient-drawer-close aria-label="ปิด">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="smart-patient-drawer-status" data-patient-drawer-status hidden></div>

        <section class="smart-patient-drawer-panel" data-patient-drawer-view>
            <div class="smart-profile-readonly-grid">
                <div><span>ชื่อ</span><strong data-profile-readonly="full_name"><?= e($fullName) ?></strong></div>
                <div><span>HN</span><strong><?= e((string) ($visit['hn'] ?? '')) ?></strong></div>
                <div><span>VN</span><strong><?= e((string) ($visit['visit_no'] ?? '')) ?></strong></div>
                <div><span>เลขบัตรประชาชน</span><strong data-profile-readonly="citizen_id"><?= e($patientProfile['citizen_id'] ?: '-') ?></strong></div>
                <div><span>วันเกิด</span><strong data-profile-readonly="birth_date_text"><?= e($patientProfile['birth_date_text'] ?: '-') ?></strong></div>
                <div><span>อายุ</span><strong data-profile-readonly="age_text"><?= e($ageText) ?></strong></div>
                <div><span>เพศ</span><strong data-profile-readonly="gender_text"><?= e($genderText) ?></strong></div>
                <div><span>โทร</span><strong data-profile-readonly="phone_text"><?= e($phoneText) ?></strong></div>
                <div class="wide"><span>ที่อยู่</span><strong data-profile-readonly="address"><?= e($patientProfile['address'] ?: '-') ?></strong></div>
                <div class="wide danger"><span>แพ้ยา</span><strong data-profile-readonly="drug_allergy_text"><?= e($hasDrugAllergy ? $drugAllergyText : 'ไม่มีประวัติแพ้ยา') ?></strong></div>
                <div class="wide warning"><span>โรคประจำตัว</span><strong data-profile-readonly="underlying_disease_text"><?= e($hasChronic ? $chronicText : 'ไม่มีโรคประจำตัว') ?></strong></div>
                <div class="wide"><span>หมายเหตุ</span><strong data-profile-readonly="note"><?= e($patientProfile['note'] ?: '-') ?></strong></div>
            </div>
        </section>

        <?php if ($canEditPatientLimited): ?>
            <form class="smart-patient-drawer-panel smart-profile-edit-form" data-patient-profile-form>
                <?= csrf_field() ?>
                <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                <input type="hidden" name="patient_id" value="<?= e((string) ($visit['patient_id'] ?? 0)) ?>">

                <div class="smart-profile-section-title">
                    <i class="bi bi-person-vcard"></i>
                    <span>ข้อมูลพื้นฐาน</span>
                </div>
                <div class="smart-profile-form-grid">
                    <label>
                        <span>คำนำหน้า</span>
                        <input type="text" name="title_name" value="<?= e($patientProfile['title_name']) ?>" <?= $canEditPatientFull ? '' : 'disabled' ?>>
                    </label>
                    <label>
                        <span>ชื่อ</span>
                        <input type="text" name="first_name" value="<?= e($patientProfile['first_name']) ?>" <?= $canEditPatientFull ? 'required' : 'disabled' ?>>
                    </label>
                    <label>
                        <span>นามสกุล</span>
                        <input type="text" name="last_name" value="<?= e($patientProfile['last_name']) ?>" <?= $canEditPatientFull ? 'required' : 'disabled' ?>>
                    </label>
                    <label>
                        <span>เลขบัตรประชาชน</span>
                        <input type="text" name="citizen_id" value="<?= e($patientProfile['citizen_id']) ?>" inputmode="numeric" maxlength="13" <?= $canEditPatientFull ? '' : 'disabled' ?>>
                    </label>
                    <label>
                        <span>วันเกิด</span>
                        <input type="text" name="birth_date" value="<?= e($patientProfile['birth_date_text']) ?>" placeholder="เช่น 28/10/2549" <?= $canEditPatientFull ? '' : 'disabled' ?>>
                    </label>
                    <label>
                        <span>เพศ</span>
                        <select name="gender" <?= $canEditPatientFull ? '' : 'disabled' ?>>
                            <option value="">ไม่ระบุ</option>
                            <option value="M" <?= selected($patientProfile['gender'], 'M') ?>>ชาย</option>
                            <option value="F" <?= selected($patientProfile['gender'], 'F') ?>>หญิง</option>
                            <option value="O" <?= selected($patientProfile['gender'], 'O') ?>>อื่นๆ</option>
                        </select>
                    </label>
                    <label class="wide">
                        <span>เบอร์โทร</span>
                        <input type="text" name="phone" value="<?= e($patientProfile['phone']) ?>" inputmode="tel">
                    </label>
                    <label class="wide">
                        <span>ที่อยู่</span>
                        <textarea name="address" rows="3"><?= e($patientProfile['address']) ?></textarea>
                    </label>
                </div>

                <div class="smart-profile-section-title safety">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>ข้อมูลสุขภาพสำคัญ</span>
                </div>
                <div class="smart-profile-form-grid">
                    <label class="wide">
                        <span>แพ้ยา</span>
                        <textarea name="drug_allergy" rows="2" placeholder="ถ้าไม่มี ให้เว้นว่าง"><?= e($patientProfile['drug_allergy']) ?></textarea>
                    </label>
                    <label class="wide">
                        <span>โรคประจำตัว</span>
                        <textarea name="underlying_disease" rows="2" placeholder="ถ้าไม่มี ให้เว้นว่าง"><?= e($patientProfile['underlying_disease']) ?></textarea>
                    </label>
                    <label class="wide">
                        <span>หมายเหตุ</span>
                        <textarea name="note" rows="2"><?= e($patientProfile['note']) ?></textarea>
                    </label>
                </div>

                <div class="smart-profile-permission-note">
                    <?= $canEditPatientFull ? 'สิทธิ์ Admin: แก้ไขข้อมูลผู้รับบริการได้ครบทุกช่อง' : 'สิทธิ์ Nurse: แก้ได้เฉพาะเบอร์โทร ที่อยู่ แพ้ยา โรคประจำตัว และหมายเหตุ' ?>
                </div>

                <div class="smart-profile-actions">
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    <button type="button" class="btn btn-outline-secondary" data-patient-drawer-close>ยกเลิก</button>
                </div>
            </form>
        <?php endif; ?>
    </aside>
</div>
