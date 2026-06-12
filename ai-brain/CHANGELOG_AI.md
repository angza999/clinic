# Changelog For AI

## Purpose
This file tracks product, workflow, schema, and UI changes in a format that future AI sessions can continue from without losing context.

## Update Rule
Whenever work changes business logic, workflow, schema, validation, UI pattern, or module boundaries:
- add a new entry at the top
- list the edited files
- describe flow impact
- note which AI context files were updated

## Entry Template
```md
## YYYY-MM-DD - Short Title

### Type
- feature | bugfix | refactor | schema | ux | docs

### Summary
- What changed

### Why
- Business or technical reason

### Files
- path/to/file1
- path/to/file2

### Flow Impact
- registration | queue | smart exam | payment | stock | report | settings | users

### Database Impact
- none | describe

### UI Impact
- none | describe

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- DATABASE_SCHEMA.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md
- KNOWN_ISSUES.md
- FUTURE_FEATURES.md
- DECISIONS.md

### Notes
- Risks, follow-up items, or guardrails
```

## Current Notable Changes

## 2026-06-07 - Smart Exam Production Density Polish

### Type
- ux
- bugfix

### Summary
- Reduced Smart Exam header height, patient context wrapping, and clinical card vertical waste.
- Added safer birthdate/age handling for Buddhist Era dates so patient context does not show impossible ages.
- Made clinical/vital layout more production-friendly: two-column clinical/vital composition on wide screens, stacked layout on notebook widths.

### Why
- The Smart Exam screen is the main one-nurse workstation; important clinical inputs should appear faster without header/context layout consuming the working area.

### Files
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`

### Flow Impact
- smart exam

### Database Impact
- none

### UI Impact
- More compact header, more stable left patient context, denser clinical/vital area, less vertical scrolling.

### AI Context Updates Required
- CHANGELOG_AI.md

### Notes
- Business logic and database structure were not changed.

## 2026-05-23 - Service Workstation Phase 2 Price History

### Type
- feature
- schema
- ux
- docs

### Summary
- Added `service_price_history` for service price change traceability.
- Added runtime schema guard for existing installs.
- Service add/edit now records price history when the price changes or a new service is created.
- Service create/update/enable/disable/export actions now write to existing `audit_logs`.
- Services right rail now shows selected usage insight, price history, and recent service audit activity.
- Cleaned remaining mojibake Thai text in service controller/view/JS.

### Why
- Service pricing is a financial control surface; admins need traceability without rewriting historical Smart Exam billing rows.

### Files
- `app/Controllers/ServiceController.php`
- `app/Views/services/index.php`
- `public/assets/js/services.js`
- `public/assets/css/services.css`
- `database/schema.sql`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/DATABASE_SCHEMA.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- services
- smart exam
- payment
- audit

### Database Impact
- adds `service_price_history`
- reuses existing `audit_logs`

### UI Impact
- Price history button is now real and updates the right rail.
- Recent service audit appears as a compact trust panel.

### AI Context Updates Required
- AI_CONTEXT.md
- UI_UX_GUIDE.md
- MODULES.md
- DATABASE_SCHEMA.md
- FUTURE_FEATURES.md
- CHANGELOG_AI.md

### Notes
- Category management and bundle/package services remain future scope.
- Historical visit service rows keep captured `unit_price`.

## 2026-05-23 - Service Workstation Phase 1 Smart Builder

### Type
- feature
- ux
- docs

### Summary
- Made service KPI cards actionable for filtering and sorting.
- Rebuilt Services view with clean Thai text, interactive table rows, usage/revenue analytics, and selected row state.
- Added Smart Service Builder panel with add/edit/duplicate/detail states, live preview, auto code generation, duplicate-code hint, validation, and smart category/price suggestions.
- Added `GET:services-export` for Admin CSV export.

### Why
- The services page needed to move beyond CRUD and support fast clinic price-standard management without changing billing schema.

### Files
- `app/Controllers/ServiceController.php`
- `app/Views/services/index.php`
- `public/index.php`
- `public/assets/js/services.js`
- `public/assets/css/services.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/DATABASE_SCHEMA.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- services
- smart exam
- payment

### Database Impact
- none
- usage analytics are read from existing `visit_services`
- historical bills keep `visit_services.unit_price`

### UI Impact
- Services now behaves like a Service Management Workstation with table-first workflow and secondary builder panel.

### AI Context Updates Required
- AI_CONTEXT.md
- UI_UX_GUIDE.md
- MODULES.md
- DATABASE_SCHEMA.md
- FUTURE_FEATURES.md
- CHANGELOG_AI.md

### Notes
- Price history, audit log, category management, and service bundles are Phase 2 scope.

## 2026-05-17 - Login Audit Trail Phase 18

### Type
- feature
- security
- ux
- docs

### Summary
- Login success, login failure, and logout actions now write to `audit_logs`.
- Users page audit panel now includes authentication activity alongside user-management actions.
- Auth audit detail stores username, role where available, IP address, and user agent, but never stores passwords.

### Why
- Clinic admins should be able to see recent access activity and failed login attempts without database tools.

### Files
- `app/Controllers/AuthController.php`
- `app/Controllers/UserController.php`
- `app/Views/users/index.php`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/MODULES.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/KNOWN_ISSUES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- auth
- users
- audit

### Database Impact
- no schema change

# 2026-05-17 - Smart Exam Inline Preset Apply

### Type
- Smart Exam workflow acceleration
- progressive-enhancement AJAX
- clinical preset UX

### Summary
- added JSON mode to `queue-apply-preset` for Smart Exam preset buttons
- kept normal POST redirect fallback for reliability
- frontend now applies preset results inline without page reload
- updated clinical fields, active preset state, order lines, totals, readiness, and payment preview from the returned payload
- documented inline preset behavior in AI context files

### Files
- `app/Controllers/QueueController.php`
- `public/assets/js/smart-exam.js`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- removes full-page reload from preset apply when JavaScript is available
- preserves backend preset merge, duplicate preset guard, stock validation, and fallback redirect behavior

### Database Impact
- no schema change
- uses existing `audit_logs` table

### UI Impact
- Users page recent audit panel now includes login success, login failure, and logout rows.

### AI Context Updates Required
- AI_CONTEXT.md
- MODULES.md
- UI_UX_GUIDE.md
- FUTURE_FEATURES.md
- KNOWN_ISSUES.md
- CHANGELOG_AI.md

### Notes
- Audit write failures are swallowed so authentication flow remains available if audit logging is temporarily unavailable.
- Payment, stock, and settings audit coverage remains future scope.

## 2026-05-17 - User Audit Trail Phase 17

### Type
- feature
- ux
- docs

### Summary
- User create, update, and password reset actions now write to `audit_logs`.
- Users page shows recent user-management audit history for Admin review.
- Audit details intentionally exclude password values and password hashes.

### Why
- User and permission changes should be traceable before the system is used in production.

