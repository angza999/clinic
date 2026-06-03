# UI/UX Guide: ดงมหาวันคลินิก

## Design Intent
The product should feel like a real clinic workstation, not a consumer app and not a generic admin dashboard.

Core design direction:
- Medical Minimal UI
- fast to scan
- low click count
- low scroll
- low cognitive load
- operational first, decoration second
- suitable for nurses on a notebook or clinic desktop

## Primary UX Principles
- Every main screen must answer: what is happening now, what should the user do next, and what can be completed on this screen.
- Important actions should stay visible without deep scrolling.
- Repeated work should support keyboard flow, presets, and quick-add patterns.
- Dense is acceptable when it improves clinical speed, but clutter is not.
- Page hierarchy must emphasize patient context, current task, and completion state.

## Global Layout System
### App Shell
- Left sidebar for navigation.
- Topbar for current workspace context and session controls.
- Main content area for task execution.

### Workstation Pages
Pages such as `queue`, `queue-exam`, `payments`, and `inventory` are workstation-first screens.

Rules:
- Use compact topbar mode when the page is already task-heavy.
- Avoid adding a second large hero block inside content unless it adds operational value.
- Keep critical patient or billing state above the fold.

## Spacing System
- Default section gap: `16px` to `24px`
- Smart Exam section gap: `12px` to `16px`
- Default card padding: `16px` to `20px`
- Default card radius: `12px` to `16px`
- Input gap inside dense forms: `8px` to `12px`

Smart Exam should intentionally use denser spacing than admin pages.

## Color Hierarchy
Primary palette:
- Deep navy: `#0f172a`
- Teal: `#0f766e`
- Blue: `#0ea5e9`
- Soft background: `#f4f7fb` to `#edf2f7`
- Main text: `#0f172a`
- Secondary text: `#475569`
- Soft border: `#dbe4f0` to `#e2e8f0`

Semantic usage:
- Waiting: amber
- In service: cyan or blue
- Waiting payment: slate or neutral
- Completed: green
- Allergy / caution: warm alert tone with stronger text contrast

Rules:
- Use semantic color sparingly.
- Neutral surfaces should dominate the screen.
- Avoid multi-color card walls unless colors encode real workflow meaning.

## Typography
Recommended scale:
- Page title: `28px` to `32px`
- Section title: `20px` to `22px`
- Card title: `16px` to `18px`
- Body text: `14px` to `15px`
- Labels: `12px` to `13px`
- Helper text: `12px`

Rules:
- Do not repeat large titles in both topbar and content hero.
- Long helper text must never compete with form labels.
- Slash-separated patient metadata should be avoided when chips or grouped labels read faster.

## Card Style
- White or near-white background
- soft border
- light shadow only
- rounded but not playful
- compact headers
- no oversized empty space inside cards

Cards should group tasks, not multiply them.

## Button Style
### Primary
- Use for final or irreversible workflow actions.
- Example: `บันทึกและจบเคส`

### Secondary / Outline
- Use for supporting navigation and non-destructive actions.
- Example: back to queue, advanced detail, quick add

### Danger / Link
- Use for remove actions only.
- Do not visually outrank the primary action.

## Form UX Rules
- Labels must remain visible. Placeholders are examples, not the main instruction.
- High-frequency fields should support Enter-based progression when safe.
- Multi-line clinical textareas should support `Ctrl + Enter` or `Cmd + Enter` to move to the next field.
- Validation must explain what is missing and what the user should do next.
- Clinical inputs and billing inputs should feel like one flow, not isolated modules.

## Sidebar Rules
- Preferred sidebar width for clinic workstations: `220px` to `240px`
- Text and icon balance should feel compact, not blocky
- Avoid oversized menu items that reduce usable workspace width

## Smart Exam Rules
Smart Exam is the primary nurse workflow and must stay faster than visit detail.

### Layout
- Use a compact topbar instead of a large duplicate page hero.
- Use a 2-column working layout:
  - left: clinical work
  - right: sticky summary and finish actions
- Keep the summary panel sticky on desktop widths.

### Patient Context
- Show patient name clearly.
- Show queue number, HN, and VN as compact chips.
- Show drug allergy status above the fold in both the active case area and summary panel.
- Avoid burying risk information inside advanced detail.

### Clinical Flow
- Preset selection must be quick and obvious.
- Vitals should be laid out in one dense logical row or grid.
- `CC` and `Dx` are mandatory completion fields.
- Smart preset helpers belong inside the same clinical block as `CC`, `PI`, `PE`, and `Dx`.

