ALTER TABLE `patients`
  ADD COLUMN `photo_path` varchar(255) DEFAULT NULL AFTER `note`;
