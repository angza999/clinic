# Dongmahawan Clinic Production Deployment Checklist

Use this checklist before moving a clinic workstation from pilot to daily production use.

## 1. Server Runtime

- Install XAMPP with Apache, PHP, and MySQL/MariaDB.
- Confirm PHP can connect to MySQL with the configured database name.
- Confirm these PHP extensions are enabled when used by the workflow:
  - `pdo_mysql`
  - `mbstring`
  - `gd` for patient card photo handling
  - `zip` for full Excel support through PhpSpreadsheet
- Run the app from the same base URL configured in `config/app.php`.

## 2. Database Setup

- Restore `database/schema.sql` for a new installation.
- Existing installations should run `database/production_readiness.sql` once after pulling this phase.
- Easiest command for existing installations:

```powershell
C:\xampp\php\php.exe tools\apply-production-readiness.php
```

- Create the first backup before entering real patient data.
- After restore, open `?page=production` as Admin and check:
  - Database schema
  - Backup directory
  - Audit log
  - Patient photo storage

## 3. Smart Card Setup

- Install Mosquitto.
- Install/enable the MOPH smartcard reader MQTT service.
- Run Dongmahawan Smart Card Bridge and confirm:
  - `http://127.0.0.1:8189/health` returns `ok: true`
  - The app can read card data from Patient Registration.
- Set the bridge and MOPH service to automatic start on the clinic computer.

## 4. Printer Setup

- Test browser print for:
  - Receipt
  - Daily/monthly reports
  - Medication sticker labels
- For stickers, start with 58x40 mm and set browser print scale to 100%.
- Disable browser header/footer for sticker printing.
- Direct TSC/Zebra/XPrinter integration is future scope; browser print is the supported Phase 1 path.

## 5. Backup / Restore

- Use Reports or Dashboard to download SQL backup daily.
- `storage/exports` stores local backup files.
- The system keeps the latest 30 `clinic_backup_*.sql` files.
- `backup_logs` records backup history in the database.
- Store a copy outside the clinic computer at least daily.

## 6. Smoke Test

Run:

```powershell
C:\xampp\php\php.exe tools\smoke-check.php
```

Then test the end-to-end clinic flow:

1. Register patient.
2. Open queue.
3. Open Smart Exam.
4. Add service.
5. Add medicine.
6. Print medication sticker.
7. Send to payment.
8. Receive payment.
9. Print receipt.
10. Download backup.

## Go-Live Rule

The system is production-ready for a small clinic when:

- Daily backup works and restore has been rehearsed.
- Smart card and printer are tested on the actual workstation.
- Admin/Nurse/Cashier accounts have correct permissions.
- A paper/manual fallback exists for the first pilot week.
