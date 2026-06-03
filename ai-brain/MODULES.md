# Modules: ดงมหาวันคลินิก

## Overview
เอกสารนี้สรุป module เชิงหน้าที่ของระบบ ไม่ใช่แค่รายชื่อ controller

## 1. Auth
Purpose:
- login/logout
- session management
- role-based access
- audit login success, login failure, and logout actions

Key Files:
- `app/Core/Auth.php`
- `app/Controllers/AuthController.php`

Important Notes:
- role มีผลต่อ default landing page
- `CASHIER` เข้า payments เป็นหลัก
- `ADMIN` และ `NURSE` เข้า queue เป็นหลัก

## 2. Patients
Purpose:
- ค้นหา, ลงทะเบียน, เปิดแฟ้ม, ดูประวัติย้อนหลัง

Key Functions:
- search patient
- create patient
- patient profile
- patient visit history
- start treatment directly from patient flow

Routes:
- `GET:patients`
- `GET:patient-show`
- `POST:patients-store`
- `POST:patient-start-treatment`

## 3. Queue
Purpose:
- ควบคุม operational state ของผู้ป่วยในวันนั้น

Key Functions:
- create queue from existing patient
- quick register new patient and open Smart Exam
- call next queue
- move queue status
- select active visit
- display queue board

Routes:
- `GET:queue`
- `GET:queue-display`
- `POST:queue-store`
- `POST:queue-quick-register`
- `POST:queue-status`

## 4. Smart Exam
Purpose:
- หน้าตรวจแบบเร็วสำหรับพยาบาล

Key Functions:
- open active case
- quick preset
- quick clinical note
- quick service add
- quick item add
- finish case to payment/no-charge

Routes:
- `GET:queue-exam`
- `POST:queue-apply-preset`
- `POST:queue-smart-finish`
- `POST:queue-quick-complete`

Dependencies:
- queue status
- services
- inventory
- visit_vitals
- payments workflow

## 5. Visit Detail
Purpose:
- หน้ารายละเอียดเชิงลึกของ visit

Key Functions:
- edit clinical notes
- add/remove services
- add/remove item usages
- save follow-up date
- send to payment

Routes:
- `GET:visit-edit`
- `POST:visit-save-clinical`
- `POST:visit-add-service`
- `POST:visit-remove-service`
- `POST:visit-add-item`
- `POST:visit-remove-item`
- `POST:visit-ready-payment`

## 6. Services
Purpose:
- master data รายการบริการและราคา

Routes:
- `GET:services`
- `POST:services-store`

Notes:
- ถูกใช้โดย Smart Exam, Visit Detail, Report, Payment calculation

Service Workstation Update:
- Route added: `POST:services-toggle`
- Assets: `public/assets/css/services.css`, `public/assets/js/services.js`
- Services page now uses KPI cards, realtime search/filter, interactive data grid, and right-side detail/edit panel.
- Admin can add/edit by `service_code`, duplicate from an existing row, and enable/disable services.
- Nurse can view/search service standards but cannot mutate records.
- Deleting services is intentionally avoided because `visit_services` preserves historical billing.

## 7. Inventory
Purpose:
- จัดการรายการยา/เวชภัณฑ์/อุปกรณ์
- จัดการ batch และ movement

Key Functions:
- create/update item
- receive batch
- manual adjust stock
- summary stock and expiry
- expose stock safety status inside Smart Exam item ordering

Routes:
- `GET:inventory`
- `POST:inventory-item-store`
- `POST:inventory-batch-store`
- `POST:inventory-adjust`

## 8. Payments
Purpose:
- ปิดรอบการเงินของ visit

Key Functions:
- list waiting payment visits
- take payment
- calculate total
- print receipt
- show printable after-care instructions when visit advice or follow-up date exists
- show configurable receipt branding from Settings
- send case back to nurse

Routes:
- `GET:payments`
- `POST:payments-store`
- `POST:payments-send-back`
- `GET:receipt`

Cashier Workstation Update:
- Assets: `public/assets/css/payments.css`, `public/assets/js/payments.js`
- Payments page now separates waiting payment queue, receipt history, and right financial action rail.
- Realtime search supports VN, HN, patient name, and receipt number.
- Existing payment methods are `CASH`, `TRANSFER`, and `QR`.
- Cash payments validate received amount and calculate change; transfer/QR payments auto-fill paid amount to net total.
- Refund/card/free workflows are future phases because the current `payments.payment_method` schema does not include those values.

## 9. Reports
Purpose:
- daily/monthly reporting
- export operational data

Key Functions:
- daily summary
- monthly summary
- printable report
- csv export

Routes:
- `GET:reports`
- `GET:report-print`
- `GET:export`

## 10. Dashboard
Purpose:
- ภาพรวมรายวันและแนวโน้มล่าสุด

