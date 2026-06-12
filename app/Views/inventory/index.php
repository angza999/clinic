<?php
$lowStockItems = [];
$nearExpiryBatches = [];
$expiredBatches = [];
$stockValue = 0.0;
$saleValue = 0.0;
$receivedToday = (float) ($receivedToday ?? 0);
$consumptionTrend = $consumptionTrend ?? [];
$today = strtotime(date('Y-m-d'));
$nearExpiryLimit = strtotime('+30 days');
$batchByItem = [];

foreach ($batches as $batch) {
    $itemId = (int) ($batch['item_id'] ?? 0);
    if (!isset($batchByItem[$itemId])) {
        $batchByItem[$itemId] = [];
    }
    $batchByItem[$itemId][] = $batch;

    $expiryTime = empty($batch['expiry_date']) ? null : strtotime((string) $batch['expiry_date']);
    if (!$expiryTime || (float) $batch['qty_balance'] <= 0) {
        continue;
    }
    if ($expiryTime < $today) {
        $expiredBatches[] = $batch;
    } elseif ($expiryTime <= $nearExpiryLimit) {
        $nearExpiryBatches[] = $batch;
    }
}

foreach ($items as $item) {
    $qtyBalance = (float) ($item['qty_balance'] ?? 0);
    $reorderLevel = (float) ($item['reorder_level'] ?? 0);
    $stockValue += $qtyBalance * (float) ($item['default_cost'] ?? 0);
    $saleValue += $qtyBalance * (float) ($item['default_price'] ?? 0);

    if ($reorderLevel > 0 && $qtyBalance <= $reorderLevel) {
        $lowStockItems[] = $item;
    }
}

$lowStockCount = count($lowStockItems);
$nearExpiryCount = count($nearExpiryBatches);
$expiredCount = count($expiredBatches);
$reorderCount = $lowStockCount + $nearExpiryCount + $expiredCount;
$estimatedProfit = $saleValue - $stockValue;
$maxUsed = 0.0;
foreach ($consumptionTrend as $trend) {
    $maxUsed = max($maxUsed, (float) ($trend['total_used'] ?? 0));
}

$expiryLabel = static function (?string $date) use ($today): array {
    if (!$date) {
        return ['ไม่มีวันหมดอายุ', 'muted', null];
    }
    $expiryTime = strtotime($date);
    if (!$expiryTime) {
        return ['วันที่ไม่ถูกต้อง', 'danger', null];
    }
    $days = (int) floor(($expiryTime - $today) / 86400);
    if ($days < 0) {
        return ['หมดอายุแล้ว', 'danger', $days];
    }
    if ($days <= 30) {
        return ['เหลืออีก ' . $days . ' วัน', 'warning', $days];
    }
    return ['เหลืออีก ' . $days . ' วัน', 'ok', $days];
};

$stockRatio = static function (float $qty, float $reorder): int {
    $base = max($reorder * 3, $qty, 1);
    return (int) min(100, max(5, round(($qty / $base) * 100)));
};
?>

