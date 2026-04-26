<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\InventoryController;
use App\Controllers\PatientController;
use App\Controllers\PaymentController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Controllers\ServiceController;
use App\Controllers\SettingsController;
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

    'GET:queue' => [QueueController::class, 'index'],
    'GET:queue-display' => [QueueController::class, 'display'],
    'POST:queue-store' => [QueueController::class, 'store'],
    'POST:queue-status' => [QueueController::class, 'updateStatus'],

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
    'GET:receipt' => [PaymentController::class, 'receipt'],

    'GET:settings' => [SettingsController::class, 'index'],
    'POST:settings-store' => [SettingsController::class, 'store'],

    'GET:export' => [ReportController::class, 'export'],
    'GET:backup' => [BackupController::class, 'download'],
];

$routeKey = $method . ':' . $page;

if (!isset($routes[$routeKey])) {
    http_response_code(404);
    exit('Page not found');
}

[$controllerClass, $controllerMethod] = $routes[$routeKey];
$controller = new $controllerClass();
$controller->{$controllerMethod}();