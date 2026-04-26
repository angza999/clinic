<?php
$clinicName = (string) system_setting('clinic_name', config('app.name'));
$clinicAddress = (string) system_setting('clinic_address', '');
$clinicPhone = (string) system_setting('clinic_phone', '');
$receiptFooter = (string) system_setting('queue_note', config('app.receipt_footer'));
?>

<div class="row justify-content-center receipt-sheet-wrap">
    <div class="col-xl-9">
        <div class="card border-0 shadow-sm receipt-sheet">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                    <div>
                        <div class="small text-uppercase text-muted">ใบเสร็จรับเงิน / ใบรับบริการ</div>
                        <h1 class="h3 mb-2"><?= e($clinicName) ?></h1>
                        <?php if ($clinicAddress !== ''): ?>
                            <div class="text-muted"><?= nl2br(e($clinicAddress)) ?></div>
                        <?php endif; ?>
                        <?php if ($clinicPhone !== ''): ?>
                            <div class="text-muted">โทร <?= e($clinicPhone) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-lg-end">
                        <div class="small text-uppercase text-muted">เลขที่ใบเสร็จ</div>
                        <div class="h4 mb-2"><?= e($payment['receipt_no']) ?></div>
                        <div class="small text-muted">วันที่ชำระ <?= thai_date($payment['paid_at']) ?></div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="small text-muted mb-2">ผู้รับบริการ</div>
                            <div><strong>HN:</strong> <?= e($payment['hn']) ?></div>
                            <div><strong>ชื่อ:</strong> <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
                            <div><strong>VN:</strong> <?= e($payment['visit_no']) ?></div>
                            <div><strong>วันที่รับบริการ:</strong> <?= thai_date($payment['visit_datetime']) ?></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="template-panel h-100">
                            <div class="small text-muted mb-2">การรับชำระ</div>
                            <div><strong>ผู้รับเงิน:</strong> <?= e($payment['cashier_name'] ?: '-') ?></div>
                            <div><strong>วิธีชำระ:</strong> <?= e($payment['payment_method']) ?></div>
                            <div><strong>ยอดสุทธิ:</strong> <?= format_money($payment['total_amount']) ?> บาท</div>
                            <div><strong>รับชำระ:</strong> <?= format_money($payment['paid_amount']) ?> บาท</div>
                            <div><strong>เงินทอน:</strong> <?= format_money($payment['change_amount']) ?> บาท</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table align-middle receipt-table">
                        <thead>
                        <tr>
                            <th>รายการ</th>
                            <th class="text-end">จำนวน</th>
                            <th class="text-end">ราคา/หน่วย</th>
                            <th class="text-end">รวม</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($serviceLines as $line): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($line['item_name']) ?></div>
                                    <div class="small text-muted">บริการ</div>
                                </td>
                                <td class="text-end"><?= format_money($line['qty']) ?></td>
                                <td class="text-end"><?= format_money($line['unit_price']) ?></td>
                                <td class="text-end"><?= format_money($line['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($itemLines as $line): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($line['item_name']) ?></div>
                                    <div class="small text-muted">ยา / เวชภัณฑ์</div>
                                </td>
                                <td class="text-end"><?= format_money($line['qty']) ?></td>
                                <td class="text-end"><?= format_money($line['unit_price']) ?></td>
                                <td class="text-end"><?= format_money($line['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceLines && !$itemLines): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">ไม่มีรายการค่าบริการหรือยาในใบเสร็จนี้</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row justify-content-end mb-4">
                    <div class="col-lg-5">
                        <div class="payment-preview">
                            <div class="d-flex justify-content-between"><span>ค่าบริการ</span><strong><?= format_money($payment['subtotal_service']) ?></strong></div>
                            <div class="d-flex justify-content-between mt-2"><span>ค่ายา/เวชภัณฑ์</span><strong><?= format_money($payment['subtotal_item']) ?></strong></div>
                            <div class="d-flex justify-content-between mt-2"><span>ส่วนลด</span><strong><?= format_money($payment['discount_amount']) ?></strong></div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-3"><span>ยอดสุทธิ</span><strong class="fs-5"><?= format_money($payment['total_amount']) ?></strong></div>
                        </div>
                    </div>
                </div>

                <?php if ($receiptFooter !== ''): ?>
                    <div class="text-center text-muted mb-4"><?= nl2br(e($receiptFooter)) ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-center gap-2 no-print flex-wrap">
                    <a href="<?= e(route_url('payments')) ?>" class="btn btn-outline-secondary">กลับไปหน้าการเงิน</a>
                    <button class="btn btn-primary" onclick="window.print()">พิมพ์ใบเสร็จ</button>
                </div>
            </div>
        </div>
    </div>
</div>