### Files
- `app/Controllers/UserController.php`
- `app/Views/users/index.php`
- `public/assets/css/app.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/MODULES.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/KNOWN_ISSUES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- users
- audit
- admin operations

### Database Impact
- no schema change
- uses existing `audit_logs` table

### UI Impact
- Users page adds a compact recent audit history panel.

### AI Context Updates Required
- AI_CONTEXT.md
- MODULES.md
- UI_UX_GUIDE.md
- FUTURE_FEATURES.md
- KNOWN_ISSUES.md
- CHANGELOG_AI.md

### Notes
- Audit write failures are swallowed so user management actions do not fail if audit logging is temporarily unavailable.
- Login, payment, stock, and settings audit coverage remains future scope.

## 2026-05-17 - Receipt Branding Settings Phase 16

### Type
- feature
- schema
- ux
- docs

### Summary
- Added receipt branding settings for clinic tax/register ID, compact text logo mark, and a dedicated receipt footer.
- Receipt page now displays the configurable branding without changing totals, numbering, or payment state.
- Settings page separates receipt footer from queue note while keeping `queue_note` as a fallback for older data.
- Added a runtime settings schema guard until the project has formal migrations.

### Why
- Printed receipts should carry clearer clinic identity and footer text without mixing receipt wording with operational queue notes.

### Files
- `app/Controllers/SettingsController.php`
- `app/Views/settings/index.php`
- `app/Views/payments/receipt.php`
- `public/assets/css/app.css`
- `database/schema.sql`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/DATABASE_SCHEMA.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- settings
- receipt
- payment display

### Database Impact
- adds nullable `clinic_tax_id`, `receipt_logo_text`, and `receipt_footer` to `system_settings`
- existing databases are patched by `SettingsController::ensureSettingsSchema()`

### UI Impact
- Settings gains receipt branding fields.
- Receipt header can show a compact text logo mark and tax/register ID.
- Receipt footer now has a dedicated setting.

### AI Context Updates Required
- AI_CONTEXT.md
- DATABASE_SCHEMA.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- FUTURE_FEATURES.md
- CHANGELOG_AI.md

### Notes
- Image logo upload, full document templates, and multiple print layouts remain future scope.

## 2026-05-16 - Database Setup Guide Phase 15

### Type
- docs

### Summary
- Added a dedicated MySQL/Navicat setup and troubleshooting guide.
- Documented the current local database defaults from `config/database.php`.
- Added fixes for common connection errors such as `using password: NO`, `Unknown database`, and `SQLSTATE[HY000] [2002]`.
- Linked the guide from `README.md`.

### Why
- Local setup problems can block clinic deployment and testing.
- The Navicat error pattern showed the project needs a clear, production-minded setup guide.

### Files
- `docs/database-setup.md`
- `README.md`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/PROJECT_RULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- setup
- database
- support

### Database Impact
- no schema change
- documents connection values and optional user creation SQL

### UI Impact
- none

### AI Context Updates Required
- AI_CONTEXT.md
- PROJECT_RULES.md
- CHANGELOG_AI.md

### Notes
- Do not commit real production database passwords.
- The default local config remains `root` with blank password for XAMPP/Laragon style development.

## 2026-05-16 - Backup Retention Policy Phase 14

### Type
- feature
- ux
- docs

### Summary
- Backup creation now applies a file retention policy after a successful SQL dump.
- The system keeps the latest 30 `clinic_backup_*.sql` files and removes older matching backup files.
- Dashboard backup status now shows current backup file count against the retention limit.
- Backup SQL header includes the active retention policy.

### Why
- Local backup files should not grow indefinitely on clinic computers.
- Operators should see that the system has a simple retention rule without needing to inspect folders manually.

### Files
- `app/Controllers/BackupController.php`
- `app/Controllers/DashboardController.php`
- `app/Views/dashboard/index.php`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/KNOWN_ISSUES.md`

### Flow Impact
- backup
- dashboard
- daily close

### Database Impact
- no schema change
- retention is file-based

### UI Impact
- Dashboard shows backup count and retention limit in the daily close backup indicator.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- FUTURE_FEATURES.md
- KNOWN_ISSUES.md
- CHANGELOG_AI.md

### Notes
- Cleanup only targets files matching `clinic_backup_*.sql` in the export directory.
- Persistent backup audit logs remain future work.

## 2026-05-16 - Backup History Indicator Phase 13

### Type
- feature
- ux
- docs

### Summary
- Dashboard now reads the latest SQL backup file from `storage/exports`.
- Daily close panel shows whether a backup was created today.
- The backup status displays latest backup time, filename, and file size.

### Why
- A one-operator clinic needs a visible reminder that backup was completed before closing the clinic.
- This reduces reliance on memory without adding a separate backup history database table yet.

### Files
- `app/Controllers/DashboardController.php`
- `app/Views/dashboard/index.php`
- `public/assets/css/dashboard.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- dashboard
- backup
- daily close

### Database Impact
- no schema change
- reads backup files from `storage/exports`

### UI Impact
- Adds compact backup status inside the daily close action area.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- CHANGELOG_AI.md

### Notes
- This is file-based history only; a persistent backup log table remains a future enhancement.

## 2026-05-16 - End-of-Day Backup Safety Phase 12

### Type
- feature
- ux
- docs

### Summary
- Dashboard daily close now includes an end-of-day action area.
- If queue/payment work remains, the primary action sends the operator to clear pending work before backup.
- If no work remains and the user is Admin, Dashboard exposes a confirmed `สำรองข้อมูลปิดวัน` action.
- SQL backup files now include daily-close metadata in the dump header: generated time, close date, receipt count, paid total, and pending work count.

### Why
- A one-nurse clinic needs a safe, obvious final step before closing the clinic and turning off the computer.
- Backup should be treated as part of the daily close workflow, not a hidden report utility.

### Files
- `app/Controllers/BackupController.php`
- `app/Views/dashboard/index.php`
- `public/assets/css/dashboard.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- dashboard
- reports
- backup
- queue
- payment

### Database Impact
- no schema change
- backup reads existing `payments` and `queue_entries` for metadata

### UI Impact
- Dashboard daily close panel now has a final action block for clearing pending work, opening reports, or downloading end-of-day backup.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- CHANGELOG_AI.md

### Notes
- This is still a direct SQL dump download; retention, backup history, and scheduled/background backup remain future work.

## 2026-05-16 - Daily Close Summary Phase 11

### Type
- feature
- ux
- docs

### Summary
- Dashboard now includes a daily close summary panel.
- Shows net paid revenue, receipt count, discount total, payment-method split, pending work checklist, and latest receipts.
- Dashboard close status changes when queue/payment work remains.

### Why
- A one-nurse clinic needs a quick operational close view before ending the day.

### Files
- `app/Controllers/DashboardController.php`
- `app/Views/dashboard/index.php`
- `public/assets/css/dashboard.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- dashboard
- payment
- queue
- reports

### Database Impact
- no schema change
- reads existing `payments`, `visits`, `patients`, and `queue_entries`

### UI Impact
- Dashboard gains an end-of-day operational close panel.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md

### Notes
- This is not a formal accounting ledger.
- Keep deeper financial reports in the Reports module.

## 2026-05-16 - Smart Exam Stock Safety Phase 10

### Type
- feature
- ux
- docs

### Summary
- Smart Exam item queries now include reorder level and nearest batch expiry.
- Frequent medicine/supply buttons show stock safety badges.
- Item dropdown includes stock status and expiry information.
- Out-of-stock items remain disabled before submit.

### Why
- Nurses should see stock risk at the point of ordering, not only inside the inventory module.

### Files
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- stock
- inventory

### Database Impact
- no schema change
- reads existing `inventory_items.reorder_level` and `inventory_batches.expiry_date`

### UI Impact
- Smart Exam medicine/supply shortcuts show `พร้อมใช้`, `ใกล้หมด`, `ใกล้หมดอายุ`, or `หมด`.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md

### Notes
- Only out-of-stock blocks ordering.
- Low stock and expiring soon are warnings, not blockers.

## 2026-05-16 - Receipt Care Instructions Phase 9

### Type
- feature
- ux
- docs

### Summary
- Receipt query now includes visit advice and follow-up date.
- Receipt page shows a printable after-care instruction panel when advice or follow-up exists.
- Receipt remains clean when no after-care data exists.

### Why
- In a one-nurse clinic, the printed receipt can also serve as the patient handout for home-care instructions and next appointment date.

### Files
- `app/Controllers/PaymentController.php`
- `app/Views/payments/receipt.php`
- `public/assets/css/app.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- payment
- receipt
- appointments

