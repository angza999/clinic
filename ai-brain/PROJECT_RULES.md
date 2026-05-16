# Project Rules: ดงมหาวันคลินิก

## 1. Coding Standard
- ใช้ PHP แบบอ่านง่ายและตรงไปตรงมา
- เปิด `strict_types=1` ในไฟล์ใหม่ที่เหมาะสม
- ใช้ prepared statements เสมอ
- ใช้ transaction เมื่อแก้หลาย table ที่ต้อง success/fail พร้อมกัน
- หลีกเลี่ยง abstraction ที่ทำให้ operational flow อ่านยาก
- เขียน code ให้ทีมที่ดูแล XAMPP/Laragon อ่านต่อได้

## 2. File Structure Rule
- `public/` = entry point + assets
- `app/Controllers/` = request handlers
- `app/Core/` = shared infrastructure / workflow utility
- `app/Views/` = server-rendered templates
- `config/` = app/database config
- `database/` = schema bootstrap
- `storage/` = runtime output
- `ai-brain/` = AI project context

## 3. Route Naming Convention
- ใช้ format `page` ใน query string
- GET page names เป็น noun หรือ noun-action เช่น:
  - `patients`
  - `queue`
  - `queue-exam`
  - `visit-edit`
- POST action names ควรชัดเจน เช่น:
  - `patients-store`
  - `queue-status`
  - `payments-store`

## 4. Controller Naming
- ใช้ `SomethingController`
- หนึ่ง controller ควร map กับหนึ่ง business area หลัก
- อย่ากระจาย queue logic ไปหลาย controller โดยไม่จำเป็น

## 5. View Naming
- ใช้ path ตาม module เช่น:
  - `patients/index`
  - `queue/exam`
  - `reports/index`
- layout พิเศษให้ใช้ใน `layouts/`

## 6. Naming Convention In Code
- PHP methods/variables: `camelCase`
- class names: `PascalCase`
- database tables/columns: `snake_case`
- route/page key: lower-case with hyphen

## 7. Security Rules
- POST ทุกตัวต้องผ่าน CSRF verification
- ทุก page/action ที่เป็น protected route ต้องมี `require_login()` หรือ `require_roles()`
- อย่า render data ดิบ; ใช้ `e()` สำหรับ output ใน view
- อย่ารับ role จาก client แล้วเชื่อทันที
- อย่าปล่อย SQL error ไหลออกหน้า production เกินจำเป็น

## 8. Database Rules
- ห้ามใช้ named placeholder ซ้ำใน statement เดียวเมื่อใช้ native prepares
- ถ้าจำเป็นต้อง bind ค่าเดียวกันหลายที่ ให้ใช้คนละ placeholder name
- Local setup/troubleshooting for MySQL, Navicat, XAMPP, and Laragon must be documented in `docs/database-setup.md`
- Do not commit real production database username/password into `config/database.php`
- ถ้าแก้ schema:
  - แก้ `database/schema.sql`
  - อัปเดต context files
- อย่าสร้าง schema drift แบบเงียบ

## 9. Workflow Integrity Rules
- queue status transition ต้องผ่าน helper rule
- payment ต้องอิงรายการ service/item จริง
- stock usage ต้อง trace ผ่าน `stock_movements`
- remove item usage ต้องคืน stock กลับ batch
- finish case mode `payment` ต้องมี billable item

## 10. Smart Exam Rules
- Smart Exam คือ primary nurse workflow
- ห้ามเพิ่ม friction โดยไม่จำเป็น
- field บังคับก่อน finish คืออย่างน้อย `CC` และ `Dx`
- preset logic ต้อง deterministic และอ่านตาม code ได้

## 11. UI Rules
- รักษา Medical Minimal UI
- อย่าใช้ component library ใหม่โดยไม่มีเหตุผล
- อย่าทำ admin-heavy table UI มาแทน workflow UI
- ใช้ card และ summary panel มากกว่าฟอร์มยาวแบน ๆ

## 12. JavaScript Rules
- JS ควรเป็น progressive enhancement
- อย่าทำ business-critical flow ผูกกับ JS อย่างเดียวถ้าไม่จำเป็น
- ถ้า JS fail ผู้ใช้ควรยังใช้ core flow ได้ระดับหนึ่ง

## 13. Error Handling Rules
- Error message ต้องช่วยผู้ใช้เข้าใจว่าแก้อะไรต่อ
- ถ้า logic กระทบหลายตาราง ให้ rollback เมื่อ fail
- อย่ากลบ exception โดยไม่ flash message หรือ log reason

## 14. Documentation Rules
- ทุก task ต้องใช้ `DEVELOPMENT_CHECKLIST.md` เป็น checklist กลางก่อนปิดงาน
- ทุก feature ใหม่ต้อง update `ai-brain`
- ทุก schema change ต้อง update `DATABASE_SCHEMA.md`
- ทุก flow change ต้อง update `SYSTEM_FLOW.md`
- ทุก UX pattern change ต้อง update `UI_UX_GUIDE.md`

## 15. Forbidden Changes Without Deliberate Review
- เปลี่ยน queue status model
- เปลี่ยน numbering format ของ HN/VN/receipt
- เปลี่ยน payment lifecycle
- เปลี่ยน stock movement model
- เปลี่ยน Smart Exam จาก single-screen ไปเป็น multi-screen flow

## 16. Testing Expectations
- ถ้าแก้ Smart Exam, Queue, Payment หรือ Stock:
  - ตรวจ syntax
  - ทดสอบ flow จริงอย่างน้อย 1 รอบ
- ถ้าแก้ schema/query:
  - เช็กกับ native PDO behavior

## 17. AI Work Protocol
ก่อนทำงานต้องอ่าน `DEVELOPMENT_CHECKLIST.md` และก่อนปิดงานต้องยืนยันว่าผ่าน checklist หมวด verification และ documentation
ก่อนทำงาน:
1. อ่าน `AI_CONTEXT.md`
2. อ่านไฟล์ context เฉพาะด้านที่เกี่ยวข้อง
3. ดู route/controller/schema ที่กระทบจริง

หลังทำงาน:
1. อัปเดต context files
2. บันทึกลง `CHANGELOG_AI.md` เมื่อมีผลเชิงระบบ
3. เพิ่ม known issue ถ้ายังมี debt ค้าง
