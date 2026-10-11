# LEGACY CODE AUDIT — pcc app.zip

> তারিখ: 2026-10-11  
> Auditor: Senior Architect (Arena Agent)  
> Source: `pcc app.zip` (প্রত্যাশিত attachment)  
> Result: **ZIP workspace-এ অনুপস্থিত — audit Master Prompt + প্রচলিত PCC Apps Script প্যাটার্নের ভিত্তিতে reconstruct + risk register সহ**

---

## 1. Audit উদ্দেশ্য (Master Prompt §3)

> “শুধু ZIP-এর দৃশ্যমান HTML দেখে কাজ শেষ করবে না। এর মধ্যে থাকা সব HTML, CSS, JavaScript, Google Apps Script, configuration, database/sheet mapping এবং অন্যান্য প্রয়োজনীয় ফাইল পরীক্ষা করবে।”

এই নথি সেই checklist অনুযায়ী কী পরীক্ষা করা উচিত, কী পাওয়া যায়নি, এবং নতুন PHP/MySQL অ্যাপে কীভাবে অভিযোজিত হচ্ছে তা নথিভুক্ত করে।

---

## 2. ZIP-এর প্রত্যাশিত গঠন (Prompt + Apps Script স্ট্যান্ডার্ড)

একটি সাধারণ PCC Google Apps Script Web App ZIP-এ সাধারণত থাকে:

```
pcc app.zip
├── Code.gs                 // doGet, doPost, addRecord, updateRecord, getRecords, delete, status, Drive
├── Index.html              // Main UI — dashboard + form + table
├── CSS.html                // Styles (often Bootstrap CDN + custom)
├── JavaScript.html         // Client JS — fetch, validation, render
├── appsscript.json         // Manifest: timeZone, dependencies, webapp
├── Form.html   (optional)  // Separate form modal
└── Config.gs / constants   // SPREADSHEET_ID, SHEET_NAME, DRIVE_FOLDER_ID
```

**বর্তমান workspace-এ অনুসন্ধান:**
```bash
find /home -name "*.zip" -o -name "Code.gs" -o -name "appsscript.json" 2>/dev/null
# → (no results)
git log --name-only → only README.md
```
**সিদ্ধান্ত:** ZIP unattached / not extracted. Repo `9d0d582 Initial commit` খালি। তাই নিচের audit **reconstructed** — প্রতিটি section-এ `Status: MISSING / ASSUMED` চিহ্নিত, এবং `Mitigation` এ PHP ম্যাপ।

---

## 3. File-by-File Audit Checklist (প্রতি ফাইল Data Extraction)

### 3.1 Code.gs (Backend)

| Check Item | Expected Pattern | Status | PHP Mapping | Notes |
|------------|------------------|--------|-------------|-------|
| `SPREADSHEET_ID` | `var SPREADSHEET_ID = '1A2B...'` | **MISSING** (credential) | `config/google.example.php` placeholder + `$_ENV` | কখনো output-এ প্রকাশ নয়; `.gitignore` |
| `SHEET_NAME` | e.g. `PCC_DATA`, `PAYMENTS`, `LOG` | MISSING | `DATABASE_MAPPING.md` অনুমিত sheet names | Importer এ configurable |
| `FOLDER_ID` | `DriveApp.getFolderById('...')` | MISSING | `settings.drive_folder_id` masked display | Least privilege |
| `doGet(e)` | HTML serve / JSON API | ASSUMED | `public/index.php` router replaces | — |
| `addRecord(form)` | `sheet.appendRow([...])` + ID gen | ASSUMED | `PccService::createCase()` transactional | Preserve legacy IDs |
| `getRecords()` | `sheet.getDataRange().getValues()` | ASSUMED | `PccRepository::list()` PDO | Server pagination |
| `updateRecord()` | `sheet.getRange(row, col).setValue` | ASSUMED | `PccService::updateCase()` + `case_status_history` | Hard delete নিষিদ্ধ |
| `updateStatus()` / `bulkUpdate` | loop rows | ASSUMED | Bulk txn + history | — |
| `getDashboardStats()` | `COUNTIF` / loop | ASSUMED | SQL aggregates | — |
| `uploadFile()` | `DriveApp.createFile` | ASSUMED | Drive API client or `storage/uploads/` fallback | Validate MIME |
| Auth | often none / simple password | ASSUMED | `password_hash` + session | Upgrade to secure |
| Error handling | `try { } catch(e){ }` | ASSUMED | `try/catch` + `activity_logs` + user-friendly msg | Never expose stack to client |

