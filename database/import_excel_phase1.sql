SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_type` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `stored_file_path` varchar(255) DEFAULT NULL,
  `total_rows` int unsigned NOT NULL DEFAULT 0,
  `success_rows` int unsigned NOT NULL DEFAULT 0,
  `error_rows` int unsigned NOT NULL DEFAULT 0,
  `duplicate_rows` int unsigned NOT NULL DEFAULT 0,
  `status` enum('UPLOADED','VALIDATED','CONFIRMED','FAILED','CANCELLED') NOT NULL DEFAULT 'UPLOADED',
  `options_json` longtext DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_import_logs_type_status` (`import_type`,`status`),
  KEY `idx_import_logs_created_by` (`created_by`),
  CONSTRAINT `fk_import_logs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_log_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_log_id` bigint unsigned NOT NULL,
  `row_number` int unsigned NOT NULL,
  `row_data_json` longtext NOT NULL,
  `mapped_data_json` longtext DEFAULT NULL,
  `status` enum('PENDING','VALID','ERROR','DUPLICATE','IMPORTED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_import_log_rows_log_status` (`import_log_id`,`status`),
  CONSTRAINT `fk_import_log_rows_log_id` FOREIGN KEY (`import_log_id`) REFERENCES `import_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
