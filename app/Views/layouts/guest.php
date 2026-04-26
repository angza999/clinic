<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? config('app.name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="card login-card shadow-lg border-0">
        <div class="brand-gradient text-white p-4">
            <div class="small text-uppercase opacity-75">Clinic System</div>
            <h2 class="h3 mb-1"><?= e(config('app.name')) ?></h2>
            <div class="opacity-75">ระบบบริหารจัดการคลินิกพยาบาล</div>
        </div>
        <div class="card-body p-4 p-lg-5">
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

