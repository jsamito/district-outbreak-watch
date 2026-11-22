# District Outbreak Watch

A secure, mobile-friendly disease-surveillance system for national and district health teams in Uganda. It receives notifications from facilities, turns them into trusted weekly indicators, flags unusual rises, alerts the right people without flooding them, supports case investigation, and publishes only aggregated data.

Built with Laravel 13, AdminLTE 2 (CSS), Chart.js, Leaflet, Alpine.js and Laravel Reverb. MySQL 8 is the database.

> **About the data.** Boundaries, population, facility locations, WHO immunization estimates and Wikipedia page views are real open data. Weekly facility reports, immunization doses and all case records are **generated sample data**, because no open weekly facility-level series exists for Uganda. They are labelled as such everywhere while `SURVEILLANCE_SYNTHETIC_DATA=true`. No real patient information is held. Details and licences: [DATA.md](DATA.md).

## Contents

1. [What it does](#what-it-does)
2. [Screens and who sees them](#screens-and-who-sees-them)
3. [Set up](#set-up)
4. [Configuration](#configuration)
5. [Commands](#commands)
6. [Notification API and standards](#notification-api-and-standards)
7. [How the system works](#how-the-system-works)
8. [Security and privacy](#security-and-privacy)
9. [Accessibility, performance and browsers](#accessibility-performance-and-browsers)
10. [Tests](#tests)
11. [Deployment](#deployment)
12. [Project layout](#project-layout)
13. [Documents](#documents)
14. [Known limits](#known-limits)

## What it does

| Area | Capability |
|---|---|
| Dashboards | Overview with in-place disease switching, weekly trends with last-year comparison and moving average, district map (drill-down), u-chart quality control, vaccination coverage, reporting completeness, public attention, 1 to 4 week projections with intervals |
| Case analytics | Epidemic curve, age and sex pyramid, case fatality with 95% intervals, onset-to-notification time, laboratory follow-up. Counts only, no identifiers |
| Alerts | Four rule methods (outbreak signal, fixed threshold, cluster, single case); one alert per area and disease while a signal lasts; cooldown; severity tiers; at most 5 immediate emails per person per hour; daily digest; escalation to national managers; per-user preferences |
| Cases | Line list and case detail with timeline, lab worklist, role-based detail. Every view is audited and **cases can never be exported** |
| Data quality | Six daily checks (missing and late reports, coverage above 100%, missing population, implausible jumps, possible duplicate cases) with a review screen |
| Working tools | Notes pinned to a week on the trends chart; named saved views of filters per screen |
| Live data | `POST /api/v1/notifications` (JSON or FHIR R4 Bundle, idempotent) updates indicators, checks alert rules and updates open dashboards over WebSockets, with polling as fallback |
| Administration | Users and roles with area scope, alert rules, data sources, API clients, audit log |
| Public site | National totals only, data sources and licences, sitemap, robots.txt, structured data, shareable summary card |

## Screens and who sees them

| Role | Sees |
|---|---|
| `system_admin` | Users, API clients, alert rules, data sources, audit log. No case records |
| `national_manager` | Every area: dashboards, forecasts, alerts (acknowledge), alert rules, exports, audit summary, data-quality review |
| `epidemiologist` | Dashboards, forecasts, alerts, pseudonymised case list and analytics, exports, data-quality review |
| `district_officer` | Their district: dashboards, full case detail, notify cases, lab results, alerts (acknowledge), exports, data-quality review |
| `clinician` | Their facility or district: notify and update cases, alerts, dashboards, exports |
| `lab_officer` | Lab worklist and results only |

A role says what a person can do; their assigned areas say where. Both are enforced on the server for every request, so changing an ID in a URL never reveals another district. The full matrix is in [docs/PLAN.md](docs/PLAN.md).

## Set up

Requirements: PHP 8.3+ with `pdo_mysql`, `gd` (with FreeType), `zip`, `intl`, `mbstring`; Composer; MySQL 8. There is no Node build step: front-end libraries are committed under `public/vendor`.

```bash
composer install
cp .env.example .env && php artisan key:generate
# edit .env: DB_*, MAIL_*, APP_URL
php artisan surveillance:install-sample     # database, Uganda geography, sample series, cases, projections, alerts
php artisan serve                           # http://localhost:8000
```

Sample accounts (created by `surveillance:install-sample`; the password is `SAMPLE_USER_PASSWORD` in `.env`): `admin@`, `manager@`, `epi@`, `officer.kasese@`, `officer.kampala@`, `clinician@`, `lab@outbreak-watch.test`. **Never create these on a production database.** Create real users with:

```bash
php artisan surveillance:create-user EMAIL "Name" ROLE --area=CODE
```

Background processes for a full installation:

```bash
php artisan schedule:work        # detection hourly, escalation every 15 min, quality check 05:30, digest 07:00
php artisan queue:work           # only if QUEUE_CONNECTION=database
php artisan reverb:start         # live dashboard updates (optional; pages fall back to polling)
```

## Configuration

Settings live in `.env` and `config/surveillance.php`.

| Setting | Purpose | Default |
|---|---|---|
| `SURVEILLANCE_SYNTHETIC_DATA` | Show the sample-data notices. Set `false` when real data is loaded | `true` |
| `SURVEILLANCE_FORCE_HTTPS` | Redirect HTTP to HTTPS | on in production |
| `SURVEILLANCE_HSTS` | Send `Strict-Transport-Security` on secure requests | `true` |
| `SURVEILLANCE_REALTIME` | Use WebSockets (Reverb) for live updates | `false` |
| `TRUSTED_PROXIES` | Proxy addresses (or `*`) when behind a load balancer | unset |
| `MAIL_*`, `MAIL_FROM_ADDRESS` | Outgoing email. Use `smtp` in production and a sender on your own domain | `log` |
| `SAMPLE_USER_PASSWORD` | Password given to sample accounts | set in `.env` |
| `small_number_floor` (config) | Counts below this are marked "small number" | `5` |

## Commands

| Command | What it does |
|---|---|
| `surveillance:install-sample` | Wipe and build a complete working system (refuses in production without `--force`) |
| `surveillance:create-user` | Create a user with a role and area |
| `surveillance:issue-api-token` | Register a facility system and print its token once |
| `surveillance:simulate-notifications` | Send test notifications through the live pipeline |
| `surveillance:detect-outbreaks` | Evaluate alert rules on the latest week; `--no-notify` suppresses email |
| `surveillance:escalate-alerts` | Escalate unacknowledged alerts |
| `surveillance:send-digest` | Send the daily digest of lower-severity alerts |
| `surveillance:check-quality` | Update data-quality flags |
| `surveillance:run-forecasts` | Compute 1 to 4 week projections with backtests |
| `surveillance:rebuild-indicators` | Recompute stored indicators from the facility reports |
| `surveillance:test-mail EMAIL` | Send one test email to check delivery and SPF, DKIM, DMARC |
| `surveillance:prepare-geography`, `import-geography` | Build and import Uganda boundaries, population and facilities |
| `surveillance:fetch-reference`, `import-reference` | Refresh and import WHO and Wikipedia snapshots |
| `surveillance:load-sample-data`, `generate-cases` | Generate the sample weekly reports, doses and case records |

## Notification API and standards

Facility systems register on **Admin > API clients** (the token is shown once and only its hash is stored) or with `surveillance:issue-api-token`. Then:

```bash
curl -X POST https://your-domain/api/v1/notifications \
  -H "Authorization: Bearer ow_xxxxxxxx" -H "Idempotency-Key: unique-per-notification" \
  -H "Content-Type: application/json" \
  -d '{"disease":"measles","facility_code":"F123","sex":"F","age_years":6,"onset_date":"2026-11-18","notification_date":"2026-11-20","classification":"suspected"}'
```

- A client can only report for its own area. Repeating a key never creates a second case.
- A FHIR R4 Bundle (Patient, Encounter, Condition) is accepted at the same address.
- ICD-10 codes are stored on diseases and diagnoses. `GET /fhir/MeasureReport` returns aggregated indicators as FHIR. SNOMED CT is not used (licence and size); the reasoning is in [docs/PLAN.md](docs/PLAN.md).

## How the system works

- **Org units** form a tree: country, region, district, facility, stored with a materialised path so "everything under this district" is one indexed query.
- **Indicators** (cases, reporting completeness, vaccination coverage) are stored per area and period and rolled up from facility to country by SQL. Live notifications adjust them in place.
- **Outbreak signal:** a week signals when its count exceeds the eight-week baseline mean plus three Poisson standard deviations and reaches a minimum count, with a guard week so a growing outbreak does not hide itself.
- **Quality chart:** a u-chart of rate per 100,000 with a 52-week baseline and a run rule.
- **Projections:** moving average, exponential smoothing and damped Holt, chosen by rolling-origin backtest against a naive forecast; intervals never go below zero.
- **Alert fatigue controls:** minimum counts, one alert per episode, cooldown, severity tiers, hourly cap, digest, preferences, escalation.

## Security and privacy

- Server-side role and area checks on every route; inactive users are refused.
- Content Security Policy with per-request nonces, no `unsafe-eval`; `X-Frame-Options: DENY`; HSTS on secure requests; `no-store` and `noindex` on signed-in pages.
- Throttled sign-in, hashed passwords, hashed API tokens, encrypted raw notification payloads.
- Audit log of sign-ins, case and analytics views, exports and configuration changes.
- Public pages show national totals only. Case-level data has no export route (tested).

## Accessibility, performance and browsers

- Built toward WCAG 2.2 AA: skip link, visible focus, text alternative and data table for every chart, colour-blind-safe palette with a second cue (shape or dash), reduced reliance on colour, 34 px or larger controls.
- Charts start at zero, show denominators and mark small numbers.
- Lighthouse (mobile, throttled): performance 85 to 100 and accessibility 96 to 100 on every page measured. Table in [docs/PDF_CHECKLIST.md](docs/PDF_CHECKLIST.md). Scripts load on demand and assets are versioned for long caching.
- Used in Chromium browsers; Firefox checked on the sign-in and public pages. Layouts checked at 1280, 768 and 375 px.

## Tests

```bash
php artisan test
```

78 tests cover sign-in, role and area isolation, indicator roll-up, detection and alerting, ingestion and idempotency, security headers, public pages, case analytics, data-quality checks, annotations, saved views, API client management and the alert rule form. Tests use a separate MySQL database (`outbreak_watch_test`, see `phpunit.xml`).

## Deployment

Step-by-step server, HTTPS and HSTS, email (SPF, DKIM, DMARC), scheduler, WebSocket and backup instructions are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Minimum for production: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, strong database credentials, a real mail transport, no sample users, then `php artisan config:cache route:cache view:cache`.

## Project layout

```
app/Console/Commands   surveillance:* commands
app/Http/Controllers   one controller per screen; Admin/ and Api/ sub-folders
app/Http/Middleware    API token auth, HTTPS redirect, security headers
app/Models             Eloquent models (OrgUnit, CaseRecord, Alert, ...)
app/Services           indicator engine, outbreak detector, alert engine, forecaster, ingestion, FHIR mapping, case analytics, data-quality checker
app/Policies           area-scope policies
database/migrations    schema; database/seeders roles, reference data, rules, sample users
database/data          committed open-data extracts (Uganda geography, WHO, Wikipedia)
resources/views        Blade views; public/js and public/css front end; public/vendor libraries
routes                 web.php, api.php, channels.php, console.php (schedule)
tests                  Feature and Unit tests
docs                   plan, requirements, research, design, deployment, checklist
```

## Documents

| File | Contents |
|---|---|
| [docs/PLAN.md](docs/PLAN.md) | Scope, roles, modules, flows, indicators, data model, phases, and "As built" differences |
| [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md) | Stakeholders, 12 user stories, indicator definitions, request lifecycle diagram |
| [docs/RESEARCH.md](docs/RESEARCH.md) | Sources and decisions |
| [docs/DESIGN.md](docs/DESIGN.md) | Design rules, wireframes, usability test script |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production checklist, email DNS, backups |
| [docs/PDF_CHECKLIST.md](docs/PDF_CHECKLIST.md) | Capstone requirement, evidence and status |
| [DATA.md](DATA.md) | Data provenance, licences and limits |

## Known limits

- Weekly facility reports, immunization doses and case records are generated, not real.
- Email has been proven against a local mail sink only; a production provider and DNS records are still to be set.
- The usability test has not been run, and no live deployment exists yet.
- Reuse terms for the WHO and Wikipedia snapshots should be confirmed before publishing.

## Licence

MIT for the application code (see `composer.json`). Data keep their original licences (see [DATA.md](DATA.md)).
