<?php
$successMessage = flash('success');
$errorMessage = flash('error');
$cleanFlashMessage = static function (?string $message, string $type): ?string {
    if ($message === null || $message === '') {
        return $message;
    }

    if (preg_match('/เธ|เน€|ย|โ/u', $message) === 1) {
        return $type === 'success'
            ? 'ดำเนินการสำเร็จ'
            : 'ไม่สามารถดำเนินการได้ กรุณาตรวจสอบสถานะเคส แล้วลองอีกครั้ง';
    }

    return $message;
};

$successMessage = $cleanFlashMessage($successMessage, 'success');
$errorMessage = $cleanFlashMessage($errorMessage, 'error');
?>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($successMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