<div class="inventory-command" data-inventory-workstation>
    <section class="inventory-command-hero">
        <div>
            <div class="inventory-eyebrow">Medical Inventory Command Center</div>
            <h1>คลังยา / เวชภัณฑ์</h1>
            <p>ดูสถานะคลัง ค้นหา รับเข้า ปรับสต๊อก ตรวจวันหมดอายุ และดูประวัติการเคลื่อนไหวจากหน้าจอเดียว</p>
        </div>
        <div class="inventory-hero-actions">
            <?php if (has_role('ADMIN')): ?>
                <button type="button" class="inventory-secondary-action" data-open-inventory-modal="item">
                    <i class="bi bi-plus-circle"></i> เพิ่มรายการ
                </button>
                <button type="button" class="inventory-primary-action" data-open-inventory-modal="receive">
                    <i class="bi bi-box-arrow-in-down"></i> รับสินค้าเข้า
                </button>
            <?php endif; ?>
            <a class="inventory-secondary-action" href="<?= e(route_url('import')) ?>">
                <i class="bi bi-file-earmark-spreadsheet"></i> Import Excel
            </a>
        </div>
    </section>

    <section class="inventory-kpi-grid" aria-label="สรุปคลัง">
        <button type="button" class="inventory-kpi-card is-normal" data-inventory-filter-jump="all">
            <span class="inventory-kpi-icon"><i class="bi bi-box-seam"></i></span>
            <span>รายการทั้งหมด</span>
            <strong><?= e((string) count($items)) ?></strong>
            <small>ยาและเวชภัณฑ์ที่อยู่ในระบบ</small>
        </button>
        <button type="button" class="inventory-kpi-card is-low" data-inventory-filter-jump="low">
            <span class="inventory-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <span>ใกล้หมด</span>
            <strong><?= e((string) $lowStockCount) ?></strong>
            <small>ต่ำกว่าหรือเท่าจุดเตือน</small>
        </button>
        <button type="button" class="inventory-kpi-card is-expiring" data-inventory-filter-jump="near-expiry">
            <span class="inventory-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
            <span>ใกล้หมดอายุ</span>
            <strong><?= e((string) $nearExpiryCount) ?></strong>
            <small>ภายใน 30 วัน</small>
        </button>
        <button type="button" class="inventory-kpi-card is-expired" data-inventory-filter-jump="expired">
            <span class="inventory-kpi-icon"><i class="bi bi-x-octagon"></i></span>
            <span>หมดอายุ</span>
            <strong><?= e((string) $expiredCount) ?></strong>
            <small>ควรแยกออกทันที</small>
        </button>
        <div class="inventory-kpi-card is-value">
            <span class="inventory-kpi-icon"><i class="bi bi-cash-coin"></i></span>
            <span>มูลค่าสต๊อก</span>
            <strong><?= format_money($stockValue) ?></strong>
            <small>คำนวณจากต้นทุนคงเหลือ</small>
        </div>
        <div class="inventory-kpi-card is-receive">
            <span class="inventory-kpi-icon"><i class="bi bi-truck"></i></span>
            <span>รับเข้าวันนี้</span>
            <strong><?= format_money($receivedToday) ?></strong>
            <small>จำนวนรับเข้ารวมวันนี้</small>
        </div>
    </section>

    <section class="inventory-control-bar">
        <div class="inventory-search-wrap">
            <i class="bi bi-upc-scan"></i>
            <input type="search" id="inventorySearch" placeholder="ค้นหายา เวชภัณฑ์ Lot หรือ Barcode" autocomplete="off">
        </div>
        <div class="inventory-filter-group" aria-label="ตัวกรองคลัง">
            <button type="button" class="inventory-filter is-active" data-inventory-filter="all">ทั้งหมด</button>
            <button type="button" class="inventory-filter" data-inventory-filter="DRUG">ยา</button>
            <button type="button" class="inventory-filter" data-inventory-filter="SUPPLY">เวชภัณฑ์</button>
            <button type="button" class="inventory-filter" data-inventory-filter="low">ใกล้หมด</button>
            <button type="button" class="inventory-filter" data-inventory-filter="near-expiry">ใกล้หมดอายุ</button>
            <button type="button" class="inventory-filter" data-inventory-filter="expired">หมดอายุ</button>
        </div>
    </section>

    <div class="inventory-layout">
        <main class="inventory-main">
            <section class="inventory-panel inventory-table-panel">
                <div class="inventory-section-header">
                    <div>
                        <div class="inventory-eyebrow">Inventory Table</div>
                        <h2><i class="bi bi-table"></i> รายการคลัง</h2>
                    </div>
                    <span class="inventory-count-pill"><span data-inventory-visible-count><?= e((string) count($items)) ?></span> รายการ</span>
                </div>

                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อสินค้า</th>
                            <th>ประเภท</th>
                            <th>คงเหลือ</th>
                            <th>จุดเตือน</th>
                            <th>Lot ล่าสุด</th>
                            <th>วันหมดอายุ</th>
                            <th>สถานะ</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $qtyBalance = (float) ($item['qty_balance'] ?? 0);
                            $reorderLevel = (float) ($item['reorder_level'] ?? 0);
                            $isLowStock = $reorderLevel > 0 && $qtyBalance <= $reorderLevel;
                            $expiry = $expiryLabel((string) ($item['nearest_expiry'] ?? ''));
                            $isExpired = $expiry[1] === 'danger';
                            $isNearExpiry = $expiry[1] === 'warning';
                            $status = $isExpired ? 'expired' : ($isLowStock ? 'low' : ($isNearExpiry ? 'near-expiry' : 'normal'));
                            $ratio = $stockRatio($qtyBalance, $reorderLevel);
                            $latestLot = (string) ($item['latest_lot'] ?: '-');
                            $searchText = strtolower(implode(' ', [
                                $item['item_code'] ?? '',
                                $item['item_name'] ?? '',
                                $item['unit_name'] ?? '',
                                $item['item_type'] ?? '',
                                $item['nearest_expiry'] ?? '',
                                $latestLot,
                            ]));
                            ?>
                            <tr
                                data-inventory-row
                                data-item-id="<?= e((string) $item['id']) ?>"
                                data-item-name="<?= e((string) $item['item_name']) ?>"
                                data-item-type="<?= e((string) $item['item_type']) ?>"
                                data-item-unit="<?= e((string) $item['unit_name']) ?>"
                                data-item-cost="<?= e((string) $item['default_cost']) ?>"
                                data-item-price="<?= e((string) $item['default_price']) ?>"
                                data-item-stock="<?= e((string) $qtyBalance) ?>"
                                data-item-status="<?= e($status) ?>"
                                data-item-search="<?= e($searchText) ?>"
                            >
                                <td><span class="inventory-code"><?= e((string) $item['item_code']) ?></span></td>
                                <td>
                                    <strong><?= e((string) $item['item_name']) ?></strong>
                                    <small><?= e((string) $item['unit_name']) ?> · ราคา <?= format_money($item['default_price'] ?? 0) ?></small>
                                </td>
                                <td>
                                    <span class="inventory-type <?= e($item['item_type'] === 'DRUG' ? 'is-drug' : 'is-supply') ?>">
                                        <?= e($item['item_type'] === 'DRUG' ? 'ยา' : 'เวชภัณฑ์') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stock-meter">
                                        <div class="stock-meter-top">
                                            <strong><?= format_money($qtyBalance) ?></strong>
                                            <small><?= e((string) $item['unit_name']) ?></small>
                                        </div>
                                        <span class="stock-meter-bar"><i style="width: <?= e((string) $ratio) ?>%"></i></span>
                                    </div>
                                </td>
                                <td><?= format_money($reorderLevel) ?></td>
                                <td><?= e($latestLot) ?></td>
                                <td>
                                    <span class="expiry-pill is-<?= e($expiry[1]) ?>"><?= e($expiry[0]) ?></span>
                                    <small class="expiry-date"><?= thai_date_only((string) ($item['nearest_expiry'] ?? '')) ?></small>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="inventory-status is-danger">หมดอายุ</span>
                                    <?php elseif ($isLowStock): ?>
                                        <span class="inventory-status is-warning">ใกล้หมด</span>
                                    <?php elseif ($isNearExpiry): ?>
                                        <span class="inventory-status is-orange">ใกล้หมดอายุ</span>
                                    <?php else: ?>
                                        <span class="inventory-status is-ok">ปกติ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <details class="inventory-row-menu">
                                        <summary aria-label="เปิดเมนูรายการ"><i class="bi bi-three-dots-vertical"></i></summary>
                                        <div>
                                            <?php if (has_role('ADMIN')): ?>
                                                <button type="button" data-row-action="receive">รับเข้า</button>
                                                <button type="button" data-row-action="adjust">ปรับสต๊อก</button>
                                            <?php endif; ?>
                                            <button type="button" data-row-action="history">ดูประวัติ</button>
                                            <?php if (has_role('ADMIN')): ?>
                                                <button type="button" data-row-action="item">แก้ไขข้อมูล</button>
                                            <?php endif; ?>
                                            <button type="button" data-row-action="label">พิมพ์ฉลาก</button>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="inventory-empty-row <?= $items ? 'd-none' : '' ?>" data-inventory-empty-row>
                            <td colspan="9">ไม่พบรายการคลังที่ตรงกับการค้นหา</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="inventory-panel inventory-analytics-panel">
                <div class="inventory-section-header">
                    <div>
                        <div class="inventory-eyebrow">Inventory Analytics</div>
                        <h2><i class="bi bi-graph-up-arrow"></i> วิเคราะห์คลัง</h2>
                    </div>
                </div>
                <div class="inventory-analytics-grid">
                    <div class="inventory-summary-box">
                        <span>มูลค่าต้นทุนรวม</span>
                        <strong><?= format_money($stockValue) ?></strong>
                    </div>
                    <div class="inventory-summary-box">
                        <span>มูลค่าขายรวม</span>
                        <strong><?= format_money($saleValue) ?></strong>
                    </div>
                    <div class="inventory-summary-box">
                        <span>กำไรคงเหลือโดยประมาณ</span>
                        <strong><?= format_money($estimatedProfit) ?></strong>
                    </div>
                </div>
                <div class="inventory-trend-grid">
                    <div>
                        <h3>Consumption Trend</h3>
                        <div class="inventory-rank-list">
                            <?php foreach ($consumptionTrend as $trend): ?>
                                <?php
                                $used = (float) ($trend['total_used'] ?? 0);
                                $percent = $maxUsed > 0 ? (int) max(6, round(($used / $maxUsed) * 100)) : 0;
                                ?>
                                <div class="inventory-rank-row">
                                    <div>
                                        <strong><?= e((string) $trend['item_name']) ?></strong>
                                        <small>ใช้ <?= format_money($used) ?> <?= e((string) $trend['unit_name']) ?> ใน 90 วัน</small>
                                    </div>
                                    <span><i style="width: <?= e((string) $percent) ?>%"></i></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$consumptionTrend): ?>
                                <div class="inventory-empty-card">
                                    <i class="bi bi-activity"></i>
                                    <strong>ยังไม่มีข้อมูลการใช้ย้อนหลัง</strong>
                                    <span>เมื่อมีการจ่ายยา ระบบจะแสดง Top 10 ให้โดยอัตโนมัติ</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h3>Forecast</h3>
                        <div class="inventory-forecast-list">
                            <?php foreach (array_slice($consumptionTrend, 0, 4) as $trend): ?>
                                <?php
                                $matching = null;
                                foreach ($items as $item) {
                                    if ((string) $item['item_name'] === (string) $trend['item_name']) {
                                        $matching = $item;
                                        break;
                                    }
                                }
                                $qty = $matching ? (float) ($matching['qty_balance'] ?? 0) : 0.0;
                                $avgMonth = ((float) ($trend['total_used'] ?? 0)) / 3;
                                $monthsLeft = $avgMonth > 0 ? $qty / $avgMonth : null;
                                ?>
                                <div class="inventory-forecast-row">
                                    <strong><?= e((string) $trend['item_name']) ?></strong>
                                    <span>ใช้เฉลี่ย <?= format_money($avgMonth) ?>/เดือน · คงเหลือ <?= format_money($qty) ?> · <?= $monthsLeft === null ? 'ยังคำนวณไม่ได้' : 'พออีก ' . format_money($monthsLeft) . ' เดือน' ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$consumptionTrend): ?>
                                <div class="inventory-empty-card compact">Forecast จะเริ่มแม่นขึ้นหลังใช้งานจริงสักระยะ</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="inventory-panel inventory-history-panel">
                <div class="inventory-section-header">
                    <div>
                        <div class="inventory-eyebrow">Movement History</div>
                        <h2><i class="bi bi-clock-history"></i> ประวัติการเคลื่อนไหว</h2>
                    </div>
                    <div class="movement-filter-group">
                        <button type="button" class="is-active" data-movement-filter="all">ทั้งหมด</button>
                        <button type="button" data-movement-filter="IN">รับเข้า</button>
                        <button type="button" data-movement-filter="OUT">จ่ายออก</button>
                        <button type="button" data-movement-filter="ADJUST">ปรับ</button>
                    </div>
                </div>
                <div class="inventory-timeline">
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        $movementType = (string) ($movement['movement_type'] ?? '');
                        $movementClass = $movementType === 'IN' ? 'is-in' : ($movementType === 'OUT' ? 'is-out' : 'is-adjust');
                        $movementIcon = $movementType === 'IN' ? 'bi-arrow-down-circle' : ($movementType === 'OUT' ? 'bi-arrow-up-circle' : 'bi-sliders');
                        $qty = (float) ($movement['qty'] ?? 0);
                        ?>
                        <div class="inventory-timeline-row <?= e($movementClass) ?>" data-movement-row data-movement-type="<?= e($movementType) ?>">
                            <span class="inventory-timeline-dot"><i class="bi <?= e($movementIcon) ?>"></i></span>
                            <div>
                                <strong><?= e((string) $movement['item_name']) ?></strong>
                                <small><?= e($movementType) ?> <?= $qty > 0 ? '+' : '' ?><?= format_money($qty) ?> <?= e((string) $movement['unit_name']) ?> · Lot <?= e((string) ($movement['lot_no'] ?: '-')) ?></small>
                            </div>
                            <div class="inventory-timeline-meta">
                                <span><?= thai_date((string) $movement['movement_datetime']) ?></span>
                                <small><?= e((string) ($movement['created_by_name'] ?: '-')) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$movements): ?>
                        <div class="inventory-empty-card">
                            <i class="bi bi-clock-history"></i>
                            <strong>ยังไม่มีประวัติการเคลื่อนไหว stock</strong>
                            <span>เมื่อรับเข้า จ่ายออก หรือปรับสต๊อก ประวัติจะขึ้นตรงนี้</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="inventory-rail">
            <section class="inventory-panel">
                <div class="inventory-section-header">
                    <div>
                        <div class="inventory-eyebrow">Alert Center</div>
                        <h2><i class="bi bi-bell"></i> แจ้งเตือน</h2>
                    </div>
                    <span class="inventory-count-pill is-alert"><?= e((string) $reorderCount) ?></span>
                </div>
                <div class="inventory-alert-list">
                    <div class="inventory-alert-summary <?= $lowStockCount ? 'is-warning' : 'is-ok' ?>">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>ยาใกล้หมด <?= e((string) $lowStockCount) ?> รายการ</span>
                    </div>
                    <div class="inventory-alert-summary <?= $nearExpiryCount ? 'is-expiring' : 'is-ok' ?>">
                        <i class="bi bi-hourglass-split"></i>
                        <span>หมดอายุภายใน 30 วัน <?= e((string) $nearExpiryCount) ?> รายการ</span>
                    </div>
                    <div class="inventory-alert-summary <?= $expiredCount ? 'is-danger' : 'is-ok' ?>">
                        <i class="bi bi-x-octagon"></i>
                        <span>หมดอายุแล้ว <?= e((string) $expiredCount) ?> รายการ</span>
                    </div>

                    <?php foreach (array_slice($expiredBatches, 0, 3) as $batch): ?>
                        <div class="inventory-alert-item is-danger">
                            <strong><?= e((string) $batch['item_name']) ?></strong>
                            <span>Lot <?= e((string) ($batch['lot_no'] ?: '-')) ?> หมดอายุ <?= thai_date_only((string) $batch['expiry_date']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($lowStockItems, 0, 4) as $item): ?>
                        <div class="inventory-alert-item is-warning">
                            <strong><?= e((string) $item['item_name']) ?></strong>
                            <span>คงเหลือ <?= format_money($item['qty_balance']) ?> / จุดเตือน <?= format_money($item['reorder_level']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($nearExpiryBatches, 0, 3) as $batch): ?>
                        <div class="inventory-alert-item is-orange">
                            <strong><?= e((string) $batch['item_name']) ?></strong>
                            <span>Lot <?= e((string) ($batch['lot_no'] ?: '-')) ?> หมดอายุ <?= thai_date_only((string) $batch['expiry_date']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$expiredBatches && !$lowStockItems && !$nearExpiryBatches): ?>
                        <div class="inventory-empty-card is-ok">
                            <i class="bi bi-check-circle"></i>
                            <strong>คลังอยู่ในสถานะปกติ</strong>
                            <span>ไม่มีรายการใกล้หมดหรือหมดอายุที่ต้องจัดการตอนนี้</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="inventory-panel barcode-ready-panel">
                <div class="inventory-section-header">
                    <div>
                        <div class="inventory-eyebrow">Barcode Ready</div>
                        <h2><i class="bi bi-qr-code-scan"></i> Barcode / QR</h2>
                    </div>
                </div>
                <div class="barcode-ready-body">
                    <input type="text" placeholder="ยิง Barcode เพื่อค้นหา/รับเข้า" data-barcode-input>
                    <small>รองรับ workflow ค้นหา รับเข้า และจ่ายออกในเฟสถัดไป</small>
                </div>
            </section>
        </aside>
    </div>

    <?php if (has_role('ADMIN')): ?>
        <div class="inventory-modal" data-inventory-modal="receive" aria-hidden="true">
            <div class="inventory-modal-backdrop" data-close-inventory-modal></div>
            <section class="inventory-modal-card" role="dialog" aria-modal="true" aria-label="รับสินค้าเข้า">
                <header>
                    <div>
                        <div class="inventory-eyebrow">Receive Stock</div>
                        <h2>รับสินค้าเข้า</h2>
                    </div>
                    <button type="button" data-close-inventory-modal aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                </header>
                <form method="post" action="<?= e(route_url('inventory-batch-store')) ?>" class="inventory-form" data-receive-form>
                    <?= csrf_field() ?>
                    <label>
                        <span>รายการยา/เวชภัณฑ์</span>
                        <select name="item_id" required data-receive-item>
                            <option value="">เลือกรายการ</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>" data-stock="<?= e((string) ($item['qty_balance'] ?? 0)) ?>" data-cost="<?= e((string) ($item['default_cost'] ?? 0)) ?>" data-unit="<?= e((string) $item['unit_name']) ?>">
                                    <?= e((string) $item['item_name']) ?> (<?= e((string) $item['unit_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="inventory-form-grid two">
                        <label>
                            <span>Lot</span>
                            <input type="text" name="lot_no" placeholder="ถ้ามี">
                        </label>
                        <label>
                            <span>วันรับเข้า</span>
                            <input type="date" name="received_date" value="<?= e(date('Y-m-d')) ?>">
                        </label>
                        <label>
                            <span>วันหมดอายุ</span>
                            <input type="date" name="expiry_date">
                        </label>
                        <label>
                            <span>ต้นทุนต่อหน่วย</span>
                            <input type="number" step="0.01" name="cost_per_unit" value="0" data-receive-cost>
                        </label>
                    </div>
                    <label>
                        <span>จำนวนรับเข้า</span>
                        <input type="number" step="0.01" name="qty_in" min="0.01" required data-receive-qty>
                    </label>
                    <div class="inventory-preview">
                        <div><span>คงเหลือเดิม</span><strong data-receive-old>0</strong></div>
                        <div><span>รับเข้า</span><strong data-receive-add>0</strong></div>
                        <div><span>คงเหลือใหม่</span><strong data-receive-new>0</strong></div>
                    </div>
                    <button type="submit" class="inventory-primary-action full"><i class="bi bi-box-arrow-in-down"></i> บันทึกรับเข้า</button>
                </form>
            </section>
        </div>

        <div class="inventory-modal" data-inventory-modal="adjust" aria-hidden="true">
            <div class="inventory-modal-backdrop" data-close-inventory-modal></div>
            <section class="inventory-modal-card" role="dialog" aria-modal="true" aria-label="ปรับสต๊อก">
                <header>
                    <div>
                        <div class="inventory-eyebrow">Adjust Stock</div>
                        <h2>ปรับสต๊อก</h2>
                    </div>
                    <button type="button" data-close-inventory-modal aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                </header>
                <form method="post" action="<?= e(route_url('inventory-adjust')) ?>" class="inventory-form" data-adjust-form>
                    <?= csrf_field() ?>
                    <label>
                        <span>เลือก Lot</span>
                        <select name="batch_id" required data-adjust-batch>
                            <option value="">เลือก Lot</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= e((string) $batch['id']) ?>" data-item-id="<?= e((string) $batch['item_id']) ?>" data-stock="<?= e((string) $batch['qty_balance']) ?>">
                                    <?= e($batch['item_name'] . ' / Lot ' . ($batch['lot_no'] ?: '-')) ?> / คงเหลือ <?= format_money($batch['qty_balance']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>จำนวนปรับ (+/-)</span>
                        <input type="number" step="0.01" name="adjust_qty" placeholder="เช่น -2 หรือ 5" required data-adjust-qty>
                    </label>
                    <label>
                        <span>เหตุผลการปรับ</span>
                        <textarea name="note" rows="3" placeholder="เช่น นับจริงแล้วคงเหลือไม่ตรง" required></textarea>
                    </label>
                    <div class="inventory-preview">
                        <div><span>คงเหลือเดิม</span><strong data-adjust-old>0</strong></div>
                        <div><span>ปรับ</span><strong data-adjust-change>0</strong></div>
                        <div><span>คงเหลือใหม่</span><strong data-adjust-new>0</strong></div>
                    </div>
                    <div class="inventory-inline-warning d-none" data-adjust-warning>จำนวนใหม่ติดลบ ไม่สามารถบันทึกได้</div>
                    <button type="submit" class="inventory-primary-action full">บันทึกการปรับสต๊อก</button>
                </form>
            </section>
        </div>

        <div class="inventory-modal" data-inventory-modal="item" aria-hidden="true">
            <div class="inventory-modal-backdrop" data-close-inventory-modal></div>
            <section class="inventory-modal-card" role="dialog" aria-modal="true" aria-label="เพิ่มรายการคลัง">
                <header>
                    <div>
                        <div class="inventory-eyebrow">Item Master</div>
                        <h2>เพิ่ม/แก้ไขรายการคลัง</h2>
                    </div>
                    <button type="button" data-close-inventory-modal aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                </header>
                <form method="post" action="<?= e(route_url('inventory-item-store')) ?>" class="inventory-form">
                    <?= csrf_field() ?>
                    <div class="inventory-form-grid two">
                        <label>
                            <span>รหัสรายการ</span>
                            <input type="text" name="item_code" placeholder="MED001" required data-item-code-input>
                        </label>
                        <label>
                            <span>ประเภท</span>
                            <select name="item_type" data-item-type-input>
                                <option value="DRUG">ยา</option>
                                <option value="SUPPLY">เวชภัณฑ์</option>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span>ชื่อรายการ</span>
                        <input type="text" name="item_name" placeholder="เช่น Paracetamol 500 mg" required data-item-name-input>
                    </label>
                    <div class="inventory-form-grid two">
                        <label>
                            <span>หน่วยนับ</span>
                            <input type="text" name="unit_name" placeholder="เม็ด / ขวด / ชิ้น" required data-item-unit-input>
                        </label>
                        <label>
                            <span>จุดเตือนต่ำสุด</span>
                            <input type="number" step="0.01" name="reorder_level" value="0">
                        </label>
                        <label>
                            <span>ต้นทุน</span>
                            <input type="number" step="0.01" name="default_cost" value="0" data-item-cost-input>
                        </label>
                        <label>
                            <span>ราคาขาย</span>
                            <input type="number" step="0.01" name="default_price" value="0" data-item-price-input>
                        </label>
                    </div>
                    <label class="inventory-check">
                        <input type="checkbox" name="is_active" checked>
                        <span>เปิดใช้งานรายการนี้</span>
                    </label>
                    <button type="submit" class="inventory-primary-action full">บันทึกรายการคลัง</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
