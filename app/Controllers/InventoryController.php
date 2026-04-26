<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

class InventoryController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $items = db()->query(
            'SELECT inventory_items.*,
                    COALESCE(SUM(inventory_batches.qty_balance), 0) AS qty_balance,
                    MIN(CASE WHEN inventory_batches.qty_balance > 0 THEN inventory_batches.expiry_date END) AS nearest_expiry
             FROM inventory_items
             LEFT JOIN inventory_batches ON inventory_batches.item_id = inventory_items.id
             GROUP BY inventory_items.id
             ORDER BY inventory_items.item_type ASC, inventory_items.item_name ASC'
        )->fetchAll();

        $batches = db()->query(
            'SELECT inventory_batches.*, inventory_items.item_name, inventory_items.unit_name
             FROM inventory_batches
             INNER JOIN inventory_items ON inventory_items.id = inventory_batches.item_id
             ORDER BY inventory_batches.expiry_date ASC, inventory_batches.id DESC
             LIMIT 100'
        )->fetchAll();

        $this->render('inventory/index', [
            'pageTitle' => 'คลังยาและเวชภัณฑ์',
            'items' => $items,
            'batches' => $batches,
        ]);
    }

    public function storeItem(): void
    {
        require_roles(['ADMIN']);

        $data = [
            'item_code' => trim((string) ($_POST['item_code'] ?? '')),
            'item_name' => trim((string) ($_POST['item_name'] ?? '')),
            'item_type' => trim((string) ($_POST['item_type'] ?? 'DRUG')),
            'unit_name' => trim((string) ($_POST['unit_name'] ?? '')),
            'reorder_level' => (float) ($_POST['reorder_level'] ?? 0),
            'default_cost' => (float) ($_POST['default_cost'] ?? 0),
            'default_price' => (float) ($_POST['default_price'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['item_code'] === '' || $data['item_name'] === '' || $data['unit_name'] === '') {
            flash('error', 'กรุณากรอกรหัส ชื่อรายการ และหน่วยนับ');
            redirect('inventory');
        }

        db()->prepare(
            'INSERT INTO inventory_items (
                item_code, item_name, item_type, unit_name, reorder_level, default_cost, default_price, is_active, created_at, updated_at
             ) VALUES (
                :item_code, :item_name, :item_type, :unit_name, :reorder_level, :default_cost, :default_price, :is_active, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                item_name = VALUES(item_name),
                item_type = VALUES(item_type),
                unit_name = VALUES(unit_name),
                reorder_level = VALUES(reorder_level),
                default_cost = VALUES(default_cost),
                default_price = VALUES(default_price),
                is_active = VALUES(is_active),
                updated_at = NOW()'
        )->execute($data);

        flash('success', 'บันทึกรายการคลังเรียบร้อย');
        redirect('inventory');
    }

    public function storeBatch(): void
    {
        require_roles(['ADMIN']);

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $lotNo = trim((string) ($_POST['lot_no'] ?? ''));
        $expiryDate = trim((string) ($_POST['expiry_date'] ?? ''));
        $qtyIn = (float) ($_POST['qty_in'] ?? 0);
        $costPerUnit = (float) ($_POST['cost_per_unit'] ?? 0);
        $receivedDate = trim((string) ($_POST['received_date'] ?? date('Y-m-d')));

        if ($itemId <= 0 || $qtyIn <= 0) {
            flash('error', 'กรุณาเลือกสินค้าและจำนวนรับเข้าให้ถูกต้อง');
            redirect('inventory');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $pdo->prepare(
                'INSERT INTO inventory_batches (
                    item_id, lot_no, expiry_date, qty_in, qty_balance, cost_per_unit, received_date, created_at, updated_at
                 ) VALUES (
                    :item_id, :lot_no, :expiry_date, :qty_in, :qty_balance, :cost_per_unit, :received_date, NOW(), NOW()
                 )'
            )->execute([
                'item_id' => $itemId,
                'lot_no' => $lotNo ?: null,
                'expiry_date' => $expiryDate ?: null,
                'qty_in' => $qtyIn,
                'qty_balance' => $qtyIn,
                'cost_per_unit' => $costPerUnit,
                'received_date' => $receivedDate ?: null,
            ]);

            $batchId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO stock_movements (
                    batch_id, item_id, movement_type, qty, unit_cost, reference_type, movement_datetime, created_by, created_at, updated_at
                 ) VALUES (
                    :batch_id, :item_id, "IN", :qty, :unit_cost, "BATCH_RECEIVE", NOW(), :created_by, NOW(), NOW()
                 )'
            )->execute([
                'batch_id' => $batchId,
                'item_id' => $itemId,
                'qty' => $qtyIn,
                'unit_cost' => $costPerUnit,
                'created_by' => current_user()['id'],
            ]);

            $pdo->commit();
            flash('success', 'บันทึกรับเข้าสินค้าเรียบร้อย');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถบันทึกรับเข้าได้: ' . $throwable->getMessage());
        }

        redirect('inventory');
    }

    public function adjustStock(): void
    {
        require_roles(['ADMIN']);

        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $adjustQty = (float) ($_POST['adjust_qty'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($batchId <= 0 || $adjustQty === 0.0) {
            flash('error', 'กรุณาระบุล็อตและจำนวนปรับสต็อก');
            redirect('inventory');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $batchStmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = :id FOR UPDATE');
            $batchStmt->execute(['id' => $batchId]);
            $batch = $batchStmt->fetch();

            if (!$batch) {
                throw new \RuntimeException('ไม่พบล็อตสินค้า');
            }

            $newBalance = (float) $batch['qty_balance'] + $adjustQty;
            if ($newBalance < 0) {
                throw new \RuntimeException('จำนวนคงเหลือไม่เพียงพอ');
            }

            $pdo->prepare('UPDATE inventory_batches SET qty_balance = :qty_balance, updated_at = NOW() WHERE id = :id')->execute([
                'qty_balance' => $newBalance,
                'id' => $batchId,
            ]);

            $pdo->prepare(
                'INSERT INTO stock_movements (
                    batch_id, item_id, movement_type, qty, unit_cost, reference_type, reference_id, note, movement_datetime, created_by, created_at, updated_at
                 ) VALUES (
                    :batch_id, :item_id, "ADJUST", :qty, :unit_cost, "MANUAL_ADJUST", :reference_id, :note, NOW(), :created_by, NOW(), NOW()
                 )'
            )->execute([
                'batch_id' => $batchId,
                'item_id' => $batch['item_id'],
                'qty' => $adjustQty,
                'unit_cost' => $batch['cost_per_unit'],
                'reference_id' => $batchId,
                'note' => $note ?: null,
                'created_by' => current_user()['id'],
            ]);

            $pdo->commit();
            flash('success', 'ปรับสต็อกเรียบร้อย');
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'ไม่สามารถปรับสต็อกได้: ' . $throwable->getMessage());
        }

        redirect('inventory');
    }
}