### Database Impact
- no schema change
- reads existing `visits.advice` and `visits.followup_date`

### UI Impact
- Receipt page gains a print-friendly after-care panel only when data exists.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md

### Notes
- Do not show empty care panels.
- Keep receipt print-friendly and avoid turning it into a full clinical note.

## 2026-05-14 - Appointment Check-In Phase 8

### Type
- feature
- ux
- docs

### Summary
- Queue page now shows scheduled appointments due today or overdue.
- Added appointment check-in route to create a visit and queue from an appointment.
- Check-in redirects directly to Smart Exam.
- System reuses an existing active queue for the patient if one already exists today.

### Why
- Follow-up appointments should become active exam cases without the nurse manually searching and creating a queue.

### Files
- `public/index.php`
- `app/Controllers/QueueController.php`
- `app/Views/queue/index.php`
- `public/assets/css/queue.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- appointments
- queue
- smart exam

### Database Impact
- no schema change
- updates existing `appointments.status` and `appointments.visit_id`
- creates `visits` and `queue_entries` through existing workflow

### UI Impact
- Queue page has a compact due appointment panel above patient search.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md

### Notes
- Appointment check-in is not a full appointment calendar.
- Avoid duplicate active queues for the same patient on the same day.

## 2026-05-14 - Follow-Up Appointment Sync Phase 7

### Type
- feature
- ux
- docs

### Summary
- Added advice and follow-up date fields to Smart Exam.
- Smart Exam finish now syncs `visits.followup_date` into `appointments`.
- Advanced visit clinical save also syncs follow-up appointments to prevent duplicate scheduled rows.
- Clearing follow-up date removes scheduled appointments linked to the visit.

### Why
- A one-nurse clinic needs follow-up scheduling to happen during case closure, not in a separate admin workflow.

### Files
- `app/Controllers/QueueController.php`
- `app/Controllers/VisitController.php`
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- appointments
- patients
- dashboard

### Database Impact
- no schema change
- writes existing `appointments` rows during Smart Exam finish and advanced visit save

### UI Impact
- Smart Exam now includes a compact advice/follow-up scheduling section.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md

### Notes
- This is appointment sync, not a full calendar module.
- One visit should have at most one scheduled follow-up appointment from this workflow.

## 2026-05-14 - Patient Snapshot Phase 6

### Type
- feature
- ux
- docs

### Summary
- Added compact patient snapshot inside Smart Exam.
- Snapshot shows allergy, underlying disease, visit count, prior unpaid cases, latest historical vitals, upcoming appointments, and recent treatment visits.
- Added a link from Smart Exam to the full patient history page.
- Kept recent visits collapsed by default to reduce cognitive load.

### Why
- In a one-nurse clinic, the operator needs key historical context without leaving the exam screen.

### Files
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- patients

### Database Impact
- none

### UI Impact
- Smart Exam now has a compact read-only patient snapshot above the main clinical workflow.

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- MODULES.md
- SMART_EXAM_LOGIC.md

### Notes
- Snapshot must stay compact and should not replace full patient history.
- Do not auto-copy old clinical text into current visits without explicit nurse action.

## 2026-05-14 - Configurable Smart Presets Phase 5

### Type
- feature
- schema
- ux
- docs

### Summary
- Added `smart_exam_presets` table for database-backed Smart Exam presets.
- Settings page now shows editable Smart Exam preset forms for Admin.
- Smart Exam reads active presets from database before falling back to legacy hardcoded presets.
- Default presets are seeded automatically when the preset table is empty.
- Backup export includes `smart_exam_presets`.

### Why
- Clinic operators should be able to adjust common Smart Exam workflows without changing PHP code.

### Files
- `public/index.php`
- `app/Controllers/SettingsController.php`
- `app/Controllers/QueueController.php`
- `app/Controllers/BackupController.php`
- `app/Views/settings/index.php`
- `database/schema.sql`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/DATABASE_SCHEMA.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- settings
- backup

### Database Impact
- new table `smart_exam_presets`

### UI Impact
- settings page includes preset editor cards

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- DATABASE_SCHEMA.md
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- MODULES.md
- CHANGELOG_AI.md

### Notes
- Preset keys must remain stable.
- Service and item code validation is still operational/manual; invalid codes will fail during preset application.

## 2026-05-14 - Quick Register Phase 4

### Type
- feature
- ux
- docs

### Summary
- Added quick registration directly inside the Queue page.
- Added `POST:queue-quick-register` to create patient, visit, and queue in one workflow.
- Quick register redirects directly to Smart Exam.
- Added duplicate guard using phone/name with explicit override checkbox.
- Added frontend duplicate hint against loaded patient options.

### Why
- For a one-nurse clinic, new walk-in intake should not require leaving queue, creating a patient, returning to queue, then opening Smart Exam manually.

### Files
- `public/index.php`
- `app/Controllers/QueueController.php`
- `app/Views/queue/index.php`
- `public/assets/js/queue.js`
- `public/assets/css/queue.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/MODULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- registration
- queue
- smart exam handoff

### Database Impact
- none
- writes to existing `patients`, `visits`, and `queue_entries`

### UI Impact
- queue page now includes compact new-patient registration
- duplicate warning appears near the quick register form

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- MODULES.md
- CHANGELOG_AI.md

### Notes
- Quick register is not a replacement for full patient demographics.
- Keep duplicate prevention conservative and visible.

## 2026-05-14 - Queue Continuation Phase 3

### Type
- feature
- ux
- docs

### Summary
- Receipt return action now sends the operator back to queue with `from_receipt=1`.
- Queue page shows a continuation panel after returning from receipt.
- Next waiting queue can be called and opened in Smart Exam with one action.
- Added queue keyboard shortcuts: `Alt+N` for next case and `Alt+S` for patient search.

### Why
- In a one-operator clinic, the nurse should move from receipt to the next Smart Exam case without extra page hunting or duplicate clicks.

### Files
- `app/Views/payments/receipt.php`
- `app/Views/queue/index.php`
- `public/assets/js/queue.js`
- `public/assets/css/queue.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- receipt
- queue
- smart exam handoff

### Database Impact
- none

### UI Impact
- queue page now has a post-receipt continuation state
- keyboard-first queue operation is supported

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- CHANGELOG_AI.md

### Notes
- `Alt+N` should only trigger when a next-case action exists.
- Keep the continuation card lightweight; it should not replace the queue board.

## 2026-05-14 - Receipt Handoff Phase 2

### Type
- feature
- ux
- docs

### Summary
- Smart Exam inline payment now redirects directly to the receipt page after successful payment.
- Receipt page supports `source=smart_exam` and shows a post-case action panel.
- Nurse role can view receipts for one-operator clinic workflow.
- Standalone payment completion also routes to the receipt page before returning to payment history.

### Why
- A single nurse should be able to receive payment, print receipt, and return to the queue without hunting through the finance module.

### Files
- `app/Controllers/QueueController.php`
- `app/Controllers/PaymentController.php`
- `app/Views/payments/receipt.php`
- `public/assets/css/app.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- payment
- receipt printing
- queue return

