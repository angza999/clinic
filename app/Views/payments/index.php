<?php
$waitingRows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] === 'WAITING_PAYMENT'));
$completedRows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] === 'COMPLETED'));
$waitingTotal = array_sum(array_map(static fn(array $row): float => (float) $row['grand_total'], $waitingRows));
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="queue-stat queue-stat-payment">
            <div class="queue-stat-label">รอชำระเงิน</div>
            <div class="queue-stat-value"><?= count($waitingRows) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="queue-stat queue-stat-complete">
            <div class="queue-stat-label">ชำระแล้ววันนี้</div>
            <div class="queue-stat-value"><?= count($completedRows) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="queue-stat queue-stat-service">
            <div class="queue-stat-label">ยอดที่รอรับเงิน</div>
            <div class="queue-stat-value"><?= format_money($waitingTotal) ?></div>
        </div>
    </div>
</div>

<div class="card section-card mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h2 class="h5 mb-1">รายการรอชำระเงิน</h2>
        <div class="small text-muted">คำนวณยอดสุทธิและเงินทอนให้เห็นก่อนกดยืนยัน</div>
    </div>
    <div class="card-body px-4">
        <?php if ($waitingRows): ?>
            <div class="row g-4">
                <?php foreach ($waitingRows as $row): ?>
                    <div class="col-xl-6">
                        <div class="payment-case-card h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div class="small text-uppercase text-muted">คิว <?= e((string) $row['queue_no']) ?></div>
                                    <div class="h5 mb-1"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-muted"><?= e($row['hn']) ?> / <?= e($row['visit_no']) ?></div>
                                </div>
                                <span class="badge bg-secondary">รอชำระเงิน</span>
                            </div>

                            <div class="payment-breakdown mb-4">
                                <div class="d-flex justify-content-between"><span>ค่าบริการ</span><strong><?= format_money($row['total_service']) ?></strong></div>
                                <div class="d-flex justify-content-between"><span>ค่ายา/เวชภัณฑ์</span><strong><?= format_money($row['total_item']) ?></strong></div>
                                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                    <span>ยอดตั้งต้น</span>
                                    <strong class="fs-5"><?= format_money($row['grand_total']) ?></strong>
                                </div>
                            </div>

                            <form method="post" action="<?= e(route_url('payments-store')) ?>" class="row g-3 payment-form" data-base-total="<?= e((string) $row['grand_total']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="visit_id" value="<?= e((string) $row['visit_id']) ?>">

                                <div class="col-md-4">
                                    <label class="form-label">ส่วนลด</label>
                                    <input type="number" step="0.01" name="discount_amount" class="form-control payment-discount" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">รับชำระ</label>
                                    <input type="number" step="0.01" name="paid_amount" class="form-control payment-paid" value="<?= e((string) $row['grand_total']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">วิธีชำระ</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="CASH">เงินสด</option>
                                        <option value="TRANSFER">โอนเงิน</option>
                                        <option value="QR">QR</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <div class="payment-preview">
                                        <div class="d-flex justify-content-between">
                                            <span>ยอดสุทธิหลังหักส่วนลด</span>
                                            <strong class="payment-net-total"><?= format_money($row['grand_total']) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>เงินทอน</span>
                                            <strong class="payment-change-total">0.00</strong>
                                        </div>
                                        <div class="small text-danger mt-2 payment-warning d-none">จำนวนเงินรับชำระน้อยกว่ายอดสุทธิ</div>
                                    </div>
                                </div>

                                <div class="col-12 d-flex flex-column flex-lg-row gap-2">
                                    <button type="submit" class="btn btn-success btn-lg payment-submit">ยืนยันรับชำระเงิน</button>
                                    <a href="<?= e(route_url('visit-edit', ['id' => $row['visit_id']])) ?>" class="btn btn-outline-secondary">ดูแฟ้ม</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="queue-empty-state">ไม่มีรายการรอชำระเงิน</div>
        <?php endif; ?>
    </div>
</div>

<div class="card section-card">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h2 class="h5 mb-1">รายการชำระเงินแล้ว</h2>
        <div class="small text-muted">ใช้ตรวจสอบย้อนหลังและพิมพ์ใบเสร็จซ้ำ</div>
    </div>
    <div class="card-body px-4">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>คิว/Visit</th>
                    <th>ผู้รับบริการ</th>
                    <th class="text-end">ยอดสุทธิ</th>
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
                        <td class="text-end fw-semibold"><?= format_money($row['grand_total']) ?></td>
                        <td><?= e($row['receipt_no'] ?: '-') ?></td>
                        <td class="text-end">
                            <?php if ($row['payment_id']): ?>
                                <a href="<?= e(route_url('receipt', ['id' => $row['payment_id']])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">เปิดใบเสร็จ</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$completedRows): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีรายการที่ชำระแล้ว</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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
