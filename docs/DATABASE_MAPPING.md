# DATABASE MAPPING — Legacy Sheets → MySQL

> তারিখ: 2026-10-11  
> DB: MySQL / MariaDB (InnoDB, utf8mb4_unicode_ci)  
> Access: PDO + prepared statements  
> Timezone: Asia/Dhaka, currency BDT (decimal 12,2), dates DATETIME

---

## 1. Design Principles

- **MySQL primary** (prompt §10): Sheets আর editable duplicate হিসেবে রাখা হবে না; importer one-way.
- **Preserve legacy:** `legacy_id`, `legacy_row_num`, `legacy_data JSON` প্রতিটি major table-এ যাতে re-import idempotent.
- **Money:** `DECIMAL(12,2)` — float নয়।
- **Audit:** সব financial table `created_at/updated_at`, `created_by` FK → `users.id`.
- **No hard delete on finance:** `deleted_at` soft delete + reversal entries.
- **Indexes:** search/filter columns এ `INDEX`, foreign keys এ `INDEX`, tracking/passport এ `UNIQUE`.

---

## 2. Full Schema Overview

```
users ──┐
settings│
customers ─┬─ pcc_cases ─┬─ case_status_history
           │              ├─ case_files
           │              ├─ case_deliveries (optional, embedded in pcc_cases)
           │              └─ payments (polymorphic: case_id/customer_id/sale_id)
suppliers ─┤
products ──┼─ stock_movements
           ├─ sales ── sale_items
           └─ purchases ── purchase_items

accounts ─┬─ transactions ── transaction_entries (double entry)
          ├─ incomes
          ├─ expenses
          └─ transfers (logical, stored as transactions type=transfer)

activity_logs, migration_logs, backup_logs
income_expense_categories, reference_persons
```

---

## 3. Legacy Sheet → Table Column Mapping

### 3.1 PCC_DATA Sheet → `pcc_cases`

অনুমিত legacy header row (Row 1) → MySQL column:

| Legacy Column (Header) | Example Value | MySQL Column | Type | Constraints | Notes |
|------------------------|---------------|--------------|------|-------------|-------|
| Tracking ID / ID | PCC-20241011-0001 | `tracking_id` | VARCHAR(32) | PRIMARY? UNIQUE NOT NULL | Preserve exact; new gen continues seq |
| Applicant Name | Md. Rahim | `applicant_name` | VARCHAR(150) NOT NULL | INDEX | |
| Applicant Name (BN) | মোঃ রহিম | `applicant_name_bn` | VARCHAR(150) NULL | | Bangla support utf8mb4 |
| Father Name | ... | `father_name` | VARCHAR(150) NULL | | |
| Mother Name | ... | `mother_name` | VARCHAR(150) NULL | | |
| DOB | 1995-06-15 | `dob` | DATE NULL | | |
| NID No | 1234567890 | `nid_no` | VARCHAR(32) NULL | INDEX | |
| Passport No | A12345678 | `passport_no` | VARCHAR(32) NULL | INDEX | Unique warn (not hard block) if active case exists |
| Passport Issue Date | 2023-01-10 | `passport_issue_date` | DATE NULL | | |
| Mobile | 01712345678 | `mobile` | VARCHAR(20) NOT NULL | INDEX | 11 digits validation |
| Email | ...@gmail | `email` | VARCHAR(150) NULL | | |
| Present Address | ... | `present_address` | TEXT NULL | | |
| Permanent Address | ... | `permanent_address` | TEXT NULL | | |
| Thana | Tejgaon | `thana` | VARCHAR(100) NULL | INDEX | |
| District | Dhaka | `district` | VARCHAR(100) NULL | INDEX | |
| Purpose | Job | `purpose` | VARCHAR(50) NULL | | ENUM Job/Visa/Other |
| Reference Person | Mr. X | `reference_person` | VARCHAR(150) NULL | | Also link to `reference_persons` / `customers` |
| Reference Phone | 018... | `reference_phone` | VARCHAR(20) NULL | | |
| Govt Fee | 500.00 | `govt_fee` | DECIMAL(12,2) DEFAULT 0 | | |
| Service Charge | 2000.00 | `service_charge` | DECIMAL(12,2) DEFAULT 0 | | |
| Other Fee | 200.00 | `other_fee` | DECIMAL(12,2) DEFAULT 0 | | |
| Total Fee | 2700.00 | `total_fee` | DECIMAL(12,2) GENERATED or app calc = sum 3 | | Stored for reporting speed |
| Paid Amount | 1000.00 | `paid_amount` | DECIMAL(12,2) DEFAULT 0 | | Live SUM(payments) sync trigger |
| Due Amount | 1700.00 | `due_amount` | DECIMAL(12,2) GENERATED `total_fee - paid_amount` | | |
| Payment Method | Cash | `payment_method` | VARCHAR(30) NULL | | First payment method |
| Transaction Ref | TRX123 | `transaction_ref` | VARCHAR(100) NULL | UNIQUE nullable | |
| Status | Receive | `status` | ENUM('Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered') NOT NULL DEFAULT 'Receive' | INDEX | Spell preserved exactly as prompt |
| Received Date | 2024-10-11 | `received_date` | DATE NOT NULL | INDEX | |
| Expected Delivery | 2024-10-20 | `expected_delivery_date` | DATE NULL | | |
| Delivered Date | 2024-10-21 | `delivered_date` | DATE NULL | | Set on Delivered |
| Delivery Recipient | Rahim | `delivery_recipient` | VARCHAR(150) NULL | | |
| Delivery Phone | 017... | `delivery_phone` | VARCHAR(20) NULL | | |
| Notes | ... | `notes` | TEXT NULL | | |
| File Link | drive.google.com/... | `file_drive_link` | VARCHAR(500) NULL | | |
| File ID | 1A2B... | `file_drive_id` | VARCHAR(200) NULL | INDEX | |
| Created At | timestamp | `created_at` | DATETIME NOT NULL | | Asia/Dhaka |
| Updated At | timestamp | `updated_at` | DATETIME | | |
| Legacy Row Num | 5 | `legacy_row_num` | INT NULL | | For traceability |
| Legacy Raw JSON | — | `legacy_data` | JSON NULL | | Preserves unknown columns |
| Created By | user | `created_by` | INT FK users(id) NULL | | |
| Deleted At | — | `deleted_at` | DATETIME NULL | | Soft delete |