### Database Impact
- none
- uses existing `payments.id` to route receipt handoff

### UI Impact
- receipt page now prioritizes print receipt and return to queue
- payments page link is visible only for Admin/Cashier

### AI Context Updates Required
- AI_CONTEXT.md
- SYSTEM_FLOW.md
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- CHANGELOG_AI.md

### Notes
- Do not auto-print by default.
- Receipt access for Nurse is intentional in one-operator clinic mode.

## 2026-05-13 - Smart Exam Inline Payment Phase 1

### Type
- feature
- ux
- docs

### Summary
- added compact payment fields directly to the Smart Exam summary panel
- added `receive_payment` finish mode to record payment and close the case from Smart Exam
- kept `waiting_payment` as a secondary path for delayed payment
- added frontend net total, change, and paid-amount validation

### Why
- one-operator clinics should finish most paid cases from Smart Exam without switching to the payment workspace

### Files
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/js/smart-exam.js`
- `public/assets/css/smart-exam.css`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- payment
- queue completion

### Database Impact
- none
- uses existing `payments` table and receipt running number logic

### UI Impact
- Smart Exam now exposes payment method, discount, paid amount, net total, and change in the right summary panel
- primary paid-case action is now `รับเงินและปิดเคส`
- delayed payment remains available as `บันทึกรอชำระ`

### AI Context Updates Required
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- CHANGELOG_AI.md

### Notes
- keep the standalone payments page as backlog/history for `WAITING_PAYMENT` and receipts

## 2026-05-13 - Payment Access Guardrail In UI

### Type
- bugfix
- ux
- docs

### Summary
- removed payment navigation and dashboard shortcuts for roles that cannot open the payment workspace
- changed unauthorized page access from raw `403` text to a flash message with redirect back to the role-appropriate home page
- kept `WAITING_PAYMENT` visible to nurses as status text without exposing a forbidden link

### Why
- nurses were still able to click `payments` from some UI entry points and hit a plain 403 page, which feels broken and unprofessional in production

### Files
- `app/Core/Auth.php`
- `app/Views/layouts/app.php`
- `app/Views/dashboard/index.php`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- dashboard
- payment
- role permission

### Database Impact
- none

### UI Impact
- payment links are now shown only to `ADMIN` and `CASHIER`
- nurse dashboard shows `รอการเงิน` as a non-clickable state instead of a forbidden shortcut

### AI Context Updates Required
- CHANGELOG_AI.md

### Notes
- keep route-level permission checks in controllers; UI hiding is only the first layer

## 2026-05-13 - Smart Exam Phase 3 Professional Polish

### Type
- ux
- feature
- docs

### Summary
- switched Smart Exam to compact topbar mode so the page reads like a workstation, not a duplicate hero page
- added compact patient identity chips for queue, HN, and VN
- surfaced drug allergy state in both the active case panel and summary panel
- added keyboard progression for vitals, clinical textareas, service entry, medicine entry, and finish handoff
- refreshed AI context docs for the new Smart Exam behavior

### Why
- Smart Exam is a high-frequency nurse workflow and needs stronger scan speed, less pointer dependence, and a more professional medical-system feel

### Files
- `app/Controllers/QueueController.php`
- `app/Views/layouts/app.php`
- `app/Views/queue/exam.php`
- `public/assets/css/app.css`
- `public/assets/css/smart-exam.css`
- `public/assets/js/smart-exam.js`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- nurse workflow

### Database Impact
- none

### UI Impact
- Smart Exam now uses compact workstation context in the topbar
- patient identity and allergy state are more visible above the fold
- keyboard-first progression is available for frequent form actions

### AI Context Updates Required
- UI_UX_GUIDE.md
- SMART_EXAM_LOGIC.md
- CHANGELOG_AI.md

### Notes
- This phase focuses on professional polish and operator speed, not new billing or clinical business rules

## 2026-05-13 - Smart Exam Phase 2 Workflow Guidance

### Type
- ux
- feature
- docs

### Summary
- added readiness checklist before finishing Smart Exam
- made step bar reflect `active`, `complete`, and `idle` states
- highlighted the latest preset after redirect
- disabled finish actions when the workflow is not actually ready
- added selected state for Smart Preset helper buttons

### Why
- Nurses should not have to infer readiness from scattered sections
- The screen needed to show the next action clearly

### Files
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/js/smart-exam.js`
- `public/assets/css/smart-exam.css`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- nurse workflow

### Database Impact
- none

### UI Impact
- summary panel now shows readiness before finish
- preset feedback after redirect is clearer
- finish actions are safer because they reflect actual page state

### AI Context Updates Required
- SMART_EXAM_LOGIC.md
- UI_UX_GUIDE.md
- CHANGELOG_AI.md

### Notes
- This phase added UI guidance and state handling, not backend auto-order behavior

## 2026-05-13 - Smart Exam Phase 1 Compact Layout

### Type
- ux
- docs

### Summary
- reduced top-of-page height and removed duplicate hero behavior
- converted service presets into compact tiles
- merged Smart Preset helpers into the clinical block
- tightened Smart Exam spacing and reduced card density
- narrowed the sidebar slightly to recover workspace width

### Why
- The original Smart Exam consumed too much vertical space and felt closer to a prototype dashboard than a production clinic screen

### Files
- `app/Views/queue/exam.php`
- `public/assets/css/smart-exam.css`
- `public/assets/css/app.css`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- smart exam
- nurse workflow

### Database Impact
- none

### UI Impact
- less scrolling on notebook screens
- denser clinical workspace
- faster preset and summary scanning

### AI Context Updates Required
- UI_UX_GUIDE.md
- CHANGELOG_AI.md

### Notes
- No business logic changes were introduced in this phase

## 2026-05-13 - Development Checklist Integration

### Type
- docs
- process

### Summary
- added `ai-brain/DEVELOPMENT_CHECKLIST.md`
- linked checklist usage into project context and rules
- defined context update as part of done criteria

### Why
- The project must stay usable across many AI sessions without context drift

### Files
- `ai-brain/DEVELOPMENT_CHECKLIST.md`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/PROJECT_RULES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- docs
- process

### Database Impact
- none

### UI Impact
- none

### AI Context Updates Required
- AI_CONTEXT.md
- PROJECT_RULES.md
- CHANGELOG_AI.md

### Notes
- Every future feature should use the checklist before closing the task

## 2026-05-12 - Smart Exam Flow Stabilization

### Type
- bugfix
- ux
- schema

### Summary
- fixed add/remove service redirect from Smart Exam back to `queue-exam`
- fixed nurse finish flow so it returns to queue instead of forcing payment page navigation
- connected `resp_rate` in visit detail and Smart Exam
- fixed PDO named placeholder duplication issues affecting finish logic

### Why
- Smart Exam is the operational core for nurse workflow and regressions here immediately affect real use

### Files
- `app/Controllers/VisitController.php`
- `app/Controllers/QueueController.php`
- `app/Controllers/UserController.php`
- `app/Views/visits/edit.php`

### Flow Impact
- smart exam
- payment handoff
- visit detail

