SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS `dongmahawan_clinic`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `dongmahawan_clinic`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `import_log_rows`;
DROP TABLE IF EXISTS `import_logs`;
DROP TABLE IF EXISTS `smart_exam_presets`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `medication_print_logs`;
DROP TABLE IF EXISTS `prescription_items`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `drug_profiles`;
DROP TABLE IF EXISTS `visit_item_usages`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `inventory_batches`;
DROP TABLE IF EXISTS `inventory_items`;
DROP TABLE IF EXISTS `visit_services`;
DROP TABLE IF EXISTS `service_price_history`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `visit_vitals`;
DROP TABLE IF EXISTS `queue_entries`;
DROP TABLE IF EXISTS `visits`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `running_numbers`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `roles` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(30) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `idx_users_role_id` (`role_id`),
  CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_logs` (
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

CREATE TABLE `import_log_rows` (
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

CREATE TABLE `patients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hn` varchar(30) NOT NULL,
  `citizen_id` varchar(20) DEFAULT NULL,
  `title_name` varchar(30) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('M','F','O') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `underlying_disease` text DEFAULT NULL,
  `drug_allergy` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_patients_hn` (`hn`),
  KEY `idx_patients_phone` (`phone`),
  KEY `idx_patients_citizen_id` (`citizen_id`),
  KEY `idx_patients_name` (`first_name`,`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_no` varchar(40) NOT NULL,
  `patient_id` bigint unsigned NOT NULL,
  `visit_datetime` datetime NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `present_illness` text DEFAULT NULL,
  `physical_exam` text DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `nursing_note` text DEFAULT NULL,
  `advice` text DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visits_visit_no` (`visit_no`),
  KEY `idx_visits_patient_id` (`patient_id`),
  KEY `idx_visits_visit_datetime` (`visit_datetime`),
  KEY `idx_visits_created_by` (`created_by`),
  CONSTRAINT `fk_visits_patient_id` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_visits_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `queue_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `queue_date` date NOT NULL,
  `queue_no` int unsigned NOT NULL,
  `status` enum('WAITING','IN_SERVICE','WAITING_PAYMENT','COMPLETED','CANCELLED') NOT NULL DEFAULT 'WAITING',
  `checked_in_at` datetime DEFAULT NULL,
  `called_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_queue_entries_visit_id` (`visit_id`),
  UNIQUE KEY `uk_queue_entries_daily_queue` (`queue_date`,`queue_no`),
  KEY `idx_queue_entries_status` (`status`),
  CONSTRAINT `fk_queue_entries_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visit_vitals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `bp_systolic` int DEFAULT NULL,
  `bp_diastolic` int DEFAULT NULL,
  `temp_c` decimal(4,1) DEFAULT NULL,
  `pulse_rate` int DEFAULT NULL,
  `resp_rate` int DEFAULT NULL,
  `spo2` int DEFAULT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visit_vitals_visit_id` (`visit_id`),
  KEY `idx_visit_vitals_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_visit_vitals_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_code` varchar(50) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_services_service_code` (`service_code`),
  KEY `idx_services_service_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_price_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint unsigned NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_price_history_service_id` (`service_id`),
  KEY `idx_service_price_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_service_price_history_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_service_price_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `smart_exam_presets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `preset_key` varchar(80) NOT NULL,
  `label` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `theme` varchar(80) DEFAULT NULL,
  `service_codes` text DEFAULT NULL,
  `item_codes_json` text DEFAULT NULL,
  `cc` varchar(255) DEFAULT NULL,
  `pi` text DEFAULT NULL,
  `pe` text DEFAULT NULL,
  `dx` varchar(255) DEFAULT NULL,
  `advice` text DEFAULT NULL,
  `followup_days` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 50,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_smart_exam_presets_key` (`preset_key`),
  KEY `idx_smart_exam_presets_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visit_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remark` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visit_services_visit_id` (`visit_id`),
  KEY `idx_visit_services_service_id` (`service_id`),
  CONSTRAINT `fk_visit_services_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_services_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_type` enum('DRUG','SUPPLY') NOT NULL DEFAULT 'DRUG',
  `unit_name` varchar(50) NOT NULL,
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inventory_items_item_code` (`item_code`),
  KEY `idx_inventory_items_name` (`item_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `lot_no` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `qty_in` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `received_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_batches_item_id` (`item_id`),
  KEY `idx_inventory_batches_expiry_date` (`expiry_date`),
  CONSTRAINT `fk_inventory_batches_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST') NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `movement_datetime` datetime NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_batch_id` (`batch_id`),
  KEY `idx_stock_movements_item_id` (`item_id`),
  KEY `idx_stock_movements_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_stock_movements_batch_id` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_movements_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_stock_movements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visit_item_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usage_note` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visit_item_usages_visit_id` (`visit_id`),
  KEY `idx_visit_item_usages_item_id` (`item_id`),
  CONSTRAINT `fk_visit_item_usages_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_item_usages_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `drug_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `drug_short_name` varchar(100) DEFAULT NULL,
  `drug_category` varchar(100) DEFAULT NULL,
  `default_dose_qty` varchar(30) DEFAULT NULL,
  `default_dose_unit` varchar(50) DEFAULT NULL,
  `default_frequency` varchar(100) DEFAULT NULL,
  `default_timing` varchar(100) DEFAULT NULL,
  `default_instruction` text DEFAULT NULL,
  `warning_text` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_drug_profiles_item_id` (`item_id`),
  KEY `idx_drug_profiles_category` (`drug_category`),
  CONSTRAINT `fk_drug_profiles_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `patient_id` bigint unsigned NOT NULL,
  `status` enum('DRAFT','READY','PRINTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prescriptions_visit_id` (`visit_id`),
  KEY `idx_prescriptions_patient_id` (`patient_id`),
  KEY `idx_prescriptions_status` (`status`),
  CONSTRAINT `fk_prescriptions_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prescriptions_patient_id` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prescriptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prescription_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint unsigned NOT NULL,
  `visit_item_usage_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `drug_name_snapshot` varchar(180) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_name` varchar(50) DEFAULT NULL,
  `instruction_text` text DEFAULT NULL,
  `warning_text` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prescription_items_usage_id` (`visit_item_usage_id`),
  KEY `idx_prescription_items_prescription_id` (`prescription_id`),
  KEY `idx_prescription_items_item_id` (`item_id`),
  CONSTRAINT `fk_prescription_items_prescription_id` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prescription_items_usage_id` FOREIGN KEY (`visit_item_usage_id`) REFERENCES `visit_item_usages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prescription_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `medication_print_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prescription_item_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned NOT NULL,
  `patient_id` bigint unsigned NOT NULL,
  `label_size` varchar(20) NOT NULL DEFAULT '58x40',
  `printer_mode` varchar(30) NOT NULL DEFAULT 'BROWSER',
  `status` enum('PRINTED','REPRINT') NOT NULL DEFAULT 'PRINTED',
  `payload_json` longtext DEFAULT NULL,
  `printed_by` bigint unsigned DEFAULT NULL,
  `printed_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_medication_print_logs_item` (`prescription_item_id`),
  KEY `idx_medication_print_logs_visit` (`visit_id`),
  KEY `idx_medication_print_logs_patient` (`patient_id`),
  CONSTRAINT `fk_medication_print_logs_item` FOREIGN KEY (`prescription_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_medication_print_logs_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_medication_print_logs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_medication_print_logs_user` FOREIGN KEY (`printed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint unsigned NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `subtotal_service` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal_item` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('CASH','TRANSFER','QR') NOT NULL DEFAULT 'CASH',
  `payment_status` enum('UNPAID','PAID','VOID') NOT NULL DEFAULT 'PAID',
  `paid_at` datetime DEFAULT NULL,
  `paid_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payments_visit_id` (`visit_id`),
  UNIQUE KEY `uk_payments_receipt_no` (`receipt_no`),
  KEY `idx_payments_paid_at` (`paid_at`),
  CONSTRAINT `fk_payments_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `purpose` varchar(150) DEFAULT NULL,
  `status` enum('SCHEDULED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appointments_patient_date` (`patient_id`,`appointment_date`),
  CONSTRAINT `fk_appointments_patient_id` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_visit_id` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clinic_name` varchar(200) NOT NULL,
  `clinic_address` text DEFAULT NULL,
  `clinic_phone` varchar(50) DEFAULT NULL,
  `clinic_tax_id` varchar(50) DEFAULT NULL,
  `receipt_logo_text` varchar(80) DEFAULT NULL,
  `receipt_footer` text DEFAULT NULL,
  `receipt_prefix` varchar(20) NOT NULL DEFAULT 'RC',
  `hn_prefix` varchar(20) NOT NULL DEFAULT 'HN',
  `expiry_alert_days` int NOT NULL DEFAULT 30,
  `queue_note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `detail_json` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `running_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number_type` varchar(30) NOT NULL,
  `running_date` date NOT NULL,
  `last_no` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_running_numbers_type_date` (`number_type`,`running_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `role_code`, `role_name`) VALUES
(1, 'ADMIN', 'Admin'),
(2, 'NURSE', 'Nurse'),
(3, 'CASHIER', 'Cashier');

INSERT INTO `users` (`role_id`, `full_name`, `username`, `password_hash`, `phone`, `is_active`) VALUES
(1, 'ผู้ดูแลระบบ', 'admin', 'admin123', '0800000000', 1),
(2, 'พยาบาล', 'nurse', 'nurse123', '0800000001', 1),
(3, 'แคชเชียร์', 'cashier', 'cashier123', '0800000002', 1);

INSERT INTO `system_settings` (`clinic_name`, `clinic_address`, `clinic_phone`, `receipt_prefix`, `hn_prefix`, `expiry_alert_days`, `queue_note`) VALUES
('ดงมหาวันคลินิก', 'กรุณากรอกที่อยู่คลินิก', '08x-xxx-xxxx', 'RC', 'HN', 30, 'ห้องน้ำอยู่ด้านนอกอาคาร');

INSERT INTO `services` (`service_code`, `service_name`, `category`, `price`, `is_active`) VALUES
('SRV001', 'ตรวจอาการทั่วไป', 'ตรวจทั่วไป', 150.00, 1),
('SRV002', 'ทำแผล', 'หัตถการ', 250.00, 1),
('SRV003', 'ฉีดยา', 'หัตถการ', 100.00, 1),
('SRV004', 'วัดสัญญาณชีพ', 'พยาบาล', 50.00, 1);

INSERT INTO `inventory_items` (`item_code`, `item_name`, `item_type`, `unit_name`, `reorder_level`, `default_cost`, `default_price`, `is_active`) VALUES
('MED001', 'Paracetamol 500mg', 'DRUG', 'เม็ด', 100.00, 0.80, 2.00, 1),
('MED002', 'Normal Saline', 'SUPPLY', 'ขวด', 10.00, 18.00, 25.00, 1),
('MED003', 'ผ้าก๊อซ', 'SUPPLY', 'ชิ้น', 50.00, 1.50, 3.00, 1);

INSERT INTO `inventory_batches` (`item_id`, `lot_no`, `expiry_date`, `qty_in`, `qty_balance`, `cost_per_unit`, `received_date`) VALUES
(1, 'PCM-2601', DATE_ADD(CURDATE(), INTERVAL 180 DAY), 500.00, 500.00, 0.80, CURDATE()),
(2, 'NS-2602', DATE_ADD(CURDATE(), INTERVAL 120 DAY), 40.00, 40.00, 18.00, CURDATE()),
(3, 'GAUZE-2601', DATE_ADD(CURDATE(), INTERVAL 240 DAY), 200.00, 200.00, 1.50, CURDATE());

INSERT INTO `stock_movements` (`batch_id`, `item_id`, `movement_type`, `qty`, `unit_cost`, `reference_type`, `reference_id`, `note`, `movement_datetime`, `created_by`)
SELECT id, item_id, 'IN', qty_in, cost_per_unit, 'BATCH_RECEIVE', id, 'Initial stock', NOW(), 1
FROM inventory_batches;
