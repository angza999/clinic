<?php
$statusMeta = queue_status_meta($visit['status'] ?? 'WAITING');
$canSendToPayment = (bool) $hasBillableItems;
?>

<div class="visit-header-card mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-lg-8">
            <div class="d-flex flex-column flex-lg-row gap-4">
                <div>
                    <div class="small text-uppercase text-muted">ผู้รับบริการ</div>
                    <div class="h4 mb-1"><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></div>
                    <div class="text-muted"><?= e($visit['hn']) ?> / <?= e($visit['visit_no']) ?></div>
                </div>
                <div class="visit-chip-group">
                    <span class="visit-chip">คิว <?= e((string) ($visit['queue_no'] ?? '-')) ?></span>
                    <span class="visit-chip">เพศ <?= e($visit['gender'] ?: '-') ?></span>
                    <span class="visit-chip">โทร <?= e($visit['phone'] ?: '-') ?></span>
                    <span class="visit-chip">แพ้ยา <?= e($visit['drug_allergy'] ?: '-') ?></span>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="visit-total-card">
                <div class="d-flex justify-content-between mb-2">
                    <span>สถานะ</span>
                    <span class="badge bg-<?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                </div>
                <div class="d-flex justify-content-between"><span>ค่าบริการ</span><strong><?= format_money($serviceTotal) ?></strong></div>
                <div class="d-flex justify-content-between"><span>ค่ายา/เวชภัณฑ์</span><strong><?= format_money($itemTotal) ?></strong></div>
                <div class="d-flex justify-content-between border-top pt-2 mt-2"><span>รวมทั้งสิ้น</span><strong class="fs-5"><?= format_money($grandTotal) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">บันทึกการพยาบาล</h2>
                <div class="small text-muted">กรอกข้อมูลสำคัญให้ครบ ส่วนปุ่มบันทึกและส่งการเงินอยู่ด้านขวาเพื่อให้เห็นตลอดเวลา</div>
            </div>
            <div class="card-body px-4">
                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                    <form method="post" action="<?= e(route_url('visit-save-clinical')) ?>" id="visit-clinical-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">

                        <div class="mb-3">
                            <label class="form-label">อาการสำคัญ</label>
                            <textarea id="chief-complaint" name="chief_complaint" class="form-control" rows="2" placeholder="เช่น มีไข้ ไอ เจ็บคอ"><?= e($visit['chief_complaint'] ?? '') ?></textarea>
                            <div class="template-panel mt-3">
                                <div class="small text-muted mb-2">อาการที่ใช้บ่อย</div>
                                <div class="shortcut-grid mb-2">
                                    <?php foreach ([
                                        ['label' => 'ไข้/ไอ/เจ็บคอ', 'text' => 'มีไข้ ไอ เจ็บคอ'],
                                        ['label' => 'ปวดศีรษะ', 'text' => 'ปวดศีรษะ'],
                                        ['label' => 'ปวดเมื่อย', 'text' => 'ปวดเมื่อยตามตัว'],
                                        ['label' => 'ทำแผล', 'text' => 'มาทำแผล/ล้างแผล'],
                                        ['label' => 'ติดตามอาการ', 'text' => 'มาติดตามอาการต่อเนื่อง'],
                                    ] as $template): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm shortcut-btn js-template-btn"
                                            data-target="chief-complaint"
                                            data-template="<?= e($template['text']) ?>"
                                        ><?= e($template['label']) ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-link btn-sm px-0 js-template-clear" data-target="chief-complaint">ล้างข้อความช่องนี้</button>
                            </div>
                        </div>

                        <div class="visit-vitals-grid mb-4">
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
                                <label class="form-label">SpO2</label>
                                <input type="number" name="spo2" class="form-control" value="<?= e((string) ($visit['spo2'] ?? '')) ?>" placeholder="98">
                            </div>
                            <div>
                                <label class="form-label">Weight</label>
                                <input type="number" step="0.1" name="weight_kg" class="form-control" value="<?= e((string) ($visit['weight_kg'] ?? '')) ?>" placeholder="60">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">การให้บริการ / Nursing Note</label>
                            <textarea id="nursing-note" name="nursing_note" class="form-control" rows="4" placeholder="บันทึกสิ่งที่ทำกับผู้รับบริการในครั้งนี้"><?= e($visit['nursing_note'] ?? '') ?></textarea>
                            <?php if (!empty($nursingTemplates)): ?>
                                <div class="template-panel mt-3">
                                    <div class="small text-muted mb-2">ข้อความใช้บ่อย กดเพื่อเติมข้อความได้ทันที</div>
                                    <div class="shortcut-grid mb-2">
                                        <?php foreach ($nursingTemplates as $template): ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm shortcut-btn js-template-btn"
                                                data-target="nursing-note"
                                                data-template="<?= e($template['text']) ?>"
                                            ><?= e($template['label']) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm px-0 js-template-clear" data-target="nursing-note">ล้างข้อความช่องนี้</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">คำแนะนำ</label>
                            <textarea id="advice-note" name="advice" class="form-control" rows="3" placeholder="เช่น พักผ่อน ดื่มน้ำมาก ๆ"><?= e($visit['advice'] ?? '') ?></textarea>
                            <?php if (!empty($adviceTemplates)): ?>
                                <div class="template-panel mt-3">
                                    <div class="small text-muted mb-2">คำแนะนำที่ใช้บ่อย</div>
                                    <div class="shortcut-grid mb-2">
                                        <?php foreach ($adviceTemplates as $template): ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm shortcut-btn js-template-btn"
                                                data-target="advice-note"
                                                data-template="<?= e($template['text']) ?>"
                                            ><?= e($template['label']) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm px-0 js-template-clear" data-target="advice-note">ล้างข้อความช่องนี้</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">นัดติดตาม</label>
                            <input type="date" name="followup_date" class="form-control" value="<?= e((string) ($visit['followup_date'] ?? '')) ?>">
                        </div>
                    </form>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">อาการสำคัญ</label>
                            <div class="form-control"><?= nl2br(e($visit['chief_complaint'] ?? '-')) ?></div>
                        </div>
                        <div class="col-md-6"><div class="form-control">BP: <?= e((string) ($visit['bp_systolic'] ?? '-')) ?>/<?= e((string) ($visit['bp_diastolic'] ?? '-')) ?></div></div>
                        <div class="col-md-6"><div class="form-control">Temp: <?= e((string) ($visit['temp_c'] ?? '-')) ?></div></div>
                        <div class="col-md-6"><div class="form-control">Pulse: <?= e((string) ($visit['pulse_rate'] ?? '-')) ?></div></div>
                        <div class="col-md-6"><div class="form-control">SpO2: <?= e((string) ($visit['spo2'] ?? '-')) ?></div></div>
                        <div class="col-md-6"><div class="form-control">Weight: <?= e((string) ($visit['weight_kg'] ?? '-')) ?></div></div>
                        <div class="col-12">
                            <label class="form-label">Nursing Note</label>
                            <div class="form-control"><?= nl2br(e($visit['nursing_note'] ?? '-')) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">คำแนะนำ</label>
                            <div class="form-control"><?= nl2br(e($visit['advice'] ?? '-')) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <?php if (has_role(['ADMIN', 'NURSE'])): ?>
            <div class="visit-action-sticky">
                <div class="card section-card mb-4 visit-action-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1">สรุปก่อนจบเคส</h2>
                        <div class="small text-muted">อยู่ด้านขวาเพื่อให้เห็นปุ่มบันทึกและส่งการเงินตลอดเวลา</div>
                    </div>
                    <div class="card-body px-4">
                        <div class="payment-preview mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <div class="small text-muted">ความพร้อมก่อนส่งการเงิน</div>
                                    <div class="fw-semibold">บริการ <?= e((string) $serviceCount) ?> รายการ / ยาและเวชภัณฑ์ <?= e((string) $itemCount) ?> รายการ</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">ยอดรวมปัจจุบัน</div>
                                    <div class="fs-5 fw-bold"><?= format_money($grandTotal) ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$canSendToPayment): ?>
                            <div class="alert alert-warning workflow-warning mb-3">
                                ยังไม่มีรายการคิดเงิน กรุณาเพิ่มบริการหรือยา/เวชภัณฑ์อย่างน้อย 1 รายการก่อนส่งชำระเงิน
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" form="visit-clinical-form" name="workflow_action" value="save" class="btn btn-outline-primary">บันทึกข้อมูล</button>
                            <button
                                type="submit"
                                form="visit-clinical-form"
                                name="workflow_action"
                                value="save_and_payment"
                                class="btn btn-primary btn-lg"
                                <?= $canSendToPayment ? '' : 'disabled' ?>
                            >บันทึกและส่งชำระเงิน</button>
                            <a href="<?= e(route_url('queue')) ?>" class="btn btn-outline-secondary">กลับไปหน้าคิว</a>
                        </div>
                    </div>
                </div>
        <?php endif; ?>

        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 mb-1">บริการที่ให้</h2>
                    <div class="small text-muted">เลือกรายการที่ใช้จริงในการตรวจครั้งนี้</div>
                </div>
                <span class="fw-semibold"><?= format_money($serviceTotal) ?></span>
            </div>
            <div class="card-body px-4">
                <?php if (has_role(['ADMIN', 'NURSE']) && $frequentServices): ?>
                    <div class="mb-3">
                        <div class="small text-muted mb-2">บริการที่ใช้บ่อย</div>
                        <div class="shortcut-grid">
                            <?php foreach ($frequentServices as $service): ?>
                                <form method="post" action="<?= e(route_url('visit-add-service')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                    <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="btn btn-outline-primary btn-sm shortcut-btn"><?= e($service['service_name']) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                    <form method="post" action="<?= e(route_url('visit-add-service')) ?>" class="row g-2 mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                        <div class="col-12">
                            <select name="service_id" class="form-select" required>
                                <option value="">เลือกบริการ</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e((string) $service['id']) ?>"><?= e($service['service_name']) ?> (<?= format_money($service['price']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <input type="number" name="qty" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-8">
                            <button type="submit" class="btn btn-outline-primary w-100">เพิ่มบริการ</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="workflow-list">
                    <?php foreach ($addedServices as $serviceLine): ?>
                        <div class="workflow-list-item">
                            <div>
                                <div class="fw-semibold"><?= e($serviceLine['service_name']) ?></div>
                                <div class="small text-muted">จำนวน <?= e((string) $serviceLine['qty']) ?> ครั้ง</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold"><?= format_money($serviceLine['line_total']) ?></div>
                                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
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
                        <div class="queue-empty-state">ยังไม่มีบริการใน Visit นี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 mb-1">ยา / เวชภัณฑ์ที่ใช้</h2>
                    <div class="small text-muted">เพิ่มเฉพาะรายการที่ใช้จริง ระบบจะตัด stock ให้อัตโนมัติ</div>
                </div>
                <span class="fw-semibold"><?= format_money($itemTotal) ?></span>
            </div>
            <div class="card-body px-4">
                <?php if (has_role(['ADMIN', 'NURSE']) && $frequentItems): ?>
                    <div class="mb-3">
                        <div class="small text-muted mb-2">รายการที่ใช้บ่อย</div>
                        <div class="shortcut-grid">
                            <?php foreach ($frequentItems as $item): ?>
                                <form method="post" action="<?= e(route_url('visit-add-item')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                                    <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <input type="hidden" name="usage_note" value="">
                                    <button type="submit" class="btn btn-outline-primary btn-sm shortcut-btn"><?= e($item['item_name']) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                    <form method="post" action="<?= e(route_url('visit-add-item')) ?>" class="row g-2 mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="visit_id" value="<?= e((string) $visit['id']) ?>">
                        <div class="col-12">
                            <select name="item_id" class="form-select" required>
                                <option value="">เลือกรายการยา/เวชภัณฑ์</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= format_money($item['default_price']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <input type="number" step="0.01" name="qty" class="form-control" value="1" min="0.01">
                        </div>
                        <div class="col-4">
                            <input type="text" name="usage_note" class="form-control" placeholder="หมายเหตุ">
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-outline-primary w-100">เพิ่มรายการ</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="workflow-list">
                    <?php foreach ($usedItems as $itemLine): ?>
                        <div class="workflow-list-item">
                            <div>
                                <div class="fw-semibold"><?= e($itemLine['item_name']) ?></div>
                                <div class="small text-muted">จำนวน <?= format_money($itemLine['qty']) ?> <?= e($itemLine['unit_name']) ?><?= $itemLine['usage_note'] ? ' / ' . e($itemLine['usage_note']) : '' ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold"><?= format_money($itemLine['line_total']) ?></div>
                                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
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
                        <div class="queue-empty-state">ยังไม่มีรายการยา/เวชภัณฑ์</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (has_role(['ADMIN', 'NURSE'])): ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-template-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.dataset.target;
            const templateText = button.dataset.template || '';
            const target = document.getElementById(targetId);

            if (!target) {
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
            if (!target) {
                return;
            }

            target.value = '';
            target.focus();
        });
    });
});
</script>