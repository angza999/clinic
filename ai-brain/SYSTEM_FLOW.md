# System Flow: ดงมหาวันคลินิก

## Overview
เอกสารนี้อธิบาย flow เชิงปฏิบัติของระบบเพื่อให้ AI และนักพัฒนารู้ว่า state ใดเกิดก่อน-หลัง และการเปลี่ยนแปลงใดกระทบ module อื่น

## Actors
- Reception / Front desk
- Nurse
- Cashier
- Admin

## Canonical Statuses
### Queue Status
- `WAITING`
- `IN_SERVICE`
- `WAITING_PAYMENT`
- `COMPLETED`
- `CANCELLED`

### Allowed Transitions
- `WAITING -> IN_SERVICE`
- `WAITING -> CANCELLED`
- `IN_SERVICE -> WAITING_PAYMENT`
- `IN_SERVICE -> COMPLETED`
- `IN_SERVICE -> CANCELLED`
- `WAITING_PAYMENT -> IN_SERVICE`
- `WAITING_PAYMENT -> COMPLETED`
- `WAITING_PAYMENT -> CANCELLED`

AI ต้องไม่เพิ่ม transition ใหม่โดยไม่อัปเดต helper `can_transition_queue_status()` และ context

## Registration Flow
1. เจ้าหน้าที่ค้นหาผู้ป่วยจาก HN, ชื่อ, นามสกุล, เบอร์โทร หรือเลขบัตร
2. ถ้าไม่พบ ให้สร้าง patient ใหม่
3. ระบบ generate `HN` ผ่าน `NumberGenerator::nextHn()`
4. ถ้าเลือก action แบบเริ่มรักษาทันที:
   - สร้าง `visit`
   - สร้าง `queue_entries`
   - redirect ไป `queue-exam`
5. ถ้าเป็นแค่บันทึกผู้ป่วย:
   - จบที่ patient profile/list

### Registration Design Intent
- ลดการบันทึกซ้ำ
- ลงทะเบียนเสร็จแล้วเปิดเคสได้ทันที
- patient record ต้องรองรับประวัติย้อนหลังและนัดหมาย

## Queue Flow
1. ระบบสร้าง queue entry ใหม่ด้วยสถานะ `WAITING`
2. Queue page แสดง 3 จุดสำคัญ:
   - เริ่มเคส
   - เปิด Smart Exam
   - สรุปเคส
3. ถ้ากดเรียกคิว:
   - queue status เปลี่ยนเป็น `IN_SERVICE`
4. ถ้าเปิด Smart Exam จากเคสที่ยัง `WAITING`
   - ระบบ promote เป็น `IN_SERVICE` ให้อัตโนมัติ
5. Queue page เลือก active visit จาก:
   - `visit_id` ที่ request มา
   - หรือเคสที่ `IN_SERVICE`
6. ถ้ามีเคสส่งต่อไปการเงิน:
   - เปลี่ยนเป็น `WAITING_PAYMENT`
7. เมื่อชำระเงินสำเร็จ:
   - เปลี่ยนเป็น `COMPLETED`

### Queue Flow Constraints
- หนึ่ง visit ต้องมี queue entry เดียว
- queue number unique ต่อวัน
- queue page ต้องช่วย nurse ทำงาน ไม่ใช่หน้ารายงาน

## Smart Exam Flow
1. เข้าหน้า `queue-exam`
2. ถ้าเคสอยู่ `WAITING` จะถูกเปลี่ยนเป็น `IN_SERVICE`
3. พยาบาลเลือกได้ 3 วิธีหลัก:
   - ใช้ preset เร็ว
   - กรอก clinical data เอง
   - เพิ่ม service/item แบบ manual
4. ระบบรองรับข้อมูล:
   - CC
   - PI
   - PE
   - Dx
   - vital signs
5. พยาบาลเพิ่มบริการ
6. พยาบาลเพิ่มยา/เวชภัณฑ์/อุปกรณ์
7. เมื่อเพิ่ม item usage:
   - ระบบตัด stock จาก batch
   - ใช้หลัก FEFO ตาม batch ที่หมดอายุก่อน
