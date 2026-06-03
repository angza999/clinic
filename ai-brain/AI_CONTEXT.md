# AI Context: ดงมหาวันคลินิก

## Project Identity
- ชื่อระบบ: ดงมหาวันคลินิก
- ประเภท: ระบบบริหารจัดการคลินิกพยาบาลขนาดเล็กถึงกลาง
- เป้าหมายหลัก: ให้เจ้าหน้าที่หน้างานทำงานได้เร็ว, ลดการคลิก, ลดการพิมพ์, และลดการสลับหน้าจอ
- ลักษณะการใช้งาน: Front desk, พยาบาล, แคชเชียร์, ผู้ดูแลระบบ

## AI Mission
ก่อนเริ่มงานทุกครั้ง AI ต้องอ่านอย่างน้อย:
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/PROJECT_RULES.md`
- `ai-brain/MODULES.md`
- `ai-brain/DEVELOPMENT_CHECKLIST.md`

และต้องเปิดไฟล์เฉพาะทางเพิ่มตามประเภทงาน:
- workflow change -> `ai-brain/SYSTEM_FLOW.md`
- schema/query change -> `ai-brain/DATABASE_SCHEMA.md`
- UI change -> `ai-brain/UI_UX_GUIDE.md`
- Smart Exam change -> `ai-brain/SMART_EXAM_LOGIC.md`
AI coding assistant ที่เข้ามาทำงานในโปรเจ็กต์นี้ต้องเข้าใจว่า:
- ระบบนี้ไม่ใช่ generic CRUD app แต่เป็น workflow clinic app
- งานทุกชิ้นต้องคำนึงถึงความเร็วหน้างานจริงก่อนความสวยงามเชิงตกแต่ง
- จุดสำคัญที่สุดของระบบคือ flow `ลงทะเบียน -> เข้าคิว -> ตรวจ/Smart Exam -> คิดเงิน -> ปิดเคส`
- การเปลี่ยนแปลง UI ต้องช่วยให้พยาบาลทำงานสั้นลง ไม่ใช่เพิ่ม interaction
- การเปลี่ยนแปลง backend ต้องไม่ทำให้ queue, stock, payment และ visit state ไม่สอดคล้องกัน

## Core Product Goals
1. ทำให้เจ้าหน้าที่รับผู้ป่วยและเปิดเคสได้เร็วที่สุด
2. ทำให้พยาบาลบันทึกการตรวจและสั่งยาได้จากหน้าจอเดียวเป็นหลัก
3. ทำให้สต๊อกถูกตัดอัตโนมัติจากการใช้งานจริง
4. ทำให้การเงินรับชำระและออกใบเสร็จได้ชัดเจน
5. ทำให้ผู้ดูแลดูรายงาน, export และ backup ได้โดยไม่ต้องใช้เครื่องมือภายนอก

## UX Concept
- Medical Minimal UI
- Single Screen Workflow
- Fewer clicks, fewer modal, fewer hidden actions
- อ่านง่ายภายใต้แรงกดดันและเวลาจำกัด
- ใช้ card, section, status badge, sticky summary ช่วยจัดลำดับงาน
- เน้น "action-first layout" มากกว่า "data-dense admin layout"

## Current Tech Stack
- PHP 8.x
- MySQL / MariaDB
- Bootstrap 5
- Vanilla JavaScript
- Server-rendered architecture

## Runtime Characteristics
- Entry point อยู่ที่ `public/index.php`
- Routing เป็น query-based page routing เช่น `index.php?page=queue`
- ใช้ session authentication
- ใช้ role guard ผ่าน `require_roles()`
- ใช้ PDO และ prepared statements
- ไม่มี framework migration system; schema หลักอยู่ใน `database/schema.sql`
- บาง feature มี runtime schema guard เช่น `QueueController::ensureSmartExamSchema()`

## Local Database Setup
- Current local database defaults live in `config/database.php`.
- Default local values are `host=127.0.0.1`, `port=3306`, `database=dongmahawan_clinic`, `username=root`, and blank password.
- MySQL/Navicat setup and troubleshooting guide is `docs/database-setup.md`.
- If users hit `Access denied ... (using password: NO)`, first check whether they are using a custom MySQL user without entering a password.
- Do not commit real production database credentials.

## User Roles
- `ADMIN`
  - เข้าถึงทุก module
  - จัดการ settings, reports, backup, users
- `NURSE`
  - ทำงานหลักที่ queue, patients, inventory, services, Smart Exam
  - ไม่มีสิทธิ์รับชำระเงิน
- `CASHIER`
  - ทำงานหลักที่ payments, patients
  - home page เริ่มที่การเงิน

## Primary Workflow
1. ค้นหาหรือสร้างผู้ป่วย
2. สร้าง Visit และ Queue ของวัน
3. เรียกคิวเข้าตรวจ
4. เปิด Smart Exam
5. บันทึก CC, PI, PE, Dx, vital signs
6. เพิ่มบริการและยา/เวชภัณฑ์
7. ตัด stock อัตโนมัติ
8. ส่งเคสไปชำระเงิน หรือปิดแบบไม่มีค่าใช้จ่าย
9. รับชำระเงินและออกใบเสร็จ
10. ปิดเคสและรวมเข้าสู่รายงาน

## Main Modules
- Patients
- Queue
- Smart Exam
- Visit Detail
- Inventory
- Services
- Payments
- Reports
- Dashboard
- Settings
- Backup
- Users

## Data Model Summary
- Patient 1:N Visit
- Visit 1:1 Queue Entry
- Visit 1:1 Visit Vitals
- Visit 1:N Visit Services
- Visit 1:N Visit Item Usages
- Visit 0..1:1 Payment
- Patient 1:N Appointment
- Inventory Item 1:N Inventory Batch
- Inventory Batch 1:N Stock Movement

## Smart Exam Position In System
- Smart Exam คือ operational core ของฝั่งพยาบาล
- ออกแบบให้เป็นหน้าหลักของการตรวจแบบเร็ว
- ไม่ใช่ EMR เต็มรูปแบบ แต่เป็น fast clinical workflow
- ใช้ preset, quick service, quick item, auto suggestion และ summary sidebar ช่วยลดเวลา

## Architectural Principles
- Keep business flow explicit
- Keep state transitions controlled
- Avoid hidden automatic mutations ที่ตรวจสอบยาก
- Prefer direct query readability over abstraction ที่ซับซ้อนเกินจำเป็น
- Preserve compatibility with XAMPP/Laragon deployment

## What AI Must Preserve
- Queue status transitions ต้องถูกต้อง
- Payment ต้องเกิดหลังมีรายการคิดเงินจริง
- Stock must decrease only when item usage is added
- Stock must be restored when item usage is removed
- Smart Exam ต้องยังคงเป็น flow เร็ว ไม่ถูกเปลี่ยนเป็น form ยาวแบบ generic admin
- Role access ต้องไม่รั่ว

## What AI Must Not Do
- ห้ามเพิ่ม UI ที่รกหรือมี modal ซ้อนกันหลายชั้น
- ห้ามย้าย nurse flow ไปหลายหน้าถ้าไม่จำเป็น
- ห้ามตัด stock ด้วยวิธีที่ไม่ trace ได้
- ห้ามทำให้ payment เกิดก่อน visit พร้อมคิดเงิน
- ห้ามทำลาย naming convention ของ route/page
- ห้ามเพิ่ม framework ใหญ่โดยไม่มีเหตุผลเชิงสถาปัตยกรรมชัดเจน
- ห้ามสร้าง abstraction จน flow หน้างานอ่านยาก
- ห้ามปล่อย context file ล้าสมัยหลังเพิ่ม feature

## AI Update Policy
เมื่อมี feature ใหม่หรือ logic เปลี่ยน:
- อัปเดต `AI_CONTEXT.md` ถ้ากระทบภาพรวมระบบ
- อัปเดต `SYSTEM_FLOW.md` ถ้ากระทบ flow
- อัปเดต `DATABASE_SCHEMA.md` ถ้ามี schema ใหม่
- อัปเดต `UI_UX_GUIDE.md` ถ้าปรับ pattern UI
- อัปเดต `MODULES.md` ถ้ามี module ใหม่หรือ scope เปลี่ยน
- อัปเดต `SMART_EXAM_LOGIC.md` ถ้ากระทบ Smart Exam
- อัปเดต `KNOWN_ISSUES.md` และ `DECISIONS.md` ตามจริงเสมอ

## Definition Of Done For AI Work
งานจะยังไม่ถือว่าเสร็จ ถ้ายังไม่ได้ทำครบ:
1. แก้โค้ดหรือเอกสารตามโจทย์
2. ตรวจ syntax หรือทดสอบตามระดับความเสี่ยงของงาน
3. อัปเดต `ai-brain/CHANGELOG_AI.md`
4. อัปเดต context file ที่เกี่ยวข้อง
5. สรุปผลกระทบต่อ workflow, role, database และความเสี่ยงคงเหลือ

## Quick Orientation For AI
- ถ้าต้องแตะ queue: อ่าน `SYSTEM_FLOW.md` + `SMART_EXAM_LOGIC.md`
- ถ้าต้องแตะ schema: อ่าน `DATABASE_SCHEMA.md`
- ถ้าต้องแตะ UI: อ่าน `UI_UX_GUIDE.md`
- ถ้าต้องแตะ business rule: อ่าน `AI_CONTEXT.md` + `DECISIONS.md`
- ถ้าต้องเริ่มงานใหม่: อ่าน `AI_CONTEXT.md`, `MODULES.md`, `PROJECT_RULES.md`

## Current Product Direction: Single-Nurse Clinic

The primary workflow now assumes one nurse may operate the whole clinic flow alone: receive patient, open queue, perform Smart Exam, add services/medicine, receive payment, and close the case.

### UX Priority
- Smart Exam is the main daily work screen.
- Payment should be completed inside Smart Exam when possible.
- Standalone payments page is fallback/admin, not the default path for normal paid visits.
- The system should reduce page switching, scrolling, and duplicate data entry.

### Payment Behavior
- `receive_payment` closes a billable visit immediately and creates a paid receipt.
- `waiting_payment` keeps the old cashier-style flow available for exceptional cases.
- `no_charge` closes free visits without creating a payment row.
- AI must preserve the rule that paid visits need at least one service or medicine/equipment item before payment is recorded.

### Receipt Closure Behavior
- After a paid Smart Exam case, redirect to the receipt page so the operator can print immediately.
- Nurse role may view receipts because in this clinic model the nurse can be the cashier.
- The receipt page should help the operator return to today's queue quickly after printing.
- If visit advice or follow-up date exists, the receipt should include it as printable after-care information.

### Queue Continuation Behavior
- Returning from receipt should carry `from_receipt=1`.
- Queue page should guide the operator to the next case instead of becoming a passive board.
- A waiting queue should be callable and openable in Smart Exam with one action.
- Keyboard shortcuts are part of the production UX for single-nurse operation.

### Quick Register Behavior
- Queue page supports quick registration for new walk-in patients.
- Quick register must create patient, visit, and queue in one workflow.
- After quick register, send the nurse directly to Smart Exam.
- Duplicate prevention matters: phone/name matches should warn before creating a new patient.
- Keep the detailed patient page for complete demographic and administrative data.

### Configurable Preset Behavior
- Smart Exam presets are stored in `smart_exam_presets`.
- Admin can edit presets from Settings.
- Smart Exam should prefer database presets and fall back to hardcoded defaults if needed.
- Preset keys must stay stable because idempotency markers use `QUICK_PRESET:<preset_key>`.

### Patient Snapshot Behavior
- Smart Exam includes compact patient context near the top of the page.
- Snapshot shows allergy, underlying disease, visit count, unpaid previous cases, latest historical vitals, upcoming appointments, and recent visits.
- Snapshot must exclude the current visit to avoid mixing historical data with current-case data.
- Full patient detail remains the source for long history review.
- AI must keep the snapshot compact and must not turn Smart Exam into a large EMR history page.

### Follow-Up Appointment Behavior
- Smart Exam has advice and follow-up date fields.
- When a Smart Exam case is finished, `followup_date` syncs to `appointments`.
- One visit should only have one active scheduled follow-up appointment from this workflow.
- Clearing the follow-up date should remove scheduled appointments linked to that visit.
- Advanced visit save must use the same appointment sync behavior to avoid duplicate appointments.

### Appointment Check-In Behavior
- Queue page shows due or overdue scheduled appointments.
- Admin/Nurse can check in an appointment directly into queue.
- Appointment check-in creates a new visit and queue, then opens Smart Exam.
- If the patient already has an active queue today, the system must reuse that queue and avoid duplicates.
- Appointment check-in is not a full calendar module.

### Appointment Agenda Behavior
- Admin/Nurse can use `GET:appointments` as the central appointment agenda.
- The agenda supports date range, status, and keyword filtering.
- Manual appointment creation uses existing active patients.
- Scheduled appointments can be rescheduled or cancelled without changing patient or visit history.
- Agenda check-in reuses `POST:appointment-checkin` and must preserve duplicate active-queue protection.
- Full calendar grid, recurrence, and reminder messaging are still future scope.

### Smart Exam Stock Safety Behavior
- Smart Exam item ordering shows stock status before adding medicine or supplies.
- Item status uses current batch balance, reorder level, and nearest expiry date.
- Out-of-stock items must be disabled.
- Low-stock and expiring-soon items should remain selectable but visibly flagged.
- Expiry warning threshold should follow `system_settings.expiry_alert_days`.

### Daily Close Behavior
- Dashboard includes a daily close summary for one-operator clinic workflow.
- Daily close shows net paid revenue, receipt count, discounts, payment-method split, latest receipts, and pending queue/payment work.
- Pending queue states should be visible before the clinic closes for the day.
- This is a lightweight operational close, not a full accounting ledger.

### End-of-Day Backup Behavior
- Backup is part of the end-of-day safety ritual, not just an admin export.
- Dashboard should guide the operator to clear pending queue/payment work before using the end-of-day backup action.
- Admin can download `สำรองข้อมูลปิดวัน` from Dashboard when daily close has no pending work.
- Backup SQL files include header metadata for close date, receipt count, paid total, and pending work count.
- Dashboard reads `storage/exports/clinic_backup_*.sql` to show latest backup status.
- If the latest backup file is from today, Dashboard should communicate that backup is already done today.
- If no file exists today, Dashboard should show a calm reminder rather than a disruptive modal.
- Backup retention is file-based: keep the latest 30 `clinic_backup_*.sql` files and remove older matching files after successful backup creation.
- Dashboard should show backup file count and retention limit so the operator understands the local cleanup policy.
- This does not replace external/offsite backup policy; it is the local daily safety step for small clinic operation.

### Receipt Branding Behavior
- Settings can store printable receipt identity fields: `clinic_tax_id`, `receipt_logo_text`, and `receipt_footer`.
- Receipt footer is separate from `queue_note`; `queue_note` remains a fallback for older databases.
- Receipt branding must not change payment lifecycle, receipt numbering, stock movement, or queue completion behavior.
- Existing databases may be patched by `SettingsController::ensureSettingsSchema()` until a formal migration system exists.

### User Audit Behavior
- User create/update/password reset actions write to `audit_logs`.
- Login success, login failure, and logout actions write to `audit_logs`.
- Users page shows the latest user-management audit rows for Admin review.
- Audit logging must not expose password values or password hashes.
- Audit coverage is intentionally partial for now; payment, stock, and settings audit expansion remains future scope.

## Product Doctrine: Medical Workstation Software

This project must be treated as a Medical Workstation, not an admin dashboard, CRUD back office, or SaaS template.

### Core Philosophy
- Speed over decoration.
- Clarity over visual effects.
- Workflow over isolated sections.
- Real clinic operation over generic management screens.
- Compact density over large dashboard whitespace.

### Mindset Shift
Old mindset to avoid:
- dashboard hero blocks
- many independent cards
- CRUD-first module pages
- large decorative sections
- repeated explanatory paragraphs
- UI that looks good but slows the nurse down

New mindset to use:
- medical workstation layout
- operational-first UI
- workflow-driven screens
- sticky summary and finish actions
- compact patient context
- quick actions close to the current task
- progressive disclosure for secondary details

### Product Decision Rule
Before changing any page, AI and developers must ask:
1. Does the user see the next action within 3 seconds?
2. Does this reduce scroll, clicks, or duplicate reading?
3. Does this support the real nurse workflow?
4. Does this keep patient risk, queue state, billing state, and finish state visible?
5. Would this still work well on a 14-inch notebook?

If the answer is no, redesign the interaction rather than adding more cards or explanations.

### Medical Workstation Architecture
Production workstation pages should be organized around:
- Main Working Area: where the user performs the current clinical or operational task.
- Sticky Summary Area: patient identity, risk, totals, readiness, and completion actions.
- Quick Actions: common actions visible near the task, not hidden in menus.
- Compact Side Panel: supporting intake/history/navigation, not a second dashboard.

### Anti-Dashboard Rule
Do not use dashboard layout patterns for clinical work pages. Queue, Smart Exam, Payments, and Visit Detail should not start with large marketing-style heroes or explanatory card walls. These pages should behave like task stations.

### Smart Exam Product Doctrine
Smart Exam is the clinical workstation core. It must feel like one continuous workflow:

`preset -> auto fill -> add services -> add medicine/equipment -> auto summary -> finish case`

Smart Exam must not become:
- a long generic form
- a multi-widget dashboard
- a multi-page wizard
- a patient history screen

Patient history and secondary details should be compact, collapsible, and available only when needed.

### Stabilized Smart Exam Workflow
Smart Exam now prioritizes production speed:
- presets merge clinical text safely instead of overwriting nurse-entered data
- URI is a first-class quick preset for common respiratory cases
- service and medicine order entry support compact search/filter
- Smart Exam service/item add-remove actions support inline JSON updates with normal POST fallback
- Smart Exam preset apply supports inline JSON updates with normal POST fallback
- the summary rail updates counts, lines, totals, readiness, and payment preview after inline order changes
- summary readiness includes clinical completeness, billing readiness, and selected-item stock state
- keyboard shortcuts support repeated nurse workflow without turning the UI into a wizard
- frontend suggestions are assistive only; backend validation remains authoritative for finish, payment, and stock

### Summary Doctrine
Summary is not an afterthought. It is part of the workstation control surface.
- Keep summary sticky on desktop.
- Keep totals and finish readiness visible.
- Do not let long service/item lists push finish actions out of reach.
- On smaller screens, convert summary actions into a clear bottom or near-bottom action area.

### Commercial Software Direction
To scale toward commercial clinic software, future work should standardize reusable workstation components:
- command header
- patient/risk strip
- compact form grid
- quick action group
- order-entry panel
- sticky summary rail
- readiness checklist
- finish/action bar

Any new module should reuse these patterns before inventing a new page style.

## 2026-05-19 - Medical Supply Workstation

- Inventory is now treated as a Medical Supply Workstation, not a form-based admin page.
- The inventory screen should surface stock risk first: low stock, near-expiry lots, expired lots, received quantity today, and estimated stock value.
- Add/receive/adjust workflows should be focused one action at a time through an action panel, not three forms visible at once.
- Stock adjustment must require a reason and must never allow negative batch balance.
- `stock_movements` remains the audit trail for receive, visit usage, import, and manual adjustment.
- Movement history should be visible from the inventory workstation so stock changes are traceable without opening the database.

## 2026-05-19 - Service Management Workstation

- Services are now treated as clinic price-standard management, not a CRUD form.
- The services page should prioritize searchable table management with a secondary detail/edit panel.
- Admin can add, edit by service code, duplicate, and enable/disable services; delete remains avoided because historical `visit_services` must stay traceable.
- Smart Exam and billing depend on active services only for new order entry, while old visit lines keep their captured `unit_price`.
- Price changes must not mutate historical billing rows.

## 2026-05-20 - Medical Cashier Workstation

- Payments is now treated as a cashier workstation, not a payment dashboard.
- The payments page separates active work into waiting payment queue, receipt history, and a sticky financial action rail.
- Cashier workflow prioritizes: find pending case -> verify total/discount -> choose method -> confirm payment -> open receipt.
- Supported payment methods remain the existing schema enum: `CASH`, `TRANSFER`, `QR`.
- Transfer and QR payments auto-fill paid amount to net total; cash payments validate received amount and change.
- No database schema change was made in this phase; refund/card/free payment methods require a future migration.

## 2026-05-23 - Service Workstation Phase 1

- Services are now managed as a Smart Service Builder workflow instead of a plain CRUD screen.
- KPI cards are actionable: show all, filter active, filter inactive, group/sort category, and sort high price.
- The service table is the primary surface with sticky headers, realtime search/filter, selected rows, sortable columns, usage count, revenue, and quick row actions.
- The right panel provides add/edit/duplicate/detail states, live preview, category-prefix code generation, frontend duplicate-code hint, price validation, and smart category suggestions.
- Service usage analytics are read from existing `visit_services`; historical bills keep captured `unit_price` and are not changed by service price edits.
- Admin can export services through `GET:services-export`; no schema change was made.
- Price history, audit log, category management, and bundle/package services remain future scope.

## 2026-05-23 - Service Workstation Phase 2

- Service price changes now write to `service_price_history`.
- Service add/edit, enable/disable, and export actions write service audit rows to existing `audit_logs`.
- Services page right rail shows selected service usage insight, price history, and recent service audit activity.
- Historical visit billing remains protected: `visit_services.unit_price` is still the source for old receipts and old visit totals.
- Runtime schema guard creates `service_price_history` on existing installs until a formal migration system exists.
- Bundle/package services and managed category table remain future scope; they should not be faked without workflow and schema review.

## 2026-05-23 - Pharmacy Sticker Label Phase 1

- Pharmacy label printing is now a dedicated workflow layer connected to Smart Exam medication usage.
- The system must read medicine lines from `visit_item_usages`; it must not create another stock deduction when generating labels.
- `drug_profiles` stores medication label defaults linked to `inventory_items`, while inventory remains the stock and price source of truth.
- `prescriptions` and `prescription_items` snapshot the printable instruction text for a visit.
- `medication_print_logs` records browser-print actions and reprint history.
- Smart Exam medication order entry includes a compact instruction builder that generates Thai medication directions into `visit_item_usages.usage_note`.
- Phase 1 uses browser print with CSS label sizes; direct TSC/Zebra/XPrinter integration remains future scope.

## 2026-05-26 - Clinic Settings Workstation Quick Wins

- Settings is now treated as a configuration workstation, not a long admin form.
- Clinic profile and document-number controls are separated into main and sticky side surfaces.
- HN/receipt previews stay visible near save actions so admins understand the next generated numbers before saving.
- Smart Exam presets are now shown as a compact list with expandable editors instead of all preset forms being open at once.
- No database schema change was made; this is a UI/UX density and workflow pass.
## 2026-05-17 - Import Excel Phase 1

- เพิ่มแนวคิด Data Onboarding สำหรับ Medical Workstation: ห้าม upload แล้วเขียนฐานข้อมูลทันที ต้องผ่าน preview, mapping, validate และ confirm ก่อนเสมอ
- Phase 1 รองรับ 3 ประเภท: ผู้รับบริการ, ยา/เวชภัณฑ์, รับ stock ตั้งต้น
- สิทธิ์: ADMIN ใช้ได้ครบ, NURSE ใช้ได้เฉพาะ import ผู้รับบริการ, CASHIER ไม่เห็นเมนู
- การรับ stock ตั้งต้นต้องสร้าง `inventory_batches` และ `stock_movements` พร้อม `reference_type = EXCEL_IMPORT`
- ใช้ `PhpSpreadsheet` ผ่าน Composer (`phpoffice/phpspreadsheet`) สำหรับอ่าน/สร้าง Excel; หากยังไม่ได้ติดตั้ง หน้า import จะแจ้ง dependency ชัดเจนและรองรับ `.csv` เป็น fallback ชั่วคราว