### Database Impact
- no new schema added in this round
- existing `resp_rate` usage became fully wired into the workflow

### UI Impact
- visit detail shows `Resp`
- Smart Exam redirect behavior is stable

### AI Context Updates Required
- SMART_EXAM_LOGIC.md
- CHANGELOG_AI.md

### Notes
- Avoid reintroducing duplicate named placeholders in PDO statements
# 2026-05-16 - Appointment Agenda Module

### Type
- feature
- workflow
- ux
- docs

### Summary
- added a dedicated Appointment Agenda page for Admin/Nurse
- added create, reschedule/edit, and cancel workflows for scheduled appointments
- connected agenda rows to existing appointment check-in and active queue reuse
- added appointment navigation, page styling, and context documentation

### Why
- Follow-up appointments already existed, but operators needed a single place to manage upcoming appointments outside the queue due-today panel.

### Files
- `app/Controllers/AppointmentController.php`
- `app/Views/appointments/index.php`
- `public/assets/css/appointments.css`
- `public/index.php`
- `app/Views/layouts/app.php`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/MODULES.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/FUTURE_FEATURES.md`
- `ai-brain/KNOWN_ISSUES.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- appointment scheduling
- appointment check-in
- queue intake

### Database Impact
- no schema change
- uses existing `appointments` table

### UI Impact
- adds agenda filters, status summary, appointment cards, inline edit controls, and create form
- Admin/Nurse can manage appointments without using patient history as the primary appointment surface

### AI Context Updates Required
- AI_CONTEXT.md
- MODULES.md
- SYSTEM_FLOW.md
- UI_UX_GUIDE.md
- FUTURE_FEATURES.md
- KNOWN_ISSUES.md
- CHANGELOG_AI.md

### Notes
- Calendar grid/reminder workflow remains future scope.
- Syntax checked with `C:\xampp\php\php.exe -l`.
- Manual HTTP smoke test confirmed login and `GET:appointments` render.

# 2026-05-17 - Medical Workstation Product Doctrine

### Type
- product direction
- ux architecture
- documentation

### Summary
- reframed the project from admin dashboard / CRUD management toward Medical Workstation Software
- documented operational-first UI, compact workflow, and workflow-first design principles
- added workstation layout rules for command header, main working area, sticky summary, quick actions, and compact side panels
- clarified Smart Exam and Queue as clinical/operational workstations rather than dashboards

### Why
- The product needs to scale toward production EMR/HIS behavior and should prioritize real nurse workflow, speed, reduced scroll, reduced cards, and lower cognitive load over decorative UI.

### Files
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- no backend workflow changed
- future UI decisions must preserve intake -> queue -> Smart Exam -> services/items -> summary/payment -> receipt/next case

### Database Impact
- no schema change

### UI Impact
- establishes Medical Workstation as the governing UI direction
- forbids dashboard hero/card-wall patterns on task-heavy clinical pages
- requires compact density, sticky summary, visible next actions, and progressive disclosure for secondary details

### AI Context Updates Required
- AI_CONTEXT.md
- UI_UX_GUIDE.md
- SYSTEM_FLOW.md
- CHANGELOG_AI.md

### Notes
- This is a product/UX architecture update only. No application runtime files were changed in this entry.

# 2026-05-17 - Workstation Phase 5 Tightening

### Type
- UI/UX implementation
- clinical workstation density
- workflow refinement

### Summary
- tightened Smart Exam into a two-zone working surface plus sticky summary rail
- reduced duplicate Queue dashboard signals by making the command bar the primary status/action surface
- made Visit Detail behave more like a compact clinical review workstation
- reduced helper text, card height, repeated status blocks, and vertical scroll pressure
- improved desktop density while preserving responsive one-column fallbacks

### Files
- `public/assets/css/smart-exam.css`
- `public/assets/css/app.css`
- `ai-brain/CHANGELOG_AI.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SYSTEM_FLOW.md`

### Flow Impact
- no backend workflow changed
- Smart Exam still follows preset -> vitals/clinical -> services/items -> summary/payment -> finish
- Queue now treats the command bar as the primary operational surface instead of duplicating status cards

### UI Impact
- Smart Exam form uses a compact workstation grid on desktop
- summary rail remains visible and action-focused
- Queue status strip is hidden when the command bar already exposes the same state
- Visit Detail panels, lists, vitals, and action rail are more compact for 14-inch notebook use

# 2026-05-17 - Stabilize Smart Exam Workflow

### Type
- Smart Exam workflow stabilization
- clinical preset intelligence
- workstation UX refinement

### Summary
- added URI as a first-class Smart Exam preset
- made preset clinical text merge safely without overwriting existing nurse-entered CC/PI/PE/Dx/advice
- ensured missing default database presets are inserted without overwriting edited presets
- aligned preset item stock movements with manual item usage traceability
- added compact service/medicine search fields inside existing order-entry panels
- added frontend stock guard for selected medicine quantity
- expanded summary readiness with stock state
- added keyboard shortcuts for search/order/finish flow

### Files
- `app/Controllers/QueueController.php`
- `app/Views/queue/exam.php`
- `public/assets/js/smart-exam.js`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- preserves single-screen Smart Exam workflow
- no queue status transition changes
- no payment lifecycle changes
- stock validation remains server-authoritative

### Database Impact
- no schema change
- existing `smart_exam_presets` receives missing default preset rows only; existing preset edits are not overwritten

# 2026-05-17 - Smart Exam Inline Order Entry

### Type
- Smart Exam workflow acceleration
- progressive-enhancement AJAX
- summary rail synchronization

### Summary
- added JSON responses to existing visit service/item add-remove endpoints when requested by Smart Exam JavaScript
- kept normal POST redirect fallback for reliability
- updated Smart Exam service/item forms to submit inline without full page reload
- refreshed main order lists and sticky summary rail counts, lines, totals, readiness, and payment preview after each add/remove
- added compact loading state for inline order forms

### Files
- `app/Controllers/VisitController.php`
- `app/Views/queue/exam.php`
- `public/assets/js/smart-exam.js`
- `public/assets/css/smart-exam.css`
- `ai-brain/AI_CONTEXT.md`
- `ai-brain/SYSTEM_FLOW.md`
- `ai-brain/UI_UX_GUIDE.md`
- `ai-brain/SMART_EXAM_LOGIC.md`
- `ai-brain/CHANGELOG_AI.md`

### Flow Impact
- reduces reload interruption in Smart Exam order entry
- preserves server-side stock validation and existing queue/payment lifecycle
- keeps summary rail as the operational control surface

### Database Impact
- no schema change
## 2026-05-17

- Added Import Excel Phase 1 architecture and implementation.
- Added `ImportController`, Import Wizard view, CSS/JS, routes, sidebar entry, Composer dependency metadata, import storage folder, and database import log schema.
- Supported Phase 1 import types: patients, inventory items, and initial inventory batches.
- Added strict preview -> mapping -> validate -> confirm workflow with duplicate detection and transaction-based confirm import.

## 2026-05-18 - Smart Card Patient Photo Storage

- Added patient card photo storage using `patients.photo_path`.
- Decoded smart-card base64 image payloads and saved image files under `storage/patient-photos/`.
- Added protected `patient-photo` route for serving patient photos without exposing direct storage paths.
- Registration now stores card photo for new patients, and smart-card reads update the photo for existing patients when a fresh card image is available.
- Patient profile and Smart Exam header now show the stored card photo with a professional placeholder fallback.

