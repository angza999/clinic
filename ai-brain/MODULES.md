# Modules: ดงมหาวันคลินิก

## Overview
เอกสารนี้สรุป module เชิงหน้าที่ของระบบ ไม่ใช่แค่รายชื่อ controller

## 1. Auth
Purpose:
- login/logout
- session management
- role-based access

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
- send case back to nurse

Routes:
- `GET:payments`
- `POST:payments-store`
- `POST:payments-send-back`
- `GET:receipt`

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
