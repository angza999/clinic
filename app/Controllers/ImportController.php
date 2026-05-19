<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\NumberGenerator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class ImportController extends Controller
{
    private const TYPES = [
        'patients' => [
            'label' => 'ผู้รับบริการ',
            'roles' => ['ADMIN', 'NURSE'],
            'columns' => ['citizen_id', 'fullname', 'gender', 'birthdate', 'phone', 'address', 'allergy', 'chronic_disease'],
            'required' => ['fullname'],
        ],
        'inventory_items' => [
            'label' => 'ยาและเวชภัณฑ์',
            'roles' => ['ADMIN'],
            'columns' => ['item_code', 'item_name', 'item_type', 'unit_name', 'default_cost', 'default_price', 'reorder_level', 'is_active'],
            'required' => ['item_name', 'unit_name'],
        ],
        'inventory_batches' => [
            'label' => 'รับ stock ตั้งต้น',
            'roles' => ['ADMIN'],
            'columns' => ['item_code', 'item_name', 'lot_no', 'expiry_date', 'qty_in', 'cost_per_unit', 'received_date'],
            'required' => ['qty_in', 'cost_per_unit'],
        ],
    ];

    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $selectedType = $this->normalizeType((string) ($_GET['type'] ?? 'patients'));
        $logId = (int) ($_GET['log'] ?? 0);
        $log = $logId > 0 ? $this->findLog($logId) : null;
        if ($log) {
            $selectedType = $this->normalizeType((string) $log['import_type']);
            $this->authorizeType($selectedType);
        }
        $rows = $log ? $this->logRows((int) $log['id'], 20) : [];

        $this->render('import/index', [
            'pageTitle' => 'นำเข้าข้อมูล Excel',
            'pageStyles' => [app_url('assets/css/import.css')],
            'pageScripts' => [app_url('assets/js/import.js')],
            'types' => $this->visibleTypes(),
            'selectedType' => $selectedType,
            'log' => $log,
            'rows' => $rows,
            'headers' => $log ? $this->headersFromRows($rows) : [],
            'defaultColumns' => self::TYPES[$selectedType]['columns'],
            'dependencyReady' => $this->spreadsheetReady(),
            'recentLogs' => $this->recentLogs(),
        ]);
    }

    public function template(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $type = $this->normalizeType((string) ($_GET['type'] ?? 'patients'));
        $this->authorizeType($type);
        $columns = self::TYPES[$type]['columns'];

        if (!$this->spreadsheetReady()) {
            $filename = $type . '_template.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo "\xEF\xBB\xBF" . implode(',', $columns) . "\n";
            exit;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($columns, null, 'A1');
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($columns)) . '1')->getFont()->setBold(true);
        foreach (range(1, count($columns)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $filename = $type . '_template.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function upload(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $type = $this->normalizeType((string) ($_POST['import_type'] ?? 'patients'));
        $this->authorizeType($type);

        $file = $_FILES['excel_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'กรุณาเลือกไฟล์ Excel สำหรับนำเข้า');
            redirect('import', ['type' => $type]);
        }

        $originalName = basename((string) $file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            flash('error', 'รองรับเฉพาะไฟล์ .xlsx, .xls หรือ .csv');
            redirect('import', ['type' => $type]);
        }

        if (!$this->spreadsheetReady() && $extension !== 'csv') {
            flash('error', 'ยังไม่พบ PhpSpreadsheet จึงอ่าน .xlsx/.xls ไม่ได้ชั่วคราว กรุณาติดตั้ง dependency หรือใช้ .csv ก่อน');
            redirect('import', ['type' => $type]);
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('error', 'ไฟล์ใหญ่เกิน 5MB สำหรับ Phase 1');
            redirect('import', ['type' => $type]);
        }

        $targetName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = BASE_PATH . '/storage/imports/' . $targetName;
        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            flash('error', 'ไม่สามารถเก็บไฟล์ upload ได้');
            redirect('import', ['type' => $type]);
        }

        try {
            $parsedRows = $extension === 'csv' ? $this->parseCsv($targetPath) : $this->parseSpreadsheet($targetPath);
            if (count($parsedRows) > 2000) {
                throw new RuntimeException('Phase 1 จำกัด 2,000 แถวต่อไฟล์');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $pdo->prepare(
                'INSERT INTO import_logs (
                    import_type, file_name, stored_file_path, total_rows, status, created_by, created_at, updated_at
                 ) VALUES (
                    :import_type, :file_name, :stored_file_path, :total_rows, "UPLOADED", :created_by, NOW(), NOW()
                 )'
            )->execute([
                'import_type' => $type,
                'file_name' => $originalName,
                'stored_file_path' => 'storage/imports/' . $targetName,
                'total_rows' => count($parsedRows),
                'created_by' => current_user()['id'] ?? null,
            ]);

            $logId = (int) $pdo->lastInsertId();
            $rowStmt = $pdo->prepare(
                'INSERT INTO import_log_rows (
                    import_log_id, row_number, row_data_json, status, created_at
                 ) VALUES (
                    :import_log_id, :row_number, :row_data_json, "PENDING", NOW()
                 )'
            );

            foreach ($parsedRows as $row) {
                $rowStmt->execute([
                    'import_log_id' => $logId,
                    'row_number' => $row['row_number'],
                    'row_data_json' => json_encode($row['data'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $pdo->commit();
            flash('success', 'อ่านไฟล์เรียบร้อย กรุณาตรวจ mapping และ validate ก่อนนำเข้า');
            redirect('import', ['type' => $type, 'log' => $logId]);
        } catch (Throwable $throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            @unlink($targetPath);
            flash('error', 'อ่านไฟล์ไม่สำเร็จ: ' . $throwable->getMessage());
            redirect('import', ['type' => $type]);
        }
    }

    public function validate(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $log = $this->requireLog((int) ($_POST['import_log_id'] ?? 0));
        $type = (string) $log['import_type'];
        $this->authorizeType($type);

        $mapping = array_map('trim', (array) ($_POST['mapping'] ?? []));
        $rows = $this->logRows((int) $log['id'], 5000);
        $stats = ['success' => 0, 'error' => 0, 'duplicate' => 0];
        $seen = [];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $update = $pdo->prepare(
                'UPDATE import_log_rows
                 SET mapped_data_json = :mapped_data_json, status = :status, error_message = :error_message
                 WHERE id = :id'
            );

            foreach ($rows as $row) {
                $raw = json_decode((string) $row['row_data_json'], true) ?: [];
                $mapped = $this->mapRow($raw, $mapping);
                $result = $this->validateMappedRow($type, $mapped, $seen);
                $status = $result['status'];

                if ($status === 'VALID') {
                    $stats['success']++;
                } elseif ($status === 'DUPLICATE') {
                    $stats['duplicate']++;
                } else {
                    $stats['error']++;
                }

                $update->execute([
                    'mapped_data_json' => json_encode($mapped, JSON_UNESCAPED_UNICODE),
                    'status' => $status,
                    'error_message' => $result['message'],
                    'id' => $row['id'],
                ]);
            }

            $pdo->prepare(
                'UPDATE import_logs
                 SET success_rows = :success_rows,
                     error_rows = :error_rows,
                     duplicate_rows = :duplicate_rows,
                     status = "VALIDATED",
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'success_rows' => $stats['success'],
                'error_rows' => $stats['error'],
                'duplicate_rows' => $stats['duplicate'],
                'id' => $log['id'],
            ]);

            $pdo->commit();
            flash('success', 'ตรวจสอบข้อมูลแล้ว: ผ่าน ' . $stats['success'] . ' แถว, ผิด ' . $stats['error'] . ' แถว, ซ้ำ ' . $stats['duplicate'] . ' แถว');
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Validate ไม่สำเร็จ: ' . $throwable->getMessage());
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        }
    }

    public function confirm(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $log = $this->requireLog((int) ($_POST['import_log_id'] ?? 0));
        $type = (string) $log['import_type'];
        $this->authorizeType($type);
        $skipErrors = isset($_POST['skip_error_rows']);

        if (($log['status'] ?? '') !== 'VALIDATED') {
            flash('error', 'กรุณา validate ก่อน confirm import');
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        }

        if (!$skipErrors && ((int) $log['error_rows'] > 0 || (int) $log['duplicate_rows'] > 0)) {
            flash('error', 'ยังมีแถวผิดหรือซ้ำ ระบบจะไม่ import จนกว่าจะแก้ไฟล์หรือเลือก skip error rows');
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        }

        $rows = $this->logRows((int) $log['id'], 5000);
        $pdo = db();
        $pdo->beginTransaction();
        $imported = 0;
        $skipped = 0;

        try {
            foreach ($rows as $row) {
                $status = (string) $row['status'];
                if ($status !== 'VALID') {
                    $skipped++;
                    $pdo->prepare('UPDATE import_log_rows SET status = "SKIPPED" WHERE id = :id')->execute(['id' => $row['id']]);
                    continue;
                }

                $mapped = json_decode((string) $row['mapped_data_json'], true) ?: [];
                match ($type) {
                    'patients' => $this->importPatient($mapped),
                    'inventory_items' => $this->importInventoryItem($mapped, (int) $log['id'], (int) $row['row_number']),
                    'inventory_batches' => $this->importInventoryBatch($mapped, (int) $log['id'], (int) $row['row_number']),
                    default => throw new RuntimeException('Unsupported import type'),
                };

                $imported++;
                $pdo->prepare('UPDATE import_log_rows SET status = "IMPORTED" WHERE id = :id')->execute(['id' => $row['id']]);
            }

            $pdo->prepare(
                'UPDATE import_logs
                 SET success_rows = :success_rows,
                     error_rows = :error_rows,
                     status = "CONFIRMED",
                     options_json = :options_json,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'success_rows' => $imported,
                'error_rows' => $skipped,
                'options_json' => json_encode(['skip_error_rows' => $skipErrors], JSON_UNESCAPED_UNICODE),
                'id' => $log['id'],
            ]);

            $pdo->commit();
            flash('success', 'Import สำเร็จ ' . $imported . ' แถว' . ($skipped > 0 ? ' / ข้าม ' . $skipped . ' แถว' : ''));
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->prepare('UPDATE import_logs SET status = "FAILED", updated_at = NOW() WHERE id = :id')->execute(['id' => $log['id']]);
            flash('error', 'Import ถูก rollback: ' . $throwable->getMessage());
            redirect('import', ['type' => $type, 'log' => (int) $log['id']]);
        }
    }

    private function spreadsheetReady(): bool
    {
        return class_exists(IOFactory::class) && class_exists(Spreadsheet::class);
    }

    private function visibleTypes(): array
    {
        return array_filter(self::TYPES, static fn (array $type): bool => has_role($type['roles']));
    }

    private function normalizeType(string $type): string
    {
        return array_key_exists($type, self::TYPES) ? $type : 'patients';
    }

    private function authorizeType(string $type): void
    {
        require_roles(self::TYPES[$type]['roles']);
    }

    private function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $header = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . '1')->getFormattedValue());
            $headers[$col] = $header !== '' ? $header : 'column_' . $col;
        }

        $rows = [];
        for ($rowNo = 2; $rowNo <= $highestRow; $rowNo++) {
            $rowData = [];
            $hasValue = false;

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $rowNo)->getFormattedValue());
                if ($value !== '') {
                    $hasValue = true;
                }
                $rowData[$headers[$col]] = $value;
            }

            if ($hasValue) {
                $rows[] = ['row_number' => $rowNo, 'data' => $rowData];
            }
        }

        return $rows;
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('เปิดไฟล์ CSV ไม่ได้');
        }

        $headers = null;
        $rows = [];
        $rowNo = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNo++;
            if ($rowNo === 1) {
                $headers = array_map(static function (string $header): string {
                    $header = trim($header);
                    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
                    return $header !== '' ? $header : 'column';
                }, $line);
                continue;
            }

            if ($headers === null) {
                continue;
            }

            $rowData = [];
            $hasValue = false;
            foreach ($headers as $index => $header) {
                $value = trim((string) ($line[$index] ?? ''));
                if ($value !== '') {
                    $hasValue = true;
                }
                $rowData[$header] = $value;
            }

            if ($hasValue) {
                $rows[] = ['row_number' => $rowNo, 'data' => $rowData];
            }
        }

        fclose($handle);
        return $rows;
    }

    private function mapRow(array $raw, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $dbField => $excelColumn) {
            if ($excelColumn === '' || !array_key_exists($excelColumn, $raw)) {
                $mapped[$dbField] = '';
                continue;
            }
            $mapped[$dbField] = trim((string) $raw[$excelColumn]);
        }

        return $mapped;
    }

    private function validateMappedRow(string $type, array $mapped, array &$seen): array
    {
        return match ($type) {
            'patients' => $this->validatePatientRow($mapped, $seen),
            'inventory_items' => $this->validateInventoryItemRow($mapped, $seen),
            'inventory_batches' => $this->validateInventoryBatchRow($mapped),
            default => ['status' => 'ERROR', 'message' => 'Unsupported import type'],
        };
    }

    private function validatePatientRow(array $row, array &$seen): array
    {
        $errors = [];
        $fullname = trim((string) ($row['fullname'] ?? ''));
        $citizenId = preg_replace('/\D+/', '', (string) ($row['citizen_id'] ?? ''));
        $phone = preg_replace('/[^\d+]+/', '', (string) ($row['phone'] ?? ''));

        if ($fullname === '') {
            $errors[] = 'fullname ว่าง';
        }
        if ($citizenId !== '' && strlen($citizenId) !== 13) {
            $errors[] = 'citizen_id ต้องมี 13 หลัก';
        }
        if ($phone !== '' && strlen(preg_replace('/\D+/', '', $phone)) < 9) {
            $errors[] = 'phone ไม่ถูกต้อง';
        }
        if ($errors !== []) {
            return ['status' => 'ERROR', 'message' => implode(', ', $errors)];
        }

        $duplicateKey = $citizenId !== '' ? 'cid:' . $citizenId : 'namephone:' . mb_strtolower($fullname) . ':' . $phone;
        if (isset($seen[$duplicateKey])) {
            return ['status' => 'DUPLICATE', 'message' => 'ซ้ำในไฟล์เดียวกัน'];
        }
        $seen[$duplicateKey] = true;

        if ($citizenId !== '') {
            $stmt = db()->prepare('SELECT id FROM patients WHERE citizen_id = :citizen_id LIMIT 1');
            $stmt->execute(['citizen_id' => $citizenId]);
            if ($stmt->fetch()) {
                return ['status' => 'DUPLICATE', 'message' => 'citizen_id ซ้ำในฐานข้อมูล'];
            }
        } elseif ($phone !== '') {
            [$firstName, $lastName] = $this->splitFullname($fullname);
            $stmt = db()->prepare('SELECT id FROM patients WHERE phone = :phone AND first_name = :first_name AND last_name = :last_name LIMIT 1');
            $stmt->execute(['phone' => $phone, 'first_name' => $firstName, 'last_name' => $lastName]);
            if ($stmt->fetch()) {
                return ['status' => 'DUPLICATE', 'message' => 'phone + fullname ซ้ำในฐานข้อมูล'];
            }
        }

        return ['status' => 'VALID', 'message' => null];
    }

    private function validateInventoryItemRow(array $row, array &$seen): array
    {
        $errors = [];
        $itemCode = trim((string) ($row['item_code'] ?? ''));
        $itemName = trim((string) ($row['item_name'] ?? ''));
        $unitName = trim((string) ($row['unit_name'] ?? ''));
        $itemType = strtoupper(trim((string) ($row['item_type'] ?? 'DRUG')));

        if ($itemName === '') {
            $errors[] = 'item_name ว่าง';
        }
        if ($unitName === '') {
            $errors[] = 'unit_name ว่าง';
        }
        if (!in_array($itemType, ['', 'DRUG', 'SUPPLY'], true)) {
            $errors[] = 'item_type ต้องเป็น DRUG หรือ SUPPLY';
        }
        foreach (['default_cost', 'default_price', 'reorder_level'] as $field) {
            if (($row[$field] ?? '') !== '' && !is_numeric((string) $row[$field])) {
                $errors[] = $field . ' ต้องเป็นตัวเลข';
            }
        }
        if ($errors !== []) {
            return ['status' => 'ERROR', 'message' => implode(', ', $errors)];
        }

        $duplicateKey = $itemCode !== '' ? 'code:' . mb_strtolower($itemCode) : 'name:' . mb_strtolower($itemName);
        if (isset($seen[$duplicateKey])) {
            return ['status' => 'DUPLICATE', 'message' => 'ซ้ำในไฟล์เดียวกัน'];
        }
        $seen[$duplicateKey] = true;

        if ($itemCode !== '') {
            $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_code = :item_code LIMIT 1');
            $stmt->execute(['item_code' => $itemCode]);
        } else {
            $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_name = :item_name LIMIT 1');
            $stmt->execute(['item_name' => $itemName]);
        }

        if ($stmt->fetch()) {
            return ['status' => 'DUPLICATE', 'message' => 'รายการซ้ำในฐานข้อมูล'];
        }

        return ['status' => 'VALID', 'message' => null];
    }

    private function validateInventoryBatchRow(array $row): array
    {
        $errors = [];
        $itemCode = trim((string) ($row['item_code'] ?? ''));
        $itemName = trim((string) ($row['item_name'] ?? ''));

        if ($itemCode === '' && $itemName === '') {
            $errors[] = 'ต้องมี item_code หรือ item_name';
        }
        if (!is_numeric((string) ($row['qty_in'] ?? '')) || (float) $row['qty_in'] <= 0) {
            $errors[] = 'qty_in ต้องมากกว่า 0';
        }
        if (!is_numeric((string) ($row['cost_per_unit'] ?? ''))) {
            $errors[] = 'cost_per_unit ต้องเป็นตัวเลข';
        }
        foreach (['expiry_date', 'received_date'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && $this->normalizeDate($value) === null) {
                $errors[] = $field . ' ต้องเป็นวันที่';
            }
        }

        if ($errors === []) {
            $item = $this->findInventoryItem($itemCode, $itemName);
            if (!$item) {
                $errors[] = 'ไม่พบ item ในคลังจาก item_code/item_name';
            }
        }

        return $errors === []
            ? ['status' => 'VALID', 'message' => null]
            : ['status' => 'ERROR', 'message' => implode(', ', $errors)];
    }

    private function importPatient(array $row): void
    {
        [$firstName, $lastName] = $this->splitFullname((string) $row['fullname']);
        db()->prepare(
            'INSERT INTO patients (
                hn, citizen_id, first_name, last_name, gender, birth_date, phone, address,
                underlying_disease, drug_allergy, is_active, created_at, updated_at
             ) VALUES (
                :hn, :citizen_id, :first_name, :last_name, :gender, :birth_date, :phone, :address,
                :underlying_disease, :drug_allergy, 1, NOW(), NOW()
             )'
        )->execute([
            'hn' => NumberGenerator::nextHn(),
            'citizen_id' => $this->digits((string) ($row['citizen_id'] ?? '')) ?: null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $this->normalizeGender((string) ($row['gender'] ?? '')),
            'birth_date' => $this->normalizeDate((string) ($row['birthdate'] ?? '')),
            'phone' => preg_replace('/[^\d+]+/', '', (string) ($row['phone'] ?? '')) ?: null,
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
            'underlying_disease' => trim((string) ($row['chronic_disease'] ?? '')) ?: null,
            'drug_allergy' => trim((string) ($row['allergy'] ?? '')) ?: null,
        ]);
    }

    private function importInventoryItem(array $row, int $logId, int $rowNumber): void
    {
        $itemCode = trim((string) ($row['item_code'] ?? ''));
        if ($itemCode === '') {
            $itemCode = 'IMP' . str_pad((string) $logId, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string) $rowNumber, 4, '0', STR_PAD_LEFT);
        }

        $isActive = in_array(strtolower(trim((string) ($row['is_active'] ?? '1'))), ['1', 'yes', 'y', 'true', 'active', 'ใช้งาน'], true) ? 1 : 0;

        db()->prepare(
            'INSERT INTO inventory_items (
                item_code, item_name, item_type, unit_name, reorder_level, default_cost, default_price, is_active, created_at, updated_at
             ) VALUES (
                :item_code, :item_name, :item_type, :unit_name, :reorder_level, :default_cost, :default_price, :is_active, NOW(), NOW()
             )'
        )->execute([
            'item_code' => $itemCode,
            'item_name' => trim((string) $row['item_name']),
            'item_type' => in_array(strtoupper((string) ($row['item_type'] ?? 'DRUG')), ['DRUG', 'SUPPLY'], true) ? strtoupper((string) ($row['item_type'] ?? 'DRUG')) : 'DRUG',
            'unit_name' => trim((string) $row['unit_name']),
            'reorder_level' => (float) ($row['reorder_level'] ?? 0),
            'default_cost' => (float) ($row['default_cost'] ?? 0),
            'default_price' => (float) ($row['default_price'] ?? 0),
            'is_active' => $isActive,
        ]);
    }

    private function importInventoryBatch(array $row, int $logId, int $rowNumber): void
    {
        $item = $this->findInventoryItem((string) ($row['item_code'] ?? ''), (string) ($row['item_name'] ?? ''));
        if (!$item) {
            throw new RuntimeException('ไม่พบ item สำหรับ row ' . $rowNumber);
        }

        $qtyIn = (float) $row['qty_in'];
        $costPerUnit = (float) $row['cost_per_unit'];
        $pdo = db();

        $pdo->prepare(
            'INSERT INTO inventory_batches (
                item_id, lot_no, expiry_date, qty_in, qty_balance, cost_per_unit, received_date, created_at, updated_at
             ) VALUES (
                :item_id, :lot_no, :expiry_date, :qty_in, :qty_balance, :cost_per_unit, :received_date, NOW(), NOW()
             )'
        )->execute([
            'item_id' => $item['id'],
            'lot_no' => trim((string) ($row['lot_no'] ?? '')) ?: null,
            'expiry_date' => $this->normalizeDate((string) ($row['expiry_date'] ?? '')),
            'qty_in' => $qtyIn,
            'qty_balance' => $qtyIn,
            'cost_per_unit' => $costPerUnit,
            'received_date' => $this->normalizeDate((string) ($row['received_date'] ?? '')) ?? date('Y-m-d'),
        ]);

        $batchId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO stock_movements (
                batch_id, item_id, movement_type, qty, unit_cost, reference_type, reference_id, note, movement_datetime, created_by, created_at, updated_at
             ) VALUES (
                :batch_id, :item_id, "IN", :qty, :unit_cost, "EXCEL_IMPORT", :reference_id, :note, NOW(), :created_by, NOW(), NOW()
             )'
        )->execute([
            'batch_id' => $batchId,
            'item_id' => $item['id'],
            'qty' => $qtyIn,
            'unit_cost' => $costPerUnit,
            'reference_id' => $logId,
            'note' => 'Excel import row ' . $rowNumber,
            'created_by' => current_user()['id'] ?? null,
        ]);
    }

    private function findInventoryItem(string $itemCode, string $itemName): ?array
    {
        $itemCode = trim($itemCode);
        $itemName = trim($itemName);
        if ($itemCode !== '') {
            $stmt = db()->prepare('SELECT * FROM inventory_items WHERE item_code = :item_code LIMIT 1');
            $stmt->execute(['item_code' => $itemCode]);
            $item = $stmt->fetch();
            if ($item) {
                return $item;
            }
        }
        if ($itemName !== '') {
            $stmt = db()->prepare('SELECT * FROM inventory_items WHERE item_name = :item_name LIMIT 1');
            $stmt->execute(['item_name' => $itemName]);
            $item = $stmt->fetch();
            return $item ?: null;
        }

        return null;
    }

    private function splitFullname(string $fullname): array
    {
        $parts = preg_split('/\s+/u', trim($fullname), 2);
        return [$parts[0] ?? '-', $parts[1] ?? '-'];
    }

    private function normalizeGender(string $value): ?string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'm', 'male', 'ชาย' => 'M',
            'f', 'female', 'หญิง' => 'F',
            'o', 'other', 'อื่นๆ', 'อื่น' => 'O',
            default => null,
        };
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && $this->spreadsheetReady()) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function findLog(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = db()->prepare('SELECT * FROM import_logs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $log = $stmt->fetch();
        return $log ?: null;
    }

    private function requireLog(int $id): array
    {
        $log = $this->findLog($id);
        if (!$log) {
            flash('error', 'ไม่พบ import log');
            redirect('import');
        }

        return $log;
    }

    private function logRows(int $logId, int $limit): array
    {
        $stmt = db()->prepare(
            'SELECT *
             FROM import_log_rows
             WHERE import_log_id = :import_log_id
             ORDER BY row_number ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['import_log_id' => $logId]);
        return $stmt->fetchAll();
    }

    private function headersFromRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $raw = json_decode((string) $rows[0]['row_data_json'], true);
        return is_array($raw) ? array_keys($raw) : [];
    }

    private function recentLogs(): array
    {
        try {
            return db()->query(
                'SELECT import_logs.*, users.full_name AS created_by_name
                 FROM import_logs
                 LEFT JOIN users ON users.id = import_logs.created_by
                 ORDER BY import_logs.id DESC
                 LIMIT 8'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }
}
