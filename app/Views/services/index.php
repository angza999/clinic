<div class="row g-4">
    <?php if (has_role('ADMIN')): ?>
        <div class="col-xl-4">
            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-0">เพิ่ม/แก้ไขรายการบริการ</h2>
                </div>
                <div class="card-body px-4">
                    <form method="post" action="<?= e(route_url('services-store')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">รหัสบริการ</label>
                            <input type="text" name="service_code" class="form-control" placeholder="SRV001" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อบริการ</label>
                            <input type="text" name="service_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <input type="text" name="category" class="form-control" placeholder="ตรวจทั่วไป / หัตถการ">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ราคา</label>
                            <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" class="form-check-input" id="service_active" checked>
                            <label class="form-check-label" for="service_active">เปิดใช้งาน</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">บันทึกรายการบริการ</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= has_role('ADMIN') ? 'col-xl-8' : 'col-12' ?>">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-0">บริการทั้งหมด</h2>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อบริการ</th>
                            <th>หมวดหมู่</th>
                            <th class="text-end">ราคา</th>
                            <th class="text-center">สถานะ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= e($service['service_code']) ?></td>
                                <td><?= e($service['service_name']) ?></td>
                                <td><?= e($service['category'] ?: '-') ?></td>
                                <td class="text-end"><?= format_money($service['price']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $service['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $service['is_active'] ? 'ใช้งาน' : 'ปิด' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$services): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีรายการบริการ</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
