# TEST PLAN — SR Computer Integrated Business Management

> তারিখ: 2026-10-11  
> Coverage: Master Prompt §16 (18 mandatory tests) + §8 Unified Accounting + security

---

## 1. Test Philosophy

- **No fake data as production:** `seed.sql` only creates admin + sample accounts; tests run on separate DB or transaction rollback.
- **Financial tests are automated** (PHP): `tests/FinancialTest.php` must pass before Phase 5 gate.
- **Manual UI tests** documented here for QA handover; each has Steps / Expected / Pass criteria.

---

## 2. Automated Tests (`tests/`)

### 2.1 `tests/FinancialTest.php` (critical — run `php tests/FinancialTest.php`)

| # | Test | Assertion |
|---|------|-----------|
| F1 | PCC payment: total 2000, paid 1000 → due 1000 | `due == total - sum(payments)` |
| F2 | Partial payment second time does not double-count income | `SUM(transactions where type=income) == total_paid` (not total_paid*2) |
| F3 | `AccountingService::post()` balances | `SUM(debit)==SUM(credit)` per txn |
| F4 | Transfer not counted as income/expense | Transfer amount not in `SELECT SUM(amount) WHERE type IN('income','expense')` |
| F5 | Reversal correctness | Original txn + reversal net 0 |
| F6 | Profit calc prompt example: service 2000, govt 500, other 200 → profit 1300 | `profit == 2000 - 700` and `gross != 2700` |
| F7 | Sales invoice stock & ledger | Sale of 2×Product → `stock_quantity -2`, `receivable + total`, `transactions` balanced |
| F8 | Concurrent Tracking ID uniqueness (simulated) | 10 parallel `generateTrackingId()` → all unique |

Run: `php tests/FinancialTest.php` → exit 0 pass, non-zero fail.

### 2.2 `tests/MigrationTest.php`

- Imports `tests/fixtures/pcc_legacy.csv` (20 rows including duplicates, Bangla names) → asserts success 18, failed 2 (duplicate tracking), `migration_logs` row, `legacy_data` preserved.

### 2.3 `tests/SecurityTest.php`

- CSRF: POST without token → 403
- SQL injection: `?search=' OR 1=1--` → no dump, prepared statement safe
- XSS: `name=<script>alert(1)</script>` → escaped in HTML output
- Auth: unauthenticated `GET /?page=pcc` → redirect login
- Rate limit: 6 failed logins in 15min → 429 / lock message

---

## 3. Manual Functional Tests (18 mandatory per Prompt §16)

### T-01 Admin login/logout

- Steps: Open `login.php` → enter `admin / admin123` (seed) → login → sidebar appears → logout → back to login.
- Expected: session regenerate, cookie httponly, redirect unauth, success notification.
- Pass: Login success, logout invalidates session (back button not bypass).

### T-02 PCC case create/edit/search

- Create: `Police Clearance → Receive New` → fill all required → Save → see Tracking ID in list.
- Edit: Click eye → Edit → change mobile → Save → `activity_logs` shows edit.
- Search: Search by passport/mobile/Bangla name → found.

### T-03 Tracking ID uniqueness

- Open 2 tabs, submit 2 cases simultaneously (or use `tests/FinancialTest.php` concurrent).
- Expected: IDs differ, never duplicate; DB `UNIQUE` prevents.
- Manual also: try insert duplicate tracking_id via DB → error.

### T-04 Status change & bulk update

- Single: View case → change Receive→SS Printed → Save → `case_status_history` 1 row, badge updates.
- Bulk: List → select 5 via checkboxes → Bulk Status → MOFA Verification → Confirm → 5 histories.

### T-05 Payment, partial & due calculations

- Case total 2000, paid 500 → due shows 1500 in list and detail.
- Add payment 300 → due 1200; `payments` table 2 rows; `transactions` income sum 800 (not 1600).

### T-06 Delivery update

- Set status Ready for Delivery → Delivery tab → enter recipient/phone/date → Mark Delivered → status Delivered + delivered_date set.

### T-07 Reports & print

- `Reports → PCC Report` select date range → table totals match `SELECT SUM`. Click Print → only report prints (no sidebar). Export CSV → open in Excel Bangla correct (BOM).

