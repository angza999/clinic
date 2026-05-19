<?php
$typeMeta = $types[$selectedType] ?? reset($types);
$selectedType = array_key_first(array_filter($types, static fn ($meta, $key) => $key === $selectedType, ARRAY_FILTER_USE_BOTH)) ?: array_key_first($types);
$typeMeta = $types[$selectedType] ?? ['label' => '-'];
$currentStep = $log ? (($log['status'] === 'VALIDATED') ? 3 : (($log['status'] === 'CONFIRMED') ? 4 : 2)) : 1;
$statusClass = static function (string $status): string {
    return match ($status) {
        'VALID', 'IMPORTED' => 'is-valid',
        'ERROR', 'FAILED' => 'is-error',
        'DUPLICATE', 'SKIPPED' => 'is-warning',
        default => 'is-pending',
    };
};
?>

<section class="import-workstation">
    <div class="import-main">
        <div class="import-panel import-hero">
            <div>
                <div class="import-kicker">Data Onboarding</div>
                <h2>นำเข้าข้อมูล Excel</h2>
                <p>Preview, mapping, validate และ confirm ก่อนเขียนเข้าฐานข้อมูลเสมอ</p>
            </div>
            <div class="import-template-actions">
                <?php foreach ($types as $typeKey => $meta): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('import-template', ['type' => $typeKey])) ?>">
                        <i class="bi bi-download me-1"></i><?= e($meta['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$dependencyReady): ?>
            <div class="import-alert is-warning">
                <strong>ยังไม่พบ PhpSpreadsheet</strong>
                <span>ติดตั้งด้วย <code>composer install</code> หรือ <code>composer require phpoffice/phpspreadsheet</code> เพื่ออ่านไฟล์ .xlsx/.xls ได้เต็มรูปแบบ ตอนนี้ระบบยังรองรับ .csv เป็น fallback</span>
            </div>
        <?php endif; ?>

        <div class="import-steps" aria-label="Import progress">
            <?php foreach ([1 => 'เลือกประเภท', 2 => 'Preview + Mapping', 3 => 'Validate', 4 => 'Result'] as $stepNo => $stepLabel): ?>
                <div class="import-step <?= $currentStep >= $stepNo ? 'active' : '' ?>">
                    <span><?= $stepNo ?></span>
                    <strong><?= e($stepLabel) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="import-grid">
            <div class="import-panel import-upload">
                <div class="panel-header">
                    <div>
                        <div class="section-kicker">Step 1</div>
                        <h3>เลือกประเภทและอัปโหลด</h3>
                    </div>
                </div>

                <form method="post" action="<?= e(route_url('import-upload')) ?>" enctype="multipart/form-data" class="import-form">
                    <?= csrf_field() ?>
                    <label class="form-label">ประเภทข้อมูล</label>
                    <div class="type-selector">
                        <?php foreach ($types as $typeKey => $meta): ?>
                            <label class="type-tile <?= $selectedType === $typeKey ? 'active' : '' ?>">
                                <input type="radio" name="import_type" value="<?= e($typeKey) ?>" <?= checked($selectedType, $typeKey) ?>>
                                <span><?= e($meta['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label class="form-label mt-3">ไฟล์ Excel</label>
                    <input class="form-control" type="file" name="excel_file" accept=".xlsx,.xls,.csv">
                    <div class="form-hint">รองรับ .xlsx, .xls, .csv ขนาดไม่เกิน 5MB / 2,000 แถว</div>

                    <button class="btn btn-primary w-100 mt-3" type="submit">
                        <i class="bi bi-upload me-1"></i>Upload และ Preview
                    </button>
                </form>
            </div>

            <div class="import-panel import-mapping">
                <div class="panel-header">
                    <div>
                        <div class="section-kicker">Step 2</div>
                        <h3>Mapping column</h3>
                    </div>
                    <?php if ($log): ?>
                        <span class="log-chip">#<?= (int) $log['id'] ?> <?= e((string) $log['status']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($log && $headers): ?>
                    <form method="post" action="<?= e(route_url('import-validate')) ?>" class="mapping-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="import_log_id" value="<?= (int) $log['id'] ?>">
                        <div class="mapping-grid">
                            <?php foreach ($defaultColumns as $field): ?>
                                <label>
                                    <span><?= e($field) ?></span>
                                    <select name="mapping[<?= e($field) ?>]" class="form-select form-select-sm">
                                        <option value="">ไม่ใช้</option>
                                        <?php foreach ($headers as $header): ?>
                                            <option value="<?= e($header) ?>" <?= selected($header, $field) ?>><?= e($header) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-success mt-3">
                            <i class="bi bi-check2-circle me-1"></i>Validate ข้อมูล
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">อัปโหลดไฟล์ก่อนเพื่อ preview และ mapping column</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="import-panel import-preview">
            <div class="panel-header">
                <div>
                    <div class="section-kicker">Preview</div>
                    <h3>ตัวอย่างข้อมูล 20 แถวแรก</h3>
                </div>
                <?php if ($log): ?>
                    <div class="summary-pills">
                        <span>ทั้งหมด <?= (int) $log['total_rows'] ?></span>
                        <span class="ok">ผ่าน <?= (int) $log['success_rows'] ?></span>
                        <span class="warn">ซ้ำ <?= (int) $log['duplicate_rows'] ?></span>
                        <span class="bad">ผิด <?= (int) $log['error_rows'] ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($rows && $headers): ?>
                <div class="preview-table-wrap">
                    <table class="table import-table align-middle">
                        <thead>
                        <tr>
                            <th>Row</th>
                            <th>Status</th>
                            <?php foreach ($headers as $header): ?>
                                <th><?= e($header) ?></th>
                            <?php endforeach; ?>
                            <th>Error</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $raw = json_decode((string) $row['row_data_json'], true) ?: []; ?>
                            <tr class="<?= e($statusClass((string) $row['status'])) ?>">
                                <td><?= (int) $row['row_number'] ?></td>
                                <td><span class="row-status"><?= e((string) $row['status']) ?></span></td>
                                <?php foreach ($headers as $header): ?>
                                    <td><?= e((string) ($raw[$header] ?? '')) ?></td>
                                <?php endforeach; ?>
                                <td><?= e((string) ($row['error_message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">ยังไม่มีข้อมูล preview</div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="import-summary">
        <div class="summary-card">
            <div class="section-kicker">Control Surface</div>
            <h3><?= e($typeMeta['label']) ?></h3>
            <?php if ($log): ?>
                <div class="result-stack">
                    <div><span>ไฟล์</span><strong><?= e((string) $log['file_name']) ?></strong></div>
                    <div><span>สถานะ</span><strong><?= e((string) $log['status']) ?></strong></div>
                    <div><span>ผ่าน</span><strong><?= (int) $log['success_rows'] ?></strong></div>
                    <div><span>ผิด</span><strong><?= (int) $log['error_rows'] ?></strong></div>
                    <div><span>ซ้ำ</span><strong><?= (int) $log['duplicate_rows'] ?></strong></div>
                </div>

                <div class="readiness-list">
                    <div class="<?= $log['status'] === 'VALIDATED' || $log['status'] === 'CONFIRMED' ? 'ready' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i> Validate แล้ว
                    </div>
                    <div class="<?= (int) $log['success_rows'] > 0 ? 'ready' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i> มีแถวที่นำเข้าได้
                    </div>
                    <div class="<?= (int) $log['error_rows'] === 0 && (int) $log['duplicate_rows'] === 0 ? 'ready' : 'blocked' ?>">
                        <i class="bi bi-exclamation-triangle-fill"></i> ไม่มี error/duplicate
                    </div>
                </div>

                <?php if ($log['status'] === 'VALIDATED'): ?>
                    <form method="post" action="<?= e(route_url('import-confirm')) ?>" class="confirm-import-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="import_log_id" value="<?= (int) $log['id'] ?>">
                        <?php if ((int) $log['error_rows'] > 0 || (int) $log['duplicate_rows'] > 0): ?>
                            <label class="skip-toggle">
                                <input type="checkbox" name="skip_error_rows" value="1">
                                <span>ข้ามแถว error/duplicate</span>
                            </label>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-database-check me-1"></i>Confirm Import
                        </button>
                    </form>
                <?php elseif ($log['status'] === 'CONFIRMED'): ?>
                    <div class="import-alert is-success">Import ชุดนี้เสร็จแล้ว</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state compact">ยังไม่มีไฟล์ที่รอ confirm</div>
            <?php endif; ?>
        </div>

        <div class="summary-card">
            <div class="section-kicker">Recent</div>
            <h3>ประวัติ Import</h3>
            <div class="recent-log-list">
                <?php foreach ($recentLogs as $recent): ?>
                    <a href="<?= e(route_url('import', ['type' => $recent['import_type'], 'log' => $recent['id']])) ?>">
                        <strong>#<?= (int) $recent['id'] ?> <?= e((string) $recent['import_type']) ?></strong>
                        <span><?= e((string) $recent['status']) ?> · <?= thai_date((string) $recent['created_at']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$recentLogs): ?>
                    <div class="empty-state compact">ยังไม่มีประวัติ</div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</section>
