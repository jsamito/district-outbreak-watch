# District Outbreak Watch

A secure, mobile-friendly disease-surveillance system for national and district health teams in Uganda. It receives notifications from facilities, turns them into trusted weekly indicators, flags unusual rises, alerts the right people without flooding them, supports case investigation, and publishes only aggregated data.

Built with Laravel 13, AdminLTE 2, Chart.js, Leaflet and Laravel Reverb. MySQL 8 is the database.

## What it does

| Area | Capability |
|---|---|
| Dashboards | Overview, weekly trends, district map and comparison, u-chart quality control, vaccination coverage, reporting completeness, public attention, 1-4 week projections |
| Alerts | Outbreak-signal and single-case rules; one alert per area and disease while a signal lasts; severity tiers; at most 5 immediate emails per person per hour; daily digest; escalation to national managers when unacknowledged |
| Cases | Line list and case detail with a timeline, lab worklist and role-based detail. Every view is audited and **cases can never be exported** |
| Live data | `POST /api/v1/notifications` (JSON or FHIR R4 Bundle, idempotent) updates indicators, checks alert rules and updates open dashboards over WebSockets, with polling as fallback |
| Standards | ICD-10 on diseases and diagnoses; FHIR `MeasureReport` at `/fhir/MeasureReport`; FHIR Bundle accepted on the notification API |
| Public site | National totals only, data sources and licences, sitemap, robots.txt, structured data, shareable summary card |
| Security | Roles plus area scope enforced on the server, CSP with nonces, HSTS, no-store caching on signed-in pages, throttled sign-in, audit log |

## Roles

`system_admin`, `national_manager`, `epidemiologist`, `district_officer`, `clinician`, `lab_officer`. A role says what a user can do; their areas say where. See `docs/PLAN.md` for the capability matrix.

## Set up

Requirements: PHP 8.3+ with `pdo_mysql`, `gd` (with FreeType), `zip`, `intl`, Composer, MySQL 8.

```bash
composer install
cp .env.example .env && php artisan key:generate
# edit .env: DB_*, MAIL_*, APP_URL
php artisan surveillance:install-sample     # database, Uganda geography, sample series, cases, projections
php artisan serve                           # http://localhost:8000
```

Sample accounts (created by `surveillance:install-sample`, password in `SAMPLE_USER_PASSWORD` in `.env`): `admin@`, `manager@`, `epi@`, `officer.kasese@`, `officer.kampala@`, `clinician@`, `lab@outbreak-watch.test`. **Never create these on a production database.**

Create real users with `php artisan surveillance:create-user EMAIL "Name" ROLE --area=CODE`.

Background processes for a full installation:

```bash
php artisan schedule:work        # hourly detection, escalation every 15 min, daily digest 07:00
php artisan queue:work           # if QUEUE_CONNECTION=database
php artisan reverb:start         # live dashboard updates (optional; the page falls back to polling)
```

Facility systems register with `php artisan surveillance:issue-api-token "Name" FACILITY_CODE` and send `POST /api/v1/notifications` with `Authorization: Bearer TOKEN` and an `Idempotency-Key` header. `php artisan surveillance:simulate-notifications` sends test notifications through the same pipeline.

## Data

Real, open data: Uganda boundaries (HDX COD-AB), district population by age and sex (UBOS via UNFPA, HDX COD-PS), facility locations (Healthsites, OpenStreetMap-derived), WHO immunization estimates, Wikipedia page views. No open weekly facility-level disease or district immunization series exists for Uganda, so those are **generated sample data** from real district populations and are labelled as such everywhere. Sources, licences and limits are in `DATA.md` and on the public "About the data" page. To use real surveillance data, load it through the notification API or replace the sample loader; set `SURVEILLANCE_SYNTHETIC_DATA=false` to remove the sample-data notices.

API clients can also be registered on the Admin > API clients screen (token shown once). Daily jobs: `surveillance:check-quality` updates the data-quality flags; `surveillance:test-mail you@example.org` checks outgoing email. Deployment, DNS records for email and backups are in `docs/DEPLOYMENT.md`.

Refresh the raw data files with `php artisan surveillance:prepare-geography DIR` (HDX downloads in `DIR`) and `php artisan surveillance:fetch-reference`.

## Production checklist

- Serve over HTTPS only. The app redirects HTTP and sends HSTS when `APP_ENV=production`; also set the header in the web server (`Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"`).
- Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, strong DB credentials, a real mail transport, and `TRUSTED_PROXIES` if behind a proxy.
- Email authentication for the sending domain: SPF, DKIM and DMARC.
- Scheduled database backups with a tested restore; keep the audit log.
- Run `php artisan config:cache route:cache view:cache` and serve static assets with compression and long cache lifetimes.

## Tests

```bash
php artisan test
```

## Project documents

`docs/PLAN.md` (scope, models, phases), `docs/REQUIREMENTS.md` (stakeholders, user stories, indicators, request lifecycle), `docs/RESEARCH.md` (sources), `DATA.md` (provenance).