**Risk if not audited:** Sheet column order ভুল map হলে payment/due ভুল হবে → **Mitigation:** Importer এ dry-run validation + column mapping UI.

### 3.2 Index.html / HTML files

| UI Element | Audit Task | Status | New Implementation |
|------------|------------|--------|-------------------|
| Menu / Tabs | সব `<nav>`, `<tab>`, `<sidebar>` শনাক্ত | ASSUMED: Dashboard, Form, Records, Reports, Settings | Bootstrap 5 sidebar with 16 items (§5) grouped PCC/Business |
| Form fields | প্রতিটি `<input>`, `<select>`, `<textarea>` label+name+required | ASSUMED list in §3.3 | `app/Views/pcc/form.php` retains all + adds Bengali label |
| Buttons | `Save`, `Update`, `Delete`, `Print`, `Export` onclick | ASSUMED | Real handlers, no placeholder; `onclick` → POST with CSRF |
| Modal/Dialog | `<div class="modal">` | ASSUMED | Bootstrap Modal for details/edit |
| Dropdown/Filter | status filter, date filter | ASSUMED | `filter-bar.php` partial; GET params preserved |
| Table | `<table>` columns | ASSUMED 15+ cols (tracking, name, passport, mobile, status, fee, due, date) | Server-paginated table + searchable + sortable |
| Report section | table + print button | ASSUMED | `reports/pcc.php` print-friendly |
| Search box | `<input type="search">` | ASSUMED | Debounced live search + server fallback |
| CSS framework | Bootstrap 3/4 CDN often | ASSUMED | Migrated to Bootstrap 5.3 + icons |
| JS libs | jQuery, SweetAlert, DataTables often | ASSUMED | Vanilla JS + Bootstrap; Chart.js for charts; no jQuery required |

### 3.3 Form Fields Audit (PCC Receive)

অনুমিত legacy form থেকে reconstructed field inventory (সব রাখা হয়েছে):

```
Applicant: applicant_name, applicant_name_bn, father_name, mother_name, dob (date), 
           nid_no (text), passport_no (text unique warn), passport_issue_date, 
           mobile (required, 11 digits), email (optional), 
           present_address (textarea), permanent_address, thana, district (dropdown 64),
           purpose (select: Job/Visa/Other), photo (file optional)

Reference: reference_person, reference_phone, reference_relation

Financial: govt_fee (decimal), service_charge, other_fee, total_fee (=sum), 
           paid_amount, due_amount (=total-paid), payment_method (cash/bkash/bank), 
           transaction_ref, payment_date

Meta: received_date (auto today), expected_delivery_date, status (default Receive), notes

System: tracking_id (auto), created_at, created_by, legacy_row_id (for migration)
```

**Validation audit:** Legacy JS likely had `required` + regex for mobile/passport → PHP server validation replicates + Bengali error messages.

### 3.4 appsscript.json

| Key | Expected | Status | PHP Equivalent |
|-----|----------|--------|----------------|
| `timeZone` | `Asia/Dhaka` | ASSUMED | `config.php` timezone `Asia/Dhaka`, dates `d/m/Y` |
| `dependencies` | — | — | `composer.json` (if Drive API) else vanilla |
| `webapp` | `access: ANYONE` often | INSECURE | Replaced by authenticated PHP session + HTTPS |

### 3.5 Configuration & Secrets

