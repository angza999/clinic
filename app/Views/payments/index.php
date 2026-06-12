<?php
$waitingRows = $waitingRows ?? [];
$receiptRows = $receiptRows ?? [];
$todaySummary = $todaySummary ?? [];
$methodSummary = $methodSummary ?? [];
$latestReceipt = $latestReceipt ?? null;
$waitingTotal = (float) ($waitingTotal ?? 0);
$paidToday = (float) ($todaySummary['paid_total'] ?? 0);
$paidCount = (int) ($todaySummary['paid_count'] ?? 0);
$voidCount = (int) ($todaySummary['void_count'] ?? 0);
$discountToday = (float) ($todaySummary['discount_total'] ?? 0);
$methodLabels = [
    'CASH' => 'เงินสด',
    'TRANSFER' => 'โอน',
    'QR' => 'QR',
];
$methodIcon = [
    'CASH' => 'bi-cash-stack',
    'TRANSFER' => 'bi-bank',
    'QR' => 'bi-qr-code',
];
$receiptUrl = static fn(?array $row): string => $row && !empty($row['payment_id'])
    ? route_url('receipt', ['id' => $row['payment_id'], 'source' => 'payments'])
    : '#';
?>

<div class="cashier-workstation">
    <section class="cashier-command-surface">
        <div class="cashier-title-block">
            <span class="eyebrow">Cashier Workstation</span>
            <h2>รับชำระ ปิดเคส และเปิดใบเสร็จ</h2>
            <p>โฟกัสคิวรอชำระก่อน ตรวจยอดให้ชัด แล้วออกใบเสร็จได้ทันที</p>
        </div>

        <div class="cashier-kpi-grid">
            <button type="button" class="cashier-kpi-card is-payment" data-payment-focus="#paymentQueue">
                <span>รอชำระ</span>
                <strong><?= count($waitingRows) ?></strong>
                <small><?= format_money($waitingTotal) ?> บาท</small>
            </button>
            <button type="button" class="cashier-kpi-card is-paid" data-payment-focus="#receiptHistory">
                <span>ชำระแล้ววันนี้</span>
                <strong><?= $paidCount ?></strong>
                <small><?= format_money($paidToday) ?> บาท</small>
            </button>
            <button type="button" class="cashier-kpi-card is-cash" data-payment-method-shortcut="CASH">
                <span>เงินสดวันนี้</span>
                <strong><?= (int) ($methodSummary['CASH']['method_count'] ?? 0) ?></strong>
                <small><?= format_money((float) ($methodSummary['CASH']['method_total'] ?? 0)) ?> บาท</small>
            </button>
            <button type="button" class="cashier-kpi-card is-transfer" data-payment-method-shortcut="TRANSFER">
                <span>โอน/QR วันนี้</span>
                <strong><?= (int) ($methodSummary['TRANSFER']['method_count'] ?? 0) + (int) ($methodSummary['QR']['method_count'] ?? 0) ?></strong>
                <small><?= format_money((float) ($methodSummary['TRANSFER']['method_total'] ?? 0) + (float) ($methodSummary['QR']['method_total'] ?? 0)) ?> บาท</small>
            </button>
            <div class="cashier-kpi-card is-muted">
                <span>ยกเลิก</span>
                <strong><?= $voidCount ?></strong>
                <small>ต้องใช้สิทธิ์ Admin</small>
            </div>
        </div>
    </section>

    <section class="cashier-control-bar">
        <div class="cashier-search">
            <i class="bi bi-search"></i>
            <input type="search" id="paymentSearch" placeholder="ค้นหา VN / HN / ชื่อ / เลขใบเสร็จ">
        </div>
        <input type="date" id="paymentDateFilter" class="cashier-control-input" value="<?= e(date('Y-m-d')) ?>" aria-label="กรองวันที่">
        <select id="paymentMethodFilter" class="cashier-control-input" aria-label="กรองวิธีชำระ">
            <option value="">ทุกวิธีชำระ</option>
            <option value="CASH">เงินสด</option>
            <option value="TRANSFER">โอน</option>
            <option value="QR">QR</option>
        </select>
        <a class="btn btn-outline-secondary cashier-control-btn" href="<?= e($receiptUrl($latestReceipt)) ?>" <?= $latestReceipt ? 'target="_blank"' : 'aria-disabled="true"' ?>>
            <i class="bi bi-printer"></i> พิมพ์ล่าสุด
        </a>
        <a class="btn btn-outline-secondary cashier-control-btn" href="<?= e(route_url('reports')) ?>">
            <i class="bi bi-file-earmark-bar-graph"></i> รายงาน
        </a>
    </section>

    <div class="cashier-layout">
        <main class="cashier-main">
            <section class="cashier-panel" id="paymentQueue">
                <div class="cashier-panel-header">
                    <div>
                        <span class="panel-kicker">Payment Queue</span>
                        <h3>คิวรอรับชำระ</h3>
                    </div>
                    <span class="cashier-count-badge"><?= count($waitingRows) ?> คิว</span>
                </div>

                <?php if ($waitingRows): ?>
                    <div class="payment-queue-list">
                        <?php foreach ($waitingRows as $row): ?>
                            <?php
                            $patientName = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
                            $grandTotal = (float) $row['grand_total'];
                            $lineCount = (int) $row['service_count'] + (int) $row['item_count'];
                            $searchText = strtolower($patientName . ' ' . $row['hn'] . ' ' . $row['visit_no'] . ' Q' . $row['queue_no']);
                            ?>
                            <article
                                class="payment-queue-card"
                                data-payment-card
                                data-search="<?= e($searchText) ?>"
                                data-method=""
                                data-date="<?= e(substr((string) $row['visit_datetime'], 0, 10)) ?>"
                            >
                                <div class="queue-card-summary">
                                    <div class="queue-token">Q<?= e((string) $row['queue_no']) ?></div>
                                    <div class="queue-patient">
                                        <div class="queue-patient-line">
                                            <strong><?= e($patientName) ?></strong>
                                            <span class="status-badge status-payment">รอชำระ</span>
                                        </div>
                                        <div class="queue-meta">
                                            HN <?= e($row['hn']) ?> · VN <?= e($row['visit_no']) ?> · ส่งมา <?= e(thai_date($row['sent_to_payment_at'] ?? $row['visit_datetime'])) ?>
                                        </div>
                                    </div>
                                    <div class="queue-total">
                                        <span>ยอดรวม</span>
                                        <strong><?= format_money($grandTotal) ?></strong>
                                    </div>
                                    <div class="queue-total is-subtle">
                                        <span>รายการ</span>
                                        <strong><?= $lineCount ?></strong>
                                    </div>
                                </div>

                                <form method="post" action="<?= e(route_url('payments-store')) ?>" class="cashier-payment-form" data-base-total="<?= e((string) $grandTotal) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visit_id" value="<?= e((string) $row['visit_id']) ?>">

                                    <div class="cashier-payment-grid">
                                        <label>
                                            <span>วิธีชำระ</span>
                                            <select name="payment_method" class="form-select payment-method">
                                                <option value="CASH">เงินสด</option>
                                                <option value="TRANSFER">โอน</option>
                                                <option value="QR">QR</option>
                                            </select>
                                        </label>
                                        <label>
                                            <span>ส่วนลด</span>
                                            <input type="number" step="0.01" min="0" name="discount_amount" class="form-control payment-discount" value="0">
                                        </label>
                                        <label>
                                            <span>รับเงิน</span>
                                            <input type="number" step="0.01" min="0" name="paid_amount" class="form-control payment-paid" value="<?= e((string) $grandTotal) ?>" required>
                                        </label>
                                        <div class="payment-live-total">
                                            <span>ยอดสุทธิ</span>
                                            <strong class="payment-net-total"><?= format_money($grandTotal) ?></strong>
                                        </div>
                                        <div class="payment-live-total">
                                            <span>เงินทอน</span>
                                            <strong class="payment-change-total">0.00</strong>
                                        </div>
                                    </div>

                                    <div class="payment-warning d-none">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <span>ตรวจยอดอีกครั้ง: เงินสดต้องรับเงินไม่น้อยกว่ายอดสุทธิ และส่วนลดต้องไม่เกินยอดรวม</span>
                                    </div>

                                    <div class="cashier-payment-actions">
                                        <button type="submit" class="btn btn-primary payment-submit">
                                            <i class="bi bi-check-circle-fill"></i> ยืนยันรับชำระ
                                        </button>
                                        <button type="submit" class="btn btn-outline-secondary" formaction="<?= e(route_url('payments-send-back')) ?>" formnovalidate data-skip-payment-confirm>
                                            ส่งกลับห้องตรวจ
                                        </button>
                                        <a class="btn btn-link" href="<?= e(route_url('visit-edit', ['id' => $row['visit_id']])) ?>">
                                            ประวัติเคส
                                        </a>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="cashier-empty-state">
                        <div class="empty-icon"><i class="bi bi-check2-circle"></i></div>
                        <h3>ไม่มีคิวรอชำระตอนนี้</h3>
                        <p>ใช้ช่องค้นหาเพื่อเปิดใบเสร็จย้อนหลัง หรือกลับไปหน้าคิวเพื่อตรวจสถานะเคส</p>
                        <div class="empty-actions">
                            <button type="button" class="btn btn-outline-secondary" data-payment-focus="#receiptHistory">
                                <i class="bi bi-receipt"></i> ดูประวัติล่าสุด
                            </button>
                            <a class="btn btn-primary" href="<?= e(route_url('queue')) ?>">
                                <i class="bi bi-grid-1x2-fill"></i> ไปหน้าคิววันนี้
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="cashier-panel" id="receiptHistory">
                <div class="cashier-panel-header">
                    <div>
                        <span class="panel-kicker">Receipt History</span>
                        <h3>ประวัติรับชำระล่าสุด</h3>
                    </div>
                    <span class="cashier-count-badge"><?= count($receiptRows) ?> รายการ</span>
                </div>

                <div class="cashier-table-wrap">
                    <table class="cashier-table">
                        <thead>
                        <tr>
                            <th>VN / คิว</th>
                            <th>ผู้รับบริการ</th>
                            <th>วิธีชำระ</th>
                            <th class="text-end">ยอดสุทธิ</th>
                            <th>ใบเสร็จ</th>
                            <th>เวลา</th>
                            <th class="text-end">ดำเนินการ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($receiptRows as $row): ?>
                            <?php
                            $patientName = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
                            $method = (string) ($row['payment_method'] ?? 'CASH');
                            $status = (string) ($row['payment_status'] ?? 'PAID');
                            $searchText = strtolower($patientName . ' ' . $row['hn'] . ' ' . $row['visit_no'] . ' ' . $row['receipt_no'] . ' Q' . $row['queue_no']);
                            ?>
                            <tr
                                data-payment-row
                                data-search="<?= e($searchText) ?>"
                                data-method="<?= e($method) ?>"
                                data-date="<?= e(substr((string) $row['paid_at'], 0, 10)) ?>"
                            >
                                <td>
                                    <strong><?= e($row['visit_no']) ?></strong>
                                    <small>Q<?= e((string) ($row['queue_no'] ?? '-')) ?></small>
                                </td>
                                <td>
                                    <strong><?= e($patientName) ?></strong>
                                    <small>HN <?= e($row['hn']) ?></small>
                                </td>
                                <td>
                                    <span class="method-badge">
                                        <i class="bi <?= e($methodIcon[$method] ?? 'bi-credit-card') ?>"></i>
                                        <?= e($methodLabels[$method] ?? $method) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold"><?= format_money($row['total_amount']) ?></td>
                                <td>
                                    <strong><?= e($row['receipt_no']) ?></strong>
                                    <?php if ($status === 'VOID'): ?>
                                        <span class="status-badge status-void">ยกเลิก</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e(thai_date($row['paid_at'])) ?>
                                    <small><?= e($row['cashier_name'] ?: '-') ?></small>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e($receiptUrl($row)) ?>" target="_blank">
                                        เปิดใบเสร็จ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$receiptRows): ?>
                            <tr>
                                <td colspan="7" class="table-empty">ยังไม่มีประวัติรับชำระ</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside class="cashier-rail">
            <section class="cashier-rail-card">
                <div class="rail-header">
                    <span>Financial Action Rail</span>
                    <strong>วันนี้</strong>
                </div>

                <div class="rail-total">
                    <span>ยอดรับชำระวันนี้</span>
                    <strong><?= format_money($paidToday) ?></strong>
                </div>

                <div class="rail-metrics">
                    <div>
                        <span>ใบเสร็จ</span>
                        <strong><?= $paidCount ?></strong>
                    </div>
                    <div>
                        <span>ส่วนลด</span>
                        <strong><?= format_money($discountToday) ?></strong>
                    </div>
                </div>

                <div class="rail-divider"></div>

                <div class="rail-section">
                    <h4>ใบเสร็จล่าสุด</h4>
                    <?php if ($latestReceipt): ?>
                        <div class="latest-receipt">
                            <strong><?= e($latestReceipt['first_name'] . ' ' . $latestReceipt['last_name']) ?></strong>
                            <span><?= e($latestReceipt['receipt_no']) ?> · <?= format_money($latestReceipt['total_amount']) ?></span>
                            <a class="btn btn-outline-secondary w-100" href="<?= e($receiptUrl($latestReceipt)) ?>" target="_blank">
                                <i class="bi bi-printer"></i> เปิด/พิมพ์ซ้ำ
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="rail-empty">ยังไม่มีใบเสร็จวันนี้</div>
                    <?php endif; ?>
                </div>

                <div class="rail-section">
                    <h4>Quick Actions</h4>
                    <button type="button" class="rail-action" data-payment-focus="#paymentSearch">
                        <i class="bi bi-search"></i> ค้นหาใบเสร็จ
                    </button>
                    <a class="rail-action" href="<?= e(route_url('reports')) ?>">
                        <i class="bi bi-download"></i> Export/รายงาน
                    </a>
                    <?php if (has_role('ADMIN')): ?>
                        <button type="button" class="rail-action is-disabled" disabled title="เตรียมสำหรับ Phase ถัดไป">
                            <i class="bi bi-arrow-counterclockwise"></i> Refund / ยกเลิก
                        </button>
                    <?php endif; ?>
                </div>

                <div class="rail-section">
                    <h4>Alerts</h4>
                    <?php if ($waitingRows): ?>
                        <div class="rail-alert is-warning">
                            <i class="bi bi-clock-history"></i>
                            มี <?= count($waitingRows) ?> คิวรอชำระ ยอดรวม <?= format_money($waitingTotal) ?>
                        </div>
                    <?php else: ?>
                        <div class="rail-alert is-ok">
                            <i class="bi bi-check-circle"></i>
                            ไม่มีคิวค้างชำระ
                        </div>
                    <?php endif; ?>
                    <div class="rail-alert is-info">
                        <i class="bi bi-info-circle"></i>
                        วิธีชำระที่รองรับตอนนี้: เงินสด, โอน, QR
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <section class="cashier-shortcut-bar">
        <span><i class="bi bi-lightning-charge-fill"></i> Shortcut</span>
        <kbd>F1</kbd> ค้นหา
        <kbd>F9</kbd> ไปคิวรอชำระ
        <kbd>Esc</kbd> ล้างตัวกรอง
    </section>
</div>
