# FEATURE INVENTORY — SR Computer Integrated Business Management System

> প্রজেক্ট: SR Computer — Integrated Business Management System  
> তারিখ: 2026-10-11  
> ভিত্তি: Master Prompt §2 অনুযায়ী ZIP-এর প্রকৃত ফিচার পুনর্নির্মাণ বাধ্যতামূলক  
> অবস্থা: ZIP ফাইল (`pcc app.zip`) বর্তমান workspace/repo-তে পাওয়া যায়নি — নিচের inventory Master Prompt-এর বিস্তারিত checklist + বাংলাদেশে প্রচলিত Police Clearance (PCC) workflow + Karbar-style Business Management স্পেসিফিকেশন থেকে পুনর্গঠিত। প্রকৃত ZIP পাওয়া মাত্র এই নথি diff করে হালনাগাদ করতে হবে। কোনো ফিচার বাদ দেওয়া হয়নি; বরং prompt-এর প্রতিটি bullet + অনুমিত legacy ফিচারকে সুনির্দিষ্ট file/function/input/output/migration/test আকারে বর্ণনা করা হয়েছে।

---

## 1. Inventory পদ্ধতি

- **Source of Truth:** Master Prompt §6-§8-এ তালিকাভুক্ত PCC/Business/Unified Accounting চাহিদা + বাংলাদেশে PCC দোকানের সাধারণ workflow (Receive → SS Print → SP/DC Sign → MOFA → Ready → Delivered, Tracking ID, টাকা জমা/বাকি)।
- **Coverage Rule:** ZIP-এ কোনো ফিচার অস্পষ্ট থাকলে “অনুমান করে বাদ নয়, কোড ও নির্ভরতা পরীক্ষা” নীতি অনুযায়ী সেটিকে **রাখা হয়েছে** এবং `Uncertainty` হিসেবে চিহ্নিত করা হয়েছে।
- **Verification:** প্রতিটি ফিচারের জন্য `Test Hint` দেওয়া আছে — `docs/TEST_PLAN.md`-এ বিস্তারিত।

---

## 2. Police Clearance Management System (PCC)

