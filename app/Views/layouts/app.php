<?php $user = current_user(); $clinicName = (string) system_setting('clinic_name', config('app.name')); ?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $clinicName) ?> | <?= e($clinicName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 d-none d-lg-block sidebar p-4">
            <div class="text-white mb-4">
                <div class="small text-uppercase opacity-75">Clinic Workflow</div>
                <h4 class="mb-1"><?= e($clinicName) ?></h4>
                <div class="small opacity-75">ระบบบริหารจัดการคลินิกพยาบาล</div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link <?= current_page() === 'queue' ? 'active' : '' ?>" href="<?= e(route_url('queue')) ?>">คิววันนี้</a>
                <a class="nav-link <?= in_array(current_page(), ['patients', 'patient-show'], true) ? 'active' : '' ?>" href="<?= e(route_url('patients')) ?>">ผู้รับบริการ</a>
                <a class="nav-link <?= current_page() === 'payments' ? 'active' : '' ?>" href="<?= e(route_url('payments')) ?>">การเงิน</a>
                <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                    <a class="nav-link <?= current_page() === 'inventory' ? 'active' : '' ?>" href="<?= e(route_url('inventory')) ?>">คลังยา/เวชภัณฑ์</a>
                    <a class="nav-link <?= current_page() === 'services' ? 'active' : '' ?>" href="<?= e(route_url('services')) ?>">บริการและราคา</a>
                <?php endif; ?>
                <?php if (has_role('ADMIN')): ?>
                    <a class="nav-link <?= current_page() === 'settings' ? 'active' : '' ?>" href="<?= e(route_url('settings')) ?>">ตั้งค่าคลินิก</a>
                <?php endif; ?>
                <a class="nav-link <?= current_page() === 'dashboard' ? 'active' : '' ?>" href="<?= e(route_url('dashboard')) ?>">Dashboard</a>
            </nav>

            <div class="mt-5 text-white-50 small">
                <div>ผู้ใช้: <?= e($user['full_name'] ?? '-') ?></div>
                <div>สิทธิ์: <?= e($user['role_name'] ?? '-') ?></div>
            </div>
        </aside>

        <main class="col-lg-10 p-0">
            <div class="brand-gradient text-white px-4 px-lg-5 py-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h1 class="h4 mb-1"><?= e($pageTitle ?? $clinicName) ?></h1>
                    <div class="small opacity-75">ออกแบบเพื่อให้รับคิว ตรวจ และชำระเงินได้เร็วในหน้าจอคอมพิวเตอร์</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark px-3 py-2"><?= e($user['full_name'] ?? '') ?> / <?= e($user['role_name'] ?? '') ?></span>
                    <form method="post" action="<?= e(route_url('logout')) ?>" class="mb-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light">ออกจากระบบ</button>
                    </form>
                </div>
            </div>

            <div class="p-4 p-lg-5">
                <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
                <?= $content ?>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>