**Indexes:**
```sql
UNIQUE KEY uq_tracking (tracking_id),
INDEX idx_status (status),
INDEX idx_mobile (mobile),
INDEX idx_passport (passport_no),
INDEX idx_received (received_date),
INDEX idx_district (district),
FULLTEXT ft_search (applicant_name, applicant_name_bn, passport_no, mobile) -- MySQL InnoDB FT
```

### 3.2 PAYMENTS (if separate sheet) / embedded payment columns → `payments`

| Legacy | MySQL | Type |
|--------|-------|------|
| Case ID / Tracking ID | `case_id` FK pcc_cases(id) NULL | INT INDEX |
| Customer ID | `customer_id` FK customers(id) NULL | INT INDEX |
| Supplier ID | `supplier_id` FK suppliers(id) NULL | INT |
| Sale ID / Purchase ID | `sale_id`, `purchase_id` NULL | INT |
| Amount | `amount` | DECIMAL(12,2) NOT NULL |
| Payment Date | `payment_date` | DATE NOT NULL |
| Method | `payment_method` | VARCHAR(30) |
| Reference | `transaction_ref` | VARCHAR(100) UNIQUE nullable |
| Type | `type` ENUM('pcc','customer','supplier','sale','purchase','expense','income') NOT NULL | |
| Account ID | `account_id` FK accounts(id) NULL | |
| Notes | `notes` | TEXT |
| Legacy ID | `legacy_id` | VARCHAR(50) |

**Constraint to prevent duplicate income:**
```sql
UNIQUE KEY uq_ref (transaction_ref) -- where not null
UNIQUE KEY uq_source (type, case_id, amount, payment_date) -- optional idempotency via app key
```

### 3.3 case_status_history

| Column | Type |
|--------|------|
| id | BIGINT PK AUTO |
| case_id | INT FK pcc_cases NOT NULL |
| from_status | ENUM same as pcc_cases NULL |
| to_status | ENUM NOT NULL |
| changed_by | INT FK users |
| changed_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| notes | VARCHAR(255) NULL |

INDEX `idx_case` (case_id).

### 3.4 case_files

| Column | Type |
|--------|------|
| id | BIGINT PK |
| case_id | INT FK |
| file_name | VARCHAR(255) |
| file_path | VARCHAR(500) — local `storage/uploads/...` or Drive ID |
| drive_file_id | VARCHAR(200) NULL |
| drive_link | VARCHAR(500) NULL |
| mime_type | VARCHAR(100) NULL |
| size | INT NULL |
| uploaded_at | DATETIME |
| uploaded_by | INT FK |

### 3.5 customers

| Column | Type |
|--------|------|
| id | INT PK AUTO |
| name | VARCHAR(150) NOT NULL |
| name_bn | VARCHAR(150) NULL |
| phone | VARCHAR(20) NOT NULL UNIQUE |
| email | VARCHAR(150) NULL |
| address | TEXT NULL |
| opening_balance | DECIMAL(12,2) DEFAULT 0 |
| balance | DECIMAL(12,2) DEFAULT 0 — computed |
| legacy_id | VARCHAR(50) NULL |
| created_at | DATETIME |
| updated_at | DATETIME |

