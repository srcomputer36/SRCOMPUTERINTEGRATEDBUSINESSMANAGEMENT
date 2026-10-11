# IMPLEMENTATION PLAN — SR Computer Integrated Business Management

> তারিখ: 2026-10-11  
> Stack: PHP 8.2+ (PDO), MySQL/MariaDB InnoDB, HTML5/CSS3/JS, Bootstrap 5.3, Chart.js, Google Drive API (optional)  
> Hosting: সাধারণ PHP/MySQL shared hosting (cPanel), HTTPS, cron optional

---

## 0. Hosting Compatibility Checklist (Phase 1 gate)

| Check | How | Required |
|-------|-----|----------|
| PHP version | `php -v` / `phpinfo()` | >=8.1 (target 8.2), `pdo_mysql`, `mbstring`, `curl`, `json`, `gd`/`fileinfo` |
| MySQL/MariaDB | `SELECT VERSION()` | >=5.7 / 10.3, InnoDB, utf8mb4 |
| SSL | cPanel → SSL Status | HTTPS enforced via `.htaccess` |
| File permissions | `uploads/`, `storage/` writable 755 | 755/644; `storage/` outside webroot if allowed |
| Cron | cPanel → Cron Jobs | Optional for scheduled backup; fallback Manual |
| Outbound API | `curl https://www.googleapis.com` | Needed for Drive sync; fallback Local if blocked |
| `.htaccess` / `mod_rewrite` | `apache_get_modules()` | For pretty URLs + backup protection |
| `allow_url_fopen` / `curl` | `ini_get` | Drive API |
| Timezone | `date_default_timezone_set('Asia/Dhaka')` | |
| Env / outside webroot | Check `DOCUMENT_ROOT` vs project root | If shared host locks webroot to `/public_html`, keep `config/` protected via `.htaccess deny` |

**Script:** `tools/hosting_check.php` outputs this checklist.

---

## 1. Phasing (Master Prompt §15)

### Phase 1: Audit ✅ (This folder)

**Goal:** No feature dropped.

- [x] Reconstruct ZIP file list (LEGACY_CODE_AUDIT §2)
- [x] FEATURE_INVENTORY.md
- [x] LEGACY_CODE_AUDIT.md + risk register
- [x] DATABASE_MAPPING.md + schema design
- [x] IMPLEMENTATION_PLAN.md (this)
- [x] TEST_PLAN.md
- [ ] Run `tools/hosting_check.php` on actual host (defer to deploy)

**Acceptance:** All 5 docs present & consistent; §2 “no feature dropped” traceable.

### Phase 2: Foundation

**Deliverables:**

- Project structure:
  ```
  public/               ← webroot
    index.php           ← front controller + router
    login.php / logout.php
    assets/css/app.css, js/app.js
    .htaccess
  app/
    Database.php (PDO singleton)
    Auth.php (login, session, rate limit)
    Helpers/Security.php (CSRF, xss, validation)
    Helpers/Functions.php (currency, bnDate)
    Services/PccService.php, AccountingService.php
    Views/layout/*.php, Views/pages/**/*.php
  config/
    config.example.php, database.example.php, google.example.php
  database/
    schema.sql, seed.sql, migrations/
  docs/
  storage/
    logs/, backups/, uploads/ (protected)
  tests/
  tools/
    hosting_check.php, import_sheets.php
  ```
- `database/schema.sql` + `seed.sql` (admin, accounts, categories, settings)
- Auth: `users` table, `password_hash()`, session regenerate, CSRF token per form, login rate limit (DB `login_attempts` or file), secure cookies (`httponly`, `samesite=Lax`, `secure` if HTTPS).
- Layout/navigation: Bootstrap 5 sidebar grouping PCC/Business, responsive, Bangla font (Noto Sans Bengali), BDT `৳`.
- Security helpers + logging `activity_logs`.

**Acceptance:** `php -S` local boots, login works, sidebar shows 16 nav items, no auth bypass.

### Phase 3: PCC Migration (Core)

**Order (dependency):**

1. `pcc_cases` CRUD: `public/pages/pcc/receive.php` (form), `list.php`, `view.php`, `edit.php`.
2. Tracking ID generator (seq table, FOR UPDATE, retry).
3. Search/filter/sort/pagination (`PccRepository::search()` indexed).
4. Single & bulk status update (`POST /api/pcc/status` with CSRF + txn).
5. Payment/due: `payments` + `AccountingService::postPccPayment()` double entry; due live = total-paid.
6. Payment history tab + delivery tab.
7. Reports/print/export: `pages/reports/pcc_report.php` → print CSS + CSV.
8. Drive integration: `DriveService.php` toggle local vs API; file list per case.
9. Validation of all inventory PCC-01…PCC-21 via `TEST_PLAN` §3.1.

**Acceptance:** Create/edit/search/status/bulk/payment/delivery/report all work; Tracking ID unique under concurrent test; no duplicate income.

### Phase 4: Business Management

Dependency order:

1. Customers & Suppliers CRUD + ledgers.
2. Income/Expense categories + records.
3. Cash & Bank accounts, transfer (excluded from P/L).
4. Products & Inventory (stock_movements triggers stock_quantity).
5. Sales (invoice multi-product) → stock out + receivable.
6. Purchases → stock in + payable.
7. Sales/Purchase return (reversal).
8. Ledgers (Customer/Supplier) reuse AccountingService.

**Acceptance:** Sales invoice reduces stock, increases receivable; transfer does not affect P/L; low-stock alert fires.

### Phase 5: Unified Accounting

- `AccountingService`: `post()` creates `transactions` + 2+ `transaction_entries` balanced; checks `SUM(debit)=SUM(credit)`.
- Idempotency: `UNIQUE(source_module, source_id, type)` + request token.
- Reversal: `reverseTransaction(id, reason)` creates compensating entries, sets `reversal_id`.
- Combined reports: `reports/combined.php` (Gross, Service Revenue, Direct Costs, Operating Expenses, Receivables, Payables, Cash/Bank, Net P/L) — validates prompt example.
- Double-entry invariant tests `tests/FinancialTest.php`.

**Acceptance:** Example in prompt (2000 service, 500 govt, 200 other, 1000 paid) yields profit 1300 not 2000; duplicate POST doesn't duplicate ledger.

### Phase 6: Data Migration

- `tools/import_sheets.php` CLI+web: upload CSV (exported from Sheets), column mapping UI, dry-run validation, duplicate detect by tracking_id/passport, error report CSV, `migration_logs`.
- `legacy_data JSON` preserves unknown columns; original IDs kept.
- Reconciliation: counts + SUM(paid/due) vs legacy.

**Acceptance:** Import 1000 legacy rows → counts match, failed rows downloadable, due reconciliation zero diff.

### Phase 7: QA & Deployment

- Functional + security + responsive + DB integrity + backup/restore tests per TEST_PLAN.
- Production config: `config/config.php` from `config.example.php`, secrets in env outside webroot where host allows.
- HTTPS verification + `.htaccess` hardening.
- Deployment package: ZIP of project + `docs/INSTALLATION_GUIDE.md`.

**Acceptance:** All TEST_PLAN cases pass; backup/restore round-trip succeeds; install guide allows fresh host deploy in <15 min.

---

## 2. Work Breakdown & File Map

| Module | Key Files | Est. LOC |
|--------|-----------|----------|
| Foundation | `app/Database.php`, `app/Auth.php`, `app/Helpers/*.php`, `public/index.php`, `Views/layout/*` | ~800 |
| PCC | `app/Services/PccService.php`, `app/Repositories/PccRepository.php`, `Views/pages/pcc/*`, `public/api/pcc_status.php` | ~1500 |
| Business | `Services/{Customer,Supplier,Product,Sale,Purchase,Account}Service.php` etc. | ~2500 |
| Accounting | `Services/AccountingService.php`, `transaction_entries` logic | ~600 |
| Drive/Sheets | `Services/DriveService.php`, `tools/import_sheets.php` | ~500 |
| Reports | `Views/pages/reports/*.php`, `Services/ReportService.php` | ~800 |
| Security/Backup | `Services/BackupService.php`, `Helpers/Security.php` | ~400 |
| Tests/Docs | `tests/*.php`, `docs/*.md` | ~600 |

Total ~7700 LOC + schema.

---

## 3. Risk & Mitigation (scope creep / hosting limits)

| Risk | Mitigation |
|------|------------|
| Shared host blocks outbound Drive → fallback local `storage/uploads/` | Toggle in Settings; docs instruct |
| No cron → no scheduled backup | Manual button + instructions to set cPanel cron if available |
| `DOCUMENT_ROOT` locked to `public_html` not `public/` | Provide `.htaccess` that denies `../config`, `../storage`, plus install guide variant |
| Legacy hidden fields missed | `legacy_data JSON` preserves; audit script diffs on ZIP arrival |
| Currency float errors | Strict DECIMAL, no float math; tests assert |

---

## 4. Timeline (for single agent, iterative)

- Phase 1 (Audit + Docs): Done 2026-10-11
- Phase 2-3 (Foundation+PCC): This session iterative — scaffold → PCC full
- Phase 4-5 (Business+Accounting): This session iterative continuation
- Phase 6-7 (Migration+QA+Deploy): This session final hardening + docs

Agent works phase-by-phase, commits per phase, pushes to `arena/5479f103...`.

---

## 5. Definition of Done Gates (per Prompt §17)

Phase cannot close if:
- Any FEATURE_INVENTORY item unimplemented/un-tested
- Duplicate income exists (test fails)
- Ledger `SUM(debit)!=SUM(credit)`
- Drive credential exposed in repo or client JS
- Placeholder button exists
- Backup not restore-tested

---

## 6. Immediate Next Steps (this session)

1. Create `database/schema.sql` + `seed.sql`
2. Create `app/*` foundation
3. Create `public/index.php` + layout + auth
4. Implement PCC full (Phase 3)
5. Implement Business core (Phase 4)
6. Unified accounting + reports (Phase 5)
7. Migration tool + backup + hardening (Phase 6-7)
8. Write `docs/INSTALLATION_GUIDE.md` + `DEPLOYMENT_GUIDE.md`

Proceed iteratively — commit after each phase slice.
