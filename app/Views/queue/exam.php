<?php
$visit = $activeVisit ?? [];
$services = $services ?? [];
$items = $items ?? [];
$quickPresets = $quickPresets ?? [];
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
$serviceLines = $visit['service_lines'] ?? [];
$itemLines = $visit['item_lines'] ?? [];
$serviceCount = count($serviceLines);
$itemCount = count($itemLines);
$fullName = trim((string) (($visit['first_name'] ?? '') . ' ' . ($visit['last_name'] ?? '')));
$drugAllergyText = trim((string) ($visit['drug_allergy'] ?? ''));
$hasDrugAllergy = $drugAllergyText !== '' && $drugAllergyText !== '-';
$serviceTotal = (float) ($visit['service_total'] ?? 0);
$itemTotal = (float) ($visit['item_total'] ?? 0);
$grandTotal = $serviceTotal + $itemTotal;
$queueStatusMeta = queue_status_meta((string) ($visit['status'] ?? 'IN_SERVICE'));
$queueStatusLabel = (string) ($queueStatusMeta['label'] ?? '');
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
            <a href="<?= e(route_url('visit-edit', ['id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-dark btn-sm">ขั้นสูง</a>
        </div>
    </section>

    <section class="card smart-exam-page-hero">
        <div class="smart-exam-page-copy">
            <div class="eyebrow">หน้าตรวจ</div>
            <p>เลือก preset, กรอก CC / Dx และสรุปยอดให้ครบก่อนจบเคสในหน้าเดียว</p>
        </div>
        <div class="smart-exam-page-actions">
            <a href="<?= e(route_url('queue', ['visit_id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-secondary">กลับไปหน้าคิว</a>
            <a href="<?= e(route_url('visit-edit', ['id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-dark">รายละเอียดขั้นสูง</a>
        </div>
    </section>

    <section class="smart-exam-steps" aria-label="Smart Exam workflow">
        <div class="smart-exam-step active" id="smartExamStepPreset"><strong>1</strong><span>เลือก preset</span></div>
        <div class="smart-exam-step" id="smartExamStepClinical"><strong>2</strong><span>ตรวจ CC / Dx</span></div>
        <div class="smart-exam-step" id="smartExamStepFinish"><strong>3</strong><span>จบเคส</span></div>
    </section>

    <section class="smart-exam-page-grid">
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
                        <div class="smart-service-grid">
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
                    </div>

                    <div class="smart-exam-card smart-vitals-card">
                        <div class="smart-card-head">
                            <h4>ข้อมูลสัญญาณชีพ</h4>
                        </div>

                        <div class="smart-vital-row">
                            <div>
                                <label for="weight_kg">Weight</label>
                                <input type="number" step="0.1" id="weight_kg" name="weight_kg" value="<?= e((string) ($visit['weight_kg'] ?? '')) ?>" placeholder="kg">
                            </div>
                            <div>
                                <label for="temp_c">Temp</label>
                                <input type="number" step="0.1" id="temp_c" name="temp_c" value="<?= e((string) ($visit['temp_c'] ?? '')) ?>" placeholder="°C">
                            </div>
                            <div>
                                <label for="pulse_rate">Pulse</label>
                                <input type="number" id="pulse_rate" name="pulse_rate" value="<?= e((string) ($visit['pulse_rate'] ?? '')) ?>" placeholder="/min">
                            </div>
                            <div>
                                <label for="resp_rate">Resp</label>
                                <input type="number" id="resp_rate" name="resp_rate" value="<?= e((string) ($visit['resp_rate'] ?? '')) ?>" placeholder="/min">
                            </div>
                            <div>
                                <label for="bp_systolic">BP บน</label>
                                <input type="number" id="bp_systolic" name="bp_systolic" value="<?= e((string) ($visit['bp_systolic'] ?? '')) ?>" placeholder="120">
                            </div>
                            <div>
                                <label for="bp_diastolic">BP ล่าง</label>
                                <input type="number" id="bp_diastolic" name="bp_diastolic" value="<?= e((string) ($visit['bp_diastolic'] ?? '')) ?>" placeholder="80">
                            </div>
                            <div>
                                <label for="spo2">SpO2</label>
                                <input type="number" id="spo2" name="spo2" value="<?= e((string) ($visit['spo2'] ?? '')) ?>" placeholder="%">
                            </div>
                        </div>
                    </div>

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
                                <textarea id="cc" name="cc" placeholder="เช่น ไข้ ไอ เจ็บคอ"><?= e((string) ($visit['chief_complaint'] ?? '')) ?></textarea>

                                <label for="pi">PI: ประวัติปัจจุบัน</label>
                                <textarea id="pi" name="pi" placeholder="รายละเอียดอาการ"><?= e((string) ($visit['present_illness'] ?? '')) ?></textarea>

                                <label for="pe">PE: ตรวจร่างกาย</label>
                                <textarea id="pe" name="pe" placeholder="ผลการตรวจร่างกาย"><?= e((string) ($visit['physical_exam'] ?? '')) ?></textarea>

                                <label for="dx">Dx: วินิจฉัยเบื้องต้น</label>
                                <input id="dx" name="dx" value="<?= e((string) ($visit['diagnosis'] ?? '')) ?>" placeholder="เช่น URI, Gastritis, Wound">

                                <div id="dxSuggest" class="smart-dx-suggest" hidden></div>
                                <div class="smart-keyboard-hint">กด Enter เพื่อไปช่องถัดไป และกด Ctrl + Enter ในช่องข้อความเพื่อข้ามไปส่วนถัดไป</div>
                            </div>

                            <div class="smart-preset-panel">
                                <div class="smart-preset-panel-head">
                                    <h5>Smart Preset</h5>
                                    <span>ตัวช่วยเติมข้อความแบบเร็ว</span>
                                </div>

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
                    <div class="smart-exam-card smart-billing-card">
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

                    <div class="smart-exam-card smart-billing-card">
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
                            <input type="text" name="usage_note" id="smartItemNoteInput" placeholder="วิธีใช้ / หมายเหตุ">
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
                    <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="receive_payment" class="btn btn-primary btn-lg w-100" id="smartFinishPayment">รับเงินและปิดเคส</button>
                    <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="waiting_payment" class="btn btn-outline-primary w-100" id="smartFinishWaitPayment">บันทึกรอชำระ</button>
                    <button type="submit" form="smartExamForm" formaction="<?= e(route_url('queue-smart-finish')) ?>" name="finish_mode" value="no_charge" class="btn btn-outline-secondary w-100" id="smartFinishNoCharge">ปิดเคสแบบไม่มีค่าใช้จ่าย</button>
                </div>
            </article>
        </aside>
    </section>
</div>