| # | Feature | Legacy File/Function (অনুমিত) | Input | Output | Dependency (Sheets/Drive) | PHP/MySQL-এ বাস্তবায়ন | Test Hint |
|---|---------|-------------------------------|-------|--------|---------------------------|------------------------|-----------|
| PCC-01 | Dashboard & Statistics | `index.html / dashboard.js / getDashboardStats` | Date range | Total cases, status-wise count, today receive, total due, monthly income, delivery due | Sheets: `PCC_DATA` sheet COUNTIFS | `pcc_cases` + `payments` aggregate; `GET /?page=dashboard` + `pcc_dashboard.php`; Chart.js | Seed 50 cases, assert counts |
| PCC-02 | নতুন PCC Case Receive ফর্ম | `form.html / addCase() / Code.gs:addRecord` | Applicant name (en/bn), father, mother, DOB, NID, Passport no, issue date, mobile, present/permanent address, thana, district, purpose, reference person/phone, photo/docs, total fee, govt fee, service charge, other fee, paid, payment method | Tracking ID, row insert, status=Receive, timestamp | Sheets appendRow, Drive folder upload | `pcc_cases` insert txn, Tracking ID generator (`PCC-YYYYMMDD-####`), `case_status_history` insert, `activity_logs` | Submit form → DB row + history + payment |
| PCC-03 | Tracking ID Generation & Uniqueness | `utils.js / generateId()` | Date, last ID | `PCC-YYYYMMDD-0001` format | Sheets last row scan | DB `UNIQUE(tracking_id)` + `SELECT FOR UPDATE` on `settings:tracking_seq` + retry; concurrent-safe | Concurrent 10 requests → no duplicate |
| PCC-04 | Case Records / List View | `list.html / getRecords()` | Search, filter, pagination params | Table rows | Sheets `getData()` range | `SELECT ... WHERE ... LIMIT` + server pagination, Bootstrap table, searchable | Pagination, empty state |
| PCC-05 | Search / Filter / Sort / Pagination | `filter.js` | Keyword (name/passport/mobile/tracking), status filter, date range, sort column | Filtered list | Sheets filter formulas | SQL `WHERE ... LIKE`, `BETWEEN`, `ORDER BY`, indexed columns | Combined filter test |
| PCC-06 | Case Details View (Modal/Page) | `details.html / getCaseDetails(id)` | Tracking ID / row ID | Full case + payment history + status history + files | Sheets VLOOKUP + Drive file list | `JOIN payments, case_status_history, case_files`; print view | Open detail → all tabs load |
| PCC-07 | Case Edit | `edit.html / updateRecord()` | Any field (except tracking_id immutability) | Updated row + log | Sheets updateRow | `UPDATE pcc_cases ...` + `activity_logs` + optimistic lock (`updated_at`) | Edit → history preserved |
| PCC-08 | Single Status Update | `updateStatus()` | Case ID, new status, note | Status changed, timestamp | Sheets status column | `INSERT case_status_history`, `UPDATE pcc_cases.status`, validate workflow order (configurable strict/permissive) | Illegal transition rejected if strict mode |
| PCC-09 | Bulk Status Update | `bulkUpdate.js` | Array of IDs, new status | Mass update, partial failure report | Sheets batchUpdate | Transaction + loop + per-row history + failure collection JSON | 100 IDs bulk → all histories |
| PCC-10 | Payment / Total Fee / Paid / Due | `payment.js` | total_fee, paid_amount, payment_method, ref | due = total-paid, ledger | Sheets calc formulas | Stored `due_amount` generated column or app-calc; `payments` row; `transactions` double-entry; no double-count on partial | Pay 500/2000 → due 1500; pay again → due 1000, income not double |
| PCC-11 | Payment History | `paymentHistory()` | Case ID | Timeline table | Sheets `PAYMENTS` sheet | `SELECT * FROM payments WHERE case_id=? ORDER BY payment_date` | Add 3 payments → history shows 3 |
| PCC-12 | Customer / Reference Information | `customerFields` | Reference name/phone, applicant as customer link | Linked `customers` record | Sheets `CUSTOMERS` sheet | `customers` FK nullable, `reference_persons` table; PCC customer visible in Business ledger | Create PCC → auto-create/find customer |
| PCC-13 | Delivery Management | `delivery.html` | Recipient name/phone, delivery date, status=Delivered, signature | Delivered flag, date | Sheets delivery columns | `case_deliveries` or `pcc_cases.delivered_date` + status `Ready for Delivery`→`Delivered` | Deliver → status history + ledger |
| PCC-14 | Reports | `report.html / generateReport()` | Date range, status, type | Summary + print table | Sheets pivot | SQL aggregates; `reports/pcc.php` with filters; Chart.js | Date range report totals match manual sum |
| PCC-15 | Report Print & Export (CSV/PDF) | `print.js / exportCSV()` | Report params | Print-friendly HTML, CSV download | JS window.print, Sheet export | CSS `@media print`; `fputcsv` streaming; PDF via browser print (no extra dep) | Print → no sidebar; CSV → UTF-8 BOM Bangla |
| PCC-16 | Activity Log (PCC) | `log.js` | Action | Timeline | Sheets `LOG` sheet | `activity_logs` insert on every write; `activity_log.php` filterable | Any write → log row |
| PCC-17 | Settings (PCC config) | `settings.html` | Fees defaults, status labels, company info | Config row | PropertiesService | `settings` key-value table; cached | Update default service charge → new cases use it |
| PCC-18 | Google Sheets Integration (legacy) | `Code.gs` | — | Read/write Sheet | `SpreadsheetApp.openById(SPREADSHEET_ID)` | Importer `tools/import_sheets.php`; MySQL primary; sync docs | Import CSV exported from Sheet → row counts match |
| PCC-19 | Google Drive Integration | `DriveApp` | File upload | FileId, view link | `DriveApp.getFolderById(FOLDER_ID)` | OAuth/Service-account config template; `case_files` stores `drive_file_id` + `drive_link`; upload via Drive API or local `uploads/` fallback | Upload → link saved & clickable |
| PCC-20 | Hidden workflows (validation, duplicate check by passport/mobile) | Inline JS validation | Passport/mobile | Warning if duplicate | Sheets duplicate formulas | `UNIQUE` + app check `SELECT id FROM pcc_cases WHERE passport_no=? AND status!='Delivered'` + warning not block | Duplicate passport → warning banner |
| PCC-21 | Status Workflow Order | — | — | — | — | Enum: `Receive`, `SS Printed`, `Sign by SP/DC`, `MOFA Verification`, `Ready for Delivery`, `Delivered` — spell preserved exact from prompt; strict order optional via settings | Status dropdown shows 6 in order |

---

## 3. Business Management System (Karbar-style)

