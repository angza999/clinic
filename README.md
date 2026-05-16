# ดงมหาวันคลินิก

Web application สำหรับระบบบริหารจัดการคลินิกพยาบาลขนาดเล็ก พัฒนาด้วย `PHP + MySQL + Bootstrap 5`

## ภาพรวมระบบ
ระบบนี้ออกแบบให้เหมาะกับคลินิกขนาดเล็กที่มีจุดใช้งานหลัก 3 ส่วน
- Reception: ลงทะเบียนและรับคิว
- ห้องตรวจ/ห้องพยาบาล: บันทึกอาการ สัญญาณชีพ บริการ และยา
- การเงิน: รับชำระเงินและพิมพ์ใบเสร็จ

## ฟีเจอร์หลัก
- Login และกำหนดสิทธิ์ผู้ใช้ `Admin`, `Nurse`, `Cashier`
- ลงทะเบียนผู้รับบริการ พร้อมสร้าง `HN` อัตโนมัติ
- ระบบคิวรายวันและสถานะ `รอรับบริการ`, `กำลังตรวจ`, `รอชำระเงิน`, `เสร็จสิ้น`
- บันทึกอาการสำคัญ, vital signs, nursing note, คำแนะนำ, นัดติดตาม
- จัดการบริการและราคา
- คลังยา/เวชภัณฑ์ พร้อมแจ้งเตือน stock ต่ำและใกล้หมดอายุ
- ระบบชำระเงิน พร้อมใบเสร็จ/ใบรับบริการ
- Dashboard รายวันและรายเดือน
- Export รายงาน และ Backup ฐานข้อมูล
- แฟ้มประวัติผู้รับบริการย้อนหลัง
- หน้าจอเรียกคิวสำหรับ Reception/หน้าห้องตรวจ
- หน้าตั้งค่าคลินิก เช่น ชื่อคลินิก ที่อยู่ เบอร์โทร และ prefix เอกสาร

## เทคโนโลยี
- PHP 8+
- MySQL / MariaDB
- Bootstrap 5
- Server-rendered UI

## โครงสร้างโปรเจกต์
- `public/` จุดเริ่มต้นของระบบ และ static assets
- `app/Controllers/` controller ของแต่ละโมดูล
- `app/Core/` class หลักของระบบ เช่น auth, database, running number
- `app/Views/` หน้า UI
- `config/` ไฟล์ตั้งค่า app และ database
- `database/schema.sql` โครงสร้างฐานข้อมูลพร้อม seed เริ่มต้น
- `docs/system-design.md` เอกสารโครงสร้างระบบ
- `docs/database-setup.md` คู่มือตั้งค่า MySQL/Navicat และแก้ปัญหา connection
- `storage/` export, backup, log และไฟล์ชั่วคราว

## การติดตั้ง
1. สร้างฐานข้อมูล MySQL ชื่อ `dongmahawan_clinic`
2. import ไฟล์ `database/schema.sql`
3. แก้ค่าการเชื่อมต่อฐานข้อมูลใน `config/database.php`
4. ตั้ง web root ไปที่โฟลเดอร์ `public/`
5. เปิดระบบผ่าน Apache / Nginx หรือใช้ PHP built-in server

ถ้าเชื่อมต่อ MySQL/Navicat ไม่ผ่าน ให้อ่านคู่มือ:

```text
docs/database-setup.md
```

ตัวอย่างการรันด้วย PHP built-in server

```powershell
php -S localhost:8000 -t public
```

จากนั้นเปิดเบราว์เซอร์ที่

```text
http://localhost:8000
```

## บัญชีเริ่มต้น
- `admin / admin123`
- `nurse / nurse123`
- `cashier / cashier123`

หมายเหตุ:
- บัญชีเริ่มต้นใช้สำหรับทดสอบระบบหลัง import ฐานข้อมูล
- ควรเปลี่ยนรหัสผ่านหรือพัฒนาระบบ password hash ก่อนใช้งานจริง

## การใช้งานแบบย่อ
1. ลงทะเบียนผู้รับบริการ หรือค้นหาผู้รับบริการเดิม
2. สร้างคิววันนี้
3. เรียกเข้าตรวจ
4. บันทึกอาการ สัญญาณชีพ บริการ และยา/เวชภัณฑ์
5. ส่งไปการเงิน
6. รับชำระเงินและพิมพ์ใบเสร็จ

## เอกสารสำคัญในโปรเจกต์
- `database/schema.sql` โครงสร้างฐานข้อมูล
- `docs/system-design.md` โครงสร้างระบบ, ER, flow
- `docs/database-setup.md` ค่าเชื่อมต่อ MySQL/Navicat และ troubleshooting
- `config/database.php` การเชื่อมต่อฐานข้อมูล
- `public/index.php` route หลักของระบบ

## Git Workflow แบบง่าย
ใช้คำสั่ง 3 บรรทัดนี้สำหรับงานประจำวันหลังแก้โค้ดเสร็จ

```powershell
git add .
git commit -m "อธิบายสิ่งที่แก้"
git push
```

ตัวอย่าง

```powershell
git add .
git commit -m "Improve queue workflow and patient history"
git push
```

## เช็กสถานะก่อน commit

```powershell
git status
```

## ดูประวัติ commit

```powershell
git log --oneline --decorate --graph --all
```

## สร้าง release tag
ตัวอย่าง tag เวอร์ชัน

```powershell
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## ข้อควรระวัง
- อย่า push ข้อมูลผู้ป่วยจริงขึ้น GitHub
- อย่าเก็บไฟล์ backup/export จริงใน repository
- อย่าเก็บ secret จริง เช่น รหัสผ่าน production database
- ตรวจ `.gitignore` ทุกครั้งเมื่อมีไฟล์ใหม่ใน `storage/`

## ไฟล์ที่ไม่ควรขึ้น Git
โปรเจกต์นี้มี `.gitignore` สำหรับกันไฟล์ประเภทนี้แล้ว
- `storage/exports/`
- `storage/backups/`
- `storage/logs/`
- `storage/cache/`
- `storage/sessions/`
- `.env`
- `vendor/`

## สถานะเวอร์ชัน
- เวอร์ชันเริ่มต้นของ repository: `v1.0.0`

## Roadmap ที่แนะนำต่อ
1. จัดการผู้ใช้และเปลี่ยนรหัสผ่าน
2. รายงานรายวัน/รายเดือนแบบพิมพ์ได้
3. Backup แบบกดจากหน้า Admin
4. สิทธิ์ละเอียดขึ้นตามบทบาทงานจริง
5. เตรียมระบบสำหรับใช้งานจริงหลายเครื่องในคลินิก
