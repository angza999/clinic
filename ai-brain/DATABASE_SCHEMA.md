# Database Schema: ดงมหาวันคลินิก

## Overview
ฐานข้อมูลใช้ MySQL/MariaDB และออกแบบแบบ transactional workflow สำหรับคลินิกขนาดเล็ก โดยเน้น queue-driven visit lifecycle

## Core Design Principles
- `patients` คือ master patient record
- `visits` คือ encounter ของแต่ละครั้ง
- `queue_entries` คือสถานะหน้างานของ visit
- `visit_services` และ `visit_item_usages` คือรายการคิดเงิน
- `inventory_batches` + `stock_movements` คือ source of truth ของ stock
- `payments` ปิดรอบการเงินของ visit

## Table Catalog

### `roles`
Purpose:
- เก็บ role หลักของระบบ

Key Fields:
- `id`
- `role_code`
- `role_name`

Relations:
- 1:N ไป `users`

### `users`
Purpose:
- เก็บบัญชีเจ้าหน้าที่

Key Fields:
- `id`
- `role_id`
- `full_name`
- `username`
- `password_hash`
- `phone`
- `is_active`
- `last_login_at`

Relations:
- N:1 ไป `roles`
- 1:N แบบ reference ไป `visits.created_by`
- 1:N แบบ reference ไป `visit_vitals.recorded_by`
- 1:N แบบ reference ไป `payments.paid_by`
- 1:N แบบ reference ไป `stock_movements.created_by`
- 1:N แบบ reference ไป `audit_logs.user_id`

### `patients`
Purpose:
- เก็บแฟ้มผู้ป่วยหลัก

Key Fields:
- `id`
- `hn`
- `citizen_id`
- `title_name`
- `first_name`
- `last_name`
- `gender`
- `birth_date`
- `phone`
- `address`
- `underlying_disease`
- `drug_allergy`
- `note`
- `is_active`

Relations:
- 1:N ไป `visits`
- 1:N ไป `appointments`

### `visits`
Purpose:
- เก็บ encounter แต่ละครั้งของผู้ป่วย

Key Fields:
- `id`
- `visit_no`
- `patient_id`
- `visit_datetime`
- `chief_complaint`
- `present_illness`
- `physical_exam`
- `diagnosis`
- `nursing_note`
- `advice`
- `followup_date`
- `created_by`

Relations:
- N:1 ไป `patients`
- N:1 ไป `users`
- 1:1 ไป `queue_entries`
- 1:1 ไป `visit_vitals`
- 1:N ไป `visit_services`
- 1:N ไป `visit_item_usages`
- 0..1:1 ไป `payments`
- 0..N ไป `appointments`

### `queue_entries`
Purpose:
- เก็บสถานะ queue operational ของ visit

Key Fields:
- `id`
- `visit_id`
- `queue_date`
- `queue_no`
- `status`
- `checked_in_at`
- `called_at`
- `finished_at`

Relations:
- 1:1 ไป `visits`

Notes:
- unique (`visit_id`)
- unique (`queue_date`, `queue_no`)
- status เป็น workflow state ที่สำคัญที่สุดของหน้างาน

### `visit_vitals`
Purpose:
- เก็บ vital signs ของ visit

Key Fields:
- `visit_id`
- `bp_systolic`
- `bp_diastolic`
- `temp_c`
- `pulse_rate`
- `resp_rate`
- `spo2`
- `weight_kg`
- `recorded_by`
- `recorded_at`

Relations:
- 1:1 ไป `visits`
- N:1 ไป `users`

### `services`
Purpose:
- master รายการบริการ

Key Fields:
- `id`
- `service_code`
- `service_name`
- `category`
- `price`
- `is_active`

Relations:
- 1:N ไป `visit_services`

Service workstation note:
- No schema change is required for the current Service Management Workstation pass.
- `service_code` remains the stable unique key used by Smart Exam presets and service upsert.
- `is_active = 0` hides services from new Smart Exam order entry but preserves historical `visit_services`.
- `visit_services.unit_price` stores the billing snapshot, so later master price edits must not mutate old visits.
- Future price audit can add `service_price_history`, but that requires a separate migration and workflow.

### `visit_services`
Purpose:
- บันทึกรายการบริการที่ใช้ใน visit

Key Fields:
- `id`
- `visit_id`
- `service_id`
- `qty`
- `unit_price`
- `line_total`
- `remark`

Relations:
- N:1 ไป `visits`
- N:1 ไป `services`

Notes:
- ใช้ทั้งคิดเงินจริงและ mark preset source ผ่าน `remark`

### `inventory_items`
Purpose:
- master รายการยา/เวชภัณฑ์/อุปกรณ์

Key Fields:
- `id`
- `item_code`
- `item_name`
- `item_type`
- `unit_name`
- `reorder_level`
- `default_cost`
- `default_price`
- `is_active`

