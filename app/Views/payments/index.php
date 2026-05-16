<?php
$waitingRows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] === 'WAITING_PAYMENT'));
$completedRows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] === 'COMPLETED'));
$waitingTotal = array_sum(array_map(static fn(array $row): float => (float) $row['grand_total'], $waitingRows));
$completedTotal = array_sum(array_map(static fn(array $row): float => (float) ($row['payment_total'] ?? $row['grand_total']), $completedRows));
$latestCompleted = $completedRows[0] ?? null;
?>

<div class="workspace-stack payment-workspace">
    <section class="section-card formal-hero-card workspace-intro-card payment-hero-card">
        <div class="payment-hero-layout">
            <div class="payment-hero-copy">
                <span class="eyebrow">การเงินและรับชำระ</span>
                <h2>รับชำระให้ชัด เห็นยอดทันที และปิดเคสได้ในจุดเดียว</h2>
                <p>ด้านซ้ายคือเคสที่พร้อมชำระเงิน ด้านขวาเป็นภาพรวมการเงินวันนี้และแนวทางทำงานสั้น ๆ สำหรับเจ้าหน้าที่การเงินหรือพยาบาลที่รับเงินหน้าเคาน์เตอร์</p>
            </div>
            <div class="payment-status-strip">
                <div class="queue-stat queue-stat-payment payment-stat-card">
                    <div class="queue-stat-label">รอชำระเงิน</div>
                    <div class="queue-stat-value"><?= count($waitingRows) ?></div>
                </div>
                <div class="queue-stat queue-stat-complete payment-stat-card">
                    <div class="queue-stat-label">ชำระเสร็จแล้ว</div>
                    <div class="queue-stat-value"><?= count($completedRows) ?></div>
                </div>
                <div class="queue-stat queue-stat-service payment-stat-card">
                    <div class="queue-stat-label">ยอดรอรับชำระ</div>
                    <div class="queue-stat-value"><?= format_money($waitingTotal) ?></div>
                </div>
                <div class="queue-stat queue-stat-waiting payment-stat-card">
                    <div class="queue-stat-label">ยอดรับชำระวันนี้</div>
                    <div class="queue-stat-value"><?= format_money($completedTotal) ?></div>
                </div>
            </div>
        </div>
    </section>

    <div class="payment-shell-grid">
        <section class="section-card payment-panel-card">
            <div class="panel-heading mb-4">
                <div>
                    <h2 class="h4 mb-1">คิวที่ต้องรับชำระตอนนี้</h2>
                    <p class="text-muted mb-0">แสดงเฉพาะเคสที่ห้องตรวจส่งมาครบแล้ว ตรวจยอด รับชำระ และส่งกลับห้องตรวจได้จากรายการนี้โดยตรง</p>
                </div>
                <span class="soft-badge"><?= count($waitingRows) ?> รายการ</span>
            </div>

            <?php if ($waitingRows): ?>
                <div class="payment-case-list">
                    <?php foreach ($waitingRows as $row): ?>
                        <article class="payment-case-card payment-case-card-pro">
                            <div class="payment-case-head">
                                <div>
                                    <div class="patient-result-title">คิว <?= e((string) $row['queue_no']) ?> - <?= e($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="payment-case-meta"><?= e($row['hn']) ?> / <?= e($row['visit_no']) ?> - มารับบริการ <?= e(thai_date($row['visit_datetime'])) ?></div>
                                </div>
                                <span class="queue-inline-chip waiting">รอชำระเงิน</span>
                            </div>

                            <div class="payment-case-metrics">
                                <div>
                                    <span>ค่าบริการ</span>
                                    <strong><?= format_money($row['total_service']) ?></strong>
                                </div>
                                <div>
                                    <span>ค่าเวชภัณฑ์/อุปกรณ์</span>
                                    <strong><?= format_money($row['total_item']) ?></strong>
                                </div>
                                <div>
                                    <span>ยอดก่อนส่วนลด</span>
                                    <strong><?= format_money($row['grand_total']) ?></strong>
                                </div>
                            </div>

                            <form method="post" action="<?= e(route_url('payments-store')) ?>" class="payment-form" data-base-total="<?= e((string) $row['grand_total']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="visit_id" value="<?= e((string) $row['visit_id']) ?>">

                                <div class="payment-field-grid">
                                    <div>
                                        <label class="form-label">ส่วนลด</label>
                                        <input type="number" step="0.01" min="0" name="discount_amount" class="form-control payment-discount" value="0">
                                    </div>
                                    <div>
                                        <label class="form-label">ยอดรับชำระ</label>
                                        <input type="number" step="0.01" min="0" name="paid_amount" class="form-control payment-paid" value="<?= e((string) $row['grand_total']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="form-label">วิธีชำระ</label>
                                        <select name="payment_method" class="form-select">
                                            <option value="CASH">เงินสด</option>
                                            <option value="TRANSFER">โอนเงิน</option>
                                            <option value="QR">QR Code</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="payment-preview payment-preview-pro mt-3">
                                    <div>
                                        <span>ยอดสุทธิ</span>
                                        <strong class="payment-net-total"><?= format_money($row['grand_total']) ?></strong>
                                    </div>
                                    <div>
                                        <span>เงินทอน</span>
                                        <strong class="payment-change-total">0.00</strong>
                                    </div>
                                </div>

                                <div class="alert alert-warning d-none payment-warning mt-3 mb-0">ยอดรับชำระน้อยกว่ายอดสุทธิ กรุณาตรวจสอบอีกครั้ง</div>

                                <div class="payment-action-row mt-3">
                                    <button type="submit" class="btn btn-primary payment-submit"><i class="bi bi-check-circle-fill me-1"></i>ยืนยันรับชำระเงิน</button>
                                    <button type="submit" class="btn btn-outline-secondary" formaction="<?= e(route_url('payments-send-back')) ?>" formnovalidate>ส่งกลับห้องตรวจ</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="queue-empty-state payment-empty-state">
                    <h3 class="h5 mb-2">ไม่มีคิวรอรับชำระในขณะนี้</h3>
                    <p class="mb-0">เมื่อห้องตรวจสรุปรายการและส่งการเงิน เคสจะปรากฏในส่วนนี้ทันที</p>
                </div>
            <?php endif; ?>
        </section>

        <aside class="payment-sticky-panel">
            <section class="section-card payment-side-card">
                <div class="section-header compact align-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">ภาพรวมการเงินวันนี้</h2>
                        <p class="mb-0">สรุปยอดและแนวทางทำงานสั้น ๆ สำหรับใช้หน้าเคาน์เตอร์</p>
                    </div>
                </div>

                <div class="summary-total-card payment-overview-card mb-3">
                    <div class="summary-total-row">
                        <span>รอรับชำระ</span>
                        <strong><?= format_money($waitingTotal) ?></strong>
                    </div>
                    <div class="summary-total-row grand">
                        <span>รับชำระแล้ววันนี้</span>
                        <strong><?= format_money($completedTotal) ?></strong>
                    </div>
                </div>

                <div class="template-panel mb-3">
                    <div class="section-step-title mb-2">ขั้นตอนการทำงาน</div>
                    <div class="workflow-list payment-workflow-list">
                        <div class="workflow-list-item"><strong>1.</strong><span>เลือกเคสที่อยู่ในสถานะรอชำระเงิน</span></div>
                        <div class="workflow-list-item"><strong>2.</strong><span>ตรวจยอด ส่วนลด และวิธีชำระให้ถูกต้อง</span></div>
                        <div class="workflow-list-item"><strong>3.</strong><span>กดยืนยันรับชำระเงิน หรือส่งกลับห้องตรวจเมื่อข้อมูลยังไม่ครบ</span></div>
                    </div>
                </div>

                <div class="payment-side-block mb-3">
                    <div class="section-step-title mb-2">สถานะล่าสุด</div>
                    <?php if ($latestCompleted): ?>
                        <div class="payment-latest-card">
                            <div class="patient-result-title mb-1"><?= e($latestCompleted['first_name'] . ' ' . $latestCompleted['last_name']) ?></div>
                            <div class="payment-case-meta mb-2">ใบเสร็จ <?= e($latestCompleted['receipt_no'] ?: '-') ?></div>
                            <div class="summary-total-row grand pt-0 mt-0 border-0">
                                <span>ยอดรับชำระล่าสุด</span>
                                <strong><?= format_money($latestCompleted['payment_total'] ?? $latestCompleted['grand_total']) ?></strong>
                            </div>
                            <?php if ($latestCompleted['payment_id']): ?>
                                <a href="<?= e(route_url('receipt', ['id' => $latestCompleted['payment_id']])) ?>" target="_blank" class="btn btn-outline-secondary w-100 mt-2">เปิดใบเสร็จล่าสุด</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="queue-empty-state queue-empty-state-compact">ยังไม่มีรายการชำระเงินในวันนี้</div>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>

    <section class="section-card payment-history-card">
        <div class="panel-heading mb-4">
            <div>
                <h2 class="h4 mb-1">ประวัติรับชำระล่าสุด</h2>
                <p class="text-muted mb-0">ใช้ตรวจสอบยอดที่ชำระแล้วและเปิดใบเสร็จย้อนหลังได้ทันที</p>
            </div>
            <span class="soft-badge">ล่าสุด <?= count($completedRows) ?> รายการ</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle payment-history-table mb-0">
                <thead>
                <tr>
                    <th>คิว/VN</th>
                    <th>ผู้รับบริการ</th>
                    <th class="text-end">ยอดชำระ</th>
                    <th>เลขที่ใบเสร็จ</th>
                    <th class="text-end">ดำเนินการ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($completedRows as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold">คิว <?= e((string) $row['queue_no']) ?></div>
                            <div class="small text-muted"><?= e($row['visit_no']) ?></div>
                        </td>
                        <td><?= e($row['hn']) ?> - <?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td class="text-end fw-semibold"><?= format_money($row['payment_total'] ?? $row['grand_total']) ?></td>
                        <td><?= e($row['receipt_no'] ?: '-') ?></td>
                        <td class="text-end">
                            <?php if ($row['payment_id']): ?>
                                <a href="<?= e(route_url('receipt', ['id' => $row['payment_id']])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">เปิดใบเสร็จ</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$completedRows): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีรายการที่ชำระเสร็จแล้ว</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.payment-form');

    forms.forEach((form) => {
        const baseTotal = parseFloat(form.dataset.baseTotal || '0');
        const discountInput = form.querySelector('.payment-discount');
        const paidInput = form.querySelector('.payment-paid');
        const netTotalEl = form.querySelector('.payment-net-total');
        const changeTotalEl = form.querySelector('.payment-change-total');
        const warningEl = form.querySelector('.payment-warning');
        const submitBtn = form.querySelector('.payment-submit');

        const formatMoney = (value) => Number(value).toFixed(2);

        const updatePreview = () => {
            const discount = Math.max(0, parseFloat(discountInput.value || '0'));
            const paid = Math.max(0, parseFloat(paidInput.value || '0'));
            const netTotal = Math.max(0, baseTotal - discount);
            const change = Math.max(0, paid - netTotal);
            const isInvalid = paid < netTotal;

            netTotalEl.textContent = formatMoney(netTotal);
            changeTotalEl.textContent = formatMoney(change);
            warningEl.classList.toggle('d-none', !isInvalid);
            submitBtn.disabled = isInvalid;
        };

        discountInput.addEventListener('input', updatePreview);
        paidInput.addEventListener('input', updatePreview);
        updatePreview();
    });
});
</script>