8. เมื่อ remove item usage:
   - ระบบคืน qty กลับ batch จาก stock movement
9. เมื่อกดจบเคส:
   - ถ้า mode = payment: ต้องมี billable items ก่อน
   - ถ้า mode = no_charge: ปิดเคสได้แม้ไม่มีค่าใช้จ่าย
10. เมื่อ finish แบบคิดเงิน:
   - queue status -> `WAITING_PAYMENT`
11. เมื่อ finish แบบไม่มีค่าใช้จ่าย:
   - queue status -> `COMPLETED`

### Smart Exam UX Intent
- เป็นหน้าหลักของ nurse
- กรอกให้น้อยที่สุดแต่ยังได้ข้อมูลเพียงพอ
- Sidebar สรุปราคาและรายการต้องมองเห็นง่าย
- การกลับไป `visit-edit` เป็น secondary path ไม่ใช่ primary path

## Visit Detail Flow
1. ใช้เมื่อผู้ใช้ต้องแก้รายละเอียดลึกกว่า Smart Exam
2. รองรับ:
   - clinical note
   - templates
   - follow-up date
   - recent visits
   - detailed service/item editing
3. เคส editable เฉพาะเมื่อ status = `IN_SERVICE`
4. ถ้า save and payment:
   - ต้องมี billable items
   - queue status -> `WAITING_PAYMENT`

## Payment Flow
1. Cashier/Admin เปิดหน้า payments
2. เห็นเฉพาะเคส `WAITING_PAYMENT` และ `COMPLETED`
3. เมื่อรับชำระ:
   - คำนวณ subtotal service
   - คำนวณ subtotal item
   - apply discount
   - ตรวจ paid amount ต้องไม่น้อยกว่ายอดสุทธิ
   - generate receipt no
   - insert/update payments
   - queue status -> `COMPLETED`
4. สามารถส่งเคสกลับห้องตรวจได้จาก `WAITING_PAYMENT`
   - queue status -> `IN_SERVICE`
   - redirect ไปหน้า visit detail

### Payment Rules
- ห้ามรับชำระถ้ายังไม่มีรายการคิดเงิน
- หนึ่ง visit มี payment เดียวเป็น canonical payment row
- receipt ต้อง trace กลับไป visit ได้

## Stock Flow
1. Admin เพิ่ม inventory item
2. Admin รับสินค้าเข้า batch
3. ระบบสร้าง `stock_movements` แบบ `IN`
4. เมื่อใช้ item ใน visit:
   - สร้าง `visit_item_usages`
   - ตัด `inventory_batches.qty_balance`
   - บันทึก `stock_movements` แบบ `OUT`
5. เมื่อปรับ stock manual:
   - update balance
   - create `stock_movements` แบบ `ADJUST`
6. Dashboard และ reports ใช้ stock summary เพื่อ:
   - เตือนของต่ำกว่า reorder level
   - เตือนใกล้หมดอายุ

### Stock Flow Constraints
- Stock ต้องย้อน trace ได้ผ่าน movement
- Batch คือ source of truth ของคงเหลือระดับ lot
- ห้ามตัด stock โดยไม่สร้าง movement

### Smart Exam Stock Safety Flow
- Smart Exam item list reads:
  - total balance from active batches
  - `inventory_items.reorder_level`
  - nearest batch expiry date with remaining balance
- UI flags items as:
  - out of stock
  - low stock
  - expiring soon
  - ready
- Out-of-stock items are disabled before submit.
- Low/expiring items remain selectable but should be visibly flagged so the nurse can decide.

## Reporting Flow
1. Admin เปิด reports
2. ดู daily report ตามวันที่
3. ดู monthly report ตามเดือน
4. พิมพ์รายงานรายวัน/รายเดือน
5. export ข้อมูล:
   - patients
   - visits today
   - revenue month
   - inventory alerts

## Daily Close Flow
1. Operator opens Dashboard near end of day.
2. Daily close panel summarizes:
   - paid receipt count
   - net paid revenue
   - discount total
   - payment method split
   - latest receipts
   - pending waiting/in-service/waiting-payment queues
