<?php
$lowStockItems = [];
$nearExpiryBatches = [];
$expiredBatches = [];
$stockValue = 0.0;
$receivedToday = (float) ($receivedToday ?? 0);
$today = strtotime(date('Y-m-d'));
$nearExpiryLimit = strtotime('+30 days');

foreach ($items as $item) {
    $qtyBalance = (float) ($item['qty_balance'] ?? 0);
    $reorderLevel = (float) ($item['reorder_level'] ?? 0);
    $stockValue += $qtyBalance * (float) ($item['default_cost'] ?? 0);

    if ($qtyBalance <= $reorderLevel) {
        $lowStockItems[] = $item;
    }
}

foreach ($batches as $batch) {
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

$lowStockCount = count($lowStockItems);
$nearExpiryCount = count($nearExpiryBatches);
$expiredCount = count($expiredBatches);
$reorderCount = $lowStockCount + $expiredCount;
?>

<div class="inventory-workstation" data-inventory-workstation>
    <section class="inventory-hero">
        <div>
            <div class="inventory-kicker">Medical Supply Workstation</div>
            <h1>คลังยา / เวชภัณฑ์</h1>
            <p>ค้นหา รับเข้า ปรับสต๊อก และตรวจรายการที่ต้องจัดการในหน้าจอเดียว</p>
        </div>
        <a class="inventory-import-link" href="<?= e(route_url('import')) ?>">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            นำเข้าข้อมูล Excel
        </a>
    </section>

    <section class="inventory-kpi-grid" aria-label="สรุปคลัง">
        <div class="inventory-kpi-card">
            <span>รายการทั้งหมด</span>
            <strong><?= e((string) count($items)) ?></strong>
            <small>ยาและเวชภัณฑ์</small>
        </div>
        <div class="inventory-kpi-card is-warning">
            <span>ใกล้หมด</span>
            <strong><?= e((string) $lowStockCount) ?></strong>
            <small>ต่ำกว่าจุดเตือน</small>
        </div>
        <div class="inventory-kpi-card is-orange">
            <span>ใกล้หมดอายุ</span>
            <strong><?= e((string) $nearExpiryCount) ?></strong>
            <small>ภายใน 30 วัน</small>
        </div>
        <div class="inventory-kpi-card is-danger">
            <span>หมดอายุ</span>
            <strong><?= e((string) $expiredCount) ?></strong>
            <small>ควรแยกออกทันที</small>
        </div>
        <div class="inventory-kpi-card is-teal">
            <span>มูลค่าสต๊อก</span>
            <strong><?= format_money($stockValue) ?></strong>
            <small>คำนวณจากต้นทุน</small>
        </div>
        <div class="inventory-kpi-card is-blue">
            <span>รับเข้าวันนี้</span>
            <strong><?= format_money($receivedToday) ?></strong>
            <small>จำนวนรวม</small>
        </div>
    </section>

    <section class="inventory-control-bar">
        <div class="inventory-search-wrap">
            <i class="bi bi-upc-scan"></i>
            <input type="search" id="inventorySearch" placeholder="ค้นหายา / เวชภัณฑ์ / lot / barcode / รหัสรายการ" autocomplete="off">
        </div>
        <div class="inventory-filter-group" aria-label="ตัวกรองคลัง">
            <button type="button" class="inventory-filter is-active" data-inventory-filter="all">ทั้งหมด</button>
            <button type="button" class="inventory-filter" data-inventory-filter="DRUG">ยา</button>
            <button type="button" class="inventory-filter" data-inventory-filter="SUPPLY">เวชภัณฑ์</button>
            <button type="button" class="inventory-filter" data-inventory-filter="low">ใกล้หมด</button>
            <button type="button" class="inventory-filter" data-inventory-filter="near-expiry">ใกล้หมดอายุ</button>
            <button type="button" class="inventory-filter" data-inventory-filter="expired">หมดอายุ</button>
        </div>
        <?php if (has_role('ADMIN')): ?>
            <div class="inventory-action-tabs" aria-label="งานคลัง">
                <button type="button" class="inventory-action-tab" data-inventory-action="item">เพิ่มรายการ</button>
                <button type="button" class="inventory-action-tab is-active" data-inventory-action="receive">รับเข้า</button>
                <button type="button" class="inventory-action-tab" data-inventory-action="adjust">ปรับสต๊อก</button>
            </div>
        <?php endif; ?>
    </section>

    <div class="inventory-layout">
        <main class="inventory-main-surface">
            <section class="inventory-panel">
                <div class="inventory-panel-header">
                    <div>
                        <div class="inventory-kicker">Inventory Table</div>
                        <h2>รายการคลัง</h2>
                    </div>
                    <span class="inventory-table-count"><span data-inventory-visible-count><?= e((string) count($items)) ?></span> รายการ</span>
                </div>

                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>รายการ</th>
                            <th>ประเภท</th>
                            <th class="text-end">คงเหลือ</th>
                            <th class="text-end">จุดเตือน</th>
                            <th>หมดอายุใกล้สุด</th>
                            <th>สถานะ</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $qtyBalance = (float) ($item['qty_balance'] ?? 0);
                            $reorderLevel = (float) ($item['reorder_level'] ?? 0);
                            $isLowStock = $qtyBalance <= $reorderLevel;
                            $expiryTime = empty($item['nearest_expiry']) ? null : strtotime((string) $item['nearest_expiry']);
                            $isExpired = $expiryTime !== null && $expiryTime < $today;
                            $isNearExpiry = !$isExpired && $expiryTime !== null && $expiryTime <= $nearExpiryLimit;
                            $status = $isExpired ? 'expired' : ($isLowStock ? 'low' : ($isNearExpiry ? 'near-expiry' : 'normal'));
                            $searchText = strtolower(implode(' ', [
                                $item['item_code'] ?? '',
                                $item['item_name'] ?? '',
                                $item['unit_name'] ?? '',
                                $item['item_type'] ?? '',
                                $item['nearest_expiry'] ?? '',
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
                                <td><?= e($item['item_type'] === 'DRUG' ? 'ยา' : 'เวชภัณฑ์') ?></td>
                                <td class="text-end fw-bold"><?= format_money($qtyBalance) ?></td>
                                <td class="text-end"><?= format_money($reorderLevel) ?></td>
                                <td><?= thai_date_only((string) ($item['nearest_expiry'] ?? '')) ?></td>
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
                                <td>
                                    <div class="inventory-row-actions">
                                        <?php if (has_role('ADMIN')): ?>
                                            <button type="button" data-row-action="receive">รับเข้า</button>
                                            <button type="button" data-row-action="adjust">ปรับ</button>
                                        <?php endif; ?>
                                        <button type="button" data-row-action="history">ประวัติ</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="inventory-empty-row <?= $items ? 'd-none' : '' ?>" data-inventory-empty-row>
                            <td colspan="8">ไม่พบรายการคลังที่ตรงกับการค้นหา</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="inventory-panel inventory-history-panel" data-inventory-action-panel="history">
                <div class="inventory-panel-header">
                    <div>
                        <div class="inventory-kicker">Movement History</div>
                        <h2>ประวัติการเคลื่อนไหวล่าสุด</h2>
                    </div>
                </div>
                <div class="inventory-timeline">
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        $movementType = (string) ($movement['movement_type'] ?? '');
                        $movementClass = $movementType === 'IN' ? 'is-in' : ($movementType === 'OUT' ? 'is-out' : 'is-adjust');
                        $qty = (float) ($movement['qty'] ?? 0);
                        ?>
                        <div class="inventory-timeline-row <?= e($movementClass) ?>">
                            <span class="inventory-timeline-dot"></span>
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
                        <div class="inventory-empty-compact">ยังไม่มีประวัติการเคลื่อนไหว stock</div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="inventory-control-rail">
            <section class="inventory-panel inventory-alert-panel">
                <div class="inventory-panel-header">
                    <div>
                        <div class="inventory-kicker">Alert Center</div>
                        <h2>ต้องจัดการ</h2>
                    </div>
                    <span class="inventory-alert-total"><?= e((string) $reorderCount) ?></span>
                </div>

                <div class="inventory-alert-list">
                    <?php foreach (array_slice($expiredBatches, 0, 5) as $batch): ?>
                        <div class="inventory-alert-item is-danger">
                            <strong><?= e((string) $batch['item_name']) ?></strong>
                            <span>Lot <?= e((string) ($batch['lot_no'] ?: '-')) ?> หมดอายุ <?= thai_date_only((string) $batch['expiry_date']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($lowStockItems, 0, 5) as $item): ?>
                        <div class="inventory-alert-item is-warning">
                            <strong><?= e((string) $item['item_name']) ?></strong>
                            <span>คงเหลือ <?= format_money($item['qty_balance']) ?> / จุดเตือน <?= format_money($item['reorder_level']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($nearExpiryBatches, 0, 4) as $batch): ?>
                        <div class="inventory-alert-item is-orange">
                            <strong><?= e((string) $batch['item_name']) ?></strong>
                            <span>Lot <?= e((string) ($batch['lot_no'] ?: '-')) ?> หมดอายุ <?= thai_date_only((string) $batch['expiry_date']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$expiredBatches && !$lowStockItems && !$nearExpiryBatches): ?>
                        <div class="inventory-empty-compact is-ok">
                            <i class="bi bi-check-circle"></i>
                            คลังอยู่ในสถานะปกติ
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (has_role('ADMIN')): ?>
                <section class="inventory-panel inventory-action-panel is-active" data-inventory-action-panel="receive">
                    <div class="inventory-panel-header">
                        <div>
                            <div class="inventory-kicker">Receive Stock</div>
                            <h2>รับสินค้าเข้าคลัง</h2>
                        </div>
                    </div>
                    <form method="post" action="<?= e(route_url('inventory-batch-store')) ?>" class="inventory-form" data-receive-form>
                        <?= csrf_field() ?>
                        <label>
                            <span>เลือกรายการ</span>
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
                                <span>Lot No.</span>
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
                        <button type="submit" class="inventory-primary-button">
                            <i class="bi bi-box-arrow-in-down"></i>
                            บันทึกรับเข้า
                        </button>
                    </form>
                </section>

                <section class="inventory-panel inventory-action-panel" data-inventory-action-panel="item">
                    <div class="inventory-panel-header">
                        <div>
                            <div class="inventory-kicker">New Item</div>
                            <h2>เพิ่มรายการคลัง</h2>
                        </div>
                    </div>
                    <form method="post" action="<?= e(route_url('inventory-item-store')) ?>" class="inventory-form">
                        <?= csrf_field() ?>
                        <div class="inventory-form-grid two">
                            <label>
                                <span>รหัสรายการ</span>
                                <input type="text" name="item_code" placeholder="MED001" required>
                            </label>
                            <label>
                                <span>ประเภท</span>
                                <select name="item_type">
                                    <option value="DRUG">ยา</option>
                                    <option value="SUPPLY">เวชภัณฑ์</option>
                                </select>
                            </label>
                        </div>
                        <label>
                            <span>ชื่อรายการ</span>
                            <input type="text" name="item_name" placeholder="เช่น Paracetamol 500 mg" required>
                        </label>
                        <div class="inventory-form-grid two">
                            <label>
                                <span>หน่วยนับ</span>
                                <input type="text" name="unit_name" placeholder="เม็ด / ขวด / ชิ้น" required>
                            </label>
                            <label>
                                <span>จุดเตือนต่ำสุด</span>
                                <input type="number" step="0.01" name="reorder_level" value="0">
                            </label>
                            <label>
                                <span>ต้นทุน</span>
                                <input type="number" step="0.01" name="default_cost" value="0">
                            </label>
                            <label>
                                <span>ราคาขาย</span>
                                <input type="number" step="0.01" name="default_price" value="0">
                            </label>
                        </div>
                        <label class="inventory-check">
                            <input type="checkbox" name="is_active" checked>
                            <span>เปิดใช้งานรายการนี้</span>
                        </label>
                        <button type="submit" class="inventory-primary-button">บันทึกรายการคลัง</button>
                    </form>
                </section>

                <section class="inventory-panel inventory-action-panel" data-inventory-action-panel="adjust">
                    <div class="inventory-panel-header">
                        <div>
                            <div class="inventory-kicker">Adjust Stock</div>
                            <h2>ปรับสต๊อก</h2>
                        </div>
                    </div>
                    <form method="post" action="<?= e(route_url('inventory-adjust')) ?>" class="inventory-form" data-adjust-form>
                        <?= csrf_field() ?>
                        <label>
                            <span>เลือกล็อต</span>
                            <select name="batch_id" required data-adjust-batch>
                                <option value="">เลือกล็อต</option>
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
                        <div class="inventory-preview" data-adjust-preview>
                            <div><span>คงเหลือเดิม</span><strong data-adjust-old>0</strong></div>
                            <div><span>ปรับ</span><strong data-adjust-change>0</strong></div>
                            <div><span>คงเหลือใหม่</span><strong data-adjust-new>0</strong></div>
                        </div>
                        <div class="inventory-inline-warning d-none" data-adjust-warning>จำนวนใหม่ติดลบ ไม่สามารถบันทึกได้</div>
                        <button type="submit" class="inventory-primary-button">บันทึกการปรับสต๊อก</button>
                    </form>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>
