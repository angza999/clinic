# Smart Exam Logic: ดงมหาวันคลินิก

## Purpose
Smart Exam is the primary nurse workspace for completing common outpatient cases on one screen.

It combines:
- queue handoff
- clinical note capture
- vital signs
- quick service presets
- medicine and supply usage
- billing summary
- case completion

## Strategic Role
- Default path for nurse workflow
- Faster than full visit detail for common cases
- Reduces page switching between queue, visit edit, stock, and payment

## Core Screen Flow
Target flow:
1. choose preset if needed
2. record or confirm vitals
3. complete `CC`, `PI`, `PE`, `Dx`
4. add services and medicines
5. review summary
6. finish case

## Current Clinical Fields
- `cc`
- `pi`
- `pe`
- `dx`

## Current Vital Fields
- `weight_kg`
- `temp_c`
- `pulse_rate`
- `resp_rate`
- `bp_systolic`
- `bp_diastolic`
- `spo2`

## Billing Inputs
- visit services
- visit item usages

## Finish Modes
### `payment`
- Use when the case has billable services or items.
- Final queue status becomes `WAITING_PAYMENT`.

### `no_charge`
- Use when the case should close without payment.
- Final queue status becomes `COMPLETED`.

## Required Validation Before Finish
- `CC` must not be empty
- `Dx` must not be empty
- if finish mode is `payment`, there must be at least 1 billable service or item

## Automatic Status Behavior
- Opening Smart Exam from a `WAITING` case changes queue status to `IN_SERVICE`
- Finishing with payment sends the case to `WAITING_PAYMENT`
- Finishing without charge closes the case as `COMPLETED`

## Quick Preset Logic
Quick presets are defined in `QueueController::quickPresets()`.

### `wound_dressing`
Intent:
- wound care case

Typical injected data:
- service `SRV002`
- items `MED002 x1`, `MED003 x2`
- `CC = มีแผล`
- `PI = แผลจากอุบัติเหตุ ไม่มีเลือดออกมาก`
- `PE = wound clean`
- `Dx = Wound`

### `injection`
Intent:
- injection-only workflow

Typical injected data:
- service `SRV003`
- `Dx = Injection service`

### `vital_signs`
Intent:
- observation or vital-only follow-up

Typical injected data:
- service `SRV004`
- `Dx = Observation`

### `followup`
Intent:
- follow-up revisit

Typical injected data:
- service `SRV001`
- `Dx = Follow up`

## Preset Idempotency Rule
- Preset-generated service or item rows must not duplicate endlessly on repeated clicks.
- Current protection uses preset remark markers such as `QUICK_PRESET:<preset_key>`.

## Clinical Composition Rule
When Smart Exam saves clinical data, the legacy nursing note is composed from:
- `PI`
- `PE`
- `Dx`

Expected format:
- `PI: ...`
- `PE: ...`
- `Dx: ...`

## Diagnosis Suggestion Logic
Current Dx suggestion is a frontend helper only. It is not a diagnostic engine.

### Current heuristics
- if `CC` contains `ไข้` and either `ไอ` or `PI` contains `น้ำมูก`
  - suggest `URI`
- if `CC` contains `ปวดท้อง`
  - suggest `Gastritis`
- if `CC` contains `มีแผล`
  - suggest `Wound`

### Important rule
- Suggestions must remain non-authoritative helper text.
- AI must not present this as automatic medical diagnosis.

## Smart Preset Text Helpers
### Append buttons
- Add common phrases into `CC` or `PE`
- Reduce repetitive typing

### Templates
Current template families:
- `uri`
- `wound`
- `gastritis`
- `iv`

Templates can fill multiple clinical fields at once.

## Workflow Guidance Behavior
Phase 2 behavior:
- after applying a quick preset, redirect back with `preset=<preset_key>`
- highlight the preset that was just used
- show immediate feedback that the preset was added
- update workflow step states
- disable finish actions when the case is not ready

