<?php $fullName = trim(($patient['title_name'] ?? '') . ' ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card section-card h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">ข้อมูลผู้รับบริการ</h2>
                <div class="small text-muted">ใช้ดูข้อมูลหลักก่อนเปิดประวัติการรักษาย้อนหลัง</div>
            </div>
            <div class="card-body px-4">
                <div class="patient-result-title mb-2"><?= e($fullName) ?></div>
                <div class="patient-meta-row mb-3">
                    <div><span>HN</span><strong><?= e($patient['hn']) ?></strong></div>
                    <div><span>โทร</span><strong><?= e($patient['phone'] ?: '-') ?></strong></div>
                </div>
                <div class="workflow-list">
                    <div class="workflow-list-item"><span>เพศ</span><strong><?= e($patient['gender'] ?: '-') ?></strong></div>
                    <div class="workflow-list-item"><span>วันเกิด</span><strong><?= thai_date_only($patient['birth_date']) ?></strong></div>
                    <div class="workflow-list-item"><span>โรคประจำตัว</span><strong><?= e($patient['underlying_disease'] ?: '-') ?></strong></div>
                    <div class="workflow-list-item"><span>แพ้ยา</span><strong><?= e($patient['drug_allergy'] ?: '-') ?></strong></div>
                    <div class="workflow-list-item"><span>มารับบริการทั้งหมด</span><strong><?= e((string) $patient['visit_count']) ?> ครั้ง</strong></div>
                    <div class="workflow-list-item"><span>ครั้งล่าสุด</span><strong><?= thai_date($patient['last_visit_at']) ?></strong></div>
                </div>

                <?php if (!empty($patient['address']) || !empty($patient['note'])): ?>
                    <div class="template-panel mt-4">
                        <div class="small text-muted mb-2">หมายเหตุ</div>
                        <?php if (!empty($patient['address'])): ?>
                            <div class="mb-2"><strong>ที่อยู่:</strong> <?= nl2br(e($patient['address'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($patient['note'])): ?>
                            <div><strong>หมายเหตุเพิ่มเติม:</strong> <?= nl2br(e($patient['note'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2 mt-4">
                    <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-secondary">กลับไปรายชื่อผู้รับบริการ</a>
                    <?php if (has_role('ADMIN')): ?>
                        <form method="post" action="<?= e(route_url('queue-store')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                            <input type="hidden" name="chief_complaint" value="">
                            <button type="submit" class="btn btn-primary w-100">รับคิวให้ผู้รับบริการคนนี้</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">นัดติดตาม</h2>
                <div class="small text-muted">แสดงประวัตินัดล่าสุดของผู้รับบริการ</div>
            </div>
            <div class="card-body px-4">
                <div class="workflow-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="workflow-list-item">
                            <div>
                                <div class="fw-semibold"><?= thai_date_only($appointment['appointment_date']) ?></div>
                                <div class="small text-muted"><?= e($appointment['purpose'] ?: 'นัดติดตาม') ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold"><?= e($appointment['status']) ?></div>
                                <div class="small text-muted"><?= e($appointment['note'] ?: '-') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$appointments): ?>
                        <div class="queue-empty-state">ยังไม่มีประวัตินัดติดตาม</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">ประวัติการรักษาย้อนหลัง</h2>
                <div class="small text-muted">รวมอาการสำคัญ สัญญาณชีพ บริการ ยา และยอดชำระในแต่ละครั้ง</div>
            </div>
            <div class="card-body px-4">
                <div class="workflow-list">
                    <?php foreach ($visits as $visit): ?>
                        <?php $statusMeta = queue_status_meta($visit['status'] ?? 'COMPLETED'); ?>
                        <div class="patient-history-card">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                                <div>
                                    <div class="fw-semibold">VN <?= e($visit['visit_no']) ?></div>
                                    <div class="small text-muted">วันที่ <?= thai_date($visit['visit_datetime']) ?> / คิว <?= e((string) ($visit['queue_no'] ?? '-')) ?></div>
                                </div>
                                <div class="text-lg-end">
                                    <span class="badge bg-<?= e($statusMeta['class']) ?> mb-2"><?= e($statusMeta['label']) ?></span>
                                    <div class="fw-semibold">ยอดชำระ <?= format_money($visit['total_amount'] ?? 0) ?></div>
                                    <div class="small text-muted">ใบเสร็จ <?= e($visit['receipt_no'] ?: '-') ?></div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-lg-6">
                                    <div class="template-panel h-100">
                                        <div class="small text-muted mb-2">อาการสำคัญ / Nursing Note</div>
                                        <div class="mb-2"><strong>อาการสำคัญ:</strong> <?= nl2br(e($visit['chief_complaint'] ?: '-')) ?></div>
                                        <div><strong>บันทึกการพยาบาล:</strong> <?= nl2br(e($visit['nursing_note'] ?: '-')) ?></div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="template-panel h-100">
                                        <div class="small text-muted mb-2">สัญญาณชีพ / คำแนะนำ</div>
                                        <div class="small mb-2">BP <?= e((string) ($visit['bp_systolic'] ?? '-')) ?>/<?= e((string) ($visit['bp_diastolic'] ?? '-')) ?> | Temp <?= e((string) ($visit['temp_c'] ?? '-')) ?> | Pulse <?= e((string) ($visit['pulse_rate'] ?? '-')) ?> | SpO2 <?= e((string) ($visit['spo2'] ?? '-')) ?> | Weight <?= e((string) ($visit['weight_kg'] ?? '-')) ?></div>
                                        <div><strong>คำแนะนำ:</strong> <?= nl2br(e($visit['advice'] ?: '-')) ?></div>
                                        <div class="small text-muted mt-2">นัดติดตาม: <?= thai_date_only($visit['followup_date']) ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="template-panel h-100">
                                        <div class="small text-muted mb-2">บริการที่ได้รับ</div>
                                        <div><?= e($visit['services_summary']) ?></div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="template-panel h-100">
                                        <div class="small text-muted mb-2">ยา / เวชภัณฑ์ที่ใช้</div>
                                        <div><?= e($visit['items_summary']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$visits): ?>
                        <div class="queue-empty-state">ยังไม่มีประวัติการรักษา</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>