# Known Issues: ดงมหาวันคลินิก

## Purpose
บันทึกปัญหาปัจจุบันและ technical debt ที่ AI ต้องรับรู้ก่อนขยายระบบต่อ

## Current Issues

## 1. No Formal Migration System
Impact:
- schema change กระจายอยู่ที่ `database/schema.sql`
- บาง field ถูก guard ด้วย runtime alter

Risk:
- environment drift
- prod/dev schema ไม่ตรงกัน

Recommendation:
- วาง lightweight migration strategy ในอนาคต

## 2. Runtime Schema Patch In QueueController
Current State:
- `QueueController::ensureSmartExamSchema()` เติม column runtime หากยังไม่มี

Impact:
- ช่วยให้ระบบรันต่อได้
- แต่ไม่ใช่ long-term architecture ที่ดี

Recommendation:
- ย้ายไป migration แบบชัดเจนเมื่อพร้อม

## 3. No Automated Test Suite
Impact:
- regression ใน queue / Smart Exam / payment / stock ตรวจพบช้า

Recommendation:
- เริ่มจาก smoke test checklist หรือ lightweight integration tests ก่อน

## 4. Seed User Password Strategy ยังไม่พร้อม production
Current State:
- seed default users ใช้ค่า password แบบเรียบง่าย
- auth รองรับทั้ง hash และ plain-text fallback

Impact:
- สะดวกสำหรับ dev setup
- ไม่เหมาะกับ production

Recommendation:
- เปลี่ยน seed เป็น password hash
- ปิด plain-text fallback ใน production-ready phase

## 5. Appointment Module ยังไม่ครบวงจร
Current State:
- มี schema และการสร้างนัดผ่าน follow-up date
- ยังไม่มีหน้า calendar/agenda module เต็มรูปแบบ

Impact:
- follow-up ใช้ได้ระดับพื้นฐาน
- ยังไม่ใช่ scheduling system เต็มตัว

## 6. Audit Log Schema มี แต่การใช้งานยังไม่ครบ
Current State:
- ตาราง `audit_logs` มีแล้ว
- ยังไม่ได้บันทึกทุก action สำคัญ

Impact:
- trace การแก้ข้อมูลย้อนหลังได้ไม่ครบ

## 7. Backup Strategy ยังเป็น Full SQL Dump ผ่าน Web Request
Impact:
- ง่ายและใช้ได้เร็ว
- ไม่เหมาะกับข้อมูลขนาดใหญ่หรือ multi-tenant scale

Recommendation:
- File-based retention now keeps the latest 30 `clinic_backup_*.sql` files.
- Background export, offsite backup, and auditable backup log table are still future work.

## 8. PDO Native Prepare Requires Unique Placeholder Names
Current State:
- ระบบใช้ `PDO::ATTR_EMULATE_PREPARES = false`

Impact:
- query ที่ใช้ named placeholder ซ้ำใน statement เดียวมีโอกาสพังด้วย `HY093`

Recommendation:
- ตรวจ query ใหม่ทุกครั้ง
- ใช้ placeholder คนละชื่อแม้ bind ค่าเดียวกัน

## 9. Smart Exam Coupling สูง
Current State:
- หน้าเดียวแตะ queue, visit, vitals, services, stock, payment handoff

Impact:
- ดีต่อ UX
- แต่เสี่ยงต่อ regression เชิง workflow

Recommendation:
- แก้แบบระวัง side effects
- เพิ่ม checklist ทุกครั้งที่แตะ flow นี้

## 10. Reporting ยังเป็น Live Query Oriented
Impact:
- ข้อมูลง่ายและตรง
- ถ้าข้อมูลโตมากอาจเริ่มช้า

Recommendation:
- หาก scale โต ให้พิจารณา report snapshot/materialized summary

## 11. Medical Documentation Depth ยังเป็น Light Clinical Record
Current State:
- ระบบรองรับ nursing/operational documentation ได้ดี
- ยังไม่ใช่ EMR ระดับเต็มพร้อม coding, allergy engine, medication safety system

Impact:
- เหมาะกับคลินิก workflow-first
- ไม่ควร over-claim capability

## Recently Resolved But Important To Remember

## A. Smart Exam Redirect Regression
Resolved:
- add/remove service จาก Smart Exam เคยเด้งไป `visit-edit`
- ตอนนี้ถูกแก้แล้ว

Lesson:
- ทุก action ที่มาจาก Smart Exam ต้อง preserve `return_to=queue-exam`

## B. Nurse Finish Redirect
Resolved:
- nurse finish case เคยถูก redirect ไปหน้า payments ทั้งที่ไม่มีสิทธิ์
- ตอนนี้กลับหน้า queue แล้ว

Lesson:
- flow transition ต้อง respect role boundary

## C. Finish Case Placeholder Error
Resolved:
- เคยเกิด `SQLSTATE[HY093]: Invalid parameter number`

Lesson:
- ห้ามใช้ named placeholder ซ้ำใน statement เดียว

## AI Maintenance Rule
ถ้าเจอ bug ใหม่:
1. เพิ่ม entry ในไฟล์นี้
2. ถ้า bug กระทบ workflow ให้ update `SYSTEM_FLOW.md`
3. ถ้า bug กระทบ decision เชิงสถาปัตยกรรม ให้ update `DECISIONS.md`