### Keyboard Flow
- `Enter` should move through vitals and order-entry fields.
- `Ctrl + Enter` or `Cmd + Enter` should move between `CC`, `PI`, and `PE`.
- After `Dx`, keyboard flow should move to billing entry if no billable items exist, or to finish actions if the case is ready.

### Billing and Finish
- Quick-add service and item forms must stay on the same screen.
- Summary should prioritize:
  - patient identity
  - allergy visibility
  - counts
  - totals
  - finish readiness
  - finish actions
- Finish actions should not sit below long unbounded summary lists.
- A readiness checklist is preferred over vague status text.
- In one-operator clinic mode, the summary panel should include compact payment fields directly inside Smart Exam.
- The primary finish action should be `รับเงินและปิดเคส` when billable items exist and paid amount is valid.
- `บันทึกรอชำระ` is a secondary action for delayed payment, not the normal completion path.

### Receipt Handoff
- After successful payment, show the receipt page immediately.
- Receipt action priority should be `print receipt`, `return to queue`, then optional `payments page` for Admin/Cashier.
- Do not force auto-print; make printing a clear, deliberate button to avoid accidental printer dialogs.
- If advice or follow-up exists, show it in a calm printable care panel before footer/action buttons.
- Do not show a blank advice section on receipts without care instructions.
- Receipt header may show a compact text logo mark, clinic tax/register ID, and a dedicated footer from Settings.
- Receipt branding should remain calm and printable; it must not compete with receipt number, patient identity, totals, or care instructions.

### Queue Continuation
- Returning from receipt should not feel like a dead end.
- Show a continuation panel when the operator returns from receipt.
- If a waiting patient exists, expose a single primary action to call and open Smart Exam.
- Support keyboard-first operation:
  - `Alt+N` for next case
  - `Alt+S` for patient search
- Avoid making the nurse click call queue first and then search for the same visit again.

### Quick Register
- Quick register belongs on queue because it is an intake action, not a reporting action.
- Keep the form compact and suitable for walk-in speed.
- Required information should be minimal: name is required, phone and complaint are highly encouraged.
- Show duplicate warnings near the form before submit when possible.
- Do not replace the full patient registration page; quick register is for fast intake only.

### Preset Admin
- Preset editing belongs in Settings because it changes clinic workflow defaults.
- Keep preset editing compact and operational: label, service codes, item codes, CC/Dx, and advice.
- Do not turn preset admin into a full EMR protocol builder.
- Show service/item codes plainly because this project currently uses code-based service and inventory catalogs.

### Patient Snapshot
- Patient snapshot belongs near the top of Smart Exam, below active case identity and above preset/vitals.
- Use compact cards, chips, and collapsible history details.
- Show high-risk information immediately:
  - drug allergy
  - underlying disease
  - unpaid previous cases
- Keep previous visits collapsed by default to reduce cognitive load.
- Do not show large history tables inside Smart Exam; link to the full patient history page instead.
- Snapshot should help nurse orientation in 5-10 seconds, not become a second patient profile page.

### Follow-Up Scheduling
- Advice and follow-up scheduling belong near the clinical form, before finish actions.
- Keep the follow-up UI compact: one advice textarea and one date field.
- The nurse should understand that setting a date creates an appointment when the case is finished.
- Do not add a full calendar inside Smart Exam.
- Detailed reschedule/cancel workflows belong in a future appointment module, not in the exam screen.

### Appointment Check-In
- Due appointments belong on the queue page because check-in is an intake action.
- Keep the appointment panel compact and above patient search.
- Primary action should be a direct `รับนัดเข้าคิว` button.
- If the patient already has a queue today, show/open the existing queue instead of creating another one.
- Do not add calendar-style UI to the queue page.

### Appointment Agenda
- The agenda page is the central surface for appointment planning, not the queue page.
- Keep filters compact: date range, status, and keyword are enough for the current workflow.
- Appointment cards should show date/time, patient identity, purpose, status, and active queue state at a glance.
- Reschedule/edit controls may live inline behind a compact details control because editing is secondary to check-in.
- Cancellation should be deliberate and require confirmation.
- Check-in should stay a primary action and reuse the existing appointment check-in flow.
- Do not build a decorative calendar grid until the clinic needs full scheduling density and reminders.

### User Management
- Admin user cards should keep role, active status, and last login visible at a glance.
- Recent user/security audit history belongs on the Users page so Admin can confirm account and login activity without opening database tools.
- Audit history should be compact and should never display password values or hashes.