## Phase 3 Operator Flow
Phase 3 adds more workstation-style behavior:
- compact topbar for Smart Exam pages
- patient context displayed as chips for queue, HN, and VN
- drug allergy shown in both the active-case header and the summary sidebar
- keyboard-first progression:
  - `Enter` moves through vitals
  - `Ctrl + Enter` or `Cmd + Enter` moves between `CC`, `PI`, and `PE`
  - `Enter` on `Dx` moves the user toward billing entry or finish actions
  - service and item quick-add forms support sequential keyboard use

## Phase 4 Inline Payment Flow
Smart Exam supports single-operator payment directly in the finish summary.

Finish actions:
- `receive_payment`
  - requires `CC`, `Dx`, and at least one billable service or item
  - records a row in `payments`
  - generates a receipt number with `NumberGenerator::nextReceiptNo()`
  - changes queue status to `COMPLETED`
- `waiting_payment`
  - requires `CC`, `Dx`, and at least one billable service or item
  - changes queue status to `WAITING_PAYMENT`
  - used only when payment is not collected immediately
- `no_charge`
  - requires `CC` and `Dx`
  - changes queue status to `COMPLETED`

Payment fields in Smart Exam:
- `payment_method`
- `discount_amount`
- `paid_amount`

Frontend behavior:
- calculates net total from current service and item totals
- calculates change amount
- disables `รับเงินและปิดเคส` when paid amount is lower than net total
- keeps `บันทึกรอชำระ` available when clinical and billable data are ready

Production rule:
- for one-operator clinic mode, most normal paid cases should finish through `receive_payment` from Smart Exam
- `WAITING_PAYMENT` should be the exception for unpaid or delayed payment cases

## Phase 5 Receipt Handoff Flow
After `receive_payment` succeeds, Smart Exam must redirect directly to the receipt page instead of returning silently to the queue.

Rules:
- `recordSmartPayment()` returns both `receipt_no` and `payment_id`.
- The receipt page accepts `source=smart_exam` to show a post-case action panel.
- Nurses can open receipt pages because in one-operator mode the nurse is also the cashier.
- Receipt actions should prioritize:
  - print receipt
  - return to today's queue
  - open payments page only for Admin/Cashier
- Do not auto-print by default; keep print as a deliberate user action to avoid surprise printer dialogs.

## Phase 5.1 Receipt Care Instructions
Receipt should include after-care context when the visit has it.

Behavior:
- Receipt query loads `visits.advice` and `visits.followup_date`.
- If either field exists, receipt shows a printable `คำแนะนำหลังรับบริการ` panel.
- Advice is shown as home-care instructions.
- Follow-up date is shown as the next appointment date.

Rules:
- Do not show an empty care panel.
- Keep receipt content print-friendly.
- Receipt remains a payment document first, but can also serve as a lightweight after-care handout.

## Phase 6 Queue Continuation Flow
After a receipt is printed or reviewed, the nurse should return to queue with `from_receipt=1`.

Behavior:
- queue page shows a continuation card when `from_receipt=1`
- if a waiting queue exists, the card offers one action to call and open Smart Exam
- next queue action should send `redirect_to_visit=1`
- queue keyboard shortcuts:
  - `Alt+N` triggers the next-case action
  - `Alt+S` focuses patient search

Production rule:
- one-operator clinics should not require the nurse to click "call queue" and then separately find/open the same visit
- the queue page is a command center between Smart Exam cases

## Phase 7 Quick Register To Smart Exam
Queue supports quick registration for new walk-in patients.

Behavior:
- `POST:queue-quick-register` creates a patient with minimal demographics
- creates visit and queue through `ClinicWorkflow::createVisitAndQueue()`
- redirects directly to Smart Exam
- duplicate guard checks phone and name before insert
- duplicate override requires `confirm_duplicate=1`

Rule:
- Quick register is for speed, not complete demographic capture.
- Do not remove the full patient registration page.

## Phase 8 Configurable Presets
Smart Exam presets are now database-backed.

Behavior:
- `smart_exam_presets` stores preset label, service codes, item codes, clinical defaults, advice, active state, and sort order.
- Settings page lets Admin edit existing presets.
- Smart Exam reads active presets from the database first.
- If the table or query fails, Smart Exam falls back to the legacy hardcoded presets.
- Default presets are seeded automatically when the table is empty.