Key Functions:
- today stats
- monthly revenue trend
- popular services
- active queue summary
- appointments today
- low stock
- expiry alert
- daily close summary
- payment method split
- latest receipt strip
- end-of-day backup handoff
- pending-work close checklist
- latest backup indicator

Routes:
- `GET:dashboard`

## 11. Settings
Purpose:
- ตั้งค่าคลินิกและ behavior สำคัญของระบบ

Key Functions:
- clinic identity
- receipt branding fields
- receipt prefix
- HN prefix
- expiry alert window
- queue note
- Smart Exam preset configuration

Routes:
- `GET:settings`
- `POST:settings-store`
- `POST:settings-preset-store`

## 12. Backup
Purpose:
- export SQL dump เพื่อสำรองข้อมูล

Routes:
- `GET:backup`

Notes:
- admin only
- สร้างไฟล์ลง `storage/exports`
- SQL dump header includes end-of-day metadata for generated time, close date, paid receipt count, paid total, and pending queue/payment work.
- Dashboard links to backup as the final close-day action when no pending work remains.
- Dashboard reads latest `clinic_backup_*.sql` from `storage/exports` for backup status display.
- Backup retention keeps the latest 30 `clinic_backup_*.sql` files after successful backup creation.
- Dashboard displays backup count against the retention limit.

## 13. Users
Purpose:
- จัดการเจ้าหน้าที่และสิทธิ์

Key Functions:
- list users
- create/update user
- activate/deactivate
- change password
- audit user create/update/password actions
- show recent user-management audit history

Routes:
- `GET:users`
- `POST:users-store`
- `POST:users-password`

## 14. Shared Core
Purpose:
- utility layer ที่ทุก module ใช้

Key Files:
- `app/helpers.php`
- `app/Core/Database.php`
- `app/Core/NumberGenerator.php`
- `app/Core/ClinicWorkflow.php`

Key Responsibilities:
- route helper
- auth helper
- queue state helper
- format helper
- running number generation
- create visit+queue transaction

## 15. Patient Snapshot
Purpose:
- show compact historical context inside Smart Exam so the nurse does not need to leave the exam screen for common safety checks

Key Functions:
- allergy and underlying disease visibility
- latest historical vital signs
- last three treatment visits
- upcoming appointments
- old unpaid case warning
- link to full patient history

Key Files:
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`

Routes:
- `GET:queue-exam`
- `GET:patient-show`

Notes:
- Snapshot is read-only in Smart Exam.
- Do not auto-copy previous visit content into the current visit without explicit nurse action.

## 16. Follow-Up Appointment Sync
Purpose:
- convert a visit follow-up date into an operational appointment without forcing the nurse into a separate module

Key Functions:
- save advice from Smart Exam
- save `visits.followup_date`
- create/update/delete scheduled `appointments` for the current visit
- deduplicate scheduled appointments linked to the same visit

Key Files:
- `app/Controllers/QueueController.php`
- `app/Controllers/VisitController.php`
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`

Routes:
- `POST:queue-smart-finish`
- `POST:visit-save-clinical`

Notes:
- This is not a full appointment calendar module.
- Full reschedule/cancel workflow remains future scope.

## 17. Appointment Check-In
Purpose:
- convert due appointments into queue visits from the daily queue screen

Key Functions:
- list scheduled appointments due today or overdue
- prevent duplicate active queue for the same patient
- create visit and queue via `ClinicWorkflow`
- mark appointment as `COMPLETED`
- redirect directly to Smart Exam

Key Files:
- `app/Controllers/QueueController.php`
- `app/Views/queue/index.php`
- `public/assets/css/queue.css`
- `public/index.php`

Routes:
- `POST:appointment-checkin`
- `GET:queue`

Notes:
- This is an intake workflow, not a calendar module.
- Appointment check-in belongs to Admin/Nurse roles.

## 18. Appointments
Purpose:
- manage scheduled follow-ups and manual appointments outside the daily queue panel

Key Functions:
- agenda list with date/status/keyword filters
- create appointment for an existing active patient
- reschedule/edit date, time, purpose, and note for scheduled appointments
- cancel scheduled appointments
- check in scheduled appointments using the existing queue intake flow
- open an existing active queue if the same patient is already queued today

Key Files:
- `app/Controllers/AppointmentController.php`
- `app/Views/appointments/index.php`
- `public/assets/css/appointments.css`
- `public/index.php`
- `app/Views/layouts/app.php`

Routes:
- `GET:appointments`
- `POST:appointments-store`
- `POST:appointments-update`
- `POST:appointments-cancel`
- `POST:appointment-checkin`

Notes:
- Uses the existing `appointments` table; no schema change.
- Admin/Nurse only.
- This is an agenda module, not a full calendar/reminder system.

