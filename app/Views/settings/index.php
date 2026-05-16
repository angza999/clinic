<div class="d-grid gap-4">
    <section class="page-hero-card">
        <div class="page-hero-layout">
            <div>
                <div class="page-hero-eyebrow">ตั้งค่าคลินิก</div>
                <h1 class="page-hero-title">กำหนดข้อมูลคลินิก เลขเอกสาร และข้อความที่ใช้ทั้งระบบ</h1>
                <p class="page-hero-text">ค่าที่บันทึกในหน้านี้จะถูกนำไปใช้กับชื่อคลินิกบนเอกสาร เลข HN ใหม่ เลขใบเสร็จใหม่ และข้อความท้ายใบเสร็จ</p>
            </div>
            <div class="queue-empty-state text-start">
                <div class="fw-semibold mb-2">ค่าที่ใช้จริงหลังบันทึก</div>
                <div>HN ใหม่: <strong><?= e(($settings['hn_prefix'] ?? 'HN')) ?>000123</strong></div>
                <div>ใบเสร็จใหม่: <strong><?= e(($settings['receipt_prefix'] ?? 'RC')) ?><?= date('Ymd') ?>-0001</strong></div>
            </div>
        </div>
    </section>

    <div class="row justify-content-center">
        <div class="col-xxl-10">
            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">ข้อมูลพื้นฐานของคลินิก</h2>
                    <div class="small text-muted">ใช้สำหรับชื่อบนเอกสาร ข้อมูลติดต่อ และข้อความท้ายใบเสร็จ</div>
                </div>
                <div class="card-body px-4 pb-4">
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
                                        <input type="text" name="clinic_phone" class="form-control" value="<?= e($settings['clinic_phone'] ?? '') ?>" placeholder="เช่น 053-000000 หรือ 08x-xxx-xxxx">
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

    <div class="row justify-content-center">
        <div class="col-xxl-10">
            <div class="card section-card">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">Smart Exam Preset</h2>
                    <div class="small text-muted">แก้ปุ่ม preset ที่ใช้ในหน้า Smart Exam เช่น บริการที่เพิ่มอัตโนมัติ ข้อความ CC/Dx และรายการเวชภัณฑ์</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row g-3">
                        <?php foreach (($smartPresets ?? []) as $preset): ?>
                            <?php
                            $itemLines = [];
                            $items = json_decode((string) ($preset['item_codes_json'] ?? '[]'), true);
                            if (is_array($items)) {
                                foreach ($items as $item) {
                                    $itemLines[] = ($item['code'] ?? '') . ':' . ($item['qty'] ?? 1);
                                }
                            }
                            ?>
                            <div class="col-xl-6">
                                <form method="post" action="<?= e(route_url('settings-preset-store')) ?>" class="template-panel h-100">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="preset_id" value="<?= (int) $preset['id'] ?>">
                                    <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
                                        <div>
                                            <div class="small text-muted">Preset Key</div>
                                            <input type="text" name="preset_key" class="form-control" value="<?= e($preset['preset_key']) ?>" required>
                                        </div>
                                        <label class="form-check mt-4">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= !empty($preset['is_active']) ? 'checked' : '' ?>>
                                            <span class="form-check-label">เปิดใช้</span>
                                        </label>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-8">
                                            <label class="form-label">ชื่อปุ่ม</label>
                                            <input type="text" name="label" class="form-control" value="<?= e($preset['label']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ลำดับ</label>
                                            <input type="number" name="sort_order" class="form-control" value="<?= e((string) $preset['sort_order']) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">คำอธิบาย</label>
                                            <textarea name="description" class="form-control" rows="2"><?= e($preset['description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Service codes</label>
                                            <input type="text" name="service_codes" class="form-control" value="<?= e($preset['service_codes'] ?? '') ?>" placeholder="SRV001, SRV002">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Item codes</label>
                                            <input type="text" name="item_codes" class="form-control" value="<?= e(implode(', ', $itemLines)) ?>" placeholder="MED002:1, MED003:2">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CC</label>
                                            <input type="text" name="cc" class="form-control" value="<?= e($preset['cc'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Dx</label>
                                            <input type="text" name="dx" class="form-control" value="<?= e($preset['dx'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">PI</label>
                                            <textarea name="pi" class="form-control" rows="2"><?= e($preset['pi'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">PE</label>
                                            <input type="text" name="pe" class="form-control" value="<?= e($preset['pe'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">นัดติดตาม (วัน)</label>
                                            <input type="number" name="followup_days" class="form-control" value="<?= e((string) ($preset['followup_days'] ?? '')) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">คำแนะนำ</label>
                                            <textarea name="advice" class="form-control" rows="2"><?= e($preset['advice'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <input type="hidden" name="theme" value="<?= e($preset['theme'] ?? 'preset-custom') ?>">
                                    <button type="submit" class="btn btn-primary w-100 mt-3">บันทึก Preset นี้</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($smartPresets)): ?>
                        <div class="queue-empty-state text-start">ยังไม่มี preset ในฐานข้อมูล เปิดหน้า Smart Exam หนึ่งครั้งเพื่อ seed ค่าเริ่มต้น หรือเพิ่มผ่านฐานข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
