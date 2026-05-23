<?php
$services = $services ?? [];
$categories = $categories ?? [];
$priceHistory = $priceHistory ?? [];
$recentAudit = $recentAudit ?? [];
$totalServices = count($services);
$activeCount = 0;
$inactiveCount = 0;
$freeCount = 0;
$totalPrice = 0.0;
$topService = null;

foreach ($services as $service) {
    $isActive = (int) ($service['is_active'] ?? 0) === 1;
    $price = (float) ($service['price'] ?? 0);
    $totalPrice += $price;
    $activeCount += $isActive ? 1 : 0;
    $inactiveCount += $isActive ? 0 : 1;
    $freeCount += $price <= 0 ? 1 : 0;

    if ($topService === null || (float) ($service['total_qty'] ?? 0) > (float) ($topService['total_qty'] ?? 0)) {
        $topService = $service;
    }
}

$averagePrice = $totalServices > 0 ? $totalPrice / $totalServices : 0;
$categoryPrefix = [
    'ตรวจทั่วไป' => 'GEN',
    'หัตถการ' => 'PROC',
    'พยาบาล' => 'NUR',
    'ฉีดยา' => 'INJ',
    'ทำแผล' => 'WND',
    'อื่น ๆ' => 'SRV',
];
$categoryOptions = array_values(array_unique(array_merge(array_keys($categoryPrefix), $categories)));
$categoryCount = count(array_unique(array_filter(array_map(static fn($value) => trim((string) $value), $categories))));
$canManage = has_role('ADMIN');
?>