## 2026-05-18 - Queue Workstation Reference UI Pass

- Refined Queue Workstation toward a professional Medical Clinic Software reference layout.
- Changed queue status from simple pills into compact status cards in the top command surface.
- Strengthened the current/next working case area with HN/VN context and clearer hierarchy.
- Tightened the left intake surface, center active-case workspace, compact 4-column queue board, and sticky right control rail.
- Added left intake shortcuts for smart-card patient lookup and Excel import.
- Added a low-noise shortcut/tip bar for fast queue operation.
- No database or queue lifecycle logic changes.

## 2026-05-18 - Queue Workstation Duplication Reduction

- Reduced duplicate visual emphasis between the top command bar and the center Smart Exam launcher.
- Kept the center workspace as the primary Smart Exam action and changed the top Smart Exam link into a lighter quick access.
- Simplified the smart-card intake shortcut label to reduce overlap with the patient registration page.
- Added a right-rail readiness checklist for registration, Smart Exam progress, billable lines, and payment readiness.
- No database or queue lifecycle logic changes.

## 2026-05-19 - Medical Supply Workstation

- Refactored inventory from a three-form page into a Medical Supply Workstation.
- Added KPI cards for total items, low stock, near expiry, expired lots, stock value, and received quantity today.
- Added command bar with realtime search, status/type filters, barcode-ready search wording, action tabs, and Excel import shortcut.
- Added compact inventory table with status badges and row quick actions for receive, adjust, and history.
- Added right-side Alert Center and focused action panels so only one admin task is visible at a time.
- Added movement history panel from existing `stock_movements`.
- Strengthened manual stock adjustment validation by requiring a reason and preserving negative-stock blocking.
- Added `public/assets/css/inventory.css` and `public/assets/js/inventory.js`.
- Database impact: no schema change.

## 2026-05-19 - Service Management Workstation

- Refactored Services from a CRUD form/table into a Service Management Workstation.
- Added KPI cards for total services, active/inactive services, categories, and average price.
- Added realtime service search, category/status filters, sortable table headers, and row selected state.
- Added quick actions for detail, edit, duplicate, and enable/disable.
- Added right-side detail/edit panel with auto code suggestion by category prefix.
- Added server-side price validation to block negative or non-numeric prices.
- Added `POST:services-toggle` for safe enable/disable instead of deleting service records.
- Added `public/assets/css/services.css` and `public/assets/js/services.js`.
- Database impact: no schema change.

## 2026-05-20 - Medical Cashier Workstation

- Refactored Payments from a static dashboard into a Medical Cashier Workstation.
- Added compact KPI command surface, receipt/search control bar, payment queue, receipt history, sticky financial action rail, and shortcut bar.
- Added `public/assets/js/payments.js` for realtime search/filter, keyboard shortcuts, payment preview, cash change calculation, and confirm-on-submit.
- Strengthened server-side payment validation for allowed methods, numeric discount/paid amount, discount <= gross total, and cash received amount >= net total.
- Transfer and QR now auto-normalize paid amount to net total on the server.
- Database impact: no schema change.

## 2026-05-23 - Pharmacy Sticker Label Phase 1

- Added `PharmacyController` with prescription sync, label preview, and print-log recording.
- Added `drug_profiles`, `prescriptions`, `prescription_items`, and `medication_print_logs` schema definitions.
- Added `GET:pharmacy-labels` and `POST:pharmacy-print-log` routes.
- Added Smart Exam medication instruction builder for dose, unit, frequency, timing, and note.
- Added Smart Exam summary action to open medication sticker printing.
- Added browser-print label preview for 58x40, 80x50, and 100x75 mm.
- Stock and payment logic are unchanged; labels read from existing `visit_item_usages`.

## 2026-05-26 - Settings Workstation Quick Wins

- Refactored Settings into a compact Clinic Configuration Workstation.
- Replaced the large explanatory hero with a compact command strip and quick anchors.
- Split clinic profile and document-number settings into balanced surfaces with a sticky preview/save rail.
- Converted Smart Exam preset management into a scannable expandable list so only one preset editor needs to be open at a time.
- Reduced textarea heights, card padding, and visible duplicate preview blocks.
- Database impact: no schema change.

## 2026-05-26 - Pharmacy Sticker Label Phase 2

- Added Pharmacy Workstation route and sidebar entry.
- Added print queue surface derived from prescriptions, prescription items, and medication print logs.
- Added recent print/reprint visibility for pharmacy label operations.
- Added drug master/profile table with search/filter and selected row state.
- Added sticky drug profile editor for label short name, category, default instruction, warning text, and active state.
- Added `public/assets/js/pharmacy-workstation.js` for click-to-edit, profile filtering, and live instruction preview.
- Database impact: no schema change beyond existing Pharmacy Phase 1 tables.
## 2026-06-03 - Production Readiness Phase

- Added Admin-only Production Readiness workstation at `?page=production`.
- Added smoke JSON route `?page=production-smoke` and CLI smoke script `tools/smoke-check.php`.
- Expanded backup coverage to include import logs, service price history, pharmacy/prescription tables, medication print logs, and backup logs.
- Added `backup_logs` schema to `database/schema.sql` and existing-install migration `database/production_readiness.sql`.
- Added `tools/apply-production-readiness.php` to apply the production readiness migration from the project runtime.
- Backup downloads now record backup history and an audit action `BACKUP_CREATED`.
- Added deployment, smart-card/printer, and privacy/security documentation:
  - `docs/PRODUCTION_DEPLOYMENT.md`
  - `docs/SMART_CARD_PRINTER_CHECKLIST.md`
  - `docs/DATA_PRIVACY_SECURITY.md`
- Added sidebar entry `ตรวจระบบใช้งานจริง` for Admin.
- Database impact: new `backup_logs` table for existing installations via migration.

## 2026-06-05 - Single Nurse Queue Workstation

- Refactored `GET:queue` around a one-nurse clinic flow: compact today status, start case panel, active case surface, compact queue board, and assistant rail.
- Removed the duplicated queue hero/status strip/second board from the Queue view.
- Added controller-side lightweight queue context for today revenue, exam completion count, low-stock count, pending cases, and latest backup status.
- Replaced the static right summary with a Single Nurse Assistant Rail showing next action, today snapshot, current case readiness, alerts, and shortcuts.
- Added keyboard shortcuts for queue operation: F1 search, F2 quick/new patient, F3 Smart Exam, F4 payment, F5 payments/receipts, F6 medication labels.
- Database impact: no schema change.

## 2026-06-05 - Queue Workstation Production UX Refactor

- Removed duplicate top summary metrics so the top bar now focuses on four queue states only.
- Moved patient totals, completed exams, revenue, and average case time into Today Snapshot.
- Made Queue Board behave as a workflow selector: clicking a queue card selects the active visit and highlights it before opening Smart Exam.
- Reduced queue card content to queue number, patient name, wait time, and automatic wait-priority for faster scan speed.
- Added full-card wait-priority coloring: normal, caution, warning, and critical based on queue age.
- Added Next Patient widget above Active Case with call-only and call-and-open-Smart-Exam actions.
- Added Queue Aging Alert for queues waiting over 30 and 60 minutes.
- Added compact workflow timeline inside Active Case.
- Added Quick Patient Preview fields in Active Case: allergy, chronic disease, visit count, queue duration, and billing summary.
- Added status-driven Next Action labels for waiting, in-service, waiting-payment, and completed cases.
- Added Financial Snapshot and Recent Activity to the right rail.
- Expanded operational alerts for low stock, waiting payment, smart-card bridge, printer readiness, backup today, and cases stuck over 30 minutes.
- Updated keyboard shortcuts to F1 search, F2 new patient, F3 Smart Exam, F4 service work, F5 call next queue, and F9 payment.
- Database impact: no schema change.

