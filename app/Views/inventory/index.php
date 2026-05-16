<?php
$lowStockCount = 0;
$nearExpiryCount = 0;
$stockValue = 0.0;

foreach ($items as $item) {
    $qtyBalance = (float) ($item['qty_balance'] ?? 0);
    $reorderLevel = (float) ($item['reorder_level'] ?? 0);
    $stockValue += $qtyBalance * (float) ($item['default_cost'] ?? 0);

    if ($qtyBalance <= $reorderLevel) {
        $lowStockCount++;
    }

    if (!empty($item['nearest_expiry']) && strtotime((string) $item['nearest_expiry']) <= strtotime('+30 days')) {
        $nearExpiryCount++;
    }
}
?>

<div class="d-grid gap-4">
    <section class="page-hero-card">
        <div class="page-hero-layout">
            <div>
                <div class="page-hero-eyebrow">คลังยาและเวชภัณฑ์</div>
                <h1 class="page-hero-title">ดูสต๊อก ควบคุมล็อต และติดตามรายการเตือนในหน้าจอเดียว</h1>
                <p class="page-hero-text">ใช้หน้านี้สำหรับเพิ่มรายการคลัง รับสินค้าเข้าล็อต ปรับสต๊อก และตรวจสอบยาใกล้หมดหรือใกล้หมดอายุแบบอ่านง่าย</p>
            </div>
            <div class="report-summary-grid inventory-summary-grid">
                <div class="report-summary-card">
                    <span>รายการทั้งหมด</span>
                    <strong><?= e((string) count($items)) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>ใกล้หมด</span>
                    <strong><?= e((string) $lowStockCount) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>ใกล้หมดอายุ</span>
                    <strong><?= e((string) $nearExpiryCount) ?></strong>
                </div>
                <div class="report-summary-card report-summary-card-accent">
                    <span>มูลค่าสต๊อก</span>
                    <strong><?= format_money($stockValue) ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <?php if (has_role('ADMIN')): ?>
            <div class="col-xl-4">
                <div class="card section-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1">เพิ่มรายการคลัง</h2>
                        <div class="small text-muted">ใช้สำหรับเพิ่มยาและเวชภัณฑ์ใหม่เข้าในระบบ</div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <form method="post" action="<?= e(route_url('inventory-item-store')) ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <div class="col-12">
                                <label class="form-label">รหัสรายการ</label>
                                <input type="text" name="item_code" class="form-control" placeholder="MED001" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">ชื่อรายการ</label>
                                <input type="text" name="item_name" class="form-control" placeholder="เช่น Paracetamol 500 mg" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเภท</label>
                                <select name="item_type" class="form-select">
                                    <option value="DRUG">ยา</option>
                                    <option value="SUPPLY">เวชภัณฑ์</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">หน่วยนับ</label>
                                <input type="text" name="unit_name" class="form-control" placeholder="เม็ด / ขวด / ชิ้น" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">จุดเตือนต่ำสุด</label>
                                <input type="number" step="0.01" name="reorder_level" class="form-control" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ต้นทุน</label>
                                <input type="number" step="0.01" name="default_cost" class="form-control" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ราคาขาย</label>
                                <input type="number" step="0.01" name="default_price" class="form-control" value="0">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="item_active" checked>
                                    <label class="form-check-label" for="item_active">เปิดใช้งานรายการนี้</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">บันทึกรายการคลัง</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="<?= has_role('ADMIN') ? 'col-xl-4' : 'col-xl-6' ?>">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รับสินค้าเข้าคลัง</h2>
                    <div class="small text-muted">บันทึกรับเข้าตามล็อต เพื่อเก็บวันหมดอายุและต้นทุนต่อหน่วย</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="post" action="<?= e(route_url('inventory-batch-store')) ?>" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-12">
                            <label class="form-label">เลือกรายการ</label>
                            <select name="item_id" class="form-select" required>
                                <option value="">เลือกรายการ</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= e($item['unit_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lot No.</label>
                            <input type="text" name="lot_no" class="form-control" placeholder="ถ้ามี">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันรับเข้า</label>
                            <input type="date" name="received_date" class="form-control" value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันหมดอายุ</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จำนวน</label>
                            <input type="number" step="0.01" name="qty_in" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ต้นทุนต่อหน่วย</label>
                            <input type="number" step="0.01" name="cost_per_unit" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success w-100">บันทึกรับเข้า</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (has_role('ADMIN')): ?>
            <div class="col-xl-4">
                <div class="card section-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 mb-1">ปรับสต๊อก</h2>
                        <div class="small text-muted">ใช้เมื่อมีการนับจริง ปรับยอด หรือแก้ไขล็อตเฉพาะรายการ</div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <form method="post" action="<?= e(route_url('inventory-adjust')) ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <div class="col-12">
                                <label class="form-label">เลือกล็อต</label>
                                <select name="batch_id" class="form-select" required>
                                    <option value="">เลือกล็อต</option>
                                    <?php foreach ($batches as $batch): ?>
                                        <option value="<?= e((string) $batch['id']) ?>">
                                            <?= e($batch['item_name'] . ' / Lot ' . ($batch['lot_no'] ?: '-')) ?> / คงเหลือ <?= format_money($batch['qty_balance']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">จำนวนปรับ (+/-)</label>
                                <input type="number" step="0.01" name="adjust_qty" class="form-control" placeholder="เช่น -2 หรือ 5" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">หมายเหตุ</label>
                                <textarea name="note" class="form-control" rows="3" placeholder="เช่น นับจริงแล้วคงเหลือไม่ตรง"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-dark w-100">บันทึกการปรับสต๊อก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">รายการคลังทั้งหมด</h2>
                    <div class="small text-muted">ดูจำนวนคงเหลือ ประเภท และสถานะเตือนของแต่ละรายการแบบอ่านเร็ว</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อรายการ</th>
                                <th>ประเภท</th>
                                <th class="text-end">คงเหลือ</th>
                                <th>สถานะ</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $isLowStock = (float) $item['qty_balance'] <= (float) $item['reorder_level'];
                                $isNearExpiry = !empty($item['nearest_expiry']) && strtotime((string) $item['nearest_expiry']) <= strtotime('+30 days');
                                ?>
                                <tr>
                                    <td><?= e($item['item_code']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($item['item_name']) ?></div>
                                        <div class="small text-muted"><?= e($item['unit_name']) ?></div>
                                    </td>
                                    <td><?= e($item['item_type'] === 'DRUG' ? 'ยา' : 'เวชภัณฑ์') ?></td>
                                    <td class="text-end fw-semibold"><?= format_money($item['qty_balance']) ?></td>
                                    <td>
                                        <?php if ($isLowStock): ?><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle me-1">สต๊อกต่ำ</span><?php endif; ?>
                                        <?php if ($isNearExpiry): ?><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">ใกล้หมดอายุ</span><?php endif; ?>
                                        <?php if (!$isLowStock && !$isNearExpiry): ?><span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">ปกติ</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$items): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีข้อมูลรายการคลัง</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">ล็อตล่าสุด</h2>
                    <div class="small text-muted">ตรวจสอบล็อตที่เพิ่งรับเข้าและดูวันหมดอายุที่ใกล้ถึงก่อน</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>รายการ</th>
                                <th>Lot</th>
                                <th class="text-end">คงเหลือ</th>
                                <th>หมดอายุ</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td><?= e($batch['item_name']) ?></td>
                                    <td><?= e($batch['lot_no'] ?: '-') ?></td>
                                    <td class="text-end"><?= format_money($batch['qty_balance']) ?></td>
                                    <td><?= thai_date_only($batch['expiry_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$batches): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีล็อตสินค้าในคลัง</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
