<?php
$visit = $visit ?? [];
$prescription = $prescription ?? [];
$items = $items ?? [];
$printLogs = $printLogs ?? [];
$labelSize = (string) ($labelSize ?? '58x40');
$clinicName = (string) ($clinicName ?? 'ดงมหาวันคลินิก');
$fullName = trim((string) (($visit['first_name'] ?? '') . ' ' . ($visit['last_name'] ?? '')));
$printItemIds = array_map(static fn(array $item): int => (int) $item['id'], $items);
$queueNo = (string) ($visit['queue_no'] ?? '-');
?>

<div class="pharmacy-label-page" data-print-log-url="<?= e(route_url('pharmacy-print-log')) ?>">
    <section class="pharmacy-command">
        <div>
            <div class="pharmacy-kicker">Pharmacy Workstation</div>
            <h1>พิมพ์สติ๊กเกอร์ซองยา</h1>
            <p>ตรวจฉลากก่อนพิมพ์ ใช้ browser print เพื่อรองรับเครื่องพิมพ์สติ๊กเกอร์พื้นฐาน</p>
        </div>
        <div class="pharmacy-command-actions">
            <a href="<?= e(route_url('queue-exam', ['id' => (int) ($visit['id'] ?? 0)])) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> กลับ Smart Exam
            </a>
            <button type="button" class="btn btn-primary pharmacy-print-button" data-pharmacy-print <?= empty($items) ? 'disabled' : '' ?>>
                <i class="bi bi-printer-fill"></i> พิมพ์สติ๊กเกอร์ยา
            </button>
        </div>
    </section>

    <section class="pharmacy-patient-strip">
        <div>
            <span>คิว <?= e($queueNo) ?></span>
            <strong><?= e($fullName !== '' ? $fullName : '-') ?></strong>
            <small>HN <?= e((string) ($visit['hn'] ?? '-')) ?> / VN <?= e((string) ($visit['visit_no'] ?? '-')) ?></small>
        </div>
        <div class="pharmacy-status-grid">
            <span><b><?= e((string) count($items)) ?></b> ฉลาก</span>
            <span><b><?= e((string) ($prescription['status'] ?? 'DRAFT')) ?></b> สถานะ</span>
            <span><b><?= e(thai_date_only(date('Y-m-d'))) ?></b> วันที่</span>
        </div>
    </section>

    <div class="pharmacy-workspace">
        <main class="pharmacy-preview-surface">
            <div class="pharmacy-preview-toolbar">
                <div>
                    <div class="pharmacy-section-title">Label Preview</div>
                    <span>ขนาดจริงสำหรับพิมพ์สติ๊กเกอร์</span>
                </div>
                <form method="get" action="<?= e(route_url('pharmacy-labels')) ?>" class="pharmacy-size-form">
                    <input type="hidden" name="page" value="pharmacy-labels">
                    <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                    <label for="labelSize">ขนาด</label>
                    <select name="size" id="labelSize" onchange="this.form.submit()">
                        <option value="58x40" <?= selected($labelSize, '58x40') ?>>58 x 40 mm</option>
                        <option value="80x50" <?= selected($labelSize, '80x50') ?>>80 x 50 mm</option>
                        <option value="100x75" <?= selected($labelSize, '100x75') ?>>100 x 75 mm</option>
                    </select>
                </form>
            </div>

            <?php if ($items): ?>
                <div class="pharmacy-label-sheet label-size-<?= e($labelSize) ?>">
                    <?php foreach ($items as $item): ?>
                        <article class="drug-label" data-prescription-item-id="<?= e((string) $item['id']) ?>">
                            <header>
                                <strong><?= e($clinicName) ?></strong>
                                <span>HN: <?= e((string) ($visit['hn'] ?? '-')) ?></span>
                            </header>
                            <div class="drug-label-patient">
                                <strong><?= e($fullName !== '' ? $fullName : '-') ?></strong>
                                <span><?= e(thai_date_only(date('Y-m-d'))) ?></span>
                            </div>
                            <div class="drug-label-name"><?= e((string) $item['drug_name_snapshot']) ?></div>
                            <div class="drug-label-instruction"><?= nl2br(e((string) ($item['instruction_text'] ?? 'ใช้ยาตามคำแนะนำของเจ้าหน้าที่'))) ?></div>
                            <div class="drug-label-qty">จำนวน <?= format_money($item['qty'] ?? 0) ?> <?= e((string) ($item['unit_name'] ?? '')) ?></div>
                            <?php if (!empty($item['warning_text'])): ?>
                                <div class="drug-label-warning">คำเตือน: <?= nl2br(e((string) $item['warning_text'])) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="pharmacy-empty">
                    <i class="bi bi-capsule"></i>
                    <strong>ยังไม่มีรายการยาสำหรับพิมพ์สติ๊กเกอร์</strong>
                    <span>กลับไป Smart Exam แล้วเพิ่มยา จากนั้นกลับมาพิมพ์สติ๊กเกอร์อีกครั้ง</span>
                </div>
            <?php endif; ?>
        </main>

        <aside class="pharmacy-control-rail">
            <section class="pharmacy-panel">
                <div class="pharmacy-section-title">รายการพร้อมพิมพ์</div>
                <div class="pharmacy-drug-list">
                    <?php foreach ($items as $item): ?>
                        <div class="pharmacy-drug-row">
                            <div>
                                <strong><?= e((string) $item['drug_name_snapshot']) ?></strong>
                                <span><?= e((string) ($item['instruction_text'] ?? 'ใช้ยาตามคำแนะนำ')) ?></span>
                            </div>
                            <em><?= (int) ($item['print_count'] ?? 0) > 0 ? 'พิมพ์แล้ว' : 'รอพิมพ์' ?></em>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$items): ?>
                        <div class="pharmacy-empty-compact">ยังไม่มีรายการยา</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="pharmacy-panel">
                <div class="pharmacy-section-title">ประวัติการพิมพ์</div>
                <div class="pharmacy-log-list" id="pharmacyPrintLogList">
                    <?php foreach ($printLogs as $log): ?>
                        <div class="pharmacy-log-row">
                            <strong><?= e((string) $log['drug_name_snapshot']) ?></strong>
                            <span><?= e($log['status'] === 'REPRINT' ? 'พิมพ์ซ้ำ' : 'พิมพ์แล้ว') ?> / <?= thai_date($log['printed_at'] ?? null) ?></span>
                            <small><?= e((string) (($log['printed_by_name'] ?? '') ?: '-')) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$printLogs): ?>
                        <div class="pharmacy-empty-compact">ยังไม่มีประวัติการพิมพ์</div>
                    <?php endif; ?>
                </div>
            </section>

            <form id="pharmacyPrintLogForm" class="pharmacy-hidden-form">
                <?= csrf_field() ?>
                <input type="hidden" name="visit_id" value="<?= e((string) ($visit['id'] ?? 0)) ?>">
                <input type="hidden" name="label_size" value="<?= e($labelSize) ?>">
                <?php foreach ($printItemIds as $itemId): ?>
                    <input type="hidden" name="prescription_item_ids[]" value="<?= e((string) $itemId) ?>">
                <?php endforeach; ?>
            </form>

            <div class="pharmacy-print-tip">
                <i class="bi bi-info-circle"></i>
                ตั้งค่าหน้าพิมพ์เป็น Scale 100% และปิด Header/Footer ของ browser เพื่อให้ขนาดฉลากตรงที่สุด
            </div>
        </aside>
    </div>
</div>