INDEX `phone`.

### 3.6 suppliers — mirroring customers + `company_name`.

### 3.7 products

| Column | Type |
|--------|------|
| id | INT PK |
| name | VARCHAR(150) NOT NULL |
| name_bn | VARCHAR(150) NULL |
| sku | VARCHAR(50) UNIQUE NOT NULL | 
| category | VARCHAR(100) NULL | INDEX |
| unit | VARCHAR(20) DEFAULT 'pcs' |
| purchase_price | DECIMAL(12,2) NOT NULL |
| selling_price | DECIMAL(12,2) NOT NULL |
| opening_stock | INT DEFAULT 0 |
| stock_quantity | INT NOT NULL DEFAULT 0 | INDEX |
| low_stock_threshold | INT DEFAULT 5 |
| description | TEXT NULL |
| created_at | DATETIME |

### 3.8 accounts (Cash/Bank/Mobile)

| Column | Type |
|--------|------|
| id | INT PK |
| name | VARCHAR(100) NOT NULL (e.g. Cash, DBBL Bank, bKash) |
| type | ENUM('cash','bank','mobile') NOT NULL |
| opening_balance | DECIMAL(12,2) DEFAULT 0 |
| balance | DECIMAL(12,2) DEFAULT 0 |
| account_no | VARCHAR(100) NULL |
| description | VARCHAR(255) NULL |
| is_active | TINYINT(1) DEFAULT 1 |
| created_at | DATETIME |

### 3.9 transactions + transaction_entries (Double Entry)

`transactions` header:

| Column | Type |
|--------|------|
| id | BIGINT PK |
| transaction_no | VARCHAR(32) UNIQUE NOT NULL (TXN-YYYYMMDD-XXXX) |
| source_module | VARCHAR(50) NOT NULL (pcc/sale/purchase/expense/income/transfer) |
| source_id | BIGINT NOT NULL |
| source_ref | VARCHAR(100) NULL |
| transaction_date | DATE NOT NULL INDEX |
| account_id | INT FK accounts NULL |
| customer_id | INT FK customers NULL |
| supplier_id | INT FK suppliers NULL |
| type | ENUM('income','expense','receivable','payable','transfer','adjustment') NOT NULL |
| amount | DECIMAL(12,2) NOT NULL |
| payment_method | VARCHAR(30) NULL |
| reference | VARCHAR(100) NULL |
| description | VARCHAR(255) NULL |
| reversal_id | BIGINT FK transactions(id) NULL — points to reversed txn |
| created_by | INT FK users |
| created_at | DATETIME |

`transaction_entries` lines (always balanced):

| Column | Type |
|--------|------|
| id | BIGINT PK |
| transaction_id | BIGINT FK NOT NULL |
| account_id | INT FK NULL |
| debit | DECIMAL(12,2) DEFAULT 0 |
| credit | DECIMAL(12,2) DEFAULT 0 |
| description | VARCHAR(255) NULL |

**Constraint:** `CHECK (debit >=0 AND credit >=0)` and app ensures `SUM(debit)=SUM(credit)` per transaction_id (validated in Service + test).

### 3.10 sales / sale_items

