<?php
$activePresetCount = 0;
foreach (($smartPresets ?? []) as $preset) {
    if (!empty($preset['is_active'])) {
        $activePresetCount++;
    }
}
$totalPresetCount = count($smartPresets ?? []);
$hnPreview = ($settings['hn_prefix'] ?? 'HN') . '000123';
$receiptPreview = ($settings['receipt_prefix'] ?? 'RC') . date('Ymd') . '-0001';
?>

<div class="settings-workstation">
    <section class="settings-command-strip">
        <div>
            <div class="settings-eyebrow">Clinic Configuration</div>
            <h2>ตั้งค่าที่มีผลกับงานหน้าคลินิก</h2>
            <p>ข้อมูลคลินิก เลขเอกสาร และ preset ของ Smart Exam ควรแก้แบบตั้งใจ เพราะมีผลกับเอกสาร คิว การเงิน และ workflow ตรวจ</p>
        </div>
        <div class="settings-status-grid">
            <a href="#clinicSettings" class="settings-status-card">
                <span>คลินิก</span>
                <strong><?= e($settings['clinic_name'] ?? '-') ?></strong>
            </a>
            <a href="#documentSettings" class="settings-status-card">
                <span>เอกสารใหม่</span>
                <strong><?= e($hnPreview) ?></strong>
            </a>
            <a href="#smartPresetSettings" class="settings-status-card">
                <span>Smart Preset</span>
                <strong><?= e((string) $activePresetCount) ?> / <?= e((string) $totalPresetCount) ?> เปิดใช้</strong>
            </a>
        </div>
    </section>

    <form method="post" action="<?= e(route_url('settings-store')) ?>" class="settings-form-shell" id="clinicSettings">
        <?= csrf_field() ?>
        <input type="hidden" name="settings_id" value="<?= e((string) ($settings['id'] ?? 0)) ?>">

        <section class="settings-panel settings-main-panel">
            <div class="settings-panel-head">
                <div>
                    <div class="settings-eyebrow">Clinic Profile</div>
                    <h3>ข้อมูลพื้นฐานของคลินิก</h3>
                </div>
                <span class="settings-chip"><i class="bi bi-building-fill-check"></i> ใช้กับเอกสารและใบเสร็จ</span>
            </div>

            <div class="settings-field-grid">
                <div class="settings-field settings-field-wide">
                    <label class="form-label">ชื่อคลินิก</label>
                    <input type="text" name="clinic_name" class="form-control form-control-lg" value="<?= e($settings['clinic_name'] ?? '') ?>" required>
                </div>
                <div class="settings-field">
                    <label class="form-label">เบอร์โทรคลินิก</label>
                    <input type="text" name="clinic_phone" class="form-control" value="<?= e($settings['clinic_phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
                </div>
                <div class="settings-field">
                    <label class="form-label">เลขประจำตัวผู้เสียภาษี / เลขทะเบียน</label>
                    <input type="text" name="clinic_tax_id" class="form-control" value="<?= e($settings['clinic_tax_id'] ?? '') ?>" placeholder="ถ้ามี">
                </div>
                <div class="settings-field">
                    <label class="form-label">แจ้งเตือนใกล้หมดอายุ (วัน)</label>
                    <input type="number" name="expiry_alert_days" class="form-control" min="1" value="<?= e((string) ($settings['expiry_alert_days'] ?? 30)) ?>">
                </div>
                <div class="settings-field">
                    <label class="form-label">ข้อความตราหน้าใบเสร็จ</label>
                    <input type="text" name="receipt_logo_text" class="form-control" maxlength="80" value="<?= e($settings['receipt_logo_text'] ?? '') ?>" placeholder="เช่น DM คลินิก">
                </div>
                <div class="settings-field settings-field-wide">
                    <label class="form-label">ที่อยู่คลินิก</label>
                    <textarea name="clinic_address" class="form-control settings-textarea-sm" placeholder="ใช้แสดงบนใบเสร็จและเอกสาร"><?= e($settings['clinic_address'] ?? '') ?></textarea>
                </div>
                <div class="settings-field settings-field-wide">
                    <label class="form-label">ข้อความท้ายใบเสร็จ</label>
                    <textarea name="receipt_footer" class="form-control settings-textarea-xs" placeholder="เช่น ขอบคุณที่ใช้บริการ"><?= e($settings['receipt_footer'] ?? '') ?></textarea>
                </div>
                <div class="settings-field settings-field-wide">
                    <label class="form-label">หมายเหตุคลินิก / ข้อความคิว</label>
                    <textarea name="queue_note" class="form-control settings-textarea-xs" placeholder="เช่น ห้องน้ำอยู่ด้านนอกอาคาร"><?= e($settings['queue_note'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <aside class="settings-panel settings-side-panel" id="documentSettings">
            <div class="settings-panel-head">
                <div>
                    <div class="settings-eyebrow">Document Numbers</div>
                    <h3>เลขเอกสาร</h3>
                </div>
            </div>

            <div class="settings-doc-grid">
                <div>
                    <label class="form-label">Prefix HN</label>
                    <input type="text" name="hn_prefix" class="form-control" maxlength="10" value="<?= e($settings['hn_prefix'] ?? 'HN') ?>">
                    <small>มีผลกับ HN ที่สร้างใหม่เท่านั้น</small>
                </div>
                <div>
                    <label class="form-label">Prefix ใบเสร็จ</label>
                    <input type="text" name="receipt_prefix" class="form-control" maxlength="10" value="<?= e($settings['receipt_prefix'] ?? 'RC') ?>">
                    <small>มีผลกับเลขใบเสร็จครั้งถัดไป</small>
                </div>
            </div>

            <div class="settings-preview-card">
                <div class="settings-preview-title">ตัวอย่างหลังบันทึก</div>
                <div><span>HN ใหม่</span><strong><?= e($hnPreview) ?></strong></div>
                <div><span>ใบเสร็จใหม่</span><strong><?= e($receiptPreview) ?></strong></div>
            </div>

            <div class="settings-savebar">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>บันทึกตั้งค่าคลินิก</button>
                <a href="<?= e(route_url('dashboard')) ?>" class="btn btn-outline-secondary">กลับ Dashboard</a>
            </div>
        </aside>
    </form>

    <section class="settings-panel settings-preset-panel" id="smartPresetSettings">
        <div class="settings-panel-head">
            <div>
                <div class="settings-eyebrow">Smart Exam Preset</div>
                <h3>จัดการ preset ที่ใช้เปิดเคสเร็ว</h3>
                <p>เปิดดูและแก้เฉพาะ preset ที่ต้องการ เพื่อลดความรกและลดโอกาสแก้ผิดรายการ</p>
            </div>
            <div class="settings-preset-summary">
                <span><?= e((string) $totalPresetCount) ?> รายการ</span>
                <strong><?= e((string) $activePresetCount) ?> เปิดใช้</strong>
            </div>
        </div>

        <?php if (empty($smartPresets)): ?>
            <div class="queue-empty-state text-start">ยังไม่มี preset ในฐานข้อมูล เปิดหน้า Smart Exam หนึ่งครั้งเพื่อ seed ค่าเริ่มต้น หรือเพิ่มผ่านฐานข้อมูล</div>
        <?php else: ?>
            <div class="settings-preset-list">
                <?php foreach (($smartPresets ?? []) as $index => $preset): ?>
                    <?php
                    $itemLines = [];
                    $items = json_decode((string) ($preset['item_codes_json'] ?? '[]'), true);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $itemLines[] = ($item['code'] ?? '') . ':' . ($item['qty'] ?? 1);
                        }
                    }
                    $isActive = !empty($preset['is_active']);
                    ?>
                    <details class="settings-preset-item" <?= $index === 0 ? 'open' : '' ?>>
                        <summary>
                            <span class="settings-preset-title">
                                <span class="settings-preset-dot <?= $isActive ? 'active' : 'inactive' ?>"></span>
                                <span>
                                    <strong><?= e($preset['label']) ?></strong>
                                    <small><?= e($preset['preset_key']) ?> · ลำดับ <?= e((string) $preset['sort_order']) ?></small>
                                </span>
                            </span>
                            <span class="settings-preset-meta">
                                <span class="settings-mini-pill"><?= e($preset['service_codes'] ?: 'ไม่มี service') ?></span>
                                <span class="settings-mini-pill"><?= count($itemLines) ?> item</span>
                                <span class="settings-mini-pill <?= $isActive ? 'success' : 'muted' ?>"><?= $isActive ? 'เปิดใช้' : 'ปิดใช้' ?></span>
                            </span>
                        </summary>

                        <form method="post" action="<?= e(route_url('settings-preset-store')) ?>" class="settings-preset-editor">
                            <?= csrf_field() ?>
                            <input type="hidden" name="preset_id" value="<?= (int) $preset['id'] ?>">
                            <input type="hidden" name="theme" value="<?= e($preset['theme'] ?? 'preset-custom') ?>">

                            <div class="settings-preset-preview">
                                <div>
                                    <div class="settings-preview-title">ผลที่เติมใน Smart Exam</div>
                                    <p><?= e($preset['description'] ?? 'ยังไม่มีคำอธิบาย') ?></p>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                                    <span>เปิดใช้</span>
                                </label>
                            </div>

                            <div class="settings-preset-grid">
                                <div>
                                    <label class="form-label">Preset Key</label>
                                    <input type="text" name="preset_key" class="form-control" value="<?= e($preset['preset_key']) ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">ลำดับ</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?= e((string) $preset['sort_order']) ?>">
                                </div>
                                <div class="settings-field-wide">
                                    <label class="form-label">ชื่อปุ่ม</label>
                                    <input type="text" name="label" class="form-control" value="<?= e($preset['label']) ?>" required>
                                </div>
                                <div class="settings-field-wide">
                                    <label class="form-label">คำอธิบาย</label>
                                    <textarea name="description" class="form-control settings-textarea-xs"><?= e($preset['description'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="form-label">Service codes</label>
                                    <input type="text" name="service_codes" class="form-control" value="<?= e($preset['service_codes'] ?? '') ?>" placeholder="SRV001, SRV002">
                                </div>
                                <div>
                                    <label class="form-label">Item codes</label>
                                    <input type="text" name="item_codes" class="form-control" value="<?= e(implode(', ', $itemLines)) ?>" placeholder="MED002:1, MED003:2">
                                </div>
                                <div>
                                    <label class="form-label">CC</label>
                                    <input type="text" name="cc" class="form-control" value="<?= e($preset['cc'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">Dx</label>
                                    <input type="text" name="dx" class="form-control" value="<?= e($preset['dx'] ?? '') ?>">
                                </div>
                                <div class="settings-field-wide">
                                    <label class="form-label">PI</label>
                                    <textarea name="pi" class="form-control settings-textarea-sm"><?= e($preset['pi'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="form-label">PE</label>
                                    <input type="text" name="pe" class="form-control" value="<?= e($preset['pe'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">นัดติดตาม (วัน)</label>
                                    <input type="number" name="followup_days" class="form-control" value="<?= e((string) ($preset['followup_days'] ?? '')) ?>">
                                </div>
                                <div class="settings-field-wide">
                                    <label class="form-label">คำแนะนำ</label>
                                    <textarea name="advice" class="form-control settings-textarea-sm"><?= e($preset['advice'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary settings-preset-save"><i class="bi bi-save2 me-1"></i>บันทึก Preset นี้</button>
                        </form>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