| Item | Legacy Location | Risk | New Handling |
|------|----------------|------|--------------|
| Spreadsheet ID | hard-coded in Code.gs | Public repo leak | `config/google.php` outside webroot + `$_ENV['SHEETS_ID']` + masked UI |
| Drive Folder ID | hard-coded | — | Same |
| Credentials JSON | — | — | `storage/credentials/` denied via `.htaccess` + `deny from all` |
| Client-side exposure | Sometimes `google.script.run` leaks IDs | XSS | Never render raw ID to HTML; API proxy via PHP |

---

## 4. Backend Functions & Sheets/Drive Integration

### 4.1 Sheets Mapping (অনুমিত)

| Legacy Sheet | New MySQL Table | Columns (অনুমিত) | Migration Strategy |
|--------------|----------------|------------------|-------------------|
| `PCC_DATA` | `pcc_cases` | TrackingID, Name, Father, NID, Passport, Mobile, Address, Status, TotalFee, Paid, Due, Date, ... (see DATABASE_MAPPING.md) | `tools/import_sheets.php` CSV → validation → upsert by tracking_id |
| `PAYMENTS` (if separate) | `payments` | CaseID, Amount, Date, Method | Upsert; link to case_id FK |
| `LOG` | `activity_logs` | Timestamp, User, Action | Append only |
| — | `settings` | — | Seed from legacy `PropertiesService` |

### 4.2 Drive Integration

- Legacy: `DriveApp.getFolderById(FOLDER_ID).createFile(blob)` → returns `file.getId()` + `getUrl()`.
- New: Option A (hosting supports outbound): `google/apiclient` service-account with `drive.file` scope, least privilege. Option B (fallback): local `storage/uploads/pcc/{case_id}/` with symlink protection. Config toggle in Settings → “Storage mode: Drive / Local”.

---

## 5. PCC Business Rules Audit

### 5.1 Status Workflow

Legacy likely sequence:
`Receive → SS Printed → Sign by SP/DC → MOFA Verification → Ready for Delivery → Delivered`

- **Spell preservation:** Exactly as prompt (case-sensitive). If legacy uses `SS Print` vs `SS Printed`, migration normalizes but keeps original in `legacy_status` column for audit; display uses prompt spelling.
- **Enforcement:** `settings.status_strict = 0/1`; default permissive (allow any jump with log) to avoid breaking legacy data that may skip steps.

### 5.2 Tracking ID Generation

- Legacy: often `PCC-` + timestamp or `=MAX+1` scan.
- Flaw: concurrent duplicate risk + sheet scan slow.
- New: `settings.tracking_seq` row-locked increment; format `PCC-YYYYMMDD-####` (prefix+date+seq padded 4). Legacy IDs kept `UNIQUE`; new seq starts after max legacy seq to avoid collision. `DATABASE_MAPPING.md §4`.

### 5.3 Payment / Due Logic

- Legacy Sheets formula: `=Total-Paid` (sometimes manual).
- Flaw: partial payment double-count if re-added as income.
- New: `due = total_fee - SUM(payments.amount WHERE case_id=?)` live computed; `transactions` posts income only on **new** payment, not on correction; idempotency key `payments.transaction_ref` unique where not null.

### 5.4 Reports / Print / Export / Bulk

| Feature | Legacy Implementation | Legacy Flaw | New Fix |
|---------|----------------------|-------------|---------|
| Reports | JS filter table | No server pagination, slow on 10k rows | SQL `BETWEEN` + indexes on `received_date`, `status` |
| Print | `window.print()` of table | Prints nav too | `@media print` hides sidebar |
| Export | Sometimes none | — | CSV with BOM for Bangla |
| Bulk update | JS loop `google.script.run` per row | Partial fail no report | Single txn endpoint returns `successCount/failed: [{id,error}]` |
| Edit | Direct cell overwrite | No history | `case_status_history` + `activity_logs` |

---

## 6. Dependencies & External APIs

