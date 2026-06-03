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
- Receipt branding reads `system_settings` only; it must not change totals, receipt numbering, or queue/payment state.
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

### Appointment Agenda Flow
- Admin/Nurse opens `GET:appointments`.
- The agenda filters appointments by date range, status, and keyword.
- The create form selects an existing active patient and inserts a scheduled appointment.
- Scheduled appointments can be rescheduled by updating date, time, purpose, and note.
- Scheduled appointments can be cancelled by changing status to `CANCELLED`.
- Check-in from the agenda uses the same `POST:appointment-checkin` flow as the queue due-appointment panel.
- If a patient already has an active queue today, the agenda shows a direct link to the existing Smart Exam instead of encouraging a duplicate queue.

### Current Limitation
- Appointment agenda exists for create/reschedule/cancel/check-in.
- Full calendar grid, automated reminders, and advanced recurrence are still future scope.
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

## Medical Workstation Flow Doctrine

The system flow must be understood as operational workstation flow, not module navigation. Users should move through patient care as one continuous line of work.

### Core Workstation Flow

The primary production flow is:

`intake/search -> queue -> Smart Exam -> services/items -> summary/payment -> receipt/next case`

Every UI or backend change should preserve this flow and reduce unnecessary page switching.

### Queue As Command Station
Queue is not a dashboard report. Queue is the command station for starting and continuing care.

Queue must prioritize:
1. active/next patient
2. intake/search/quick register
3. due appointment check-in
4. open Smart Exam
5. payment/summary state
6. queue boards for situational awareness

Queue should not prioritize:
- large hero text
- passive statistics
- decorative card groups
- separate step cards that do not perform work

### Smart Exam As Clinical Workstation
Smart Exam is the main clinical working surface.

The operational sequence is:
1. confirm patient and risk
2. choose preset or manually enter clinical data
3. record vitals
4. complete CC/PI/PE/Dx
5. add services
6. add medicines/equipment
7. review sticky summary
8. finish as paid, waiting payment, or no charge

The UI should support this sequence without making the nurse interpret a dashboard.

### Summary As Control Surface
Summary is part of the workflow, not just a report.

Summary must keep visible:
- patient identity
- allergy/risk
- service and item counts
- totals
- readiness checklist
- finish/payment actions

Long service/item lists should never block the finish action. Lists may scroll, collapse, or summarize counts while the finish action remains reachable.

### Progressive Disclosure Flow
Secondary context should be available without dominating the workflow.

Use progressive disclosure for:
- previous visit history
- detailed patient history
- advanced visit editing
- extended appointment context
- low-frequency administrative details

Do not use progressive disclosure for:
- allergy/risk
- active queue status
- current totals
- missing required completion fields
- primary finish/payment actions

### 14-Inch Notebook Constraint
The main flow must work on a 14-inch notebook. This means:
- the user should see the next action above the fold
- Smart Exam should show patient/risk plus preset/vitals quickly
- Queue should show command bar plus active work area quickly
- Cards and sections should not consume vertical space unless they support immediate work

### Workflow Quality Gate
Before adding or changing a flow, verify:
1. What is the starting state?
2. What is the target state?
3. What is the user's next action?
4. Can the user complete it with fewer clicks than before?
5. Does any critical state become hidden?
6. Does the page become more compact without losing clinical safety?

If a change makes the user scroll more, click more, or think more without improving safety, it should be redesigned.

### Phase 5 Operational Flow Rule
When a screen already has a command header, do not repeat the same state as large dashboard cards below it. Repetition should be replaced by:

1. a compact command bar for current state and next action
2. a bounded working area for the active task
3. a sticky summary rail for totals, readiness, and finish actions
4. scroll-limited lists for historical or secondary information
5. responsive stacking that preserves the same workflow order on smaller screens

### Stabilized Smart Exam Flow Rule
Smart Exam should complete a common case without leaving the page:

1. open active case
2. apply preset or type clinical text
3. merge preset clinical defaults without overwriting existing nurse-entered text
4. add service and medicine/equipment through compact order entry
5. show frontend suggestions and stock guards as assistance only
6. keep backend validation authoritative for stock, payment, and queue transitions
7. finish through summary rail as paid, waiting payment, or no charge