`sales`: id, invoice_no UNIQUE (INV-S-YYYYMMDD-####), customer_id FK, sale_date, subtotal, discount, tax, total, paid, due (generated), payment_method, status ENUM('paid','partial','due'), notes, created_by, created_at, legacy_id, deleted_at.

`sale_items`: id, sale_id FK, product_id FK, quantity INT, unit_price DECIMAL, discount DECIMAL, total DECIMAL, INDEX sale_id.

### 3.11 purchases / purchase_items — mirroring sales with supplier.

### 3.12 stock_movements

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | INT FK NOT NULL INDEX |
| type | ENUM('in','out','adjustment','sale','purchase','sale_return','purchase_return','opening') NOT NULL |
| quantity | INT NOT NULL (signed: + for in, - for out) |
| reference_type | VARCHAR(50) NULL (sale/purchase/adjustment) |
| reference_id | BIGINT NULL |
| notes | VARCHAR(255) NULL |
| created_by | INT FK |
| created_at | DATETIME |

### 3.13 incomes / expenses

Simplified — also mirrored to `transactions` double entry.

`incomes`: id, category, description, amount, income_date, payment_method, account_id FK, reference, created_by, created_at.

`expenses`: same, `category` FK `income_expense_categories`.

### 3.14 income_expense_categories

| Column | Type |
|--------|------|
| id | INT PK |
| name | VARCHAR(100) NOT NULL |
| type | ENUM('income','expense') NOT NULL |
| created_at | DATETIME |

### 3.15 activity_logs, migration_logs, backup_logs, settings, users, reference_persons

- `activity_logs`: id BIGINT PK, user_id INT FK, action VARCHAR(50), module VARCHAR(50), record_id BIGINT NULL, description TEXT, ip_address VARCHAR(45), created_at DATETIME, INDEX (module, created_at).
- `migration_logs`: id, source ENUM('sheets','csv'), file_name, total_rows, success, failed, errors JSON, created_at.
- `backup_logs`: id, filename, type ENUM('auto','manual'), size INT, status ENUM('success','failed'), created_by, created_at.
- `settings`: `key` VARCHAR(100) PK, `value` TEXT, `updated_at` DATETIME. Keys: `company_name`, `company_logo`, `tracking_prefix`, `tracking_seq`, `status_strict`, `default_govt_fee`, `default_service_charge`, `drive_folder_id`, `sheets_id`, `currency`, `date_format`.
- `users`: id, username UNIQUE, email UNIQUE, password_hash, role ENUM('admin','staff') DEFAULT 'admin', is_active, last_login, created_at.
- `reference_persons`: id, name, phone, relation, created_at.

---

## 4. Tracking ID & Transaction No Generation

**Tracking ID:**
```sql
-- settings: tracking_seq = MAX seq from legacy
-- PHP:
BEGIN;
SELECT value FROM settings WHERE `key`='tracking_seq' FOR UPDATE;
seq = (int)value + 1;
tracking_id = 'PCC-' + date('Ymd') + '-' + str_pad(seq,4,'0',STR_PAD_LEFT);
UPDATE settings SET value=seq WHERE `key`='tracking_seq';
INSERT pcc_cases (tracking_id, ...) VALUES (...);
COMMIT;
-- On duplicate (race), retry up to 3 times.
```

**Invoice/Transaction Nos:** same pattern with own seq keys (`invoice_seq_sale`, `txn_seq`).

Legacy import preserves original `tracking_id` verbatim; seq initialized to `MAX(CAST(SUBSTRING(tracking_id, 14) AS UNSIGNED))`.

---

## 5. DECIMAL & Date Conventions

- All money `DECIMAL(12,2)`; PHP `number_format($amt,2)` + `৳` prefix; Bangla digits optional via helper `toBanglaNumber()`.
- Dates `DATE` or `DATETIME` UTC stored, displayed `d/m/Y` (or `d-m-Y`) per `settings.date_format`, timezone `Asia/Dhaka` (`date_default_timezone_set`).
- CSV import expects `d/m/Y` or `Y-m-d` with flexible parser `DateTime::createFromFormat` loop.

---

## 6. Referential Integrity

```sql
FOREIGN KEY fk_case_customer (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
FOREIGN KEY fk_payment_case (case_id) REFERENCES pcc_cases(id) ON DELETE CASCADE;
-- etc — all InnoDB FK with ON DELETE appropriate (CASCADE for children, SET NULL for optional links)
```

**No hard delete** on `pcc_cases`, `sales`, `purchases`, `transactions` — use `deleted_at` + reversal entries; FK still enforced.

---

## 7. Migration Preservation Fields

Every major entity has:
```sql
legacy_id VARCHAR(100) NULL COMMENT 'original Sheet row ID / Tracking',
legacy_row_num INT NULL,
legacy_data JSON NULL,
INDEX idx_legacy (legacy_id)
```
Import script sets `legacy_id = tracking_id` (legacy) to allow re-import idempotent `ON DUPLICATE KEY UPDATE`.

---

## 8. Example Accounting Treatment (Prompt §8)

Case: Service charge 2000, govt fee 500, other 200 → Total 2700? Prompt example says govt fee & other are costs not revenue. Our treatment options:

- **Option A (Implemented):** `total_fee = service_charge` (2000) revenue; govt_fee + other_fee stored as `direct_cost` linked to case; profit = 2000 - (500+200) = 1300. Ledger: on receive 1000 paid: Debit Cash 1000 Credit Receivable 1000; on completion: Debit Receivable 2000 Credit Service Revenue 2000; separate Expense entries for govt fee/other when incurred.
- **Option B (If legacy treats total as sum):** Alternate config flag `settings.fee_mode = inclusive` where `total_fee = govt+service+other`.

Implemented as **Option A default** with config toggle; `DATABASE_MAPPING.md` documents both; `reports/profit_loss.php` shows both breakdowns.

Mathematically validated in `tests/FinancialTest.php`.

---

## 9. Full DDL Location

`database/schema.sql` — single source of truth, idempotent `CREATE TABLE IF NOT EXISTS`, followed by `database/seed.sql` for default admin, accounts, categories.

---

*Next: `IMPLEMENTATION_PLAN.md` phases based on this mapping.*
