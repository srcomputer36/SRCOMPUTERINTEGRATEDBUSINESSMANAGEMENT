-- Seed data for SR Computer
USE `srcomputer`;

-- Default admin: username admin, password admin123
-- hash generated with password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@srcomputer.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- Settings
INSERT INTO `settings` (`key`, `value`) VALUES
('company_name', 'SR Computer'),
('company_address', 'Bangladesh'),
('company_phone', '01700000000'),
('company_email', 'info@srcomputer.local'),
('currency', 'BDT'),
('currency_symbol', '৳'),
('date_format', 'd/m/Y'),
('tracking_prefix', 'PCC-'),
('tracking_seq', '0'),
('invoice_seq_sale', '0'),
('invoice_seq_purchase', '0'),
('txn_seq', '0'),
('status_strict', '0'),
('default_govt_fee', '500'),
('default_service_charge', '1500'),
('default_other_fee', '0'),
('fee_mode', 'service_only'),
('drive_folder_id', ''),
('sheets_id', ''),
('drive_mode', 'local')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- Accounts
INSERT INTO `accounts` (`name`, `type`, `opening_balance`, `balance`) VALUES
('Cash', 'cash', 0, 0),
('Bank - DBBL', 'bank', 0, 0),
('bKash', 'mobile', 0, 0),
('Nagad', 'mobile', 0, 0)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Categories
INSERT INTO `income_expense_categories` (`name`, `type`) VALUES
('Service Charge', 'income'),
('Sales', 'income'),
('Other Income', 'income'),
('Government Fee', 'expense'),
('Office Rent', 'expense'),
('Electricity', 'expense'),
('Internet', 'expense'),
('Transport', 'expense'),
('Printing', 'expense'),
('Other Expense', 'expense')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Sample customer/supplier/product (optional demo, marked legacy)
INSERT INTO `customers` (`name`, `phone`, `address`, `opening_balance`) VALUES
('Demo Customer', '01700000001', 'Dhaka', 0)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `suppliers` (`name`, `phone`, `address`) VALUES
('Demo Supplier', '01800000001', 'Dhaka')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `products` (`name`, `sku`, `category`, `purchase_price`, `selling_price`, `stock_quantity`, `low_stock_threshold`) VALUES
('A4 Paper Rim', 'SKU-001', 'Stationery', 400.00, 450.00, 50, 10),
('Ink Cartridge', 'SKU-002', 'Accessories', 1200.00, 1500.00, 20, 5)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
