# Deployment Guide — Production

## HTTPS
- Enable AutoSSL / Let's Encrypt via cPanel → SSL/TLS Status → Run AutoSSL.
- Uncomment HTTPS force in `public/.htaccess`:
  ```apache
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
  ```
- In `config/config.php` set `'cookie_secure'=>true`.

## Security Headers (already in public/.htaccess)
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

## File Protection
- `config/.htaccess` and `storage/.htaccess` deny all.
- Test: `curl -I https://domain/config/config.php` → 403, `https://domain/storage/backups/file.sql.gz` → 403 (only PHP proxy download allowed).

## Database
- Use strong password, restrict user to `localhost` only.
- Charset utf8mb4_unicode_ci already in schema.
- Enable InnoDB (default).

## Backups
- Manual via UI; also recommend host-level daily backup (ask provider).
- Store off-server encrypted copy weekly.
- Test restore quarterly on staging subdomain.

## Google Credentials
- Never commit `storage/credentials/service-account.json`.
- Scope: `https://www.googleapis.com/auth/drive.file` only (least privilege).
- Do not share folder as public; share only with service account email.

## Updates
- Pull via Git: `git pull origin main` on server (if SSH) or upload changed files.
- After pull, run `php tools/hosting_check.php` and compare `database/schema.sql` diff; apply migrations manually.

## Monitoring
- Check `activity_logs` daily for anomalies.
- Review `backup_logs` for failures.
- Set up UptimeRobot for `https://domain/login.php`.

## Performance
- Enable OPcache in cPanel → Select PHP Version → Options → opcache.
- Keep tables indexed (already); run `OPTIMIZE TABLE` monthly if >50k rows.

## Rollback
- Keep last 3 backups in `storage/backups/` + off-server.
- To rollback code: `git checkout <previous-tag>` or re-upload previous ZIP.