### Inline Smart Exam Ordering
- Smart Exam service/item forms submit normally as fallback.
- With JavaScript available, order forms send JSON requests to the same visit endpoints.
- The server returns the recalculated order summary after each add/remove.
- The page updates service lines, item lines, totals, counts, readiness, and payment preview without navigation.
- Payment/finish still uses server-side Smart Exam finish logic.

### Inline Smart Exam Presets
- Smart Exam preset buttons submit normally as fallback.
- With JavaScript available, preset buttons send JSON requests to `queue-apply-preset`.
- The server merges clinical fields, applies preset service/item lines if not already applied, and returns clinical + order summary payloads.
- The page updates clinical fields and the summary rail without leaving the workstation.
## Import Excel Flow

Import Excel เป็น workflow แบบปลอดภัย:

1. เลือกประเภทข้อมูล
2. Upload ไฟล์ Excel
3. Preview 10-20 แถวแรก
4. Mapping column จาก Excel เข้ากับ field ระบบ
5. Validate ทุกแถว
6. แสดงจำนวนผ่าน, ผิด, ซ้ำ
7. Confirm import
8. เขียนฐานข้อมูลด้วย transaction และบันทึก import log

ระบบไม่อนุญาตให้บันทึกทันทีหลัง upload เพื่อป้องกันข้อมูลผิดเข้าฐานข้อมูลจริง

## Medical Cashier Flow

Cashier workflow is:

1. Smart Exam or Visit Detail sends a case to `WAITING_PAYMENT`.
2. Payments workstation shows the case in the waiting payment queue.
3. Cashier verifies service/item total, discount, and payment method.
4. Cashier confirms payment.
5. Backend creates/updates `payments`, marks queue entry `COMPLETED`, and opens receipt.
6. If billing is incomplete, cashier can send the case back to `IN_SERVICE`.

Rules:
- Current payment methods are `CASH`, `TRANSFER`, and `QR`.
- Cash requires received amount >= net total.
- Transfer/QR auto-fill paid amount to net total and do not calculate change.
- Refund/free/card workflows require a future schema phase.

## Service Price Governance Flow

Service management workflow is:

1. Admin searches/selects an existing service or starts a new service.
2. Admin edits code, name, category, price, and active state in the Service Builder.
3. Backend validates service code/name and blocks negative price.
4. If the price changes, backend writes `service_price_history`.
5. Backend writes a service audit row for create, update, enable, disable, or export.
6. Smart Exam and new billing use the current active service price.
7. Historical visit service lines continue to read `visit_services.unit_price`.

Rules:
- Do not hard delete services that may appear in historical visits.
- Price history supports audit/trust; it must not recalculate old receipts.
- Bundle/package services require a future workflow design before implementation.

## Pharmacy Sticker Label Flow

1. Nurse opens Smart Exam.
2. Nurse adds medicine through `visit-add-item`; this deducts stock and writes `stock_movements` as before.
3. Medication instruction builder writes the generated Thai direction into `visit_item_usages.usage_note`.
4. Nurse clicks `พิมพ์สติ๊กเกอร์ยา` from the Smart Exam summary rail.
5. `PharmacyController` syncs `prescriptions` and `prescription_items` from current `visit_item_usages`.
6. The label preview page renders one sticker per prescription item.
7. Browser print is used for Phase 1 and `medication_print_logs` records print/reprint actions.

Rules:
- Label printing must never deduct stock.
- Label printing must never create or update payment totals.
- Prescription item snapshots should preserve printable instruction text even if drug defaults change later.

## Pharmacy Workstation Phase 2 Flow

1. Staff opens `GET:pharmacy` from the sidebar.
2. The workstation calculates pending/printed label queue from `prescriptions`, `prescription_items`, and `medication_print_logs`.
3. Staff opens a queued visit through `pharmacy-labels` to preview and print/reprint labels.
4. Admin/Nurse selects a drug profile row in the drug master table.
5. The sticky editor loads profile defaults and previews the printable label name/instruction.
6. Saving profile defaults updates `drug_profiles` only; it does not rewrite existing prescription snapshots.

Rules:
- Existing prescription items keep their current printable instruction until Smart Exam/label sync creates or updates that visit snapshot.
- Drug profile editing is master-data work; it must not alter stock, payment, or visit totals.
- Reprint visibility comes from `medication_print_logs`, not from a separate queue table.