## 2026-06-05 - Queue Workstation Version 2

- Consolidated the right rail into one compact Today Status panel with daily overview, finance, critical alerts, latest work, activity summary, backup reminder, and shortcuts.
- Added `POST:queue-close-next` so a paid/no-charge active case can be completed and the next waiting queue can be promoted automatically.
- Added guardrails for close-next: waiting cases must open Smart Exam first, unpaid billable cases route toward finance, and overlapping active cases are blocked.
- Added patient history quick view in Active Case for recent visits, Dx/complaint, services, items, and payment total.
- Made medication sticker printing contextual in Active Case; it appears only when the case has medicine/item usage.
- Added queue transition, case close-next, Smart Exam finish, and medication label print audit logging through existing `audit_logs`.
- Updated queue JavaScript to prevent double-submit on queue status and close-next forms.
- Database impact: no schema change.

## 2026-06-05 - Queue Workstation Final Production Refactor

- Reduced the live Queue Board from four columns to three: waiting, in service, and waiting payment.
- Moved completed cases into a History tab so the live board focuses only on work that still needs action.
- Expanded Queue cards to show queue number, patient name, HN, wait time, and priority without using HN/VN-heavy detail blocks.
- Rebalanced Active Case as the primary clinical surface with Patient Safety Banner, compact case facts, workflow timeline, event timeline, one primary next action, sticker shortcut, and patient history drawer.
- Removed duplicated service/item/total summary by merging those values into the active preview row.
- Simplified Today Status alerts to critical queue blockers only: aged queues, smart-card bridge offline, and printer offline. Backup reminder is no longer shown in the queue rail.
- Renamed the left recent list toward Recent Search to reduce duplication with Queue Board.
- Verified `GET:queue` at 1366x768 and 1920x1080 with no horizontal overflow and no SQLSTATE error.
- Database impact: no schema change.

## 2026-06-05 - Smart Exam Progressive Disclosure

- Added a left Patient Context surface to Smart Exam with patient photo, name, HN/VN, age, gender, phone, allergy, chronic disease, visit count, latest history, latest medication, and full patient file link.
- Rebalanced Smart Exam into three surfaces: Patient Context, Clinical Work, and Sticky Summary.
- Reduced duplicate clinical context by hiding the center duplicate Active Case and Patient Snapshot blocks on the Smart Exam page.
- Added a Smart Template dropdown for faster preset selection and moved advanced quick buttons into a collapsible section.
- Reduced Smart Exam visual weight by hiding repeated helper text, compressing card padding, and lowering textarea height.
- Verified PHP syntax, JavaScript syntax, and diff whitespace checks.
- Database impact: no schema change.

## 2026-06-06 - Case History

- Refactored `visit-edit` from a treatment workflow page into a read-only-first "ประวัติเคส" page.
- Removed the visible clinical workflow form, nursing templates, service add/remove controls, medicine/equipment add/remove controls, payment handoff button, and case finish workflow from the page.
- Added Patient Summary, current clinical snapshot, Visit Timeline, Service History, Drug History, Payment History, and Audit Log sections.
- Updated `VisitController::edit()` to load review histories and audit rows instead of active service/item master data for order entry.
- Added responsive review styling in `public/assets/css/app.css`.
- Verified PHP syntax and browser rendering for `GET:visit-edit&id=33`; no SQLSTATE error, no visit-edit action forms, and no horizontal overflow.
- Database impact: no schema change.

## 2026-06-06 - Workflow Sidebar + Queue Alert Bar

- Refactored Sidebar Navigation from module order into workflow groups: Daily Work, Clinic Management, Reports, and Admin.
- Moved Queue Today, Patient Registration, Payments, and Pharmacy Stickers to the top Daily Work group for one-nurse operation.
- Kept Appointments but moved it into Clinic Management instead of daily workflow.
- Removed the visible Next Patient card from Queue Station and replaced it with a compact top Alert Bar.
- Restored Queue Board to four Kanban columns: Waiting, In Service, Waiting Payment, Completed.
- Changed queue cards to open Smart Exam directly through `queue-exam&id=visit_id`.
- Removed unused Queue Board tab/history CSS and JavaScript handlers.
- Verified PHP syntax and diff whitespace checks.
- Database impact: no schema change.

## 2026-06-06 - Smart Exam Patient Context Three-Dot Menu

- Added a compact three-dot menu to the Smart Exam Patient Context card.
- Added menu actions for full patient data, edit patient profile, case history, patient card print placeholder, QR Code print placeholder, and close menu.
- Added a right-side Patient Profile drawer for read-only review and inline profile editing without leaving Smart Exam.
- Added `POST:queue-patient-profile-update` and `QueueController::updatePatientProfile()` with role-based field permissions.
- Admin can edit all supported patient profile fields; Nurse can edit only phone, address, chronic disease, drug allergy, and note.
- Patient profile saves return JSON and update the visible Patient Context Card immediately.
- Added audit logging through existing `audit_logs` using action `UPDATE_PATIENT_PROFILE` and `table_name = patients`.
- Verified PHP syntax for `QueueController`, `queue/exam.php`, and `public/index.php`; verified JavaScript syntax for `smart-exam.js`.
- Database impact: no schema change.

## 2026-06-07 - Smart Exam Minimal Clinical Flow

- Refined Smart Exam into a denser single-nurse clinical flow without changing endpoints or database schema.
- Changed service presets into compact one-line controls and hid preset descriptions from the default path.
- Collapsed duplicate center Patient Snapshot so patient safety context lives primarily in the left Patient Context surface.
- Reordered visual priority so Clinical Note leads before Vitals, then Services, then Drugs/Supplies.
- Stacked service and drug order-entry cards vertically with compact quick-add rows, tighter inputs, and shorter selected-line lists.
- Reduced sticky Summary rail height and moved no-charge close under a secondary disclosure so only three primary controls are visible.
- Verified PHP syntax for `app/Views/queue/exam.php`.
- Database impact: no schema change.

## 2026-06-07 - Smart Exam Responsive Exam Grid Fix

- Fixed Smart Exam overlap between Vital Signs and CC/PI/PE/Dx by replacing the examination area with explicit CSS Grid surfaces.
- Moved the Smart Preset dropdown into the top row beside preset quick actions.
- Added `smart-exam-top-grid` for Preset Quick Action + Template Dropdown and `smart-exam-work-grid` for Clinical Note + Vital Signs.
- Vital Signs now render as ordered grid fields: BP, BP lower, Temp, Pulse, Resp, SpO2, Weight.
- Examination containers now enforce `min-width: 0`, `max-width: 100%`, and `overflow: hidden` to prevent card overflow.
- Below 1400px, the examination grid stacks to one column to support 1366x768 without overlap.
- Verified PHP syntax for `app/Views/queue/exam.php`; local `queue-exam&id=43` returned HTTP 200 with no SQLSTATE/Fatal/Parse marker.
- Database impact: no schema change.

## 2026-06-07 - Smart Exam Pharmacy Compact Surface