### T-08 Google Drive file access

- Upload file on case view (PDF/JPG) → link appears; click opens correct `drive_link` or local `/storage/uploads/...` (protected, auth required). Direct URL without auth → 401. Public URL not exposed.

### T-09 Customer & supplier ledger

- Create customer, do Sale 5000 (paid 2000 due 3000) + PCC case 2000 (paid 1000 due 1000) for same customer (link via phone) → Customer Ledger shows both, opening+transactions=closing, total receivable 4000.

### T-10 Sales, purchases & stock movement

- Product A stock 10 → Sale 3 → stock 7 → Purchase 5 → stock 12 → low-stock threshold 5: when stock 4 badge red.

### T-11 Cash/bank transfer

- Cash 10,000 bank 5,000 → Transfer 1,000 cash→bank → Cash 9,000 bank 6,000 → P/L unchanged (check `Reports → Profit & Loss` before/after equal).

### T-12 Duplicate payment prevention

- Submit payment form twice quickly (double-click or resubmit) → only one `payments` row (idempotency token), ledger not doubled.

### T-13 Financial transaction rollback

- Simulate failure mid-txn (e.g., kill DB after sale header insert) → no orphan `sale_items` or half `stock_movements`; entire txn rolled back (requires PDO `beginTransaction` + catch rollback).

### T-14 MySQL migration & historical data validation

- Export Sheets to CSV → `Tools → Import` dry-run → see mapping → import → `migration_logs` counts + error CSV download → compare legacy SUM(due) vs MySQL SUM(due) zero diff.

### T-15 Backup & restore

- `Backup & Restore → Create Backup` → file appears in `storage/backups/` (not public), download requires auth → `Restore` on test DB → row counts match original; `backup_logs` shows success.

### T-16 CSRF & authorization

- Intercept POST `pcc/status` remove `_csrf` → 403.
- Logout → `curl /?page=customers` → redirect login.

### T-17 Mobile/desktop UI

- Resize to 375px (mobile), 768px (tablet), 1440px (desktop): sidebar collapses to hamburger, tables scroll horizontally, no overlap, Bangla text not clipped.

### T-18 Empty dataset & invalid input

- Fresh DB: Dashboards show `0` not crash; lists show “কোনো তথ্য পাওয়া যায়নি / No data” empty state.
- Submit case with missing required mobile → validation error inline Bengali; submit paid > total → error; submit negative qty on sale → error.

---

## 4. Database Integrity Tests

- `FOREIGN KEY` check: try delete customer with sales → restrict or set null not cascade delete financial history.
- `DECIMAL` precision: enter 99999999.99 → stored exact; 100.999 → rounded 101.00 per validation.
- `CHECK` ledger balanced: `SELECT transaction_id FROM transaction_entries GROUP BY transaction_id HAVING SUM(debit)!=SUM(credit)` → 0 rows.

---

## 5. Security Tests (Prompt §11)

- Login rate limit: 5 fails → lock 15 min per IP+user.
- Session: `session_regenerate_id(true)` on login; cookie `httponly=1, samesite=Lax, secure` if HTTPS.
- XSS: all `echo` via `e()` helper `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- SQLi: all queries via PDO prepared; grep `\.query\(.*\$_` → 0.
- Secrets: `config/*.php` not in `public/`; `.gitignore` covers `config/config.php`, `storage/credentials/*`; GitHub scan.
- Backup auth: direct `/storage/backups/*.sql.gz` URL → `.htaccess deny from all` + PHP proxy.

---

## 6. How to Run

```bash
# automated
php tests/FinancialTest.php
php tests/MigrationTest.php
php tests/SecurityTest.php

# hosting check
php tools/hosting_check.php

# manual: checklist spreadsheet `docs/QA_CHECKLIST.csv` row-by-row
```

Pass criteria: automated exit 0 + all 18 manual marked PASS + DB integrity 0 violations.

---

## 7. QA Handover

- Tester fills `docs/QA_CHECKLIST.csv` (columns: TestID, Steps, Expected, Actual, Pass/Fail, Notes).
- On ZIP arrival, re-run T-02…T-07 against legacy data parity.

---

*This plan fulfills Master Prompt §16 “কমপক্ষে” 18 tests + financial/structural validation.*
