<div class="row justify-content-center">
    <div class="col-xxl-10">
        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">ตั้งค่าคลินิก</h2>
                <div class="small text-muted">ค่าที่บันทึกที่นี่จะถูกใช้กับชื่อคลินิกบนเอกสาร เลข HN/ใบเสร็จใหม่ และข้อความท้ายใบเสร็จ</div>
            </div>
            <div class="card-body px-4">
                <form method="post" action="<?= e(route_url('settings-store')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="settings_id" value="<?= e((string) ($settings['id'] ?? 0)) ?>">

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">ชื่อคลินิก</label>
                                    <input type="text" name="clinic_name" class="form-control form-control-lg" value="<?= e($settings['clinic_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เบอร์โทรคลินิก</label>
                                    <input type="text" name="clinic_phone" class="form-control" value="<?= e($settings['clinic_phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">แจ้งเตือนใกล้หมดอายุ (วัน)</label>
                                    <input type="number" name="expiry_alert_days" class="form-control" min="1" value="<?= e((string) ($settings['expiry_alert_days'] ?? 30)) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ที่อยู่คลินิก</label>
                                    <textarea name="clinic_address" class="form-control" rows="3" placeholder="ใช้แสดงบนใบเสร็จและเอกสาร"><?= e($settings['clinic_address'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ข้อความท้ายใบเสร็จ / หมายเหตุคลินิก</label>
                                    <textarea name="queue_note" class="form-control" rows="3" placeholder="เช่น ขอบคุณที่ใช้บริการ หรือ ห้องน้ำอยู่ด้านนอกอาคาร"><?= e($settings['queue_note'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="template-panel h-100">
                                <h3 class="h6 mb-3">เลขเอกสารใหม่ที่จะใช้ต่อจากนี้</h3>
                                <div class="mb-3">
                                    <label class="form-label">Prefix HN</label>
                                    <input type="text" name="hn_prefix" class="form-control" maxlength="10" value="<?= e($settings['hn_prefix'] ?? 'HN') ?>">
                                    <div class="small text-muted mt-1">มีผลกับ HN ที่สร้างใหม่เท่านั้น</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Prefix ใบเสร็จ</label>
                                    <input type="text" name="receipt_prefix" class="form-control" maxlength="10" value="<?= e($settings['receipt_prefix'] ?? 'RC') ?>">
                                    <div class="small text-muted mt-1">มีผลกับเลขใบเสร็จที่รับชำระครั้งถัดไป</div>
                                </div>
                                <div class="queue-empty-state text-start mt-4">
                                    <div class="fw-semibold mb-2">ตัวอย่างผลลัพธ์</div>
                                    <div>HN ใหม่: <strong><?= e(($settings['hn_prefix'] ?? 'HN')) ?>000123</strong></div>
                                    <div>ใบเสร็จใหม่: <strong><?= e(($settings['receipt_prefix'] ?? 'RC')) ?><?= date('Ymd') ?>-0001</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-lg-row gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">บันทึกตั้งค่าคลินิก</button>
                        <a href="<?= e(route_url('dashboard')) ?>" class="btn btn-outline-secondary">กลับไป Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>