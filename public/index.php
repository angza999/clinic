<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\AppointmentController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\InventoryController;
use App\Controllers\PatientController;
use App\Controllers\PaymentController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Controllers\ServiceController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Controllers\VisitController;

verify_csrf();

$page = $_GET['page'] ?? (auth_check() ? default_home_page() : 'login');
$method = request_method();

$routes = [
    'GET:login' => [AuthController::class, 'showLogin'],
    'POST:login' => [AuthController::class, 'login'],
    'POST:logout' => [AuthController::class, 'logout'],

    'GET:dashboard' => [DashboardController::class, 'index'],

    'GET:patients' => [PatientController::class, 'index'],
    'GET:patient-show' => [PatientController::class, 'show'],
    'POST:patients-store' => [PatientController::class, 'store'],
    'POST:patient-start-treatment' => [PatientController::class, 'startTreatment'],

    'GET:queue' => [QueueController::class, 'index'],
    'GET:queue-exam' => [QueueController::class, 'exam'],
    'GET:queue-display' => [QueueController::class, 'display'],
    'POST:queue-store' => [QueueController::class, 'store'],
    'POST:queue-quick-register' => [QueueController::class, 'quickRegister'],
    'POST:appointment-checkin' => [QueueController::class, 'appointmentCheckin'],
    'POST:queue-status' => [QueueController::class, 'updateStatus'],
    'POST:queue-apply-preset' => [QueueController::class, 'applyPreset'],
    'POST:queue-smart-finish' => [QueueController::class, 'smartFinish'],
    'POST:queue-quick-complete' => [QueueController::class, 'quickComplete'],

    'GET:appointments' => [AppointmentController::class, 'index'],
    'POST:appointments-store' => [AppointmentController::class, 'store'],
    'POST:appointments-update' => [AppointmentController::class, 'update'],
    'POST:appointments-cancel' => [AppointmentController::class, 'cancel'],

    'GET:visit-edit' => [VisitController::class, 'edit'],
    'POST:visit-save-clinical' => [VisitController::class, 'saveClinical'],
    'POST:visit-add-service' => [VisitController::class, 'addService'],
    'POST:visit-remove-service' => [VisitController::class, 'removeService'],
    'POST:visit-add-item' => [VisitController::class, 'addItemUsage'],
    'POST:visit-remove-item' => [VisitController::class, 'removeItemUsage'],
    'POST:visit-ready-payment' => [VisitController::class, 'markReadyForPayment'],

    'GET:services' => [ServiceController::class, 'index'],
    'POST:services-store' => [ServiceController::class, 'store'],

    'GET:inventory' => [InventoryController::class, 'index'],
    'POST:inventory-item-store' => [InventoryController::class, 'storeItem'],
    'POST:inventory-batch-store' => [InventoryController::class, 'storeBatch'],
    'POST:inventory-adjust' => [InventoryController::class, 'adjustStock'],

    'GET:payments' => [PaymentController::class, 'index'],
    'POST:payments-store' => [PaymentController::class, 'store'],
    'POST:payments-send-back' => [PaymentController::class, 'sendBack'],
    'GET:receipt' => [PaymentController::class, 'receipt'],

    'GET:users' => [UserController::class, 'index'],
    'POST:users-store' => [UserController::class, 'store'],
    'POST:users-password' => [UserController::class, 'changePassword'],

    'GET:reports' => [ReportController::class, 'index'],
    'GET:report-print' => [ReportController::class, 'print'],
    'GET:export' => [ReportController::class, 'export'],

    'GET:settings' => [SettingsController::class, 'index'],
    'POST:settings-store' => [SettingsController::class, 'store'],
    'POST:settings-preset-store' => [SettingsController::class, 'storePreset'],

    'GET:backup' => [BackupController::class, 'download'],
];

$routeKey = $method . ':' . $page;

if (!isset($routes[$routeKey])) {
    http_response_code(404);
    exit('Page not found');
}

[$controllerClass, $controllerMethod] = $routes[$routeKey];
$controller = new $controllerClass();

try {
    $controller->{$controllerMethod}();
} catch (\PDOException $exception) {
    http_response_code(503);

    $clinicName = htmlspecialchars((string) config('app.name', 'Clinic System'), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars((string) $exception->getMessage(), ENT_QUOTES, 'UTF-8');
    $isConnectionError = str_contains($exception->getMessage(), 'SQLSTATE[HY000] [2002]');
    $title = $isConnectionError ? 'ยังเชื่อมต่อฐานข้อมูลไม่ได้' : 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
    $detail = $isConnectionError
        ? 'กรุณาตรวจสอบว่า MySQL หรือ MariaDB กำลังทำงานอยู่ เช่น กด Start ที่ MySQL ใน XAMPP หรือ Laragon แล้วลองโหลดหน้าเว็บใหม่อีกครั้ง'
        : 'ระบบพบข้อผิดพลาดจากฐานข้อมูล กรุณาตรวจสอบการตั้งค่าการเชื่อมต่อหรือข้อความด้านล่าง';

    echo '<!doctype html>';
    echo '<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $clinicName . '</title>';
    echo '<style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#f4f7fb;padding:32px;color:#0f172a}.card{max-width:780px;margin:40px auto;background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);padding:32px}.badge{display:inline-block;background:#fee2e2;color:#991b1b;padding:8px 12px;border-radius:999px;font-weight:600;margin-bottom:16px}.muted{color:#475569}.code{margin-top:16px;padding:12px 14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;font-size:14px;word-break:break-word}.actions{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap}.btn{display:inline-block;padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:600}.btn-primary{background:#2563eb;color:#fff}.btn-light{background:#e2e8f0;color:#0f172a}</style>';
    echo '</head><body>';
    echo '<div class="card">';
    echo '<div class="badge">Database Connection</div>';
    echo '<h1 style="margin:0 0 10px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="muted" style="margin:0 0 8px;">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<div class="code">' . $message . '</div>';
    echo '<div class="actions">';
    echo '<a class="btn btn-primary" href="javascript:location.reload()">ลองโหลดหน้าใหม่</a>';
    echo '<a class="btn btn-light" href="' . htmlspecialchars(app_url('index.php?page=login'), ENT_QUOTES, 'UTF-8') . '">กลับไปหน้าเข้าสู่ระบบ</a>';
    echo '</div></div></body></html>';
    exit;
}