3. If pending work exists, Dashboard shows a warning-style status instead of `พร้อมปิดวัน`.
4. Operator should clear queue/payment work before considering the clinic day closed.
5. When no pending work remains, Admin can trigger end-of-day backup from the same Dashboard close panel.
6. Backup downloads a SQL dump and writes daily-close metadata into the dump header.
7. Backup cleanup keeps the latest 30 `clinic_backup_*.sql` files and deletes older matching files.
8. Dashboard reads the latest backup file in `storage/exports` and shows whether backup was already created today.

### Daily Close Scope
- This is an operational close summary.
- It is not a formal accounting ledger or tax report.
- Formal reports remain in the Reports module.
- Backup remains a local SQL dump and does not yet include background scheduling.
- Retention is file-based and limited to local `clinic_backup_*.sql` files.

## Appointment Flow
1. ระบบนัดหมายยังเป็น flow รอง
2. ปัจจุบันนัดหมายเกิดจาก:
   - `followup_date` ที่บันทึกใน visit
   - ระบบสร้าง row ใน `appointments`
3. Dashboard และ patient history อ่านข้อมูลนี้ได้

### Smart Exam Follow-Up Sync
- Nurse enters advice and optional `followup_date` in Smart Exam.
- On finish, Smart Exam saves the clinical fields and then syncs `appointments`.
- If a scheduled appointment for the same `visit_id` exists:
  - update date, purpose, and note
  - remove duplicate scheduled appointments for that visit
- If no appointment exists and `followup_date` is set:
  - create one `appointments` row with purpose `นัดติดตามอาการ`
- If `followup_date` is empty:
  - delete scheduled appointments linked to the visit
- Sync must run in the same transaction as clinical save/payment closure.

### Appointment Check-In Flow
- Queue page shows scheduled appointments due today or overdue.
- Nurse can click `รับนัดเข้าคิว`.
- System checks whether the patient already has an active queue today.
- If active queue exists:
  - open the existing Smart Exam visit
  - do not create a duplicate queue
- If no active queue exists:
  - create a new visit and queue through `ClinicWorkflow::createVisitAndQueue()`
  - mark appointment as `COMPLETED`
  - link appointment to the new visit
  - redirect directly to Smart Exam

### Current Limitation
- ยังไม่มี dedicated appointment calendar module
- ยังไม่มี reschedule workflow เต็มรูปแบบ

## Backup Flow
1. Admin กด backup from Reports or `สำรองข้อมูลปิดวัน` from Dashboard.
2. ระบบอ่าน schema และ data จาก table ที่กำหนด
3. Before table export, system reads today's paid receipt count, paid total, and pending queue/payment work.
4. SQL dump header includes generated time, daily close date, receipt count, paid total, and pending work count.
5. SQL dump header includes active retention policy.
6. สร้าง SQL dump ไว้ที่ `storage/exports`
7. Cleanup old backups beyond the retention limit.
8. ส่งไฟล์กลับให้ดาวน์โหลดทันที

### Backup Safety Rules
- Dashboard should encourage clearing pending work before backup.
- Backup download still works as an Admin utility, but the end-of-day UX should frame it as the final close step.
- Latest backup indicator is file-based and uses `clinic_backup_*.sql` modified time.
- Retention cleanup must only target files matching `clinic_backup_*.sql`.
- If backup history needs auditing, add a real backup log table in a future phase.
- Do not store sensitive backup files in a publicly browsable directory.

## Failure Handling Principles
- ถ้ากระบวนการเดียวกระทบหลาย table ให้ใช้ transaction
- ถ้า stock หรือ payment fail ต้อง rollback ทั้งชุด
- Error message ควรชี้ว่าผิดตรง workflow ไหน ไม่ใช่แค่ SQL fail