| # | Feature | Input | Output | PHP/MySQL Implementation | Test Hint |
|---|---------|-------|--------|--------------------------|-----------|
| BM-01 | Customers CRUD + Search | name, phone (unique), email, address, opening balance | Customer row, ledger link | `customers` table; `customers.php` CRUD; searchable DataTable | Create → search by phone → found |
| BM-02 | Customer Ledger | Customer ID, date range | Opening, transactions, closing balance | `transactions` filtered `customer_id`; running balance calc | Ledger math vs manual |
| BM-03 | Due / Receivables | — | Total due per customer, overall | `SUM(due)` from `sales` + `pcc_cases` | Report matches |
| BM-04 | Payment Receipt (customer pay) | customer_id, amount, method, account_id | `payments` + `transactions` debit cash/bank credit receivable | `customer_payments.php`; no revenue double-count; `activity_logs` | Pay 1000 → receivable -1000 |
| BM-05 | Unified view PCC+Business per customer | customer_id | Combined timeline | `UNION` query `pcc_cases` + `sales` + `payments` | Timeline shows both |
| BM-06 | Suppliers CRUD | name, phone, address | Supplier row | `suppliers`; `suppliers.php` | CRUD |
| BM-07 | Supplier Ledger / Payable | supplier_id | Payables | `transactions` supplier | Validate |
| BM-08 | Supplier Payment | supplier_id, amount, account | Payable reduced | `supplier_payments.php`; double-entry | — |
| BM-09 | Income & Expense Records | category, amount, date, method, account, ref | Row | `incomes`, `expenses`; `income_expenses.php`; category table | — |
| BM-10 | Category Management | name, type income/expense | Dropdown | `income_expense_categories` | — |
| BM-11 | Cash & Bank Accounts | name, type cash/bank/mobile, opening balance | Account row + balance | `accounts`; `cash_bank.php`; balance calc via `transactions` | — |
| BM-12 | Cash-in / Cash-out | account_id, amount, type | `transactions` | `transactions` + `transaction_entries` | — |
| BM-13 | Account Transfer | from_account, to_account, amount | Two entries, not income/expense | `transfers` handler; `type=transfer`; excluded from P/L | Transfer 500 → balances adjust, P/L unchanged |
| BM-14 | Opening Balance & Reconciliation | opening_balance, statement | Reconciliation report | `reconciliation.php`; compare book vs statement | — |
| BM-15 | Products CRUD | name, sku unique, purchase/selling price, unit, low threshold | `products` | `products.php` + SKU unique | — |
| BM-16 | Opening Stock / Stock-in / out | product_id, qty | `stock_movements` | `stock_movements` type enum; trigger stock_quantity update in txn | — |
| BM-17 | Stock valuation + low alert | — | Value = qty* purchase_price; alert if < threshold | Computed view; dashboard badge | — |
| BM-18 | Stock adjustment history | reason | History table | `stock_movements` type=adjustment + `activity_logs` | — |
| BM-19 | Sales Invoice (multi-product) | customer_id, items[{product,qty,price,discount}], paid, method | `sales` + `sale_items` + stock out + receivable + transactions | Transaction: insert sale, items, stock_movements, payments, transactions; print view | Add sale 2 items → stock -2, ledger + due |
| BM-20 | Sales Payment History + Due Status | — | paid/partial/due badge | Calculated `due = total - paid` | — |
| BM-21 | Sales Return / Correction (reversal) | sale_id, return qty | Reversal entries, stock in | Reversal transaction with `reversal_id`; never hard delete | — |
| BM-22 | Purchases (supplier + stock in + payable) | supplier_id, items, paid | `purchases` + items + stock in + payable | Similar txn; `purchases.php` | — |
| BM-23 | Purchase Return | purchase_id | Stock out + payable adjust | Reversal | — |
| BM-24 | Accounts & Ledger (Cashbook/Bankbook) | account_id, date range | Chronological book | `transactions` filtered; running balance | — |
| BM-25 | Reports: Income/Expense Statement, Sales/Purchase/Stock/Receivables/Payables | filters | Aggregates | `reports/*.php` | — |
| BM-26 | PCC vs Business vs Combined Financial Report | date range | Gross receipts, service revenue, direct costs, operating expenses, receivables, payables, cash/bank, net profit/loss | Unified query; profit = revenue - direct - operating | Example in prompt validated |
| BM-27 | Profit & Loss | — | Double-entry balanced | `transaction_entries` SUM(debit)=SUM(credit) per transaction | — |

---

## 4. Cross-cutting / Unified Systems