<div class="service-workstation" data-service-workstation>
    <section class="service-hero">
        <div>
            <div class="service-kicker">Service Management Workstation</div>
            <h1>บริการและราคา</h1>
            <p>บริหารมาตรฐานบริการ ราคา สถานะ และข้อมูลการใช้งานสำหรับ Smart Exam และการเงิน</p>
        </div>
        <div class="service-hero-actions">
            <?php if ($canManage): ?>
                <button type="button" class="service-primary-action" data-service-new>
                    <i class="bi bi-plus-circle"></i> เพิ่มบริการ
                </button>
                <a class="service-secondary-action" href="<?= e(route_url('services-export')) ?>">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            <?php endif; ?>
            <a class="service-secondary-action" href="<?= e(route_url('import', ['type' => 'services'])) ?>">
                <i class="bi bi-file-earmark-spreadsheet"></i> Import Excel
            </a>
        </div>
    </section>

    <section class="service-kpi-grid" aria-label="สรุปบริการ">
        <button type="button" class="service-kpi-card" data-service-kpi="all">
            <span>บริการทั้งหมด</span>
            <strong><?= e((string) $totalServices) ?></strong>
            <small>คลิกเพื่อแสดงทั้งหมด</small>
        </button>
        <button type="button" class="service-kpi-card is-teal" data-service-kpi="active">
            <span>เปิดใช้งาน</span>
            <strong><?= e((string) $activeCount) ?></strong>
            <small>ใช้ใน Smart Exam ได้</small>
        </button>
        <button type="button" class="service-kpi-card is-muted" data-service-kpi="inactive">
            <span>ปิดใช้งาน</span>
            <strong><?= e((string) $inactiveCount) ?></strong>
            <small>ซ่อนจากการเลือกใหม่</small>
        </button>
        <button type="button" class="service-kpi-card is-blue" data-service-kpi="category">
            <span>หมวดหมู่</span>
            <strong><?= e((string) $categoryCount) ?></strong>
            <small>เรียงตามกลุ่มบริการ</small>
        </button>
        <button type="button" class="service-kpi-card is-orange" data-service-kpi="price">
            <span>ราคาเฉลี่ย</span>
            <strong><?= e(number_format($averagePrice, 2)) ?></strong>
            <small><?= $freeCount > 0 ? e($freeCount . ' รายการไม่มีค่าใช้จ่าย') : 'เรียงจากราคาสูง' ?></small>
        </button>
    </section>

    <section class="service-control-bar">
        <div class="service-search-wrap">
            <i class="bi bi-search"></i>
            <input id="serviceSearch" type="search" placeholder="ค้นหาชื่อบริการ รหัส หมวดหมู่ หรือราคา">
        </div>
        <select id="serviceCategoryFilter" aria-label="กรองหมวดหมู่">
            <option value="all">ทุกหมวดหมู่</option>
            <?php foreach ($categoryOptions as $category): ?>
                <option value="<?= e($category) ?>"><?= e($category) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="service-filter-group" aria-label="กรองสถานะ">
            <button type="button" class="service-filter is-active" data-service-filter="all">ทั้งหมด</button>
            <button type="button" class="service-filter" data-service-filter="active">เปิดใช้</button>
            <button type="button" class="service-filter" data-service-filter="inactive">ปิดใช้</button>
            <button type="button" class="service-filter" data-service-filter="free">ไม่มีค่าใช้จ่าย</button>
        </div>
    </section>

    <div class="service-layout">
        <section class="service-panel service-main-panel">
            <div class="service-panel-header">
                <div>
                    <div class="service-kicker">Service Data Grid</div>
                    <h2>รายการบริการของคลินิก</h2>
                </div>
                <span class="service-count"><span data-service-visible-count><?= e((string) $totalServices) ?></span> รายการ</span>
            </div>
            <div class="service-table-wrap">
                <table class="service-table">
                    <thead>
                        <tr>
                            <th><button type="button" data-service-sort="code">รหัส</button></th>
                            <th><button type="button" data-service-sort="name">ชื่อบริการ</button></th>
                            <th><button type="button" data-service-sort="category">หมวดหมู่</button></th>
                            <th class="text-end"><button type="button" data-service-sort="price">ราคา</button></th>
                            <th class="text-end"><button type="button" data-service-sort="usage">ใช้แล้ว</button></th>
                            <th class="text-end"><button type="button" data-service-sort="income">รายได้</button></th>
                            <th>สถานะ</th>
                            <th class="text-end">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody data-service-tbody>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceId = (int) ($service['id'] ?? 0);
                            $code = (string) ($service['service_code'] ?? '');
                            $name = (string) ($service['service_name'] ?? '');
                            $category = trim((string) ($service['category'] ?? '')) ?: 'อื่น ๆ';
                            $price = (float) ($service['price'] ?? 0);
                            $usage = (float) ($service['total_qty'] ?? 0);
                            $income = (float) ($service['total_income'] ?? 0);
                            $lastUsed = (string) ($service['last_used_at'] ?? '');
                            $smartExamCount = (int) ($service['smart_exam_preset_count'] ?? 0);
                            $status = (int) ($service['is_active'] ?? 0) === 1 ? 'active' : 'inactive';
                            $search = trim($code . ' ' . $name . ' ' . $category . ' ' . number_format($price, 2));
                            ?>
                            <tr data-service-row
                                data-id="<?= e((string) $serviceId) ?>"
                                data-code="<?= e($code) ?>"
                                data-name="<?= e($name) ?>"
                                data-category="<?= e($category) ?>"
                                data-price="<?= e((string) $price) ?>"
                                data-usage="<?= e((string) $usage) ?>"
                                data-income="<?= e((string) $income) ?>"
                                data-last-used="<?= e($lastUsed) ?>"
                                data-smart-exam="<?= e((string) $smartExamCount) ?>"
                                data-status="<?= e($status) ?>"
                                data-search="<?= e($search) ?>">
                                <td><span class="service-code"><?= e($code) ?></span></td>
                                <td>
                                    <strong><?= e($name) ?></strong>
                                    <small><?= $lastUsed !== '' ? 'ใช้ล่าสุด ' . e($lastUsed) : 'ยังไม่มีประวัติใช้งาน' ?></small>
                                </td>
                                <td><span class="service-category"><?= e($category) ?></span></td>
                                <td class="text-end">
                                    <strong><?= e(number_format($price, 2)) ?></strong>
                                    <?php if ($price <= 0): ?><small class="service-free-badge">ไม่มีค่าใช้จ่าย</small><?php endif; ?>
                                </td>
                                <td class="text-end"><?= e(number_format($usage, 0)) ?></td>
                                <td class="text-end"><?= e(number_format($income, 2)) ?></td>
                                <td>
                                    <span class="service-status is-<?= e($status) ?>"><?= $status === 'active' ? 'เปิดใช้' : 'ปิดใช้' ?></span>
                                </td>
                                <td>
                                    <div class="service-row-actions">
                                        <button type="button" data-service-row-action="detail">ดู</button>
                                        <button type="button" data-service-row-action="history">ประวัติราคา</button>
                                        <?php if ($canManage): ?>
                                            <button type="button" data-service-row-action="edit">แก้ไข</button>
                                            <button type="button" data-service-row-action="duplicate">Duplicate</button>
                                            <form class="service-inline-form" method="post" action="<?= e(route_url('services-toggle')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="service_id" value="<?= e((string) $serviceId) ?>">
                                                <?php if ($status !== 'active'): ?>
                                                    <input type="hidden" name="is_active" value="1">
                                                <?php endif; ?>
                                                <button type="submit"><?= $status === 'active' ? 'ปิด' : 'เปิด' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="service-empty-row d-none" data-service-empty-row>
                            <td colspan="8">ไม่พบบริการตามเงื่อนไขที่ค้นหา</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="service-detail-panel">
            <section class="service-panel">
                <div class="service-panel-header">
                    <div>
                        <div class="service-kicker" data-service-panel-mode><?= $canManage ? 'Add Service' : 'Readonly Detail' ?></div>
                        <h2 data-service-panel-title><?= $canManage ? 'เพิ่มบริการใหม่' : 'รายละเอียดบริการ' ?></h2>
                    </div>
                    <span class="service-panel-badge">Builder</span>
                </div>

                <div class="service-live-preview">
                    <span data-preview-code>SRV---</span>
                    <strong data-preview-name>ชื่อบริการ</strong>
                    <small><span data-preview-category>หมวดหมู่</span> · <span data-preview-status>เปิดใช้งาน</span></small>
                    <b><span data-preview-price>0.00</span> บาท</b>
                </div>

                <?php if ($canManage): ?>
                    <form class="service-form" method="post" action="<?= e(route_url('services-store')) ?>" data-service-form>
                        <?= csrf_field() ?>
                        <label>
                            <span>รหัสบริการ</span>
                            <div class="service-code-row">
                                <input name="service_code" data-service-code placeholder="SRV001" autocomplete="off" required>
                                <button type="button" data-service-generate-code>Auto</button>
                            </div>
                            <small data-service-code-validation></small>
                        </label>
                        <label>
                            <span>ชื่อบริการ</span>
                            <input name="service_name" data-service-name placeholder="เช่น ฉีดยา ทำแผล ตรวจอาการทั่วไป" required>
                            <small data-service-name-validation></small>
                        </label>
                        <label>
                            <span>หมวดหมู่</span>
                            <input name="category" list="serviceCategoryList" data-service-category placeholder="เลือกหรือพิมพ์หมวดหมู่">
                            <datalist id="serviceCategoryList">
                                <?php foreach ($categoryOptions as $category): ?>
                                    <option value="<?= e($category) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                        <label>
                            <span>ราคา</span>
                            <input name="price" data-service-price type="number" min="0" step="0.01" value="0.00" required>
                            <small data-service-price-validation></small>
                        </label>
                        <label class="service-check">
                            <input type="checkbox" name="is_active" value="1" data-service-active checked>
                            <span>เปิดใช้งานใน Smart Exam</span>
                        </label>
                        <div class="service-code-hint" data-service-suggestion>ระบบจะแนะนำหมวดหมู่และ prefix จากชื่อบริการ</div>
                        <div class="service-form-actions">
                            <button type="submit" class="service-primary-action" data-service-submit>บันทึกบริการ</button>
                            <button type="button" class="service-secondary-action" data-service-reset>ล้างฟอร์ม</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="service-readonly-detail">เลือกบริการจากตารางเพื่อดูรายละเอียด</div>
                <?php endif; ?>
            </section>

            <section class="service-panel">
                <div class="service-panel-header">
                    <div>
                        <div class="service-kicker">Usage Insight</div>
                        <h2>ข้อมูลการใช้งาน</h2>
                    </div>
                </div>
                <div class="service-insight-body">
                    <div data-service-selected-insight>
                        <span>ยังไม่ได้เลือกบริการ</span>
                        <strong><?= $topService ? e((string) $topService['service_name']) : 'ไม่มีข้อมูล' ?></strong>
                        <small><?= $topService ? 'ใช้บ่อยสุด ' . e(number_format((float) ($topService['total_qty'] ?? 0), 0)) . ' ครั้ง' : 'เลือกบริการเพื่อดูสถิติ' ?></small>
                    </div>
                    <div data-service-price-history>
                        <span>ประวัติราคา</span>
                        <strong>เลือกบริการเพื่อดูการเปลี่ยนราคา</strong>
                        <small>บันทึกเมื่อเพิ่มบริการใหม่หรือแก้ไขราคา</small>
                    </div>
                </div>
            </section>

            <section class="service-panel">
                <div class="service-panel-header">
                    <div>
                        <div class="service-kicker">Service Audit</div>
                        <h2>ความเคลื่อนไหวล่าสุด</h2>
                    </div>
                </div>
                <div class="service-audit-list">
                    <?php if ($recentAudit === []): ?>
                        <div class="service-audit-empty">ยังไม่มี audit ของบริการ</div>
                    <?php else: ?>
                        <?php foreach ($recentAudit as $audit): ?>
                            <div class="service-audit-row">
                                <strong><?= e((string) ($audit['action'] ?? '-')) ?></strong>
                                <span><?= e((string) ($audit['actor_name'] ?? 'System')) ?> · <?= e((string) ($audit['created_at'] ?? '-')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</div>

<script type="application/json" id="servicePrefixMap"><?= json_encode($categoryPrefix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="servicePriceHistoryMap"><?= json_encode($priceHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