Relations:
- 1:N ไป `inventory_batches`
- 1:N ไป `visit_item_usages`
- 1:N ไป `stock_movements`

### `inventory_batches`
Purpose:
- เก็บ stock ระดับ lot/batch

Key Fields:
- `id`
- `item_id`
- `lot_no`
- `expiry_date`
- `qty_in`
- `qty_balance`
- `cost_per_unit`
- `received_date`

Relations:
- N:1 ไป `inventory_items`
- 1:N ไป `stock_movements`

Notes:
- ใช้ FEFO / expiry-oriented consumption

### `stock_movements`
Purpose:
- audit trail ของ stock movement

Key Fields:
- `id`
- `batch_id`
- `item_id`
- `movement_type`
- `qty`
- `unit_cost`
- `reference_type`
- `reference_id`
- `note`
- `movement_datetime`
- `created_by`

Relations:
- N:1 ไป `inventory_batches`
- N:1 ไป `inventory_items`
- N:1 ไป `users`

Movement Types:
- `IN`
- `OUT`
- `ADJUST`

Inventory workstation note:
- No new table is required for the current Medical Supply Workstation pass.
- `inventory_items` remains the item master.
- `inventory_batches.qty_balance` remains the batch-level current balance.
- `stock_movements` remains the audit trail for receive, Smart Exam usage, Excel import, and manual adjustment.
- Manual adjustment must include a clear `note` so stock changes can be reviewed later.
- If commercial-grade balance replay is needed later, consider adding a nullable `balance_after` field to `stock_movements`.

### `visit_item_usages`
Purpose:
- บันทึกการใช้ยา/เวชภัณฑ์/อุปกรณ์ใน visit

Key Fields:
- `id`
- `visit_id`
- `item_id`
- `qty`
- `unit_price`
- `line_total`
- `usage_note`

Relations:
- N:1 ไป `visits`
- N:1 ไป `inventory_items`

Notes:
- เป็น source ของค่า item subtotal
- ต้องสัมพันธ์กับ stock movement

### `payments`
Purpose:
- บันทึกการชำระเงินและใบเสร็จ

Key Fields:
- `id`
- `visit_id`
- `receipt_no`
- `subtotal_service`
- `subtotal_item`
- `discount_amount`
- `total_amount`
- `paid_amount`
- `change_amount`
- `payment_method`
- `payment_status`
- `paid_at`
- `paid_by`

Relations:
- 1:1 ไป `visits`
- N:1 ไป `users`

Payment Methods:
- `CASH`
- `TRANSFER`
- `QR`

Payment Status:
- `UNPAID`
- `PAID`
- `VOID`

### `appointments`
Purpose:
- เก็บนัดหมายติดตาม

Key Fields:
- `id`
- `patient_id`
- `visit_id`
- `appointment_date`
- `appointment_time`
- `purpose`
- `status`
- `note`

Relations:
- N:1 ไป `patients`
- optional N:1 ไป `visits`

### `system_settings`
Purpose:
- เก็บค่าคลินิกและ behavior ที่ปรับได้จาก admin

Key Fields:
- `clinic_name`
- `clinic_address`
- `clinic_phone`
- `clinic_tax_id`
- `receipt_logo_text`
- `receipt_footer`
- `receipt_prefix`
- `hn_prefix`
- `expiry_alert_days`
- `queue_note`

Notes:
- มีผลต่อ numbering และข้อความบนเอกสาร

Receipt Branding Notes:
- `clinic_tax_id`, `receipt_logo_text`, and `receipt_footer` support printable clinic identity.
- Existing databases are patched by `SettingsController::ensureSettingsSchema()` when Settings is opened or saved.

### `audit_logs`
Purpose:
- เตรียมไว้สำหรับ action logging

Key Fields:
- `user_id`
- `action`
- `table_name`
- `record_id`
- `detail_json`

Current State:
- schema มีแล้ว แต่ระบบยังใช้ไม่เต็ม

### `running_numbers`
Purpose:
- เก็บ running number ตามประเภทและวันที่

Key Fields:
- `number_type`
- `running_date`
- `last_no`

Used For:
- HN
- VN
- QUEUE
- RECEIPT

## Relationship Summary
- `roles.id` -> `users.role_id`
- `patients.id` -> `visits.patient_id`
- `visits.id` -> `queue_entries.visit_id`
- `visits.id` -> `visit_vitals.visit_id`
- `visits.id` -> `visit_services.visit_id`
- `services.id` -> `visit_services.service_id`
- `inventory_items.id` -> `inventory_batches.item_id`
- `inventory_items.id` -> `visit_item_usages.item_id`
- `visits.id` -> `visit_item_usages.visit_id`
- `inventory_batches.id` -> `stock_movements.batch_id`
- `visits.id` -> `payments.visit_id`
- `patients.id` -> `appointments.patient_id`

