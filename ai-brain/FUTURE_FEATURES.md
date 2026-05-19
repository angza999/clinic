# Future Features: ดงมหาวันคลินิก

## Goal
รายการนี้เป็น roadmap เชิงผลิตภัณฑ์และเชิงสถาปัตยกรรมสำหรับการขยายระบบในอนาคต

## Priority A: Near-Term Workflow Improvements

### 1. Full Appointment Module
- calendar / agenda view
- create / reschedule / cancel appointment
- appointment reminder workflow
- dashboard integration ที่ลึกขึ้น

### 2. Configurable Smart Exam Presets
- admin จัดการ preset ได้เอง
- map preset -> services/items/default text
- รองรับ protocol ตามประเภทหัตถการ

### 3. Receipt / Document Branding
- richer image logo upload remains future scope
- printer-friendly layout options remain future scope
- receipt/footer templates beyond a single footer remain future scope

### 4. Better User Management
- force password reset
- role edit rules ชัดขึ้น
- broader audit trail beyond user-management actions

## Priority B: Clinical Efficiency

### 5. Structured Diagnosis Catalog
- controlled Dx list
- shortcut by common clinic conditions
- optional ICD mapping

### 6. Drug Instruction Templates
- dose/frequency templates
- common dispense note shortcuts
- safer repeat prescribing pattern

### 7. Allergy / Contraindication Assistance
- warning layer ก่อนเพิ่ม item usage
- patient-specific allergy check

### 8. Procedure Sets / Order Sets
- หัตถการหรือ protocol ที่ bundle service + supply + note

## Priority C: Reporting & Operations

### 9. Report Snapshot / Summary Tables
- ลดภาระ live query
- รองรับข้อมูลมากขึ้น

### 10. Inventory Enhancements
- stock card per item
- movement history UI
- batch merge/close
- expired stock write-off workflow

### 11. Financial Enhancements
- void payment flow
- refund / correction flow
- more payment methods
- cash drawer summary

## Priority D: Platform Hardening

### 12. Formal Migration System
- versioned schema changes
- deployment safer ขึ้น

### 13. Automated Test Layer
- smoke test flow
- payment/stock regression tests

### 14. Audit Logging Expansion
- log critical mutations
- expand beyond login/password/user admin actions

### 15. Background Export / Backup Jobs
- scheduled backup
- configurable retention policy
- admin backup history log table
- offsite backup or cloud sync option

## Priority E: Advanced Product Direction

### 16. Multi-Branch / Multi-Clinic Support
- tenant-aware settings
- branch-aware queue and reports

### 17. Doctor Review Layer
- add physician role
- sign-off workflow
- clinical approval state

### 18. API / Integration Layer
- lab integration
- messaging/reminder integration
- external reporting connector

### 19. Barcode / QR Support
- inventory receive via barcode
- patient lookup / receipt lookup via QR

### 20. Analytics Dashboard 2.0
- patient revisit trends
- revenue by service category
- nurse workload / queue timing

## Non-Goals For Near Term
- ไม่เปลี่ยนระบบเป็น SPA เต็มรูปแบบ
- ไม่เพิ่ม framework หนักโดยไม่มีเหตุผลชัด
- ไม่ทำ EMR เต็มรูปแบบก่อน flow พื้นฐานนิ่ง

## AI Prioritization Guidance
ถ้าต้องเลือกทำงาน:
1. เลือกสิ่งที่ลด click / ลดเวลาในหน้างานก่อน
2. เลือกสิ่งที่ทำให้ stock/payment/queue traceable ก่อน
3. อย่าทำ feature advanced ที่ทำให้ core workflow ช้าลง
## Import Excel Phase 2

- รองรับ import `services`
- เพิ่ม background cleanup สำหรับไฟล์ใน `storage/imports`
- เพิ่ม export result report หลัง import
- เพิ่ม mode update existing records พร้อม diff preview
- เพิ่ม template `.xlsx` พร้อม sample row และ data validation dropdown