Rules:
- Preset keys must remain stable because applied service rows use `QUICK_PRESET:<preset_key>`.
- Service codes must exist in `services.service_code`.
- Item codes must exist in `inventory_items.item_code`.
- Avoid making preset admin too complex; this is an operational shortcut for common clinic workflows.

## Phase 9 Patient Snapshot
Smart Exam now shows compact patient context before the clinical form.

Behavior:
- `QueueController::patientSnapshot()` loads profile risk, recent visits, latest vitals, upcoming appointments, unpaid old cases, and last payment date.
- Snapshot excludes the current visit so nurses do not confuse current-case data with historical data.
- The snapshot appears directly below the active case header in `queue/exam`.
- Full patient history remains available through the patient detail link.

Snapshot should include:
- drug allergy and underlying disease
- total visit count
- unpaid previous cases
- latest vital signs
- upcoming appointments
- last three treatment visits with CC, Dx, services, items, and receipt total

Rules:
- Snapshot is clinical context, not a replacement for full history.
- Keep it compact; do not let history push preset/vitals/finish actions too far down.
- Allergy and unpaid-case warnings must be visible without opening details.
- Do not auto-fill new clinical text from prior visits unless the nurse explicitly chooses an action.

## Phase 10 Follow-Up Appointment Sync
Smart Exam now supports advice and follow-up scheduling in the same finish flow.

Behavior:
- Smart Exam includes `advice` and `followup_date` fields.
- `queue-smart-finish` saves advice and follow-up date into `visits`.
- If `followup_date` exists, the system creates or updates one active `appointments` row for the current visit.
- If `followup_date` is cleared, scheduled appointments linked to the visit are removed.
- Advanced visit save uses the same sync behavior to prevent duplicate follow-up appointments.

Rules:
- Appointment sync happens inside the same transaction as clinical save and finish/payment logic.
- One visit should have at most one active scheduled follow-up appointment created by this workflow.
- Do not create appointment rows when the nurse has not set a follow-up date.
- Appointment purpose should stay operational and simple: `นัดติดตามอาการ`.

## Phase 11 Appointment Check-In
Appointments can now become active Smart Exam cases from the queue page.

Behavior:
- Queue page lists appointments with `status = SCHEDULED` and `appointment_date <= CURDATE()`.
- `POST:appointment-checkin` receives the appointment.
- If the patient already has an active queue today, redirect to that Smart Exam visit.
- Otherwise create a new visit and queue, mark the appointment `COMPLETED`, link it to the new visit, then redirect to Smart Exam.

Rules:
- Do not create duplicate active queues for the same patient on the same day.
- Check-in is for due/overdue scheduled appointments, not a full appointment calendar.
- Appointment check-in is a nurse/admin operation.

## Phase 12 Smart Exam Stock Safety
Smart Exam now exposes stock safety information before the nurse adds medicine or supplies.

Behavior:
- Item queries include current balance, reorder level, and nearest non-empty batch expiry.
- Frequent item buttons show stock status badges:
  - `พร้อมใช้`
  - `ใกล้หมด`
  - `ใกล้หมดอายุ`
  - `หมด`
- Out-of-stock shortcut buttons and dropdown options are disabled.
- Expiry warning uses clinic `expiry_alert_days` setting.

Rules:
- Stock safety belongs in Smart Exam because item usage is deducted from the exam workflow.
- Do not hide stock risk only inside the inventory module.
- Warnings should be compact and operational; they should not block use unless stock is zero.

## Phase 13 Stabilized Smart Exam Workflow
Smart Exam now treats preset, suggestion, order entry, readiness, and finish as one continuous workstation flow.

Behavior:
- URI preset is available as a first-class common case preset.
- Default presets are inserted when missing without overwriting existing database preset edits.
- Applying a preset merges CC, PI, PE, Dx, advice, and follow-up safely:
  - empty fields are filled
  - existing text is preserved
  - duplicate preset text is not appended again
- Preset item stock movement uses `VISIT_USAGE` reference with the usage row id so stock restoration follows the same trace model as manual item usage.
- Smart suggestions remain frontend-only:
  - Temp >= 37.5 shows fever suggestion
  - fever + cough + rhinorrhea suggests URI
  - abdominal pain suggests Gastritis
  - wound text suggests Wound