## Important Business Rules In Schema
- หนึ่ง visit มี queue entry เดียว
- หนึ่ง visit มี payment หลักหนึ่งรายการ
- HN ไม่ reset รายวัน
- VN, queue, receipt reset ตามวัน
- qty balance อยู่ที่ batch ไม่ใช่ item summary table

## Performance Notes
- schema ปัจจุบันพอเหมาะกับคลินิกขนาดเล็กถึงกลาง
- ถ้า scale สูงขึ้น ให้พิจารณา:
  - เพิ่ม covering index ตาม report query
  - archive payments/report snapshot
  - แยก read model สำหรับ dashboard

## Schema Change Rules For AI
เมื่อแก้ schema:
1. อัปเดต `database/schema.sql`
2. อัปเดตไฟล์นี้
3. อัปเดต flow ที่เกี่ยวข้องใน `SYSTEM_FLOW.md`
4. ถ้าเป็น Smart Exam field ให้แก้ `SMART_EXAM_LOGIC.md`
5. ห้าม rely เฉพาะ runtime auto-alter เป็น long-term solution

### `smart_exam_presets`
Purpose: configurable Smart Exam quick presets for common clinic workflows.

Key fields:
- `preset_key`: stable preset identifier used by Smart Exam forms
- `label`: button label shown to nurse
- `description`: short helper text
- `theme`: visual style class such as `preset-wound`
- `service_codes`: comma/space separated service codes to add
- `item_codes_json`: JSON array of `{code, qty}` item usages
- `cc`, `pi`, `pe`, `dx`: clinical defaults
- `advice`: default patient advice
- `followup_days`: optional follow-up date offset
- `sort_order`: display order
- `is_active`: hide/show preset

Relations:
- `service_codes` maps to `services.service_code`
- `item_codes_json[].code` maps to `inventory_items.item_code`

Rules:
- Presets are configurable from Settings.
- Smart Exam reads active presets from this table first.
- Hardcoded fallback remains for safety if table creation/query fails.
## Patient Card Photo

- `patients.photo_path`: stores a relative path to the saved smart-card photo file.
- Patient photos are stored as files under `storage/patient-photos/`, not as database BLOB data.
- The app serves photos through the protected `patient-photo` route after checking the patient record and safe storage path.

## Import Logs

เพิ่มตารางสำหรับ audit การนำเข้าข้อมูล:

- `import_logs`: เก็บ import batch, ประเภทข้อมูล, ชื่อไฟล์, จำนวนแถว, จำนวนผ่าน/ผิด/ซ้ำ, status, created_by
- `import_log_rows`: เก็บข้อมูลรายแถวเป็น JSON, mapped data, status และ error message

Stock batch import ต้องเขียน:

- `inventory_batches`
- `stock_movements` โดยใช้ `movement_type = IN`, `reference_type = EXCEL_IMPORT`, `reference_id = import_logs.id`

## Payment Schema Notes

- Current `payments.payment_method` enum supports only `CASH`, `TRANSFER`, and `QR`.
- Medical Cashier Workstation uses this existing enum without schema migration.
- `discount_amount`, `total_amount`, `paid_amount`, and `change_amount` are used for cashier validation and receipt output.
- Future refund/card/free payment workflow requires a migration before UI options are enabled.

## Service Schema Notes

- Service Workstation Phase 2 adds `service_price_history` for auditable price changes.
- Usage count, total income, and latest use are calculated from `visit_services`.
- Smart Exam preset linkage is derived from existing `smart_exam_presets.service_codes`.
- `visit_services.unit_price` preserves historical billing price; editing `services.price` affects future selections only.
- `service_price_history.old_price` may be null when the row records the initial price for a newly created service.
- `service_price_history.changed_by` references `users.id` and uses `ON DELETE SET NULL`.
- Service action audit uses existing `audit_logs` with `table_name = services`.
- Category management is still stored as `services.category` free text; a dedicated category table remains future scope.

## Pharmacy Label Schema Notes

- `drug_profiles` is a companion table for `inventory_items` and stores label-oriented drug defaults such as short name, category, default dose, default instruction, and warning text.
- `inventory_items` remains the source of truth for item identity, price, active state, and stock linkage.
- `prescriptions` is one row per visit and snapshots the medication label workflow state.
- `prescription_items` links to `visit_item_usages` through `visit_item_usage_id`; this prevents label generation from becoming a second stock deduction path.
- `medication_print_logs` records browser print and reprint events by prescription item, visit, patient, label size, printed user, and timestamp.
- Label printing does not touch `payments`, `inventory_batches`, or `stock_movements`.
- Pharmacy Workstation Phase 2 uses the same Phase 1 pharmacy tables; no new table or column was added.
- The print queue is derived from missing/existing `medication_print_logs` per `prescription_items` row.