### Stock Safety In Smart Exam
- Medicine and supply shortcuts should show stock status before the nurse clicks them.
- Use compact badges for `พร้อมใช้`, `ใกล้หมด`, `ใกล้หมดอายุ`, and `หมด`.
- Out-of-stock items should be visibly disabled.
- Low-stock/expiry warnings should be semantic and calm, not alarming unless action is blocked.
- Dropdown labels may include stock and expiry status because this is a workstation UI, not a consumer app.

### Daily Close Dashboard
- Daily close belongs on Dashboard because it is an end-of-day operational check.
- Show the close status as `พร้อมปิดวัน` only when no pending queue/payment work remains.
- Keep payment method split compact and scannable.
- Latest receipts should be a short strip, not a full payment table.
- End-of-day backup action belongs inside the close panel, below the summary/checklist.
- If work remains, the primary action should be `เคลียร์งานค้างก่อน`, not backup.
- If no work remains, Admin can see `สำรองข้อมูลปิดวัน` with a confirmation prompt.
- Show latest backup status next to the end-of-day action so the operator knows whether today's backup is done.
- Backup status should be compact: status label, timestamp, filename, and size.
- Include file count and retention limit in backup status, e.g. `เก็บ 12 / 30 ไฟล์ล่าสุด`.
- Use calm green for today's backup and warm neutral for missing/stale backup.
- Do not turn Dashboard into a formal accounting report; link users to Reports for deeper reporting.

## Professional System Rules
To feel like real medical software:
- prefer calm density over large decorative whitespace
- prefer structured chips and compact summaries over paragraph-like metadata
- prefer semantic alerts over colorful cards
- prefer workflow confirmation over marketing-style explanations

The UI should feel operational, clinical, and trustworthy.

## Forbidden UI
- Do not turn Smart Exam into a multi-step wizard that hides the whole case behind separate pages.
- Do not use large marketing hero sections inside dense clinical workspaces.
- Do not force deep scrolling to reach the finish action.
- Do not hide allergy, queue status, or total price inside accordions.
- Do not add decorative cards that duplicate page title or repeat obvious instructions.

## Responsive Intent
- Primary target: desktop and 14-inch notebook
- Tablet/mobile should remain readable, but desktop efficiency is more important than perfect mobile parity
- On smaller widths, stack columns but keep summary readable and finish actions visible

## AI Editing Checklist
Before changing UI, AI should ask:
1. Does this reduce clicks or reduce scrolling?
2. Does this make the next action more obvious?
3. Does this help nurses finish a common case faster?
4. Does this preserve Medical Minimal UI?
5. Does this make the screen look more like a clinic workstation than an admin template?

## Medical Workstation Design Doctrine

The UI direction is Medical Workstation Software. It must not be designed like an admin dashboard, CRUD management panel, or generic SaaS template.

### Design Philosophy
- Fast is better than decorative.
- Clear is better than clever.
- Workflow continuity is better than separated sections.
- Compact is better than oversized when it improves nurse speed.
- Real operational use is better than visually impressive presentation.

### Workstation Layout Mindset
Use this page structure for operational screens:

1. Command Header
   - patient, queue, date, status, or active task
   - primary next action
   - no large hero copy

2. Main Working Area
   - the actual form, exam, queue action, or order entry
   - high-frequency inputs first
   - minimal explanatory text

3. Sticky Summary Area
   - patient/risk visibility
   - service/item counts
   - totals
   - readiness
   - finish/payment actions

4. Compact Side Panel
   - search, recent items, due appointments, or secondary context
   - collapsible when not essential

### Density Rules
- Target desktop and 14-inch notebook first.
- Default workstation gap: 8px to 16px.
- Default workstation panel padding: 12px to 16px.
- Inputs should usually be 38px to 40px tall.
- Use 8px-based spacing; avoid one-off spacing values unless required by layout.
- Do not use large cards for secondary information.
- Empty states should be compact and actionable.

### Card Rules
Use cards only when they create useful grouping. Do not turn every section into a card.

Avoid:
- card inside card inside card
- oversized cards with only text
- cards used as decoration
- repeated card headings that explain obvious workflow steps

Prefer:
- bounded panels for active work
- thin borders over heavy shadows
- compact headers
- scroll-limited lists
- disclosure for secondary details

### Typography Rules For Workstation Pages
- Workstation page title: 18px to 24px.
- Panel title: 15px to 18px.
- Body: 14px to 15px.
- Label: 12px to 13px, semibold.
- Avoid huge headings inside Queue, Smart Exam, Payments, and Visit Detail.
- Remove helper paragraphs when labels and layout already explain the task.

### Color Balance
- Neutral surfaces must dominate.
- Teal is for primary clinical/operational actions.
- Blue is for information and links.
- Amber/red are for warning, unpaid, allergy, or blocked action.
- Do not build colorful card walls. Color should encode state, not decorate.

