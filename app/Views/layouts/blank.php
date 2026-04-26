<?php $clinicName = (string) system_setting('clinic_name', config('app.name')); ?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $clinicName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-white">
    <div class="container py-4">
        <?= $content ?>
    </div>
</body>
</html>