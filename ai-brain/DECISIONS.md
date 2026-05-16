# Decisions: ดงมหาวันคลินิก

## Purpose
บันทึก architectural และ UX decisions สำคัญ เพื่อให้ AI ไม่แก้ระบบสวนทางกับเหตุผลเดิม

## ADR-001: Use Server-Rendered PHP Instead Of SPA
Decision:
- ใช้ PHP server-rendered architecture

Reason:
- ติดตั้งง่ายบน XAMPP/Laragon
- ทีมดูแลอ่านและแก้ง่าย
- ลด operational complexity
- เหมาะกับคลินิกที่ต้องการ deploy เร็ว

Tradeoff:
- interactivity บางส่วนต้องใช้ JS enhancement เฉพาะจุด
- UI state management ไม่ยืดหยุ่นเท่า SPA

## ADR-002: Queue-Centric Visit Lifecycle
Decision:
- ให้ `queue_entries.status` เป็น operational state หลักของ visit

Reason:
- หน้างานคลินิกคิดเป็น "คิวตอนนี้อยู่ขั้นไหน"
- staff เข้าใจง่ายกว่า state machine ที่ซ่อนอยู่ใน visit

Tradeoff:
- queue module กลายเป็นจุดศูนย์กลางของ workflow

## ADR-003: Smart Exam As Primary Nurse Workspace
Decision:
- Smart Exam เป็นหน้า default สำหรับการตรวจแบบเร็ว

Reason:
- ลดการสลับหน้า
- พยาบาลต้องทำงานเร็วกว่า detail-heavy form
- ระบบ clinic นี้เน้น throughput และความชัดเจน

Tradeoff:
- coupling กับหลาย subsystem
- ต้องระวัง regression สูง

## ADR-004: Keep Visit Detail As Secondary Deep-Edit Path
Decision:
- คงหน้า `visit-edit` สำหรับเคสที่ต้องลงรายละเอียด

Reason:
- ไม่ทุกเคสต้องใช้ form ลึก
- แต่ระบบยังต้องมีทางแก้ข้อมูลเชิงลึก

Tradeoff:
- มี 2 path สำหรับ visit
- ต้องรักษา consistency ระหว่าง Smart Exam กับ Visit Detail

## ADR-005: Batch-Level Inventory With Movement Log
Decision:
- ใช้ `inventory_batches` + `stock_movements`

Reason:
- trace stock ได้
- รองรับ expiry-aware usage
- รองรับต้นทุนต่อ batch

Tradeoff:
- logic ซับซ้อนกว่า stock summary table
- แต่เหมาะกับคลินิกจริงมากกว่า

## ADR-006: Payment Happens After Clinical Completion
Decision:
- payment flow ต้องเริ่มหลัง queue status เป็น `WAITING_PAYMENT`

Reason:
- แยกหน้าที่ระหว่าง nurse กับ cashier
- ลดความสับสน
- บังคับให้ clinical entry กับ billing entry complete ก่อนคิดเงิน

Tradeoff:
- มี handoff ระหว่าง role
- ต้องมีทาง send back ไปห้องตรวจเมื่อ billing ยังไม่ถูก

## ADR-007: One Payment Row Per Visit
Decision:
- ใช้ unique payment per visit

Reason:
- billing model ง่าย
- receipt trace ตรง
- เหมาะกับคลินิก workflow เดียวต่อ visit

Tradeoff:
- ไม่รองรับ split payment / installment แบบซับซ้อน

## ADR-008: Running Number By Dedicated Table
Decision:
- ใช้ `running_numbers` สำหรับ HN/VN/QUEUE/RECEIPT

Reason:
- คุม sequence ได้
- ใช้ transaction/lock เพื่อกันชนกัน
- ปรับ prefix ผ่าน settings ได้

Tradeoff:
- ต้องระวัง concurrency และ transaction order

## ADR-009: Use Native PDO Prepares
Decision:
- ตั้ง `PDO::ATTR_EMULATE_PREPARES = false`

Reason:
- behavior ใกล้ database จริง
- ลด ambiguity บางกรณี

Tradeoff:
- ห้ามใช้ named placeholder ซ้ำใน statement เดียว
- นักพัฒนาต้องระวังมากขึ้น

## ADR-010: Runtime Schema Guard Is Temporary
Decision:
- ยอมให้มี `ensureSmartExamSchema()` ชั่วคราว

Reason:
- ใช้ประคองระบบระหว่าง evolution ของ Smart Exam
- ลดโอกาสระบบพังใน environment ที่ schema ยังไม่อัปเดต

Tradeoff:
- ไม่ใช่แนวทางระยะยาว
- เสี่ยง drift

## ADR-011: Medical Minimal UI Over Admin-Dense UI
Decision:
- เลือก layout แบบ action cards, summary panels, clear sections

Reason:
- staff หน้างานไม่ได้ต้องการ data table หนาแน่นตลอดเวลา
- ต้องเห็น next action เร็ว

Tradeoff:
- บางหน้าจะมี detail น้อยกว่าระบบ back-office ทั่วไป

## ADR-012: Role-Specific Home Page
Decision:
- `ADMIN`/`NURSE` เริ่มที่ queue
- `CASHIER` เริ่มที่ payments

Reason:
- ลด click แรกหลัง login
- พาผู้ใช้เข้าหน้าหลักของงานตัวเองทันที

## ADR-013: Follow-Up Stored Both In Visit And Appointment
Decision:
- `followup_date` อยู่ใน `visits`
- และระบบสร้าง `appointments` เพื่อใช้ operationally

Reason:
- visit ต้องเก็บ clinical plan
- appointment ต้องเก็บ operational schedule

Tradeoff:
- ต้องระวัง consistency

## ADR-014: AI Context Is Part Of The Product
Decision:
- `ai-brain` ถือเป็น project artifact จริง ไม่ใช่เอกสารเสริม

Reason:
- โปรเจ็กต์นี้ต้องรองรับการทำงานร่วมกับ AI ระยะยาว
- context ที่ล้าสมัยทำให้ AI สร้าง regression ได้ง่าย

Rule:
- ทุก feature ใหม่ต้อง update context file ที่เกี่ยวข้อง

## Decision Rules For Future Changes
ก่อนเปลี่ยน architecture หรือ flow สำคัญ:
1. ต้องบันทึก decision ใหม่ในไฟล์นี้
2. ต้องระบุ tradeoff
3. ต้องอัปเดต flow/schema/UI docs ตามผลกระทบ
