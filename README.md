# ดงมหาวันคลินิก

WebApp ระบบบริหารจัดการคลินิกพยาบาลสำหรับคลินิกขนาดเล็ก พัฒนาด้วย `PHP + MySQL + Bootstrap 5`

## ฟีเจอร์หลัก
- Login และกำหนดสิทธิ์ `Admin`, `Nurse`, `Cashier`
- ลงทะเบียนผู้รับบริการ พร้อมสร้าง `HN` อัตโนมัติ
- ระบบคิวรายวันและสถานะ `รอรับบริการ`, `กำลังตรวจ`, `รอชำระเงิน`, `เสร็จสิ้น`
- บันทึกอาการสำคัญ, vital signs, การให้บริการ, ยา/เวชภัณฑ์, คำแนะนำ, นัดติดตาม
- จัดการรายการบริการและราคา
- คลังยา/เวชภัณฑ์ พร้อมแจ้งเตือน stock ต่ำและใกล้หมดอายุ
- ชำระเงินและพิมพ์ใบเสร็จ
- Dashboard รายวัน/รายเดือน
- Export CSV สำหรับ Excel และ Backup SQL

## โครงสร้างโปรเจกต์
- `public/` front controller และ assets
- `app/Controllers/` logic ของแต่ละโมดูล
- `app/Views/` หน้า UI
- `config/` ตั้งค่า app และ database
- `database/schema.sql` โครงสร้างฐานข้อมูลพร้อม seed เริ่มต้น
- `docs/system-design.md` สรุป ER และ flow

## วิธีติดตั้ง
1. สร้างฐานข้อมูล MySQL และ import ไฟล์ [schema.sql](E:/Clinic/database/schema.sql)
2. แก้ค่าฐานข้อมูลใน [database.php](E:/Clinic/config/database.php)
3. ตั้ง DocumentRoot ไปที่ `E:/Clinic/public`
4. เปิดผ่าน Apache/Nginx หรือใช้ PHP built-in server เช่น `php -S localhost:8000 -t public`

## บัญชีเริ่มต้น
- `admin / admin123`
- `nurse / nurse123`
- `cashier / cashier123`

หมายเหตุ:
- seed เริ่มต้นใช้รหัสผ่าน plain text เพื่อให้ import แล้วเข้าใช้งานได้ทันที ควรเปลี่ยนเป็น password hash และเพิ่มหน้าจัดการผู้ใช้ในลำดับถัดไป
- export เป็นไฟล์ `CSV` ซึ่งเปิดด้วย Excel ได้ทันที
- backup เป็นไฟล์ `SQL`

## ลำดับที่แนะนำในการต่อยอด
1. เพิ่มหน้าจัดการผู้ใช้และเปลี่ยนรหัสผ่าน
2. เพิ่ม validation และ audit log ให้ครอบคลุมทุก action
3. เพิ่มใบคิวพิมพ์และเลขเรียกคิวบนจอ
4. เพิ่มรายงานนัดติดตามและประวัติผู้รับบริการแบบละเอียด

