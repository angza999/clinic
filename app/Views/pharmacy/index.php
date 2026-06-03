<?php
$kpis = $kpis ?? [];
$printQueue = $printQueue ?? [];
$drugProfiles = $drugProfiles ?? [];
$recentLogs = $recentLogs ?? [];
$firstProfile = $drugProfiles[0] ?? [];
$profileJson = static function (array $row): string {
    return e(json_encode([
        'item_id' => (int) ($row['item_id'] ?? 0),
        'item_name' => (string) ($row['item_name'] ?? ''),
        'drug_short_name' => (string) ($row['drug_short_name'] ?? ''),
        'drug_category' => (string) ($row['drug_category'] ?? ''),
        'default_dose_qty' => (string) ($row['default_dose_qty'] ?? ''),
        'default_dose_unit' => (string) ($row['default_dose_unit'] ?? ''),
        'default_frequency' => (string) ($row['default_frequency'] ?? ''),
        'default_timing' => (string) ($row['default_timing'] ?? ''),
        'default_instruction' => (string) ($row['default_instruction'] ?? ''),
        'warning_text' => (string) ($row['warning_text'] ?? ''),
        'profile_active' => (int) ($row['profile_active'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
?>

<div class="pharmacy-workstation-page">
    <section class="pharmacy-command pharmacy-command-compact">
        <div>
            <div class="pharmacy-kicker">Pharmacy Workstation</div>
            <h1>สติ๊กเกอร์ซองยา</h1>
            <p>จัดการคิวพิมพ์ พิมพ์ซ้ำ และตั้งค่า default วิธีใช้ยาจากคลังยา</p>
        </div>
        <div class="pharmacy-kpi-grid">
            <button type="button" class="pharmacy-kpi-card" data-pharmacy-filter="pending">
                <span>รอพิมพ์</span>
                <strong><?= e((string) ($kpis['pending_labels'] ?? 0)) ?></strong>
            </button>
            <button type="button" class="pharmacy-kpi-card" data-pharmacy-filter="printed">
                <span>พิมพ์วันนี้</span>
                <strong><?= e((string) ($kpis['printed_today'] ?? 0)) ?></strong>
            </button>
            <button type="button" class="pharmacy-kpi-card" data-pharmacy-filter="all">
                <span>ยาทั้งหมด</span>
                <strong><?= e((string) ($kpis['drug_count'] ?? 0)) ?></strong>
            </button>
            <button type="button" class="pharmacy-kpi-card pharmacy-kpi-card-warning" data-pharmacy-filter="risk">
                <span>ต้องตรวจ profile</span>
                <strong><?= e((string) ($kpis['profile_risk'] ?? 0)) ?></strong>
            </button>
        </div>
    </section>

    <div class="pharmacy-workstation-grid">
        <aside class="pharmacy-panel pharmacy-queue-surface">
            <div class="pharmacy-panel-head">
                <div>
                    <div class="pharmacy-section-title">Print Queue</div>
                    <h2>คิวพิมพ์ฉลากยา</h2>
                </div>
                <span class="pharmacy-count-pill"><?= e((string) count($printQueue)) ?> เคส</span>
            </div>

            <div class="pharmacy-queue-list">
                <?php foreach ($printQueue as $queue): ?>
                    <?php
                    $patientName = trim((string) (($queue['first_name'] ?? '') . ' ' . ($queue['last_name'] ?? '')));
                    $pendingCount = (int) ($queue['pending_count'] ?? 0);
                    ?>
                    <article class="pharmacy-queue-card <?= $pendingCount > 0 ? 'is-pending' : 'is-printed' ?>">
                        <div>
                            <strong><?= e($patientName !== '' ? $patientName : '-') ?></strong>
                            <span>HN <?= e((string) ($queue['hn'] ?? '-')) ?> / VN <?= e((string) ($queue['visit_no'] ?? '-')) ?></span>
                        </div>
                        <div class="pharmacy-queue-meta">
                            <span>ฉลาก <?= e((string) ($queue['label_count'] ?? 0)) ?></span>
                            <span><?= $pendingCount > 0 ? 'รอพิมพ์ ' . e((string) $pendingCount) : 'พิมพ์แล้ว' ?></span>
                        </div>
                        <a class="btn btn-sm btn-primary" href="<?= e(route_url('pharmacy-labels', ['visit_id' => (int) ($queue['visit_id'] ?? 0)])) ?>">
                            <i class="bi bi-printer-fill"></i> Preview
                        </a>
                    </article>
                <?php endforeach; ?>
                <?php if (!$printQueue): ?>
                    <div class="pharmacy-empty-compact">
                        <i class="bi bi-check-circle"></i>
                        ยังไม่มีคิวพิมพ์ฉลากยา
                    </div>
                <?php endif; ?>
            </div>

            <div class="pharmacy-log-mini">
                <div class="pharmacy-section-title">Recent Print</div>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="pharmacy-log-row-mini">
                        <strong><?= e((string) ($log['drug_name_snapshot'] ?? '-')) ?></strong>
                        <span><?= e((string) ($log['hn'] ?? '-')) ?> · <?= thai_date($log['printed_at'] ?? null) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentLogs): ?>
                    <span class="pharmacy-muted">ยังไม่มีประวัติพิมพ์ล่าสุด</span>
                <?php endif; ?>
            </div>
        </aside>

        <main class="pharmacy-panel pharmacy-drug-master">
            <div class="pharmacy-panel-head">
                <div>
                    <div class="pharmacy-section-title">Drug Master</div>
                    <h2>ยาและ default วิธีใช้</h2>
                </div>
                <div class="pharmacy-master-tools">
                    <label class="pharmacy-search">
                        <i class="bi bi-search"></i>
                        <input type="search" placeholder="ค้นหาชื่อยา / code / category" data-pharmacy-profile-search>
                    </label>
                    <select data-pharmacy-profile-status>
                        <option value="all">ทั้งหมด</option>
                        <option value="active">เปิดใช้</option>
                        <option value="risk">ต้องตรวจ</option>
                        <option value="inactive">ปิดใช้</option>
                    </select>
                </div>
            </div>

            <div class="pharmacy-profile-table">
                <div class="pharmacy-profile-row pharmacy-profile-header">
                    <span>ยา</span>
                    <span>วิธีใช้ default</span>
                    <span>Stock</span>
                    <span>สถานะ</span>
                </div>
                <?php foreach ($drugProfiles as $index => $profile): ?>
                    <?php
                    $instruction = trim((string) ($profile['default_instruction'] ?? ''));
                    $isRisk = $instruction === '';
                    $isActive = (int) ($profile['profile_active'] ?? 0) === 1;
                    ?>
                    <button
                        type="button"
                        class="pharmacy-profile-row <?= $index === 0 ? 'is-selected' : '' ?> <?= $isRisk ? 'is-risk' : '' ?>"
                        data-pharmacy-profile-row
                        data-profile="<?= $profileJson($profile) ?>"
                        data-search="<?= e(strtolower((string) (($profile['item_code'] ?? '') . ' ' . ($profile['item_name'] ?? '') . ' ' . ($profile['drug_category'] ?? '')))) ?>"
                        data-status="<?= $isRisk ? 'risk' : ($isActive ? 'active' : 'inactive') ?>"
                    >
                        <span>
                            <strong><?= e((string) ($profile['item_name'] ?? '-')) ?></strong>
                            <small><?= e((string) (($profile['item_code'] ?? '') ?: 'ไม่มีรหัส')) ?> · ใช้แล้ว <?= e((string) ($profile['use_count'] ?? 0)) ?> ครั้ง</small>
                        </span>
                        <span><?= e($instruction !== '' ? $instruction : 'ยังไม่ตั้ง default วิธีใช้') ?></span>
                        <span><?= format_money($profile['total_qty'] ?? 0) ?> <?= e((string) ($profile['unit_name'] ?? '')) ?></span>
                        <span><em class="<?= $isRisk ? 'risk' : ($isActive ? 'active' : 'inactive') ?>"><?= $isRisk ? 'ต้องตรวจ' : ($isActive ? 'เปิดใช้' : 'ปิดใช้') ?></em></span>
                    </button>
                <?php endforeach; ?>
                <?php if (!$drugProfiles): ?>
                    <div class="pharmacy-empty-compact">ยังไม่พบรายการยาในคลังยา</div>
                <?php endif; ?>
            </div>
        </main>

        <aside class="pharmacy-panel pharmacy-editor">
            <div class="pharmacy-panel-head">
                <div>
                    <div class="pharmacy-section-title">Smart Builder</div>
                    <h2>ตั้งค่า label profile</h2>
                </div>
                <span class="pharmacy-count-pill">Drug</span>
            </div>

            <div class="pharmacy-profile-preview">
                <span>Preview</span>
                <strong data-pharmacy-preview-name><?= e((string) (($firstProfile['drug_short_name'] ?? '') ?: ($firstProfile['item_name'] ?? '-'))) ?></strong>
                <p data-pharmacy-preview-instruction><?= e((string) (($firstProfile['default_instruction'] ?? '') ?: 'ยังไม่ตั้ง default วิธีใช้')) ?></p>
            </div>

            <form method="post" action="<?= e(route_url('drug-profile-store')) ?>" class="pharmacy-editor-form">
                <?= csrf_field() ?>
                <label>
                    <span>เลือกรายการยา</span>
                    <select name="item_id" data-pharmacy-field="item_id" required>
                        <?php foreach ($drugProfiles as $profile): ?>
                            <option value="<?= e((string) ($profile['item_id'] ?? 0)) ?>" <?= selected((string) ($firstProfile['item_id'] ?? ''), (string) ($profile['item_id'] ?? '')) ?>>
                                <?= e((string) ($profile['item_name'] ?? '-')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>ชื่อย่อ / ชื่อบนฉลาก</span>
                    <input type="text" name="drug_short_name" data-pharmacy-field="drug_short_name" value="<?= e((string) ($firstProfile['drug_short_name'] ?? '')) ?>" placeholder="เช่น Paracetamol 500mg">
                </label>

                <label>
                    <span>Category</span>
                    <input type="text" name="drug_category" data-pharmacy-field="drug_category" value="<?= e((string) ($firstProfile['drug_category'] ?? '')) ?>" placeholder="เช่น ยาแก้ปวด">
                </label>

                <div class="pharmacy-editor-inline">
                    <label>
                        <span>ครั้งละ</span>
                        <input type="text" name="default_dose_qty" data-pharmacy-field="default_dose_qty" value="<?= e((string) ($firstProfile['default_dose_qty'] ?? '')) ?>" placeholder="1">
                    </label>
                    <label>
                        <span>หน่วย</span>
                        <input type="text" name="default_dose_unit" data-pharmacy-field="default_dose_unit" value="<?= e((string) ($firstProfile['default_dose_unit'] ?? '')) ?>" placeholder="เม็ด">
                    </label>
                </div>

                <div class="pharmacy-editor-inline">
                    <label>
                        <span>ความถี่</span>
                        <select name="default_frequency" data-pharmacy-field="default_frequency">
                            <?php foreach (['', 'วันละ 1 ครั้ง', 'วันละ 2 ครั้ง', 'วันละ 3 ครั้ง', 'ทุก 4 ชั่วโมง', 'ก่อนนอน'] as $frequency): ?>
                                <option value="<?= e($frequency) ?>" <?= selected((string) ($firstProfile['default_frequency'] ?? ''), $frequency) ?>><?= e($frequency !== '' ? $frequency : 'เลือก') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>เวลา</span>
                        <select name="default_timing" data-pharmacy-field="default_timing">
                            <?php foreach (['', 'ก่อนอาหาร', 'หลังอาหาร', 'ก่อนนอน', 'เมื่อมีอาการ'] as $timing): ?>
                                <option value="<?= e($timing) ?>" <?= selected((string) ($firstProfile['default_timing'] ?? ''), $timing) ?>><?= e($timing !== '' ? $timing : 'เลือก') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label>
                    <span>วิธีใช้บนฉลาก</span>
                    <textarea name="default_instruction" data-pharmacy-field="default_instruction" rows="3" placeholder="ระบบสร้างให้อัตโนมัติได้"><?= e((string) ($firstProfile['default_instruction'] ?? '')) ?></textarea>
                </label>

                <label>
                    <span>คำเตือน</span>
                    <textarea name="warning_text" data-pharmacy-field="warning_text" rows="2" placeholder="เช่น ห้ามใช้เกินวันละ 8 เม็ด"><?= e((string) ($firstProfile['warning_text'] ?? '')) ?></textarea>
                </label>

                <label class="pharmacy-check-row">
                    <input type="checkbox" name="is_active" value="1" data-pharmacy-field="is_active" <?= (int) ($firstProfile['profile_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>เปิดใช้ profile นี้</span>
                </label>

                <button type="submit" class="btn btn-primary pharmacy-print-button">
                    <i class="bi bi-save2-fill"></i> บันทึก Drug Profile
                </button>
            </form>
        </aside>
    </div>
</div>
