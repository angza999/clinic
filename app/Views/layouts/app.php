<?php
$user = current_user();
$clinicName = (string) system_setting('clinic_name', config('app.name'));
$currentPage = current_page();
$pageTitleText = (string) ($pageTitle ?? $clinicName);
$pageTopbarMode = (string) ($pageTopbarMode ?? 'default');
$pageDescriptions = [
    'queue' => 'จัดการคิว เปิด Smart Exam และดูสรุปเคสจากหน้าจอเดียว',
    'queue-exam' => 'บันทึกประวัติ ตรวจร่างกาย และจบเคสในหน้า Smart Exam',
    'queue-display' => 'หน้าจอแสดงคิวสำหรับหน้าห้องตรวจและจุดรอรับบริการ',
    'patients' => 'ค้นหา ลงทะเบียน และเปิดแฟ้มผู้รับบริการอย่างเป็นระบบ',
    'patient-show' => 'ติดตามประวัติการรับบริการและนัดหมายย้อนหลัง',
    'payments' => 'ตรวจยอด รับชำระ และออกใบเสร็จอย่างชัดเจน',
    'inventory' => 'ติดตามสต๊อก เวชภัณฑ์ และวันหมดอายุในมุมมองเดียว',
    'services' => 'ดูแลรายการบริการและราคาให้เป็นมาตรฐานเดียวกัน',
    'users' => 'กำหนดผู้ใช้งาน สิทธิ์ และความพร้อมของทีมงาน',
    'reports' => 'สรุปรายงานประจำวัน ประจำเดือน และไฟล์สำรองข้อมูล',
    'settings' => 'กำหนดข้อมูลคลินิก รูปแบบเอกสาร และการแจ้งเตือน',
    'dashboard' => 'ภาพรวมการให้บริการ รายได้ และสถานะงานประจำวัน',
];
$pageDescription = $pageDescriptions[$currentPage] ?? 'ระบบบริหารจัดการคลินิกพยาบาล';

$navItems = [
    ['page' => 'queue', 'label' => 'คิววันนี้', 'icon' => 'bi-grid-1x2-fill', 'url' => route_url('queue'), 'visible' => true, 'active' => $currentPage === 'queue'],
    ['page' => 'patients', 'label' => 'ผู้รับบริการ', 'icon' => 'bi-person-vcard-fill', 'url' => route_url('patients'), 'visible' => true, 'active' => in_array($currentPage, ['patients', 'patient-show'], true)],
    ['page' => 'payments', 'label' => 'การเงิน', 'icon' => 'bi-cash-stack', 'url' => route_url('payments'), 'visible' => has_role(['ADMIN', 'CASHIER']), 'active' => $currentPage === 'payments'],
    ['page' => 'inventory', 'label' => 'คลังยา/เวชภัณฑ์', 'icon' => 'bi-capsule-pill', 'url' => route_url('inventory'), 'visible' => has_role(['ADMIN', 'NURSE']), 'active' => $currentPage === 'inventory'],
    ['page' => 'services', 'label' => 'บริการและราคา', 'icon' => 'bi-clipboard2-pulse-fill', 'url' => route_url('services'), 'visible' => has_role(['ADMIN', 'NURSE']), 'active' => $currentPage === 'services'],
    ['page' => 'users', 'label' => 'จัดการผู้ใช้', 'icon' => 'bi-people-fill', 'url' => route_url('users'), 'visible' => has_role('ADMIN'), 'active' => $currentPage === 'users'],
    ['page' => 'reports', 'label' => 'รายงานและ Backup', 'icon' => 'bi-bar-chart-fill', 'url' => route_url('reports'), 'visible' => has_role('ADMIN'), 'active' => in_array($currentPage, ['reports', 'report-print'], true)],
    ['page' => 'settings', 'label' => 'ตั้งค่าคลินิก', 'icon' => 'bi-sliders2', 'url' => route_url('settings'), 'visible' => has_role('ADMIN'), 'active' => $currentPage === 'settings'],
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => route_url('dashboard'), 'visible' => true, 'active' => $currentPage === 'dashboard'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitleText) ?> | <?= e($clinicName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/app.css')) ?>" rel="stylesheet">
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?>
        <link href="<?= e((string) $pageStyle) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar sidebar">
        <div class="sidebar-inner">
            <div class="sidebar-brand text-white">
                <div class="sidebar-kicker">Clinic Workflow</div>
                <h4 class="mb-1"><?= e($clinicName) ?></h4>
                <div class="sidebar-subtitle">ระบบบริหารจัดการคลินิกพยาบาล</div>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($navItems as $navItem): ?>
                    <?php if (!$navItem['visible']) { continue; } ?>
                    <a class="nav-link <?= $navItem['active'] ? 'active' : '' ?>" href="<?= e($navItem['url']) ?>">
                        <span class="nav-link-icon"><i class="bi <?= e($navItem['icon']) ?>"></i></span>
                        <span class="nav-link-text"><?= e($navItem['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer text-white-50 small">
                <div class="sidebar-footer-label">ผู้ใช้ที่เข้าสู่ระบบ</div>
                <div class="sidebar-footer-name"><?= e($user['full_name'] ?? '-') ?></div>
                <div class="sidebar-footer-role"><?= e($user['role_name'] ?? '-') ?></div>
            </div>
        </div>
    </aside>

    <main class="app-main-shell">
        <div class="brand-gradient app-topbar <?= $pageTopbarMode === 'compact' ? 'app-topbar-compact' : '' ?>">
            <div class="app-topbar-inner">
                <div>
                    <?php if ($pageTopbarMode === 'compact'): ?>
                        <div class="topbar-kicker">Nurse Station</div>
                        <div class="topbar-compact-label"><?= e($pageTitleText) ?></div>
                    <?php else: ?>
                        <div class="topbar-kicker">หน้าทำงานหลัก</div>
                        <h1 class="h3 mb-2"><?= e($pageTitleText) ?></h1>
                        <div class="topbar-description"><?= e($pageDescription) ?></div>
                    <?php endif; ?>
                </div>
                <div class="topbar-actions">
                    <span class="topbar-user-badge">
                        <i class="bi bi-person-badge-fill"></i>
                        <span><?= e($user['full_name'] ?? '') ?> / <?= e($user['role_name'] ?? '') ?></span>
                    </span>
                    <form method="post" action="<?= e(route_url('logout')) ?>" class="mb-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light px-3">
                            <i class="bi bi-box-arrow-right me-1"></i>ออกจากระบบ
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="app-content px-3 px-lg-4 py-4">
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach (($pageScripts ?? []) as $pageScript): ?>
    <script src="<?= e((string) $pageScript) ?>"></script>
<?php endforeach; ?>
</body>
</html>