## AI Change Checklist
ก่อนแก้ flow ไหน ให้ตอบให้ได้:
1. state เริ่มต้นคืออะไร
2. state ปลายทางคืออะไร
3. มี table ไหนโดนแตะบ้าง
4. มี side effect กับ stock/payment/queue หรือไม่
5. role ไหนใช้ flow นี้จริง

## One-Operator Clinic Flow Update

### Smart Exam Inline Payment Flow
Use this flow for a clinic where one nurse handles registration, exam, service, medicine, and payment.

1. Patient enters queue and opens Smart Exam.
2. Nurse selects preset or manually enters CC/Dx/vitals.
3. Nurse adds services and medicines/equipment in the same screen.
4. Smart Exam calculates billable totals from `visit_services` and `visit_item_usages`.
5. Right summary panel shows payment method, discount, received amount, net total, and change.
6. Primary action is `receive_payment`:
   - validate CC and Dx
   - validate at least one billable service/item
   - validate paid amount >= net total
   - create `payments` row
   - generate receipt number
   - update queue status to `COMPLETED`
7. Secondary action is `waiting_payment`:
   - validate clinical note and billable rows
   - update queue status to `WAITING_PAYMENT`
   - used only when payment cannot be received immediately
8. No-charge action is allowed for follow-up or free cases:
   - validate CC and Dx
   - update queue status to `COMPLETED`

### State Rules
- `IN_SERVICE` + `receive_payment` -> `COMPLETED`
- `IN_SERVICE` + `waiting_payment` -> `WAITING_PAYMENT`
- `IN_SERVICE` + `no_charge` -> `COMPLETED`
- A paid visit must have only one canonical row in `payments`.
- For single-nurse clinic UX, the standalone payments page is fallback/admin, not the normal daily path.

### Receipt Handoff
- After `receive_payment`, redirect to `receipt&id=<payment_id>&source=smart_exam`.
- Receipt page is part of the closure flow, not only a finance history screen.
- Nurse role can view receipt because the nurse may be the payment operator.
- Receipt should display visit advice and follow-up date when available.
- The receipt can function as a lightweight after-care instruction sheet.
- Primary next steps on receipt:
  - print receipt
  - return to queue
  - open payments page only if role is Admin/Cashier

### Next Case Continuation
- Receipt `return to queue` should include `from_receipt=1`.
- Queue page uses `from_receipt=1` to show a continuation panel for the next case.
- If a waiting queue exists, the continuation action should call the queue and open Smart Exam in one action.
- Normal next-queue action should prefer `redirect_to_visit=1` for one-operator clinics.
- Keyboard shortcuts on queue page:
  - `Alt+N` = call/open next case when available
  - `Alt+S` = focus patient search

### Quick Register Flow
- Queue page may register a new patient with minimal fields:
  - full name
  - phone
  - gender
  - drug allergy
  - chief complaint
- `POST:queue-quick-register` creates:
  - `patients`
  - `visits`
  - `queue_entries`
- After creation, redirect directly to `queue-exam`.
- Duplicate guard checks phone and name before creating a new patient.
- If a possible duplicate exists, require explicit `confirm_duplicate=1`.
- Full patient registration page remains available for complete demographic entry.

### Configurable Smart Preset Flow
- Admin opens Settings and edits active Smart Exam presets.
- Preset records live in `smart_exam_presets`.
- Queue/Smart Exam load active presets ordered by `sort_order`.
- Applying a preset still uses the same Smart Exam flow:
  - add configured service codes
  - add configured item codes and quantities
  - apply configured CC/PI/PE/Dx/advice/follow-up defaults
- If database presets cannot be loaded, the system falls back to hardcoded defaults.

### Patient Snapshot Flow
- When Smart Exam opens, the current visit is moved to or kept in `IN_SERVICE`.
- The system loads a patient snapshot for the same `patient_id`.
- Snapshot data excludes the current `visit_id`.
- The nurse sees:
  - profile risk: allergy, underlying disease, visit count
  - prior unpaid cases
  - latest historical vital signs
  - upcoming scheduled appointments
  - last three treatment visits
- If the nurse needs full history, use the patient detail link.
- The snapshot must not create, update, or auto-copy clinical data by itself.