### Cognitive Load Rules
Every workstation screen should reduce thinking:
- one obvious primary action per context
- patient risk visible without searching
- totals/readiness visible without scrolling
- secondary data collapsed by default
- repeated metadata shown as chips or compact rows
- no paragraph-heavy operational panels

### Workflow Rules
Operational pages must be designed around the user doing the work, not reading about the work.

For each page, verify:
- Can the user start the common task within 3 seconds?
- Is the next action visible?
- Are patient, status, and risk clear?
- Is the finish/submit/payment action reachable?
- Does the page avoid unnecessary scroll on a 14-inch notebook?

### Smart Exam Workstation Pattern
Smart Exam should follow:

`Patient/Risk Header -> Preset -> Vitals -> Clinical Fields -> Services -> Medicines -> Summary -> Finish`

Rules:
- Preset and vitals should appear early.
- Patient snapshot should be a risk strip, not a history page.
- Recent history and detailed context should be collapsed by default.
- Summary rail must remain sticky on desktop.
- Finish actions must not be pushed below long lists.
- The flow should feel like one continuous clinical station, not separate dashboard widgets.

### Queue Workstation Pattern
Queue should be a command board:

`Command Bar -> Next Case / Intake -> Active Exam -> Summary / Payment State -> Queue Boards`

Rules:
- Queue is not a passive dashboard.
- Do not use large hero blocks.
- Call/open next case should be one obvious action.
- Due appointments and quick registration belong in compact intake surfaces.
- Queue boards are situational awareness, not the primary work area.

### Queue Workstation Reference Layout
Queue Workstation should visually follow a professional clinical operations board:

`Queue Status Cards + Current Working Case -> Intake/Search -> Active Patient Workspace -> Sticky Control Rail -> Compact Queue Board -> Shortcut Bar`

Rules:
- Keep the top command area compact, with status counts as small cards and the current/next case as the strongest working signal.
- Use three balanced surfaces on desktop: left intake, center clinical workspace, right control rail.
- Queue board columns should show dense rows, not oversized dashboard cards.
- Empty queue states should be quiet and compact.
- The right rail should feel like an operational control surface, with patient, billing, readiness, and payment state always reachable.
- Avoid giving the same case/action equal visual weight in top bar, center workspace, and right rail. Each surface must have a distinct job.
- If Smart Exam appears in more than one place, only one should be the primary call to action; other placements must be compact quick access.
- Use muted medical status colors; avoid candy pastel or decorative gradients.
- Add shortcut hints as a small bottom utility bar, not instructional content inside the main workflow.

### Enterprise Consistency
Reusable workstation components should be preferred:
- `command-header`
- `risk-strip`
- `clinical-panel`
- `compact-form-grid`
- `quick-action-group`
- `order-entry-panel`
- `summary-rail`
- `readiness-checklist`
- `finish-action-bar`

Future modules should adopt these patterns so the product feels like one healthcare system rather than a collection of Bootstrap pages.

### Forbidden Workstation Patterns
- Large dashboard hero on task-heavy pages.
- Decoration-first gradients or colorful card walls.
- Long helper text above active controls.
- Hidden primary actions.
- Finish actions below unbounded lists.
- Generic CRUD tables as the first answer to clinical workflows.

### Phase 5 Workstation Rules
The next production tightening pass must keep these five rules:

1. Command first: Queue and Smart Exam start with patient/status/action, not hero copy.
2. Working surface second: vitals, presets, clinical inputs, services, and items must be grouped as active tools, not separate dashboard widgets.
3. Summary rail always visible: totals, readiness, payment, and finish actions must stay reachable on desktop.
4. Text only when it prevents error: remove helper paragraphs when labels, status, and controls already explain the task.
5. Mobile fallback is stacked, not simplified away: all clinical actions remain available when columns collapse.

### Stabilized Smart Exam Workflow Rules
- Preset buttons should behave like clinical accelerators, not navigation cards.
- Preset application must preserve existing clinical text and merge new defaults safely.
- Suggestions should be short, editable, and non-authoritative.
- Order entry search belongs inside the existing services/medicine panels; do not add a new search card.
- Stock warnings should appear near order entry and summary readiness, with server validation kept as the source of truth.
- Keyboard shortcuts may be shown as a compact hint, not a large instruction panel.

### Inline Order Entry UX
- Adding/removing services or medicines inside Smart Exam should not force a full page reload when JavaScript is available.
- The right summary rail must update immediately after every order action.
- Keep the normal form submit fallback so the workstation remains reliable on older devices or script failure.
- Inline feedback should be brief and rail-based; avoid toast/popup noise.
- Do not turn order entry into a modal cart. The nurse should stay in the same working surface.

