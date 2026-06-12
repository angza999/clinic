<?php
$preset = $selectedPreset ?? null;
$presetServices = $preset['services'] ?? [];
$presetMedications = $preset['medications'] ?? [];
$presetSupplies = $preset['supplies'] ?? [];

$rowAt = static fn(array $rows, int $index): array => $rows[$index] ?? [];
$rowCount = static fn(array $rows, int $min = 4): int => max($min, count($rows) + 1);
?>

<section class="tp-shell">
    <div class="tp-hero">
        <div>
            <div class="tp-kicker">TREATMENT PRESET</div>
            <h1>จัดการ Preset การรักษา</h1>
            <p>กำหนดชุดบริการ ยา และเวชภัณฑ์ให้ Smart Exam เพิ่มเข้าคดีได้ในคลิกเดียว</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(route_url('queue')) ?>">
            <i class="bi bi-arrow-left-short"></i> กลับคิววันนี้
        </a>
    </div>

    <div class="tp-layout">
        <aside class="tp-list-panel">
            <div class="tp-panel-head">
                <div>
                    <div class="tp-kicker">PRESETS</div>
                    <h2>รายการ Preset</h2>
                </div>
                <a class="btn btn-sm btn-primary" href="<?= e(route_url('treatment-presets')) ?>">
                    <i class="bi bi-plus-lg"></i> ใหม่
                </a>
            </div>

            <div class="tp-preset-list">
                <?php foreach ($presets as $item): ?>
                    <?php $isSelected = $preset && (int) $preset['id'] === (int) $item['id']; ?>
                    <a class="tp-preset-row <?= $isSelected ? 'is-active' : '' ?>" href="<?= e(route_url('treatment-presets', ['id' => (int) $item['id']])) ?>">
                        <span>
                            <strong><?= e((string) $item['preset_name']) ?></strong>
                            <small><?= (int) $item['service_count'] ?> บริการ · <?= (int) $item['medication_count'] ?> ยา · <?= (int) $item['supply_count'] ?> เวชภัณฑ์</small>
                        </span>
                        <em class="<?= !empty($item['is_active']) ? 'active' : 'inactive' ?>">
                            <?= !empty($item['is_active']) ? 'เปิดใช้' : 'ปิดใช้' ?>
                        </em>
                    </a>
                <?php endforeach; ?>

                <?php if (!$presets): ?>
                    <div class="tp-empty">ยังไม่มี Treatment Preset</div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="tp-builder">
            <form method="post" action="<?= e(route_url('treatment-presets-store')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="preset_id" value="<?= e((string) ($preset['id'] ?? 0)) ?>">

                <div class="tp-builder-card">
                    <div class="tp-builder-top">
                        <div>
                            <div class="tp-kicker">BUILDER</div>
                            <h2><?= $preset ? 'แก้ไข Preset' : 'เพิ่ม Preset ใหม่' ?></h2>
                        </div>
                        <label class="tp-switch">
                            <input type="checkbox" name="is_active" value="1" <?= (!$preset || !empty($preset['is_active'])) ? 'checked' : '' ?>>
                            <span>เปิดใช้งาน</span>
                        </label>
                    </div>

                    <div class="tp-grid-2">
                        <label>
                            <span>ชื่อ Preset</span>
                            <input class="form-control" name="preset_name" value="<?= e((string) ($preset['preset_name'] ?? '')) ?>" placeholder="เช่น URI, ฉีดยาแก้แพ้, วิตามินรวม" required>
                        </label>
                        <label>
                            <span>คำอธิบายสั้น</span>
                            <input class="form-control" name="description" value="<?= e((string) ($preset['description'] ?? '')) ?>" placeholder="แสดงให้พยาบาลอ่านก่อนยืนยัน">
                        </label>
                    </div>
                </div>

                <div class="tp-builder-card">
                    <div class="tp-section-title">
                        <i class="bi bi-clipboard2-pulse"></i>
                        <span>บริการที่จะเพิ่ม</span>
                    </div>

                    <div class="tp-lines">
                        <?php for ($i = 0; $i < $rowCount($presetServices); $i++): ?>
                            <?php $line = $rowAt($presetServices, $i); ?>
                            <div class="tp-line tp-line-service">
                                <select class="form-select" name="service_id[]">
                                    <option value="">เลือกบริการ</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= (int) $service['id'] ?>" <?= (int) ($line['service_id'] ?? 0) === (int) $service['id'] ? 'selected' : '' ?>>
                                            <?= e((string) $service['service_name']) ?> (<?= e((string) $service['service_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control" type="number" step="0.01" min="0.01" name="service_qty[]" value="<?= e((string) ($line['qty'] ?? '1')) ?>" aria-label="จำนวนบริการ">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="tp-builder-card">
                    <div class="tp-section-title">
                        <i class="bi bi-capsule-pill"></i>
                        <span>ยาใน Preset</span>
                    </div>

                    <div class="tp-lines">
                        <?php for ($i = 0; $i < $rowCount($presetMedications); $i++): ?>
                            <?php $line = $rowAt($presetMedications, $i); ?>
                            <div class="tp-line tp-line-med">
                                <select class="form-select" name="medicine_id[]">
                                    <option value="">เลือกยา</option>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <option value="<?= (int) $medicine['id'] ?>" <?= (int) ($line['medicine_id'] ?? 0) === (int) $medicine['id'] ? 'selected' : '' ?>>
                                            <?= e((string) $medicine['item_name']) ?> (<?= e((string) $medicine['item_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control" type="number" step="0.01" min="0.01" name="medicine_qty[]" value="<?= e((string) ($line['qty'] ?? '1')) ?>" aria-label="จำนวนยา">
                                <input class="form-control" name="medicine_instruction[]" value="<?= e((string) ($line['instruction'] ?? '')) ?>" placeholder="วิธีใช้ เช่น ครั้งละ 1 เม็ด หลังอาหาร">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="tp-builder-card">
                    <div class="tp-section-title">
                        <i class="bi bi-box-seam"></i>
                        <span>เวชภัณฑ์ / อุปกรณ์</span>
                    </div>

                    <div class="tp-lines">
                        <?php for ($i = 0; $i < $rowCount($presetSupplies); $i++): ?>
                            <?php $line = $rowAt($presetSupplies, $i); ?>
                            <div class="tp-line tp-line-supply">
                                <select class="form-select" name="supply_id[]">
                                    <option value="">เลือกเวชภัณฑ์</option>
                                    <?php foreach ($supplies as $supply): ?>
                                        <option value="<?= (int) $supply['id'] ?>" <?= (int) ($line['supply_id'] ?? 0) === (int) $supply['id'] ? 'selected' : '' ?>>
                                            <?= e((string) $supply['item_name']) ?> (<?= e((string) $supply['item_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control" type="number" step="0.01" min="0.01" name="supply_qty[]" value="<?= e((string) ($line['qty'] ?? '1')) ?>" aria-label="จำนวนเวชภัณฑ์">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="tp-actions">
                    <?php if ($preset): ?>
                        <button type="submit" formaction="<?= e(route_url('treatment-presets-delete')) ?>" class="btn btn-outline-danger">
                            <i class="bi bi-archive"></i> ปิดใช้งาน
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save2"></i> บันทึก Preset
                    </button>
                </div>
            </form>
        </main>
    </div>
</section>