## Module Priority By Business Importance
1. Queue
2. Smart Exam
3. Payments
4. Patients
5. Inventory
6. Reports
7. Dashboard
8. Settings
9. Backup
10. Users

## AI Editing Guidance
- ถ้าแก้ workflow หลัก ให้เริ่มจาก Queue/Smart Exam/Payments ก่อน
- ถ้าแก้ master data ให้ดูผลกระทบต่อ Visit และ Reports
- ถ้าเพิ่ม module ใหม่ ต้องอัปเดตไฟล์นี้เสมอ
## Smart Card Patient Photo

- Smart-card photo payloads are decoded and saved under `storage/patient-photos/`.
- `patients.photo_path` stores the relative file path.
- `PatientController::photo()` serves photos through the protected `patient-photo` route.
- Registration stores photos for new patients; smart-card read updates existing patient photos when a fresh image is available.
- Patient profile and Smart Exam show the stored photo with a placeholder fallback.

## Inventory Workstation Update

- Controller: `app/Controllers/InventoryController.php`
- View: `app/Views/inventory/index.php`
- Assets: `public/assets/css/inventory.css`, `public/assets/js/inventory.js`
- Inventory is now a Medical Supply Workstation with KPI cards, search/filter command bar, inventory table, alert rail, focused action panels, and movement history.
- Admin can add items, receive batches, and adjust stock from one focused action panel at a time.
- Nurse can scan inventory status and movement history without seeing all admin forms as the primary workflow.
- Manual adjustment requires a reason and still writes `stock_movements` with `movement_type = ADJUST`.

## Import Excel

- Controller: `app/Controllers/ImportController.php`
- View: `app/Views/import/index.php`
- Assets: `public/assets/css/import.css`, `public/assets/js/import.js`
- Routes: `import`, `import-template`, `import-upload`, `import-validate`, `import-confirm`
- Flow: เลือกประเภท -> upload -> preview -> mapping -> validate -> confirm import -> result summary
- Phase 1 types:
  - `patients`
  - `inventory_items`
  - `inventory_batches`

## Service Workstation Phase 1

- Controller: `app/Controllers/ServiceController.php`
- View: `app/Views/services/index.php`
- Assets: `public/assets/css/services.css`, `public/assets/js/services.js`
- Routes: `services`, `services-store`, `services-toggle`, `services-export`
- Admin workflow: search/filter services, add/edit by service code, duplicate into a new code, enable/disable, export CSV.
- Nurse workflow: view and scan active/inactive service definitions without mutation rights.
- Analytics are read from existing `visit_services` totals and do not add fields.
- Historical Smart Exam/billing rows keep captured `unit_price`; editing a service price only affects new selections.

## Service Workstation Phase 2

- Adds `service_price_history` for service price change traceability.
- Reuses `audit_logs` for service create/update/enable/disable/export actions.
- Right rail shows selected service usage insight, price history, and recent service audit rows.
- `ServiceController::ensurePhase2Schema()` creates the history table for existing local installs.
- Category table and bundle/package services are still future scope.
- Services import ถูกเก็บเป็น Phase 2 เพื่อลด risk ต่อ workflow หลัก

## Pharmacy Sticker Label Phase 1

- Controller: `app/Controllers/PharmacyController.php`
- View: `app/Views/pharmacy/labels.php`
- Assets: `public/assets/css/pharmacy-labels.css`, `public/assets/js/pharmacy-labels.js`
- Routes: `pharmacy-labels`, `pharmacy-print-log`
- Smart Exam entry point: `app/Views/queue/exam.php` medication panel and summary action rail.
- Purpose: create prescription snapshots from already-added `visit_item_usages`, preview sticker labels, print through browser, and log print/reprint actions.
- Key safety rule: label generation never deducts stock. Stock deduction remains owned by `VisitController::addItemUsage()` and `stock_movements` with `reference_type = VISIT_USAGE`.
- Phase 1 is browser-print only; direct thermal printer integration is future scope.

## Pharmacy Sticker Label Phase 2

- Controller: `app/Controllers/PharmacyController.php`
- Views: `app/Views/pharmacy/index.php`, `app/Views/pharmacy/labels.php`
- Assets: `public/assets/css/pharmacy-labels.css`, `public/assets/js/pharmacy-workstation.js`, `public/assets/js/pharmacy-labels.js`
- Routes: `pharmacy`, `pharmacy-labels`, `drug-profile-store`, `pharmacy-print-log`
- Admin/Nurse/Cashier can view the Pharmacy Workstation and label queue.
- Admin/Nurse can edit drug label profiles through `POST:drug-profile-store`.
- Workstation surfaces: print queue, recent print logs, drug master/profile table, and sticky smart builder profile editor.
- Database impact: no new schema beyond Phase 1 pharmacy tables.
