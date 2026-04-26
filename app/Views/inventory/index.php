<div class="row g-4 mb-4">
    <?php if (has_role('ADMIN')): ?>
        <div class="col-xl-4">
            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-0">เพิ่มรายการคลัง</h2>
                </div>
                <div class="card-body px-4">
                    <form method="post" action="<?= e(route_url('inventory-item-store')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">รหัสรายการ</label>
                            <input type="text" name="item_code" class="form-control" placeholder="MED001" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อรายการ</label>
                            <input type="text" name="item_name" class="form-control" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ประเภท</label>
                                <select name="item_type" class="form-select">
                                    <option value="DRUG">ยา</option>
                                    <option value="SUPPLY">เวชภัณฑ์</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">หน่วยนับ</label>
                                <input type="text" name="unit_name" class="form-control" placeholder="เม็ด/ขวด/ชิ้น" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">จุดสั่งซื้อ</label>
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
                        </div>
                        <div class="form-check mt-3">
                            <input type="checkbox" name="is_active" class="form-check-input" id="item_active" checked>
                            <label class="form-check-label" for="item_active">เปิดใช้งาน</label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-4 w-100">บันทึกรายการคลัง</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= has_role('ADMIN') ? 'col-xl-4' : 'col-xl-6' ?>">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-0">รับสินค้าเข้าคลัง</h2>
            </div>
            <div class="card-body px-4">
                <form method="post" action="<?= e(route_url('inventory-batch-store')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">เลือกรายการ</label>
                        <select name="item_id" class="form-select" required>
                            <option value="">เลือก</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= e($item['unit_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lot No.</label>
                        <input type="text" name="lot_no" class="form-control">
                    </div>
                    <div class="row g-3">
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
                    </div>
                    <button type="submit" class="btn btn-success mt-4 w-100">บันทึกรับเข้า</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (has_role('ADMIN')): ?>
    <div class="col-xl-4">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-0">ปรับสต็อก</h2>
            </div>
            <div class="card-body px-4">
                <form method="post" action="<?= e(route_url('inventory-adjust')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
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
                    <div class="mb-3">
                        <label class="form-label">จำนวนปรับ (+/-)</label>
                        <input type="number" step="0.01" name="adjust_qty" class="form-control" placeholder="เช่น -2 หรือ 5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea name="note" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-dark w-100">บันทึกการปรับสต็อก</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-0">รายการคลังทั้งหมด</h2>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อรายการ</th>
                            <th>ประเภท</th>
                            <th class="text-end">คงเหลือ</th>
                            <th>แจ้งเตือน</th>
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
                                    <?= e($item['item_name']) ?>
                                    <div class="small text-muted"><?= e($item['unit_name']) ?></div>
                                </td>
                                <td><?= e($item['item_type'] === 'DRUG' ? 'ยา' : 'เวชภัณฑ์') ?></td>
                                <td class="text-end fw-semibold"><?= format_money($item['qty_balance']) ?></td>
                                <td>
                                    <?php if ($isLowStock): ?><span class="badge bg-danger">stock ต่ำ</span><?php endif; ?>
                                    <?php if ($isNearExpiry): ?><span class="badge bg-warning text-dark">ใกล้หมดอายุ</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีข้อมูลคลัง</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-0">ล็อตล่าสุด</h2>
            </div>
            <div class="card-body px-4">
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
                            <tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีล็อตในคลัง</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
