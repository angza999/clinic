<?php
$statusMeta = queue_status_meta($visit['status'] ?? 'WAITING');
$canSendToPayment = (bool) $hasBillableItems && $isEditable;
$birthDate = !empty($visit['birth_date']) ? new DateTimeImmutable((string) $visit['birth_date']) : null;
$ageText = $birthDate ? (string) $birthDate->diff(new DateTimeImmutable('today'))->y . ' ปี' : '-';
?>

<div class="visit-page-shell visit-workspace-page">
    <div class="visit-header-card visit-workspace-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div>
                <div class="small text-uppercase text-muted mb-2">รายละเอียดเคสขั้นสูง</div>
                <h1 class="h3 mb-2">ตรวจสอบและแก้ไขรายละเอียดเคสแบบเต็ม</h1>
                <p class="text-muted mb-0">หน้าหลักสำหรับพยาบาลคือ Smart Exam ส่วนหน้านี้ใช้เมื่อต้องตรวจรายละเอียด ประวัติ รายการบริการ หรือแก้ไขข้อมูลขั้นสูง</p>
            </div>
            <div class="visit-hero-status">
                <div class="small text-muted">สถานะเคส</div>
                <span class="badge bg-<?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                <?php if (in_array($visit['status'] ?? '', ['WAITING', 'IN_SERVICE'], true)): ?>
                    <a href="<?= e(route_url('queue-exam', ['id' => (int) $visit['id']])) ?>" class="btn btn-primary btn-sm mt-2">กลับไป Smart Exam</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="alert alert-<?= e($statusGuidance['class']) ?> workflow-warning mb-4">
        <div class="fw-semibold mb-1"><?= e($statusGuidance['title']) ?></div>
        <div><?= e($statusGuidance['message']) ?></div>
    </div>

    <div class="visit-workspace-grid">
        <aside class="visit-info-column">
            <div class="card section-card visit-info-card mb-4">
                <div class="card-body">
                    <div class="small text-uppercase text-muted mb-2">ข้อมูลผู้รับบริการ</div>
                    <div class="h4 mb-1"><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></div>
                    <div class="text-muted mb-3">HN <?= e($visit['hn']) ?> / VN <?= e($visit['visit_no']) ?></div>

                    <div class="visit-info-list">
                        <div><span>คิว</span><strong><?= e((string) ($visit['queue_no'] ?? '-')) ?></strong></div>
                        <div><span>เพศ</span><strong><?= e($visit['gender'] ?: '-') ?></strong></div>
                        <div><span>อายุ</span><strong><?= e($ageText) ?></strong></div>
                        <div><span>โทรศัพท์</span><strong><?= e($visit['phone'] ?: '-') ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="card section-card visit-info-card mb-4">
                <div class="card-body">
                    <div class="small text-uppercase text-muted mb-2">ข้อมูลสุขภาพสำคัญ</div>
                    <div class="visit-flag-block">
                        <div class="visit-flag-label">แพ้ยา</div>
                        <div class="visit-flag-value"><?= nl2br(e($visit['drug_allergy'] ?: '-')) ?></div>
                    </div>
                    <div class="visit-flag-block">
                        <div class="visit-flag-label">โรคประจำตัว</div>
                        <div class="visit-flag-value"><?= nl2br(e($visit['underlying_disease'] ?: '-')) ?></div>
                    </div>
                </div>
            </div>

            <div class="card section-card visit-history-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="small text-uppercase text-muted">ประวัติล่าสุด</div>
                            <div class="fw-semibold">3 ครั้งก่อนหน้า</div>
                        </div>
                        <a href="<?= e(route_url('patient-show', ['id' => $visit['patient_id']])) ?>" class="btn btn-sm btn-outline-secondary">เปิดแฟ้ม</a>
                    </div>

                    <?php if ($recentVisits): ?>
                        <div class="visit-history-list">
                            <?php foreach ($recentVisits as $recentVisit): ?>
                                <div class="visit-history-item">
                                    <div class="d-flex justify-content-between gap-3 align-items-start mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= e($recentVisit['visit_no']) ?></div>
                                            <div class="small text-muted"><?= e(thai_date($recentVisit['visit_datetime'])) ?></div>
                                        </div>
                                        <?php $recentStatus = queue_status_meta($recentVisit['status'] ?? 'WAITING'); ?>
                                        <span class="badge bg-<?= e($recentStatus['class']) ?>"><?= e($recentStatus['label']) ?></span>
                                    </div>
                                    <div class="small text-muted mb-2">อาการสำคัญ: <?= e($recentVisit['chief_complaint'] ?: '-') ?></div>
                                    <div class="small text-muted">ยอดรวม <?= format_money((float) $recentVisit['service_total'] + (float) $recentVisit['item_total']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="queue-empty-state">ยังไม่มีประวัติการรักษาก่อนหน้า</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <main class="visit-main-column">
            <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                <div class="card section-card mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <div class="small text-uppercase text-muted mb-2">ส่วนหลักของเคส</div>
                        <h2 class="h5 mb-1">ซักประวัติ ตรวจร่างกาย และบันทึกการพยาบาล</h2>
                        <div class="small text-muted">บันทึกข้อมูลสำคัญของเคสนี้ก่อน แล้วจึงเพิ่มบริการหรืออุปกรณ์ด้านล่าง</div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <form method="post" action="<?= e(route_url('visit-save-clinical')) ?>" id="visit-clinical-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">

                            <fieldset <?= $isEditable ? '' : 'disabled' ?>>
                                <div class="mb-4">
                                    <label class="form-label">อาการสำคัญ (CC)</label>
                                    <textarea id="chief-complaint" name="chief_complaint" class="form-control" rows="2" placeholder="เช่น มีไข้ ไอ เจ็บคอ"><?= e($visit['chief_complaint'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <div class="section-step-title">สัญญาณชีพ</div>
                                    <div class="visit-vitals-grid">
                                        <div>
                                            <label class="form-label">BP บน</label>
                                            <input type="number" name="bp_systolic" class="form-control" value="<?= e((string) ($visit['bp_systolic'] ?? '')) ?>" placeholder="120">
                                        </div>
                                        <div>
                                            <label class="form-label">BP ล่าง</label>
                                            <input type="number" name="bp_diastolic" class="form-control" value="<?= e((string) ($visit['bp_diastolic'] ?? '')) ?>" placeholder="80">
                                        </div>
                                        <div>
                                            <label class="form-label">Temp</label>
                                            <input type="number" step="0.1" name="temp_c" class="form-control" value="<?= e((string) ($visit['temp_c'] ?? '')) ?>" placeholder="36.8">
                                        </div>
                                        <div>
                                            <label class="form-label">Pulse</label>
                                            <input type="number" name="pulse_rate" class="form-control" value="<?= e((string) ($visit['pulse_rate'] ?? '')) ?>" placeholder="72">
                                        </div>
                                        <div>
                                            <label class="form-label">Resp</label>
                                            <input type="number" name="resp_rate" class="form-control" value="<?= e((string) ($visit['resp_rate'] ?? '')) ?>" placeholder="18">
                                        </div>
                                        <div>
                                            <label class="form-label">SpO2</label>
                                            <input type="number" name="spo2" class="form-control" value="<?= e((string) ($visit['spo2'] ?? '')) ?>" placeholder="98">
                                        </div>
                                        <div>
                                            <label class="form-label">Weight</label>
                                            <input type="number" step="0.1" name="weight_kg" class="form-control" value="<?= e((string) ($visit['weight_kg'] ?? '')) ?>" placeholder="60">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">บันทึกการพยาบาล / Nursing Note</label>
                                    <textarea id="nursing-note" name="nursing_note" class="form-control" rows="5" placeholder="บันทึกการประเมิน อาการที่พบ การดูแล และสิ่งที่ทำในครั้งนี้"><?= e($visit['nursing_note'] ?? '') ?></textarea>
                                    <?php if (!empty($nursingTemplates)): ?>
                                        <div class="template-panel mt-3">
                                            <div class="small text-muted mb-2">ปุ่มลัดข้อความที่ใช้บ่อย</div>
                                            <div class="shortcut-grid mb-2">
                                                <?php foreach ($nursingTemplates as $template): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm shortcut-btn js-template-btn" data-target="nursing-note" data-template="<?= e($template['text']) ?>"><?= e($template['label']) ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-link btn-sm px-0 js-template-clear" data-target="nursing-note">ล้างข้อความช่องนี้</button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">คำแนะนำกลับบ้าน</label>
                                    <textarea id="advice-note" name="advice" class="form-control" rows="3" placeholder="เช่น การดูแลตนเอง อาการที่ควรกลับมาพบเจ้าหน้าที่ และข้อควรระวัง"><?= e($visit['advice'] ?? '') ?></textarea>
                                    <?php if (!empty($adviceTemplates)): ?>
                                        <div class="template-panel mt-3">
                                            <div class="small text-muted mb-2">คำแนะนำที่ใช้บ่อย</div>
                                            <div class="shortcut-grid mb-2">
                                                <?php foreach ($adviceTemplates as $template): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm shortcut-btn js-template-btn" data-target="advice-note" data-template="<?= e($template['text']) ?>"><?= e($template['label']) ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-link btn-sm px-0 js-template-clear" data-target="advice-note">ล้างข้อความช่องนี้</button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">วันนัดติดตาม</label>
                                        <input type="date" name="followup_date" class="form-control" value="<?= e((string) ($visit['followup_date'] ?? '')) ?>">
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card section-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="small text-uppercase text-muted mb-2">บริการที่ให้</div>
                    <h2 class="h5 mb-1">บันทึกรายการบริการ</h2>
                    <div class="small text-muted">กดจากรายการใช้บ่อย หรือเลือกจากรายการทั้งหมดเพื่อบันทึกค่าบริการในเคสนี้</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if ($isEditable && $frequentServices): ?>
                        <div class="shortcut-grid mb-3">
                            <?php foreach ($frequentServices as $service): ?>
                                <form method="post" action="<?= e(route_url('visit-add-service')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                    <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="btn btn-outline-primary shortcut-btn w-100 text-start">
                                        <div class="fw-semibold"><?= e($service['service_name']) ?></div>
                                        <div class="small text-muted"><?= format_money($service['price']) ?></div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isEditable): ?>
                        <form method="post" action="<?= e(route_url('visit-add-service')) ?>" class="row g-2 mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                            <div class="col-md-7">
                                <select name="service_id" class="form-select" required>
                                    <option value="">เลือกบริการ</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= e((string) $service['id']) ?>"><?= e($service['service_name']) ?> (<?= format_money($service['price']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="qty" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary w-100">เพิ่มบริการ</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="workflow-list compact-workflow-list">
                        <?php foreach ($addedServices as $serviceLine): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($serviceLine['service_name']) ?></div>
                                    <div class="small text-muted">จำนวน <?= e((string) $serviceLine['qty']) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold"><?= format_money($serviceLine['line_total']) ?></div>
                                    <?php if ($isEditable): ?>
                                        <form method="post" action="<?= e(route_url('visit-remove-service')) ?>" class="mt-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                            <input type="hidden" name="service_line_id" value="<?= e((string) $serviceLine['id']) ?>">
                                            <button class="btn btn-link btn-sm text-danger p-0">ลบ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$addedServices): ?>
                            <div class="queue-empty-state">ยังไม่มีบริการที่บันทึกในเคสนี้</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card section-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="small text-uppercase text-muted mb-2">ยาและอุปกรณ์ที่ใช้</div>
                    <h2 class="h5 mb-1">บันทึกรายการยา / เวชภัณฑ์ / อุปกรณ์</h2>
                    <div class="small text-muted">เลือกจากคลัง ระบบจะตัดสต๊อกอัตโนมัติเมื่อเพิ่มในเคสนี้</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if ($isEditable && $frequentItems): ?>
                        <div class="shortcut-grid mb-3">
                            <?php foreach ($frequentItems as $item): ?>
                                <form method="post" action="<?= e(route_url('visit-add-item')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                    <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <input type="hidden" name="usage_note" value="">
                                    <button type="submit" class="btn btn-outline-success shortcut-btn w-100 text-start">
                                        <div class="fw-semibold"><?= e($item['item_name']) ?></div>
                                        <div class="small text-muted"><?= format_money($item['default_price']) ?> / <?= e($item['unit_name']) ?></div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isEditable): ?>
                        <form method="post" action="<?= e(route_url('visit-add-item')) ?>" class="row g-2 mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                            <div class="col-12">
                                <select name="item_id" class="form-select" required>
                                    <option value="">เลือกรายการยา / เวชภัณฑ์ / อุปกรณ์</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= format_money($item['default_price']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="qty" class="form-control" value="1" min="0.01">
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="usage_note" class="form-control" placeholder="หมายเหตุการใช้">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-success w-100">เพิ่มรายการ</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="workflow-list compact-workflow-list">
                        <?php foreach ($usedItems as $itemLine): ?>
                            <div class="workflow-list-item">
                                <div>
                                    <div class="fw-semibold"><?= e($itemLine['item_name']) ?></div>
                                    <div class="small text-muted">จำนวน <?= format_money($itemLine['qty']) ?> <?= e($itemLine['unit_name']) ?><?= $itemLine['usage_note'] ? ' / ' . e($itemLine['usage_note']) : '' ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold"><?= format_money($itemLine['line_total']) ?></div>
                                    <?php if ($isEditable): ?>
                                        <form method="post" action="<?= e(route_url('visit-remove-item')) ?>" class="mt-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                            <input type="hidden" name="usage_id" value="<?= e((string) $itemLine['id']) ?>">
                                            <button class="btn btn-link btn-sm text-danger p-0">ลบ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$usedItems): ?>
                            <div class="queue-empty-state">ยังไม่มีรายการยา / เวชภัณฑ์ / อุปกรณ์ในเคสนี้</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <aside class="visit-summary-column">
            <div class="visit-action-sticky">
                <div class="card section-card mb-4 visit-action-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <div class="small text-uppercase text-muted mb-2">สรุปค่าใช้จ่าย</div>
                        <h2 class="h5 mb-1">สรุปเคสและจบงาน</h2>
                        <div class="small text-muted">ตรวจยอดรวมและเลือกขั้นตอนถัดไปจากแผงด้านขวา</div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="payment-preview mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <div class="small text-muted">รายการที่บันทึกแล้ว</div>
                                    <div class="fw-semibold">บริการ <?= e((string) $serviceCount) ?> รายการ / ยาและอุปกรณ์ <?= e((string) $itemCount) ?> รายการ</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">ยอดรวมปัจจุบัน</div>
                                    <div class="fs-5 fw-bold"><?= format_money($grandTotal) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="visit-summary-metrics mb-3">
                            <div>
                                <span>ค่าบริการ</span>
                                <strong><?= format_money($serviceTotal) ?></strong>
                            </div>
                            <div>
                                <span>ค่ายา/อุปกรณ์</span>
                                <strong><?= format_money($itemTotal) ?></strong>
                            </div>
                            <div class="visit-summary-total">
                                <span>รวมสุทธิ</span>
                                <strong><?= format_money($grandTotal) ?></strong>
                            </div>
                        </div>

                        <?php if (!$isEditable): ?>
                            <div class="alert alert-secondary workflow-warning mb-3">หน้านี้อยู่ในโหมดดูอย่างเดียว เนื่องจากเคสไม่ได้อยู่ระหว่างตรวจแล้ว</div>
                        <?php elseif (!$canSendToPayment): ?>
                            <div class="alert alert-warning workflow-warning mb-3">ยังส่งการเงินไม่ได้ กรุณาเพิ่มบริการหรือยา/เวชภัณฑ์/อุปกรณ์อย่างน้อย 1 รายการก่อน</div>
                        <?php endif; ?>

                        <div class="visit-step-panel mb-3">
                            <div class="visit-step-label">ขั้นตอนถัดไป</div>
                            <div class="visit-step-value">
                                <?= $canSendToPayment ? 'ตรวจข้อมูลครบแล้ว สามารถบันทึกและส่งไปการเงินได้ทันที' : 'บันทึกข้อมูลต่อ หรือเพิ่มรายการคิดเงินก่อนส่งชำระเงิน' ?>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" form="visit-clinical-form" name="workflow_action" value="save" class="btn btn-outline-primary" <?= $isEditable ? '' : 'disabled' ?>>บันทึกข้อมูล</button>
                            <button type="submit" form="visit-clinical-form" name="workflow_action" value="save_and_payment" class="btn btn-primary btn-lg" <?= $canSendToPayment ? '' : 'disabled' ?>>บันทึกและส่งชำระเงิน</button>
                            <a href="<?= e(route_url('queue')) ?>" class="btn btn-outline-secondary">กลับไปหน้าคิว</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-template-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.dataset.target;
            const templateText = button.dataset.template || '';
            const target = document.getElementById(targetId);

            if (!target || target.disabled) {
                return;
            }

            const currentValue = target.value.trim();
            target.value = currentValue ? currentValue + "\n" + templateText : templateText;
            target.focus();
        });
    });

    document.querySelectorAll('.js-template-clear').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target || '');
            if (!target || target.disabled) {
                return;
            }

            target.value = '';
            target.focus();
        });
    });
});
</script>
