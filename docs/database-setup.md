# Database Setup Guide

คู่มือนี้ใช้สำหรับตั้งค่า MySQL/MariaDB ของโปรเจค **ดงมหาวันคลินิก** บนเครื่อง local เช่น XAMPP, Laragon, Navicat หรือ phpMyAdmin

## ค่าฐานข้อมูลปัจจุบันของโปรเจค

ไฟล์ที่ระบบใช้อ่านค่าการเชื่อมต่อ:

```text
config/database.php
```

ค่า local default:

| รายการ | ค่า |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `dongmahawan_clinic` |
| Username | `root` |
| Password | ว่าง |
| Charset | `utf8mb4` |

> ถ้าใช้ XAMPP/Laragon แบบ local ปกติ ให้เริ่มจาก `root` และ password ว่างก่อน อย่าใช้ user `dongmahawan_clinic` เว้นแต่สร้าง user นี้ใน MySQL แล้วจริง

## ขั้นตอนติดตั้งฐานข้อมูลแบบเร็ว

1. เปิด MySQL/MariaDB ใน XAMPP หรือ Laragon
2. สร้าง database ชื่อ `dongmahawan_clinic`
3. Import ไฟล์ `database/schema.sql`
4. ตรวจค่าใน `config/database.php`
5. เปิดระบบที่ `http://localhost:8000`

## SQL สำหรับสร้าง Database

ใช้ใน Navicat, phpMyAdmin หรือ MySQL console:

```sql
CREATE DATABASE IF NOT EXISTS dongmahawan_clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

จากนั้น import:

```text
database/schema.sql
```

## Navicat Connection สำหรับ Local/XAMPP

กรณีง่ายสุดสำหรับเครื่อง local:

| Field | Value |
|---|---|
| Connection Name | `dongmahawan_clinic_local` |
| Host | `127.0.0.1` |
| Port | `3306` |
| User Name | `root` |
| Password | ว่าง |
| Save password | เลือกได้ |

หลัง Test Connection ผ่าน ให้เลือก database:

```text
dongmahawan_clinic
```

## ถ้าต้องการใช้ User เฉพาะของโปรเจค

ถ้าอยากใช้ username `dongmahawan_clinic` แทน `root` ต้องสร้าง user ก่อน:

```sql
CREATE USER IF NOT EXISTS 'dongmahawan_clinic'@'localhost'
IDENTIFIED BY 'change_this_password';

CREATE USER IF NOT EXISTS 'dongmahawan_clinic'@'127.0.0.1'
IDENTIFIED BY 'change_this_password';

GRANT ALL PRIVILEGES ON dongmahawan_clinic.*
TO 'dongmahawan_clinic'@'localhost';

GRANT ALL PRIVILEGES ON dongmahawan_clinic.*
TO 'dongmahawan_clinic'@'127.0.0.1';

FLUSH PRIVILEGES;
```

แล้วแก้ `config/database.php`:

```php
'username' => 'dongmahawan_clinic',
'password' => 'change_this_password',
```

## Error ที่พบบ่อย

### `Access denied for user 'dongmahawan_clinic'@'localhost' (using password: NO)`

สาเหตุ:
- กำลังใช้ user `dongmahawan_clinic`
- แต่ไม่ได้ใส่ password
- หรือ user นี้ยังไม่ได้ถูกสร้างใน MySQL

วิธีแก้เร็ว:
- เปลี่ยน Navicat เป็น `root`
- Password ว่าง
- Host `127.0.0.1`
- Port `3306`

วิธีแก้ถาวร:
- สร้าง user `dongmahawan_clinic` ด้วย SQL ด้านบน
- ใส่ password ให้ตรงทั้ง Navicat และ `config/database.php`

### `Access denied for user 'root'@'localhost'`

สาเหตุ:
- MySQL local มี password ของ root แล้ว
- `config/database.php` ยังตั้ง password ว่าง

วิธีแก้:
- ใส่ password root ใน Navicat
- แก้ `config/database.php` ให้ตรงกับ password จริง

### `Unknown database 'dongmahawan_clinic'`

สาเหตุ:
- ยังไม่ได้สร้าง database
- หรือชื่อ database สะกดไม่ตรง

วิธีแก้:
- รัน SQL `CREATE DATABASE`
- import `database/schema.sql`
- ตรวจชื่อใน `config/database.php`

### `SQLSTATE[HY000] [2002]`

สาเหตุ:
- MySQL/MariaDB ยังไม่ทำงาน
- port ไม่ใช่ `3306`
- ใช้ host ผิด เช่น `localhost` แล้ว socket/path ไม่ตรง

วิธีแก้:
- เปิด MySQL ใน XAMPP/Laragon
- ใช้ host `127.0.0.1`
- ตรวจ port ใน XAMPP/Laragon ว่าเป็น `3306`

## ตรวจสอบผ่าน Command Line

ถ้าใช้ XAMPP บน Windows:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 -P 3306
```

ถ้า root มี password:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p -h 127.0.0.1 -P 3306
```

ตรวจว่ามี database หรือไม่:

```sql
SHOW DATABASES;
USE dongmahawan_clinic;
SHOW TABLES;
```

## ข้อควรระวัง Production

- ห้ามใช้ `root` ใน production
- ห้ามใช้ password ว่างใน production
- ห้าม commit password จริงขึ้น Git
- ควรสร้าง user เฉพาะของระบบพร้อมสิทธิ์เฉพาะ database นี้
- ควร backup ก่อน import schema หรือแก้ permission
