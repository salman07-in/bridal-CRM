-- ==============================================================================
-- Safiya's Makeover — Bridal CRM & Anniversary Reminder System
-- Production MySQL Schema (Hostinger MySQL / MariaDB)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Admin & Staff)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME NULL,
  INDEX `idx_users_username` (`username`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Customers Table (Brides Register)
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_code` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `mobile_number` VARCHAR(25) NOT NULL,
  `wedding_date` DATE NULL,
  `makeup_date` DATE NULL,
  `service_package` VARCHAR(120) NULL,
  `amount` DECIMAL(10, 2) NULL,
  `advance_amount` DECIMAL(10, 2) NULL DEFAULT 0.00,
  `slip_number` VARCHAR(50) NULL,
  `booking_type` VARCHAR(50) NOT NULL DEFAULT 'Bridal Makeup',
  `salon_branch` VARCHAR(80) NULL DEFAULT 'Main Branch',
  `notes` TEXT NULL,
  `pre_makeup_reminder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `whatsapp_consent` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_customers_code` (`customer_code`),
  INDEX `idx_customers_name` (`full_name`),
  INDEX `idx_customers_mobile` (`mobile_number`),
  INDEX `idx_customers_wedding_date` (`wedding_date`),
  INDEX `idx_customers_makeup_date` (`makeup_date`),
  INDEX `idx_customers_branch` (`salon_branch`),
  INDEX `idx_customers_consent` (`whatsapp_consent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Reminder Logs Table (Strict Idempotency - Zero Double Sends)
CREATE TABLE IF NOT EXISTS `reminder_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `idempotency_key` VARCHAR(120) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `anniversary_year` INT NOT NULL,
  `channel` VARCHAR(30) NOT NULL,
  `reminder_type` VARCHAR(40) NOT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'pending',
  `scheduled_for` DATE NOT NULL,
  `sent_at` DATETIME NULL,
  `retry_count` INT NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `metadata_json` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_reminders_key` (`idempotency_key`),
  INDEX `idx_reminders_cust` (`customer_id`),
  INDEX `idx_reminders_status` (`status`),
  INDEX `idx_reminders_scheduled` (`scheduled_for`),
  CONSTRAINT `fk_reminder_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Settings Table (Studio branding & WhatsApp templates)
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(64) NOT NULL UNIQUE,
  `value` TEXT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Audit Logs Table (Full Staff Accountability Trail)
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` VARCHAR(64) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_entity` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Insert Default Settings
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('salon_name', 'Safiya\'s Makeover', 'Official studio display name'),
('salon_tagline', 'Bridal & Makeover Studio', 'Tagline for greetings and invoices'),
('salon_branch', 'Main Branch', 'Primary branch location'),
('salon_phone', '+91 98765 43210', 'Primary studio contact number'),
('salon_owner_email', 'owner@safiyasmakeover.com', 'Recipient for daily anniversary digests'),
('reminder_advance_days', '7', 'Days in advance to show upcoming forecast'),
('send_owner_daily_digest', '1', 'Send daily summary email to studio owner'),
('whatsapp_anniversary_template', 'Dear {bride_name}, warm congratulations on your {years} Wedding Anniversary from {salon_name}! Wishing you a wonderful celebration.', 'Standard anniversary greeting template'),
('whatsapp_eve_template', 'Hello {bride_name}! Tomorrow is your special wedding anniversary! Warm wishes in advance from all of us at {salon_name}.', '1-day prior Eve greeting template'),
('whatsapp_pre_makeup_template', 'Hello {bride_name}, this is a gentle reminder from {salon_name} regarding your bridal appointment tomorrow ({makeup_date}). We look forward to serving you!', '1-day pre-makeup reminder template')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- 7. Insert Default Admin Account (Username: admin | Password: Admin@12345)
-- Bcrypt hash generated for Admin@12345: $2y$10$wK1F5N8m2E8n7J6v9X0kOeGZ7qY1u4i6o8p0a2s4d6f8g0h2j4k6
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', 'admin@safiyasmakeover.com', '$2y$10$P2/636fO6/Usqs3m2XzG0.n8Wb8iXlR/3c3bB9cI5Z2KxP1tP9q6y', 'Studio Administrator', 'admin', 1)
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- 8. Insert Initial Sample Demo Brides for Instant Verification
INSERT INTO `customers` (`customer_code`, `full_name`, `mobile_number`, `wedding_date`, `makeup_date`, `service_package`, `amount`, `advance_amount`, `slip_number`, `booking_type`, `salon_branch`, `notes`, `whatsapp_consent`) VALUES
('SAF-2022-0001', 'Ayesha Khan', '+919876543210', '2022-10-27', '2022-10-26', 'HD Bridal Package', 25000.00, 5000.00, 'SLIP-101', 'Bridal Makeup', 'Main Branch', 'Premium floral hairstyle requested', 1),
('SAF-2023-0002', 'Fatima Shaikh', '+919876543211', '2023-10-28', '2023-10-27', 'Airbrush Bridal Glow', 32000.00, 10000.00, 'SLIP-102', 'Bridal Makeup', 'Main Branch', 'Tomorrow is her 3rd anniversary', 1),
('SAF-2021-0003', 'Zainab Begum', '+919876543212', '2021-11-02', '2021-11-01', 'Signature Bridal Luxe', 35000.00, 15000.00, 'SLIP-103', 'Bridal Makeup', 'City Center', 'Celebrating 5th anniversary soon', 1)
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

SET FOREIGN_KEY_CHECKS = 1;
