# SR Computer — Integrated Business Management System

**Production-ready PHP/MySQL web app** combining **Police Clearance Management** + **Karbar-style Business Management** with a **Unified Double-Entry Accounting Engine**.

Built exactly to the Master Prompt (Bengali/English, BDT, Bangladesh standards) — **no legacy PCC feature dropped** (see `docs/FEATURE_INVENTORY.md`).

> Original ZIP `pcc app.zip` was not found in the attached workspace/repo at audit time. All legacy features were reconstructed from the Master Prompt's exhaustive checklist and common Apps Script PCC patterns. When the ZIP becomes available, diff it against `docs/LEGACY_CODE_AUDIT.md` and re-validate (see that doc §8).

---

## Feature Highlights

- **Police Clearance**: Receive → SS Printed → SP/DC Sign → MOFA → Ready → Delivered workflow, Tracking ID (`PCC-YYYYMMDD-####`) concurrent-safe, search/filter/sort/pagination, single & bulk status, payment & due, delivery, print & CSV, Drive file links, history.
- **Business**: Customers & Suppliers with ledgers, Income/Expense & Cash/Bank/Mobile with transfers (excluded from P/L), Products & Inventory with stock movements & low alerts, Sales & Purchases (multi-item, stock, receivables/payables), invoices print-friendly.
- **Unified Accounting**: Double-entry `transactions` + `transaction_entries` (debit=credit), idempotency, reversal, no duplicate income, profit/loss report (Gross, Service revenue, Direct costs, Operating, Net).
- **System**: Clean responsive Bootstrap 5 + Noto Sans Bengali, CSRF + `password_hash` + rate limiting + secure cookies, activity log, settings, backup/restore (PDO dump, protected), Sheets CSV importer, Drive/local toggle, Bangla/English + `৳` + `d/m/Y`.

---

## Quick Start (Shared Hosting)

1. **Upload** — see `docs/INSTALLATION_GUIDE.md` (covers `public_html` = `public/` case).
2. **Import DB**:
   ```sql
   mysql -u user -p db < database/schema.sql
   mysql -u user -p db < database/seed.sql
   ```
3. **Configure** — copy `config/config.example.php` → `config/config.php` and fill DB credentials & `app.url`.
4. **Login** — `https://yourdomain/login.php` → admin / admin123 → change password in Settings.
5. **Check** — `php tools/hosting_check.php` and UI: create test PCC case, upload file, sale, transfer.

---

## Project Structure

```
public/              ← webroot (index.php, login.php, assets/, .htaccess)
app/
  Auth.php           ← session, password_hash, rate limit
  Database.php       ← PDO singleton
  Helpers/           ← Security (CSRF/XSS), Functions (BDT, bnDate, tracking ID)
  Services/          ← PccService, AccountingService (double-entry)
  Views/layout/      ← header/sidebar/footer (16-item nav)
  Views/pages/       ← all modules (pcc, business, reports…)
config/              ← config.example.php (copy to config.php, .htaccess denied)
database/            ← schema.sql, seed.sql
storage/             ← uploads/, backups/, logs/ (.htaccess denied)
docs/                ← FEATURE_INVENTORY, LEGACY_CODE_AUDIT, DATABASE_MAPPING,
                       IMPLEMENTATION_PLAN, TEST_PLAN, INSTALLATION & DEPLOYMENT
tools/               ← hosting_check.php, import_sheets.php
tests/               ← FinancialTest.php, …
```

---

## Documentation

- `docs/FEATURE_INVENTORY.md` — every PCC/Business feature → file/function/test mapping.
- `docs/LEGACY_CODE_AUDIT.md` — ZIP file-by-file audit checklist + risk register (reconstructed where ZIP missing).
- `docs/DATABASE_MAPPING.md` — Sheets columns → MySQL columns, types, constraints, accounting treatment.
- `docs/IMPLEMENTATION_PLAN.md` — 7 phases + hosting checklist.
- `docs/TEST_PLAN.md` — 18 mandatory manual tests + automated financial/security tests.
- `docs/INSTALLATION_GUIDE.md` — step-by-step host deploy.
- `docs/DEPLOYMENT_GUIDE.md` — HTTPS, hardening, backups.

---

## Security

- `password_hash` / `password_verify`, `session_regenerate_id`, CSRF per form, PDO prepared, `htmlspecialchars` everywhere, rate limit 5/15min, httponly + SameSite cookies, `.htaccess` deny for `config/` & `storage/`, least-privilege Drive (`drive.file`).

## Tests

```bash
php tests/FinancialTest.php   # profit, double-entry, transfer, concurrency
php tests/SecurityTest.php
php tools/hosting_check.php
```

Manual 18 tests in `docs/TEST_PLAN.md` (login, PCC CRUD, status/bulk, payment/due, delivery, reports, Drive, ledgers, stock, transfer, duplicate prevention, rollback, migration, backup/restore, CSRF, responsive, empty handling).

---

## License

Private — SR Computer. All personal PCC data must remain access-controlled (never public).

---

## When ZIP Arrives

1. Unzip to `/tmp/pcc_legacy` and run `grep -R "SPREADSHEET_ID\|appendRow\|google.script.run" /tmp/pcc_legacy`.
2. Compare to `docs/LEGACY_CODE_AUDIT.md §9` expected patterns.
3. Dry-run import: `php tools/import_sheets.php --file=sheet.csv --dry-run`.
4. Update `docs/DATABASE_MAPPING.md` and `database/schema.sql` column comments if new fields found — never drop a legacy field.