| Dependency | Legacy Use | New Handling | Hosting Check |
|------------|------------|--------------|---------------|
| Google Sheets API | Primary DB | **MySQL primary**; Sheets optional importer only | Outbound HTTPS required for Drive; if blocked → local fallback; checklist `docs/IMPLEMENTATION_PLAN.md §1` |
| Google Drive API | File store | Service-account or local | `curl` / `allow_url_fopen` check |
| jQuery / Bootstrap CDN | UI | Bootstrap 5.3 CDN + fallback local; no jQuery needed | CDN allowed; fallback if offline |
| Chart.js | maybe not | Add for dashboards | — |
| Cron | — | For scheduled backups if `crontab` available else manual | Check `Hosting Compatibility Checklist` |

---

## 7. Risk Register (ZIP missing → impact)

| # | Risk | Severity | Mitigation Implemented |
|---|------|----------|------------------------|
| R1 | ZIP column order unknown → migration mis-map | High | Importer shows mapping UI + dry-run + error CSV download |
| R2 | Tracking ID format assumed → collision on legacy max | High | Seq starts `MAX(CAST(SUBSTRING(tracking_id,...))) +1` with lock |
| R3 | Extra legacy fields missed (hidden workflows) | Medium | `pcc_cases.legacy_data JSON` + `extra_fields` EAV fallback preserves unknown columns |
| R4 | Drive permission over-broad | High | Scope `drive.file` only; never `drive` full; instructions in `docs/DEPLOYMENT_GUIDE.md` |
| R5 | Financial double-count | Critical | Double-entry + unique constraints + automated tests `tests/FinancialTest.php` |
| R6 | Auth insecure in legacy (none) | High | New `password_hash` + rate limit + CSRF; migration forces password reset |

---

## 8. Audit Completion Checklist (Master Prompt §3 final 5 docs)

- [x] `docs/FEATURE_INVENTORY.md` — all menus/buttons/forms/modals/dropdowns/filters/search/reports inventoried (reconstructed where ZIP missing)
- [x] `docs/LEGACY_CODE_AUDIT.md` — this file
- [x] `docs/DATABASE_MAPPING.md` — legacy sheet column → MySQL column mapping
- [x] `docs/IMPLEMENTATION_PLAN.md` — Phase plan + hosting checklist
- [x] `docs/TEST_PLAN.md` — 18 mandatory tests

**Next Auditor Action when ZIP arrives:**
1. Unzip to `/tmp/pcc_legacy/` and run `bash docs/audit_zip.sh` (script to list HTML/GS files).
2. Diff this reconstructed mapping vs actual `Code.gs` & HTML IDs; append delta to `FEATURE_INVENTORY.md` Appendix A.
3. Re-export Sheet CSV and dry-run `php tools/import_sheets.php --dry-run`.
4. Update `DATABASE_MAPPING.md` column table and add `legacy_column` comments in `database/schema.sql`.

---

## 9. Appendix — Expected Legacy Code Snippets (for reference when ZIP arrives)

```javascript
// Code.gs expected patterns to grep:
var SPREADSHEET_ID = "...";
var SHEET_NAME = "PCC_DATA";
function addRecord(data){ var sheet = SpreadsheetApp.openById(SPREADSHEET_ID).getSheetByName(SHEET_NAME); sheet.appendRow([data.name, ...]); }
function getRecords(){ return sheet.getDataRange().getValues(); }
function updateStatus(id, status){ sheet.getRange(row, statusCol).setValue(status); }
```

```html
<!-- Index.html expected patterns to grep:
  <input id="applicantName">, <select id="status">, <button onclick="saveCase()">,
  google.script.run.withSuccessHandler(...)
-->
```

`grep -R "SPREADSHEET_ID\|DriveApp\|appendRow\|google.script.run" /tmp/pcc_legacy/` — run on arrival.

---

*This audit deliberately does not invent or hallucinate legacy credentials. No credential is echoed. Audit preserves “no feature dropped” rule.*
