-- Production readiness migration for existing installations.
-- Run after pulling the Production Readiness phase if the database was created
-- before backup_logs was added to database/schema.sql.

CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    receipt_count INT UNSIGNED NOT NULL DEFAULT 0,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pending_work_count INT UNSIGNED NOT NULL DEFAULT 0,
    retention_limit INT UNSIGNED NOT NULL DEFAULT 30,
    status ENUM('CREATED','FAILED') NOT NULL DEFAULT 'CREATED',
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_backup_logs_created_at (created_at),
    KEY idx_backup_logs_created_by (created_by),
    CONSTRAINT fk_backup_logs_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
