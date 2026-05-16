# Development Checklist: ดงมหาวันคลินิก

## Purpose
ไฟล์นี้เป็น operational checklist สำหรับ AI coding assistant และนักพัฒนา
ใช้เพื่อลด context loss, ลด regression และบังคับให้ทุกงานอัปเดต `ai-brain` ตามจริง

## When To Use
- ใช้ก่อนเริ่มทุก task ที่มีการอ่าน/แก้โค้ด
- ใช้ก่อน merge, deploy, หรือส่งมอบงาน
- ใช้ทุกครั้งที่มี feature ใหม่, bugfix, schema change, UX change หรือ business rule change

## 1. Before Start
- อ่าน `ai-brain/AI_CONTEXT.md`
- อ่าน `ai-brain/PROJECT_RULES.md`
- อ่าน `ai-brain/MODULES.md`
- ถ้างานแตะ workflow ให้เปิด `ai-brain/SYSTEM_FLOW.md`
- ถ้างานแตะ schema/query ให้เปิด `ai-brain/DATABASE_SCHEMA.md`
- ถ้างานแตะ UI ให้เปิด `ai-brain/UI_UX_GUIDE.md`
- ถ้างานแตะ Smart Exam ให้เปิด `ai-brain/SMART_EXAM_LOGIC.md`
- ระบุให้ชัดว่างานครั้งนี้กระทบ module ไหน, route ไหน, controller ไหน, table ไหน
- ตรวจว่ามี role/permission กระทบหรือไม่
- ตรวจว่ามีผลต่อ `queue status`, `payment flow`, `stock movement`, `visit status` หรือไม่

## 2. Design Check Before Coding
- งานนี้ลดจำนวนคลิกหรืออย่างน้อยต้องไม่เพิ่ม friction โดยไม่จำเป็น
- งานนี้ยังคง Medical Minimal UI
- งานนี้ยังรองรับ single-screen workflow ถ้าเป็น nurse-facing flow
- งานนี้ไม่สร้าง hidden side effect ที่ trace ยาก
- งานนี้ไม่ทำให้ query หรือ state transition อ่านยากเกินจำเป็น
- ถ้ามี schema change ต้องวางแผนอัปเดต `database/schema.sql` และ `DATABASE_SCHEMA.md`
- ถ้ามี decision ใหม่ที่มีผลระยะยาว ต้องเตรียมบันทึกใน `DECISIONS.md`

## 3. During Implementation
- ใช้ prepared statements เสมอ
- ห้ามใช้ named placeholder ซ้ำใน statement เดียวเมื่อใช้ native PDO prepares
- ถ้าแก้หลายตารางที่ต้องสำเร็จพร้อมกัน ให้ใช้ transaction
- ถ้าเป็น stock logic ต้องมี movement trace กลับไปที่ต้นทางได้
- ถ้าเป็น payment logic ต้องอิงรายการ billable จริง
- ถ้าเป็น Smart Exam ต้องรักษา flow เร็วและตรง ไม่กลายเป็น generic admin form
- ถ้าแตะ role access ต้องทดสอบอย่างน้อย role ที่ได้รับผลกระทบจริง

## 4. Before Finish
- ตรวจ syntax ของไฟล์ PHP ที่แก้
- ทดสอบ flow จริงอย่างน้อย 1 รอบถ้างานกระทบ `queue`, `smart exam`, `payment`, หรือ `stock`
- ตรวจว่าการ redirect หลัง action ยังพาผู้ใช้กลับ flow ที่ถูกต้อง
- ตรวจว่าข้อมูลที่บันทึกสะท้อนในหน้าจอแก้ไข/ดูรายละเอียดครบ
- ตรวจว่าไม่มี regression กับ role ที่ไม่มีสิทธิ์
- ตรวจว่าไม่มี silent failure, SQL error, หรือ flash message ที่สับสน

## 5. Documentation Update Checklist
- อัปเดต `ai-brain/CHANGELOG_AI.md`
- อัปเดต `ai-brain/SYSTEM_FLOW.md` ถ้า flow เปลี่ยน
- อัปเดต `ai-brain/DATABASE_SCHEMA.md` ถ้า schema/query model เปลี่ยน
- อัปเดต `ai-brain/UI_UX_GUIDE.md` ถ้า pattern หน้าจอเปลี่ยน
- อัปเดต `ai-brain/MODULES.md` ถ้ามี module ใหม่หรือขอบเขต module เปลี่ยน
- อัปเดต `ai-brain/SMART_EXAM_LOGIC.md` ถ้า Smart Exam logic เปลี่ยน
- อัปเดต `ai-brain/KNOWN_ISSUES.md` ถ้าพบ debt หรือข้อจำกัดใหม่
- อัปเดต `ai-brain/FUTURE_FEATURES.md` ถ้ามี roadmap ใหม่
- อัปเดต `ai-brain/DECISIONS.md` ถ้ามีการตัดสินใจเชิงสถาปัตยกรรมหรือ UX ใหม่

## 6. Delivery Checklist
- สรุปสิ่งที่เปลี่ยนเป็นภาษาคนอ่านเข้าใจได้
- ระบุไฟล์สำคัญที่แก้
- ระบุสิ่งที่ทดสอบแล้ว
- ระบุสิ่งที่ยังไม่ได้ทดสอบหรือความเสี่ยงที่เหลือ
- ระบุ context files ที่ถูกอัปเดต

## 7. Required Mini Template For Every Task
```md
Task:
- งานที่กำลังทำคืออะไร

Impacted Areas:
- modules:
- routes/pages:
- controllers/views:
- database tables:
- roles:

Risk Check:
- queue:
- smart exam:
- payment:
- stock:

Docs To Update:
- CHANGELOG_AI.md
- ...

Verification:
- syntax:
- manual flow:
- notes:
```

## 8. Hard Stop Rules
- ถ้ายังไม่ได้อ่าน context ที่เกี่ยวข้อง ห้ามเริ่มแก้ logic สำคัญ
- ถ้ายังไม่เข้าใจผลกระทบต่อ queue/payment/stock ห้าม commit logic เปลี่ยน state
- ถ้ายังไม่ได้อัปเดต context files ที่เกี่ยวข้อง ห้ามถือว่างานเสร็จ
