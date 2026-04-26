<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class ServiceController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $services = db()->query(
            'SELECT * FROM services ORDER BY is_active DESC, service_name ASC'
        )->fetchAll();

        $this->render('services/index', [
            'pageTitle' => 'รายการบริการ',
            'services' => $services,
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);

        $serviceCode = trim((string) ($_POST['service_code'] ?? ''));
        $serviceName = trim((string) ($_POST['service_name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($serviceCode === '' || $serviceName === '') {
            flash('error', 'กรุณากรอกรหัสและชื่อบริการ');
            redirect('services');
        }

        db()->prepare(
            'INSERT INTO services (service_code, service_name, category, price, is_active, created_at, updated_at)
             VALUES (:service_code, :service_name, :category, :price, :is_active, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                service_name = VALUES(service_name),
                category = VALUES(category),
                price = VALUES(price),
                is_active = VALUES(is_active),
                updated_at = NOW()'
        )->execute([
            'service_code' => $serviceCode,
            'service_name' => $serviceName,
            'category' => $category ?: null,
            'price' => $price,
            'is_active' => $isActive,
        ]);

        flash('success', 'บันทึกรายการบริการเรียบร้อย');
        redirect('services');
    }
}

