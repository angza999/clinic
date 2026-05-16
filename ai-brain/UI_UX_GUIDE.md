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
