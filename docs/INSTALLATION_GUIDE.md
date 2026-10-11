# Installation Guide — SR Computer Integrated Business Management

> Stack: PHP 8.1+ (8.2 recommended), MySQL/MariaDB, Bootstrap 5, no Node/Docker required.

## 1. Hosting Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `mbstring`, `curl`, `json`, `fileinfo`, `gd`
- MySQL 5.7+ / MariaDB 10.3+ with InnoDB & utf8mb4
- Apache with `mod_rewrite` + `.htaccess` allowed (Nginx: map public/ as root)
- HTTPS (SSL certificate)
- Recommended: `allow_url_fopen` or `curl` for optional Drive API; Cron for scheduled backup (optional)

Run pre-check: `php tools/hosting_check.php` or open `https://yourdomain/tools/hosting_check.php` (delete after check).

## 2. Upload

### Option A — Standard (public_html is webroot)
If host locks webroot to `public_html`:

1. Upload **contents of `public/`** into `public_html/` (index.php, login.php, assets/, .htaccess)
2. Upload remaining folders to **one level above** `public_html` (so `public_html/../app`, `../config`, `../database`, `../storage`):
   ```
   /home/username/
     app/
     config/
     database/
     docs/
     storage/
     tests/
     tools/
     public_html/  ← contains index.php etc.
   ```
3. In `public_html/index.php`, adjust include paths if needed:
   ```php
   require_once __DIR__ . '/../app/Database.php'; // now ../app from public_html
   ```
   (The provided `public/index.php` already uses `__DIR__ . '/../app'` so it works when `public/` is renamed to `public_html`.)

### Option B — Modern host (you can set document root)
Set document root to `/path/to/project/public` and upload whole project as-is.

## 3. Database Setup

1. Create database `srcomputer` (or any name) via cPanel → MySQL Databases (utf8mb4_unicode_ci).
2. Create user & grant all privileges.
3. Import schema:
   ```bash
   mysql -u username -p database_name < database/schema.sql
   mysql -u username -p database_name < database/seed.sql
   ```
   Or via phpMyAdmin → Import → `schema.sql` then `seed.sql`.

## 4. Configuration

1. Copy `config/config.example.php` → `config/config.php` (if not already).
2. Edit `config/config.php`:
   ```php
   'db' => [
     'host' => 'localhost',
     'database' => 'your_db_name',
     'username' => 'your_db_user',
     'password' => 'your_db_pass',
   ],
   'app' => [
     'url' => 'https://yourdomain.com',
     'timezone' => 'Asia/Dhaka',
     'env' => 'production',
   ],
   'security' => [
     'cookie_secure' => true, // true if HTTPS
   ],
   ```
3. `config/config.php` and `storage/credentials/` are denied via `.htaccess` (Require all denied).

## 5. Permissions

```bash
chmod 755 storage storage/uploads storage/backups storage/logs
chmod 644 config/config.php
```

## 6. First Login

- URL: `https://yourdomain.com/login.php`
- Default: **admin / admin123**
- Immediately change password via Settings → Security.

## 7. Google Drive (optional)

1. Create Google Cloud project → Enable Drive API → Create Service Account → Download JSON.
2. Share your Drive folder with service account email (Viewer/Editor with least privilege).
3. Place JSON at `storage/credentials/service-account.json`.
4. Settings → Google Integration → set Folder ID, Sheets ID, mode=drive.
5. If host blocks outbound HTTPS, keep mode=local (uses `storage/uploads/`).

Credentials never committed; `.gitignore` covers `storage/credentials/*`.

## 8. Backup & Cron (optional)

- Manual: Backup & Restore → Create Backup Now (protected download).
- Scheduled: cPanel → Cron Jobs → Add:
  ```
  0 2 * * * /usr/bin/php /home/username/tools/backup_cron.php >/dev/null 2>&1
  ```
  (If `backup_cron.php` not present, create shim calling same PDO dump logic.)

## 9. Import Legacy Sheets Data

1. In old Google Sheets, File → Download → CSV.
2. Backup current DB (Backup → Create).
3. Run `php tools/import_sheets.php --file=/path/to.csv --dry-run` first to validate.
4. Then `php tools/import_sheets.php --file=/path/to.csv` (or use web upload in future Settings).
5. Check `migration_logs` and compare row counts.

## 10. Post-Install Checklist

- [ ] `tools/hosting_check.php` all OK (then delete or restrict it)
- [ ] HTTPS enforced (visit http → redirects to https)
- [ ] Login works, session regenerates, wrong password rate-limited
- [ ] Create test PCC case → tracking ID generates → search works
- [ ] Upload file → appears in Drive/Files page, protected
- [ ] Create customer → customer ledger shows PCC + sales unified
- [ ] Create product → sale → stock decreases → purchase → stock increases
- [ ] Transfer between accounts → P/L unchanged
- [ ] Backup → download → restore on test DB round-trip
- [ ] Print report → only report prints, Bangla correct
- [ ] Mobile view (375px) sidebar collapses, tables scroll

## 11. Troubleshooting

- DB connection failed → check `config/config.php` host/user/pass, and `mysql -u user -p -e "SHOW DATABASES;"`
- 500 error → check `storage/logs/` or `error_log`, enable `debug=>true` temporarily.
- File upload fails → check `php -i | grep upload_max_filesize` (needs >=5M) & `storage/uploads` writable.
- Drive API fails → keep mode=local; check `curl` enabled.
- Tracking ID duplicate on import → script skips duplicates; check `migration_logs.errors`.

## 12. Security Hardening

- Delete `tools/hosting_check.php` from public access after check or restrict via `.htaccess`.
- Ensure `config/` and `storage/` not web-accessible (test `https://yourdomain.com/config/config.php` → 403).
- Set `cookie_secure=true` when HTTPS active.
- Regular backups off-server (download & store encrypted elsewhere).

---
Need help? See `docs/DEPLOYMENT_GUIDE.md` for production hardening and `docs/DATABASE_MAPPING.md` for column mapping.