- Refined the Smart Exam drug/supply card into a compact pharmacy quick-order surface.
- Added `smart-medication-card` for medication-only layout rules without changing service-entry behavior.
- Quick medication cards are shorter, use a denser name/stock/status grid, and avoid oversized visual weight.
- Medication instruction controls are grouped into a compact preset row with a concise generated instruction preview.
- Selected drug/supply rows now render as compact table-like rows with truncated long text instead of tall cards.
- Summary rail drug lines are constrained to prevent repeated medication detail from dominating the finish area.
- Verified PHP syntax for `app/Views/queue/exam.php`; local `queue-exam&id=43` returned HTTP 200 with no SQLSTATE/Fatal/Parse marker.
- Database impact: no schema change.

## 2026-06-07 - Smart Exam Service Compact Surface

- Refined the Smart Exam service card into a compact service order surface.
- Added `smart-service-order-card` for service-only layout rules without changing add-service logic.
- Quick service actions now render as compact pill buttons instead of tall dashboard-like cards.
- Service search, select, quantity, and add controls are tighter and aligned for faster order entry.
- Selected service lines now render as compact table-like rows with safe truncation for long service names.
- Verified PHP syntax for `app/Views/queue/exam.php`; local `queue-exam&id=43` returned HTTP 200 with no SQLSTATE/Fatal/Parse marker.
- Database impact: no schema change.

## 2026-06-07 - Smart Exam One-Nurse Clinical Layout

- Refactored the Smart Exam clinical section for a one-nurse workflow without changing business logic or database schema.
- Collapsed Smart Template from an always-visible right panel into a modal dialog opened by `เลือก Template` / `+ เพิ่มเติม`.
- Added a visible pencil edit action on Patient Context that opens the existing patient profile editor instead of adding an inline edit form.
- Reordered clinical fields to CC, PI, Dx, PE and added textarea auto-expand for longer CC/PI/PE text.
- Merged Vital Signs into the clinical card below PE with a compact grid: BP, BP lower, Temp, Pulse, Resp, SpO2, Weight.
- Added chronic disease visibility to the sticky Summary rail alongside allergy, service count, item count, totals, sticker, and finish actions.
- Verified PHP syntax for `app/Views/queue/exam.php`, JavaScript syntax for `smart-exam.js`, and local `queue-exam&id=43` returned HTTP 200 with no SQLSTATE/Fatal/Parse marker.
- Database impact: no schema change.
- 2026-06-07: Added Treatment Preset bundle foundation.
  - Added Admin management page for Treatment Presets.
  - Added normalized preset tables for services, medications, and supplies.
  - Added Smart Exam Treatment Preset cards with confirmation dialog.
  - Added transactional preset application that creates service/item lines and stock movements.
  - Prevents accidental duplicate application of the same Treatment Preset per visit.

## 2026-06-07 - Smart Exam Treatment Preset Bundles

- Added Admin-only Treatment Preset management at `treatment-presets`.
- Added Treatment Preset sidebar navigation under Clinic Management.
- Added normalized Treatment Preset schema:
  - `preset_master`
  - `preset_services`
  - `preset_medications`
  - `preset_supplies`
- Smart Exam now renders active Treatment Presets as compact bundle cards.
- Clicking a Treatment Preset opens a confirmation dialog showing services, medications, and supplies before applying.
- Applying a Treatment Preset now uses a transaction to add visit services, add item usage rows, deduct stock from inventory batches, and create `stock_movements`.
- If stock is insufficient, the entire preset application rolls back and shows a clear warning.
- The same Treatment Preset cannot be applied twice to the same visit by accident.
- Updated AI context, system flow, UX guide, module notes, database schema notes, and future roadmap.
- Database impact: new Treatment Preset tables only; existing service, visit, billing, and stock tables are reused.

## 2026-06-10 - Professional Clinic Dashboard Redesign

- Redesigned Dashboard into a commercial Clinic SaaS overview without changing DashboardController database queries.
- Added compact Dashboard header with greeting, date/time, and today's clinic status.
- Added six hero KPI cards: today's patients, in service, waiting payment, revenue, follow-up appointments, and low stock.
- Moved urgent operational work directly under KPIs as a Priority Task Board.
- Replaced report-like tables with CSS-based revenue and popular-service chart surfaces.
- Added dedicated Follow-up, Finance Today, Inventory Watch, and Backup status panels.
- Updated Dashboard responsive rules for 4 KPI columns on desktop, 2 on tablet, and 1 on mobile.
- Reworked sidebar grouping into workflow-oriented sections: daily work, treatment, reports, and admin.
- Database impact: no schema change.

## 2026-06-10 - Dashboard Polish V2

- Refined Dashboard visual hierarchy for a more premium Clinic SaaS feel without changing queries, database schema, or business logic.
- Added a compact Notification Strip under the greeting header showing up to three important operational signals.
- Upgraded KPI cards with status lines and tighter card density so the dashboard reads faster and wastes less vertical space.
- Added revenue target progress to Finance Today using the existing revenue value.
- Limited Inventory Watch to low-stock and near-expiry medication/supply risks.
- Moved backup visibility into a new System Health section with backup status, receipt count, pending work, and backup action.
- Cleaned the sidebar grouping so Dashboard sits in daily work and removed the duplicate `$navGroups` definition from the layout.
- Polished sidebar IA to match Dashboard V2: daily work, treatment, reports/statistics, and system-only admin links.
- Added a direct Statistics sidebar shortcut to the Dashboard analytics section without adding routes or database queries.
- Verified PHP syntax for `app/Views/dashboard/index.php` and `app/Views/layouts/app.php`.
- Database impact: no schema change.

## 2026-06-10 - Reports & Backup Commercial Report Center

- Redesigned Reports & Backup into a commercial Report Center focused on analytics, historical data, export, and backup management.
- Removed the old dashboard-like summary layout and replaced it with a hero, date/month filters, Daily Analytics cards, Patient Report table, trend charts, service ranking, payment mix, Export Center, and Backup Center.
- Added client-side patient report search and sorting for faster daily review.
- Added monthly patient trend data to `ReportController` and made daily visit export / monthly revenue export respect the selected date/month.
- Added export actions for appointments and monthly report CSV without changing database schema.
- Backup Center now reads existing `storage/exports/clinic_backup_*.sql` files for latest backup, file count, file size, total storage use, and today's backup status.
- Added dedicated `public/assets/css/reports.css` and `public/assets/js/reports.js`.
- Verified PHP syntax and local `reports` page HTTP 200 with no SQLSTATE/Fatal markers.
- Database impact: no schema change.

## 2026-06-11 - Medical Inventory Command Center

- Refactored the Inventory page from a form-heavy stock screen into a Medical Inventory Command Center.
- Added modern KPI cards for total items, low stock, near expiry, expired, stock value, and received quantity today.
- Added prominent search for item name, item code, lot, and barcode-ready workflows with filter chips for item type and stock/expiry status.
- Upgraded the inventory table with sticky headers, stock progress bars, nearest-expiry status, latest lot, muted status badges, and a compact row action menu.
- Moved receive stock, stock adjustment, and item master forms into focused modals with realtime stock preview.
- Added Alert Center, Movement History timeline filters, Inventory Analytics, Consumption Trend, Forecast, and Barcode Ready surfaces.
- Database impact: no schema change; existing `inventory_items`, `inventory_batches`, and `stock_movements` tables are reused.
