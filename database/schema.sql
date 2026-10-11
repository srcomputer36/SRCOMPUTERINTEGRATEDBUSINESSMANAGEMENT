-- SR Computer Integrated Business Management System
-- Database Schema — MySQL/MariaDB InnoDB, utf8mb4_unicode_ci
-- Generated 2026-10-11, Asia/Dhaka
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET time_zone = '+06:00';

CREATE DATABASE IF NOT EXISTS `srcomputer` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `srcomputer`;

-- -------------------------------------------------
-- users
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- settings
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- customers
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `name_bn` VARCHAR(150) NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `legacy_id` VARCHAR(100) NULL,
  `legacy_row_num` INT NULL,
  `legacy_data` JSON NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_phone` (`phone`),
  INDEX `idx_name` (`name`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- suppliers
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `company_name` VARCHAR(150) NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `legacy_id` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_phone` (`phone`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- reference_persons
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `reference_persons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `relation` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- accounts (Cash / Bank / Mobile)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('cash','bank','mobile') NOT NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `account_no` VARCHAR(100) NULL,
  `description` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- income_expense_categories
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `income_expense_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_name_type` (`name`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- products
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `name_bn` VARCHAR(150) NULL,
  `sku` VARCHAR(50) NOT NULL,
  `category` VARCHAR(100) NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `purchase_price` DECIMAL(12,2) NOT NULL,
  `selling_price` DECIMAL(12,2) NOT NULL,
  `opening_stock` INT NOT NULL DEFAULT 0,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT NOT NULL DEFAULT 5,
  `description` TEXT NULL,
  `legacy_id` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_sku` (`sku`),
  INDEX `idx_category` (`category`),
  INDEX `idx_stock` (`stock_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- pcc_cases
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `pcc_cases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_id` VARCHAR(32) NOT NULL,
  `applicant_name` VARCHAR(150) NOT NULL,
  `applicant_name_bn` VARCHAR(150) NULL,
  `father_name` VARCHAR(150) NULL,
  `mother_name` VARCHAR(150) NULL,
  `dob` DATE NULL,
  `nid_no` VARCHAR(32) NULL,
  `passport_no` VARCHAR(32) NULL,
  `passport_issue_date` DATE NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NULL,
  `present_address` TEXT NULL,
  `permanent_address` TEXT NULL,
  `thana` VARCHAR(100) NULL,
  `district` VARCHAR(100) NULL,
  `purpose` VARCHAR(50) NULL,
  `reference_person` VARCHAR(150) NULL,
  `reference_phone` VARCHAR(20) NULL,
  `customer_id` INT UNSIGNED NULL,
  `govt_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `other_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(30) NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `status` ENUM('Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered') NOT NULL DEFAULT 'Receive',
  `received_date` DATE NOT NULL,
  `expected_delivery_date` DATE NULL,
  `delivered_date` DATE NULL,
  `delivery_recipient` VARCHAR(150) NULL,
  `delivery_phone` VARCHAR(20) NULL,
  `notes` TEXT NULL,
  `file_drive_id` VARCHAR(200) NULL,
  `file_drive_link` VARCHAR(500) NULL,
  `legacy_id` VARCHAR(100) NULL,
  `legacy_row_num` INT NULL,
  `legacy_data` JSON NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_tracking` (`tracking_id`),
  UNIQUE KEY `uq_transaction_ref` (`transaction_ref`),
  INDEX `idx_status` (`status`),
  INDEX `idx_mobile` (`mobile`),
  INDEX `idx_passport` (`passport_no`),
  INDEX `idx_received` (`received_date`),
  INDEX `idx_district` (`district`),
  INDEX `idx_customer` (`customer_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- case_status_history
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `case_status_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(50) NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by` INT UNSIGNED NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` VARCHAR(255) NULL,
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`case_id`) REFERENCES `pcc_cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- case_files
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `case_files` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `drive_file_id` VARCHAR(200) NULL,
  `drive_link` VARCHAR(500) NULL,
  `mime_type` VARCHAR(100) NULL,
  `size` INT UNSIGNED NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` INT UNSIGNED NULL,
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`case_id`) REFERENCES `pcc_cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- payments (polymorphic)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('pcc','customer','supplier','sale','purchase','expense','income') NOT NULL,
  `case_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL,
  `sale_id` INT UNSIGNED NULL,
  `purchase_id` INT UNSIGNED NULL,
  `account_id` INT UNSIGNED NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` VARCHAR(30) NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `legacy_id` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_case` (`case_id`),
  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_supplier` (`supplier_id`),
  INDEX `idx_sale` (`sale_id`),
  INDEX `idx_date` (`payment_date`),
  UNIQUE KEY `uq_transaction_ref` (`transaction_ref`),
  FOREIGN KEY (`case_id`) REFERENCES `pcc_cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- sales
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(32) NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `sale_date` DATE NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(30) NULL,
  `status` ENUM('paid','partial','due') NOT NULL DEFAULT 'due',
  `notes` TEXT NULL,
  `legacy_id` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_invoice` (`invoice_no`),
  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_date` (`sale_date`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  INDEX `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- purchases
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(32) NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `purchase_date` DATE NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(30) NULL,
  `status` ENUM('paid','partial','due') NOT NULL DEFAULT 'due',
  `notes` TEXT NULL,
  `legacy_id` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uq_invoice` (`invoice_no`),
  INDEX `idx_supplier` (`supplier_id`),
  INDEX `idx_date` (`purchase_date`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  INDEX `idx_purchase` (`purchase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- stock_movements
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `type` ENUM('in','out','adjustment','sale','purchase','sale_return','purchase_return','opening') NOT NULL,
  `quantity` INT NOT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` BIGINT NULL,
  `notes` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- transactions + transaction_entries (Double Entry)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transaction_no` VARCHAR(32) NOT NULL,
  `source_module` VARCHAR(50) NOT NULL,
  `source_id` BIGINT NOT NULL,
  `source_ref` VARCHAR(100) NULL,
  `transaction_date` DATE NOT NULL,
  `account_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL,
  `type` ENUM('income','expense','receivable','payable','transfer','adjustment') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(30) NULL,
  `reference` VARCHAR(100) NULL,
  `description` VARCHAR(255) NULL,
  `reversal_id` BIGINT UNSIGNED NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_txn_no` (`transaction_no`),
  UNIQUE KEY `uq_source` (`source_module`,`source_id`,`type`),
  INDEX `idx_date` (`transaction_date`),
  INDEX `idx_account` (`account_id`),
  INDEX `idx_customer` (`customer_id`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reversal_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_entries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` BIGINT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NULL,
  `debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` VARCHAR(255) NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
  INDEX `idx_txn` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- incomes / expenses (also mirrored in transactions)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `incomes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NULL,
  `category` VARCHAR(100) NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `income_date` DATE NOT NULL,
  `payment_method` VARCHAR(30) NULL,
  `account_id` INT UNSIGNED NULL,
  `reference` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `income_expense_categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_date` (`income_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NULL,
  `category` VARCHAR(100) NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `payment_method` VARCHAR(30) NULL,
  `account_id` INT UNSIGNED NULL,
  `reference` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `income_expense_categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- activity_logs
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` BIGINT NULL,
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_module` (`module`),
  INDEX `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- migration_logs
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `migration_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `source` VARCHAR(50) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `total_rows` INT NOT NULL DEFAULT 0,
  `success` INT NOT NULL DEFAULT 0,
  `failed` INT NOT NULL DEFAULT 0,
  `errors` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- backup_logs
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `type` ENUM('auto','manual') NOT NULL,
  `size` INT UNSIGNED NULL,
  `status` ENUM('success','failed') NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------
-- login_attempts (rate limiting)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_identifier` (`identifier`),
  INDEX `idx_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