- Service and medicine order entry include compact search/filter fields.
- Medicine quantity is checked against visible stock before submit as a frontend guard, while server stock validation remains authoritative.
- Summary readiness includes CC, Dx, billing item presence, and selected-item stock state.
- Keyboard shortcuts:
  - Ctrl/Cmd+K focuses order search
  - F2 focuses service/medicine order entry
  - F9 focuses the first enabled finish action
  - Esc clears suggestion/alert focus state

Rules:
- Suggestions must never force diagnosis.
- JS stock validation is only a fast UI guard; backend stock checks remain the source of truth.
- Finish warnings should appear in the summary rail instead of popup-heavy interaction.

## Phase 14 Inline Order Entry
Smart Exam order entry now uses progressive-enhancement AJAX for services and medicines.

Behavior:
- Existing `visit-add-service`, `visit-remove-service`, `visit-add-item`, and `visit-remove-item` endpoints still support normal POST + redirect fallback.
- When Smart Exam sends `Accept: application/json` / `X-Requested-With: XMLHttpRequest`, the endpoint returns an order summary JSON payload instead of redirecting.
- The frontend updates:
  - main service list
  - main medicine/item list
  - summary rail service/item counts
  - summary rail line items
  - service, item, and grand totals
  - readiness state and payment preview
- Server-side stock validation remains authoritative; frontend stock validation is only a speed guard.

Rules:
- Do not replace the server-rendered fallback. Smart Exam must remain usable if JavaScript fails.
- Inline order entry must not create a separate modal/cart flow.
- Summary rail is the control surface and must stay synchronized after every add/remove.

## Phase 15 Inline Preset Apply
Smart Exam preset application now follows the same progressive-enhancement pattern.

Behavior:
- Existing `queue-apply-preset` still supports normal POST + redirect fallback.
- When Smart Exam sends a JSON request, applying a preset returns:
  - merged clinical fields
  - applied preset metadata
  - recalculated service/item summary
- The frontend updates CC, PI, PE, Dx, advice, follow-up date, active preset state, main order lists, totals, readiness, and payment preview without a full page reload.
- Presets still use safe merge rules and do not duplicate services/items if the same preset was already applied.

Rules:
- Preset apply must feel like an accelerator, not page navigation.
- Backend merge and duplicate-preset checks remain authoritative.
- If inline preset fails, the summary rail alert should explain the issue without opening a popup.

## Service Addition Logic
- Add service via `visit-add-service`
- When the request comes from Smart Exam, send `return_to=queue-exam`
- After add or remove, redirect back to the same Smart Exam visit unless the request is JSON, then return summary JSON

## Item Addition Logic
- Add item usage via `visit-add-item`
- When the request comes from Smart Exam, send `return_to=queue-exam`
- Stock must be checked before deduction
- Stock consumption follows batch rules based on expiry / received order

## Summary Sidebar Logic
The sidebar must show:
- patient identity
- allergy status
- service lines
- item lines
- service total
- item total
- grand total
- readiness checklist
- finish actions

Rules:
- Summary is a working panel, not decorative information.
- Finish actions should remain reachable without excessive scrolling.
- Long line lists should not push the finish buttons too far below the fold.

## Known Fragility Points
- Smart Exam touches queue, visit, stock, and payment at the same time
- Finish logic regressions can break the entire nurse workflow
- PDO native prepare should not reuse the same named placeholder multiple times in one statement
- Redirect behavior after add/remove actions must preserve Smart Exam context

## Rules For Future Extensions
- If adding a new preset, document:
  - intent
  - injected services
  - injected items
  - clinical text defaults
- If adding a new field, update:
  - schema reference
  - controller save logic
  - view
  - summary logic
  - AI context files
- If adding automation or suggestion logic, keep nurse control obvious and visible

## Future Direction
- configurable protocol templates
- controlled diagnosis catalog
- reusable nursing macros
- reusable drug instruction templates
- stronger keyboard workflow for heavy-use clinics
