# Deployment, email and backups

This is the checklist for putting District Outbreak Watch on a public server. Items marked **verify** must be checked on the real host; nothing here has been run against a live domain yet.

## 0. First deployment: https://public-health-surveillance.joseneous.com

1. **DNS and web server.** Point `public-health-surveillance.joseneous.com` at the server. The web root must be the `public/` folder, not the project folder. Get a certificate before going live (`certbot --nginx -d public-health-surveillance.joseneous.com` or the host's AutoSSL), because the application redirects to HTTPS and sends HSTS in production.
2. **Code.** `git clone <repository> /var/www/public-health-surveillance && cd /var/www/public-health-surveillance`, then `composer install --no-dev --optimize-autoloader`. No Node build is needed.
3. **Database.** Create an empty MySQL 8 database and a user with rights on it only.
4. **Environment.** `cp .env.production.example .env`, fill in `DB_*` and the mail settings with the production credentials, then `php artisan key:generate --force`.
5. **Permissions.** The web user must be able to write `storage/` and `bootstrap/cache/`: `chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache`.
6. **Install.** On an empty database only (this command wipes the database):
   ```bash
   php artisan surveillance:install-sample --no-users --production-users --force
   ```
   It builds the schema, loads the Uganda geography and reference data, generates the sample series and cases, runs the checks and creates the live accounts listed in the README. Before running it, open `database/seeders/ProductionUsersSeeder.php` and set the real email addresses.
7. **Caches.** `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. **Scheduler.** Add the cron line from section 1 below.
9. **Check.** Sign in as one of the administrators, change the temporary password when asked, and open each menu item. `curl -sI https://public-health-surveillance.joseneous.com | grep -i strict-transport` must show the HSTS header.

To update later: `git pull && composer install --no-dev -o && php artisan migrate --force && php artisan config:cache route:cache view:cache`. Never run `surveillance:install-sample` on a database that holds real data.

## 1. Server

- PHP 8.3 or newer with `pdo_mysql`, `mbstring`, `gd`; MySQL 8; a web server (nginx or Apache) pointing at `public/`.
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a new `APP_KEY`, the real `APP_URL` (https), `SURVEILLANCE_SYNTHETIC_DATA=false` once real data is loaded, `SURVEILLANCE_FORCE_HTTPS=true`.
- `php artisan migrate --force && php artisan db:seed --class=RolesAndPermissionsSeeder --force && php artisan config:cache route:cache view:cache`.
- Scheduler: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`. It runs outbreak detection hourly, escalation every 15 minutes, the daily digest at 07:00 and the data-quality check at 05:30.
- Real-time updates: run `php artisan reverb:start` under a process manager (systemd or supervisor) and proxy the WebSocket path over TLS.

## 2. HTTPS and HSTS

- Certificate from Let's Encrypt (`certbot --nginx -d your-domain`) and automatic renewal.
- The application sends `Strict-Transport-Security` on secure requests and redirects plain HTTP when `SURVEILLANCE_FORCE_HTTPS=true`.
- **Verify:** `curl -sI https://your-domain | grep -i strict-transport`, then an SSL Labs test (target grade A). Keep the result as evidence.

## 3. Email

Set `MAIL_MAILER=smtp` and the provider's host, port, user and password in `.env`. Use a sender address on your own domain, then publish three DNS records for it:

| Record | Name | Example value |
|---|---|---|
| SPF (TXT) | `your-domain` | `v=spf1 include:_spf.your-provider.example -all` |
| DKIM (TXT) | `selector._domainkey.your-domain` | the public key your provider gives you |
| DMARC (TXT) | `_dmarc.your-domain` | `v=DMARC1; p=quarantine; rua=mailto:dmarc@your-domain; adkim=s; aspf=s` |

Start DMARC at `p=none` for two weeks, read the reports, then move to `quarantine`.

**Verify:** `php artisan surveillance:test-mail you@your-domain`, open the message in Gmail, choose "Show original" and confirm `SPF: PASS`, `DKIM: PASS`, `DMARC: PASS`. Alert emails already carry `Auto-Submitted` and `List-Unsubscribe` headers and contain counts and areas only, never case details.

## 4. Backups

- Nightly dump, kept 14 days, copied off the server: `mysqldump --single-transaction --routines outbreak_watch | gzip > /backups/ow-$(date +%F).sql.gz`.
- Case records are encrypted at the application level for the raw notification payload only; protect the dump like any health data (encrypted disk or `gpg` before copying off the server).
- **Verify a restore** once a quarter on a spare database: `gunzip -c file.sql.gz | mysql outbreak_watch_restore`, then sign in and check the latest week.

## 5. Before go-live

- Change all sample passwords or run without the sample users (`surveillance:install-sample --no-users`), and create real accounts with `surveillance:create-user`.
- Run `composer audit` and `npm audit` equivalents (vendored front-end libraries are listed in `README.md`); review the result.
- Lighthouse (mobile) on the live URL for the public pages and the signed-in dashboard; test on a throttled connection.