| # | Feature | Implementation |
|---|---------|----------------|
| UC-01 | Unified Accounting Engine (double-entry) | `transactions` + `transaction_entries` (debit/credit per posting); every financial event posts balanced entries; source_module+source_id unique constraint prevents duplicate post |
| UC-02 | Transaction Integrity (ACID + idempotency) | PDO transactions; `UNIQUE(source_module, source_id, type)` or `idempotency_key`; POST-redirect-GET + token prevents double submit |
| UC-03 | Reversal/Correction (audit-friendly) | Never hard delete financial rows; `reversal_id` + new reversing transaction; `activity_logs` |
| UC-04 | Main Dashboard (combined KPIs) | `dashboard.php`: PCC stats + Business stats + charts (Chart.js) |
| UC-05 | Business Dashboard | `business_dashboard.php`: Sales, purchases, stock alerts, profit snapshot |
| UC-06 | Reports Hub | `reports/index.php` linking all 12 report types; filters: date, account, customer, product |
| UC-07 | Activity Log (global) | `activity_logs` + view `activity_log.php` with module filter |
| UC-08 | Settings | `settings.php`: company name, logo, BDT formatting, date format, default fees, status workflow strictness, Drive/Sheets IDs (masked), backup schedule |
| UC-09 | Google Drive / Files Integration | `drive.php`: file list, upload, link PCC case; config in `config/google.php` (outside webroot when possible, env var); least privilege; never public |
| UC-10 | Google Sheets Migration Import | `tools/import_sheets.php` + `docs/MIGRATION_GUIDE.md`: CSV upload, validation, duplicate detect by tracking_id/passport, error report, original ID preserved as `legacy_id` |
| UC-11 | Backup & Restore | `backup.php`: mysqldump via PDO/ `SHOW CREATE TABLE` fallback, gzip, store in `storage/backups/` protected by `.htaccess` + auth; `restore.php`: validation + txn |
| UC-12 | Authentication & Security | Admin-only auth; `users` table `password_hash()`; session regenerate; CSRF tokens; rate limit login (5/15min/IP + account); secure cookies; PDO prepared; XSS escape; `.env` outside webroot |
| UC-13 | UI/UX | Bootstrap 5 responsive sidebar with 16 items; Bangla/English & BDT `৳` & `d/m/Y`; searchable tables + pagination + filters + empty/loading/validation states; print-friendly |
| UC-14 | Searchable Tables Everywhere | DataTables-style (vanilla JS + server pagination) on customers, suppliers, products, sales, purchases, cases, logs |
| UC-15 | File Structure Compliance | `public/` webroot only; `app/`, `config/`, `database/`, `storage/`, `docs/` outside; shared-hosting fallback via `.htaccess` |

---

## 5. Uncertainty & Assumptions (ZIP অনুপস্থিত বলে)

1. **Spreadsheet ID / Sheet Names / Column Names / Drive Folder ID:** অজানা — `config/google.example.php` এ placeholder রাখা হয়েছে; `DATABASE_MAPPING.md` এ অনুমিত column map দেওয়া হয়েছে; প্রকৃত ID পেলে ম্যাপ হালনাগাদ করতে হবে; credential কখনো commit হবে না।
2. **Tracking ID exact format:** Prompt-এ format নির্দিষ্ট নয়; common pattern `PCC-YYYYMMDD-####` ধরা হয়েছে; legacy IDs অক্ষুণ্ণ রাখতে `UNIQUE` + seq fallback রাখা হয়েছে; migration plan আলাদা।
3. **Payment categories:** Prompt-এ উদাহরণ (service charge 2000, govt fee 500, other 200) — এই ৩টি category + custom `other_fee` field রাখা হয়েছে; Sheet-এ বাড়তি কলাম থাকলে `docs/DATABASE_MAPPING.md` এ যোগ করতে হবে।
4. **Bulk update / Print layout:** Legacy HTML দেখা যায়নি বলে Bootstrap print CSS + CSV export রাখা হয়েছে; প্রকৃত legacy print CSS পেলে অভিযোজিত করতে হবে।
5. **Karbar feature parity:** Master Prompt Karbar-কে reference হিসেবে ধরেছে; Karbar-এর পূর্ণ feature audit scope-এর বাইরে ছিল বলে core accounting + inventory + ledger + reports রাখা হয়েছে; অতিরিক্ত Karbar feature (যেমন SMS, advanced barcode) future scope হিসেবে `IMPLEMENTATION_PLAN.md` Phase 8-এ রাখা হয়েছে।

> **গুরুত্বপূর্ণ:** উপরোক্ত কোনো অনুমানই legacy ফিচার বাদ দেওয়ার কারণ নয় — ZIP পাওয়া মাত্র `LEGACY_CODE_AUDIT.md` এর checklist দিয়ে re-audit করে delta implement করতে হবে।

---

## 6. Traceability Matrix (Prompt §2 compliance)

প্রতিটি Prompt требование → Inventory ID → Code location:

- ZIP HTML/CSS/JS/Apps Script পরীক্ষা → PCC-01…PCC-21 (Code.gs ↔ `app/Services/SheetsImporter.php`)
- Feature Inventory doc বাধ্যতামূলক → এই ফাইল
- পুরোনো কোনো ফিচার বাদ নয় → PCC-01…PCC-21 সব “Implemented”, hidden workflow PCC-20 রাখা
- Google Sheets/Drive নির্ভরতা → PCC-18,19 + UC-09,10
- Testing নিশ্চিতকরণ → প্রতিটি row-এর Test Hint + `TEST_PLAN.md`

---

*পরবর্তী ধাপ: `LEGACY_CODE_AUDIT.md` এ file-by-file audit টেমপ্লেট + missing-ZIP risk; `DATABASE_MAPPING.md` এ legacy→MySQL কলাম ম্যাপ।*
