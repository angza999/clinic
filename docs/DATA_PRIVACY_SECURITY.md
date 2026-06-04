# Data Privacy And Security Notes

This project stores sensitive patient and clinic data. Use these rules before production use.

## Patient Data

- Limit system access to named users.
- Avoid shared accounts except during initial setup.
- Disable inactive users.
- Review `audit_logs` for critical actions.

## Patient Card Photos

- Patient card photos are stored under `storage/patient-photos`.
- The app serves photos through a protected route, not a direct public file path.
- Do not copy the storage folder to public web directories.

## Backups

- SQL backup files may contain patient, payment, and clinical data.
- Store backups in a secure location.
- Do not keep backups only on the same workstation.
- Consider encrypting offsite backups.

## Import Files

- Uploaded/imported files should be treated as sensitive.
- Keep `storage/imports` outside the public web path.
- Periodically delete old import staging files after confirm/rollback.

## Permissions

- Admin: settings, users, reports, backup, import, inventory/service management.
- Nurse: queue, Smart Exam, patients, inventory operation, pharmacy labels.
- Cashier: payments, receipts, pharmacy label view/print if needed.

## Production Hardening Still Recommended

- Add formal migration version tracking.
- Expand audit logs for every high-risk mutation.
- Add automated regression tests for Smart Exam, payment, import, and stock movement.