### Inline Preset UX
- Applying a Smart Exam preset should not feel like navigation.
- Preset feedback belongs in the existing alert/summary flow, not in a new modal.
- After preset apply, keep the nurse near the clinical fields and update the summary rail immediately.
- Active preset state should be visible, but not dominate the workstation.

### Medical Supply Workstation UX
- Inventory pages should behave like supply operation surfaces, not long CRUD forms.
- Keep KPI risk cards at the top: total items, low stock, near expiry, expired, stock value, and received today.
- Use a command bar for search, barcode-ready lookup, filters, and primary stock actions.
- Show only one inventory action form at a time: add item, receive stock, or adjust stock.
- Put alerts in a right control rail so low-stock and expiring-lot risk is visible while the table is scanned.
- Inventory table rows should include quick actions for receive, adjust, and history.
- Receive and adjust forms should preview old stock, change quantity, and new stock before submit.
- Adjustment warnings should be inline and operational; avoid popups for expected stock validation.

### Service Management Workstation UX
- Service management pages should make the table the primary surface and the form a secondary side panel.
- Keep KPI cards compact: all services, active, inactive, categories, and average price.
- Use realtime search and filters for category/status so nurses/admins can find services in 1-2 seconds.
- Service rows should expose quick actions for detail, edit, duplicate, and enable/disable.
- Use muted category/status badges; price 0 should show "ไม่มีค่าใช้จ่าย".
- Editing price should communicate that historical visit bills keep their captured unit price.
- Avoid hard delete in service management unless a dedicated audit/impact check exists.

### Medical Cashier Workstation UX
- Payments should behave like a cashier workbench, not a finance dashboard.
- The top area should be compact KPI + search/filter controls, not a large hero.
- The main surface must prioritize waiting payment queue and make "ยืนยันรับชำระ" the strongest action.
- Receipt history should be searchable by VN, HN, patient name, or receipt number.
- The right rail should show today's totals, last receipt, quick actions, and alerts.
- Payment forms should preview net total and change immediately.
- Expected validation belongs inline near the payment form; avoid modal-heavy cashier workflows.
- Keep methods aligned with current schema until a migration adds card/free/refund workflows.

### Service Workstation Phase 1 UX
- Services should feel like clinic price-standard management, not a CRUD form.
- The service table is the main working surface; builder/detail panel is secondary.
- KPI cards must be clickable operational controls, not static metrics.
- Search, category filter, and status filter should find a service within 1-2 seconds.
- Service row actions should stay compact: detail, edit, duplicate, enable/disable, and future price history.
- Builder panel should make state obvious: Add Service, Edit Service, Duplicate Service, or Readonly Detail.
- Live preview should show code, name, category, active state, and price before save.
- Smart suggestions are assistive only: category suggestion from service name and price warnings must not block unless price is invalid.
- Use muted category/status badges; price 0 should show "ไม่มีค่าใช้จ่าย".
- Do not add price history or bundle UI as fake controls until schema and audit behavior are designed.

### Service Workstation Phase 2 UX
- Price history is now a real detail surface and should stay in the right rail, not become a modal.
- Audit activity should be compact and low-noise; it supports trust, not daily editing.
- Keep row actions short even as governance features grow.
- Category management and bundle/package workflows must be designed as focused workflows before adding full UI.
- Historical billing safety should be visible in product behavior: editing current service price must never rewrite old visit service lines.

### Pharmacy Sticker Label UX
- Pharmacy label printing should feel like a workstation continuation of Smart Exam, not a separate document generator.
- The primary action is `พิมพ์สติ๊กเกอร์ยา`; preview should be visible immediately with real patient and drug data.
- Avoid modal chains. Use one preview surface, one control rail, and browser print for Phase 1.
- Label UI must show physical label sizes: 58x40, 80x50, and 100x75 mm.
- Medication instruction builder should reduce typing with dose, frequency, timing, and note controls.
- Warnings must be compact and operational; allergy/drug warning automation is future scope unless a drug interaction model exists.

### Clinic Settings Workstation UX
- Settings should behave like a configuration workstation, not a long admin form.
- Keep clinic profile and document-number controls visible as separate surfaces.
- Show a compact "what will change" preview for HN and receipt number before save.
- Smart Exam presets should appear as a scannable list first, then expand into an editor only when needed.
- Do not show every preset form open at once; it increases accidental edits and scroll.
- Preset rows should show active/inactive state, service codes, item count, and sort order in the summary line.
- Keep settings quick wins schema-free unless a future audit/settings-history workflow is approved.
