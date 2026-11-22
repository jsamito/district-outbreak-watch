# Capstone brief checklist: requirement, evidence, status

Status key: **Done** (built and checked), **Partly** (built, evidence incomplete), **To do** (not started). Measured on 22 Nov 2026 on a local machine.

## 4. What the dashboard needs

| Requirement | Evidence | Status |
|---|---|---|
| At least six visualizations | Weekly trend line, comparison bars, headline numbers, district map, quality (u) control chart, vaccination coverage, reporting completeness, forecast with intervals, epidemic curve, age and sex pyramid, onset-to-notification histogram | Done |
| Date or facility filter, tooltips or drill-down, aggregated CSV | Area, facility, disease and date filters on every chart page; tooltips on all charts; map click drills into a district; `/exports` is aggregate-only and no case export route exists (tested) | Done |
| At least three data sources | HDX boundaries and population, Healthsites facilities, WHO immunization estimates, Wikipedia page views, generated weekly facility reports and cases (see `DATA.md`) | Done |
| Email alert on a threshold, and how to avoid alert fatigue | Four rule methods, one alert per area and disease while a signal lasts, cooldown, severity tiers, 5 instant emails per hour per person, daily digest, escalation, per-user preferences; SMTP delivery proven against a local mail sink; real provider not yet set | Partly |
| Login with at least two roles | Six roles plus area scope enforced on the server; audit log | Done |
| Health standards or a reason to omit | ICD-10 on diseases and diagnoses, FHIR MeasureReport out, FHIR Bundle in; SNOMED CT left out (licence and size) | Done |

## Standards

| Requirement | Evidence | Status |
|---|---|---|
| Laptop, tablet, phone | Checked in the built-in browser at 1280, 768 and 375 px on dashboard, trends, map, case analytics, forms; two layout faults found and fixed (breadcrumb overlap, filter widths). Not tried on a physical phone | Partly |
| WCAG 2.2 AA, colour-blind-safe | Lighthouse accessibility 96 to 100 on every page (contrast failures found on three pages and fixed); palette checked for colour-blind safety, second cue (shape or dash) on every series, text alternative for every chart, skip link, visible focus. No screen-reader or colour-vision simulator pass yet | Partly |
| Lighthouse performance 85+ on a slow connection | Mobile profile with throttling (see table below): every page 85 or higher | Done |
| HTTPS only with HSTS | Redirect and `Strict-Transport-Security` implemented and tested; cannot be shown on a local HTTP server. Needs the live domain and an SSL Labs result | To do (on deploy) |
| Three browsers | Chromium (Chrome and the built-in browser) full use; Firefox 158 rendered the login and public pages correctly. Signed-in pages in Firefox and an Edge or Safari pass are still to do | Partly |
| Honest data | Axes start at zero, denominators beside rates, counts under 5 marked as small numbers, intervals on fatality and forecasts, synthetic data labelled while it is loaded | Done |

### Lighthouse (mobile, throttled, local server behind a gzip proxy)

| Page | Performance | Accessibility | Best practices |
|---|---|---|---|
| Overview | 91 | 96 (fixed since) | 100 |
| Trends | 85 | 100 | 100 |
| Map | 91 | 100 | 100 |
| Quality chart | 95 | 100 | 100 |
| Vaccination | 88 | 100 | 100 |
| Reporting | 96 | 100 | 100 |
| Forecasts | 97 | 100 | 100 |
| Case analytics | 97 | 97 (fixed since) | 100 |
| Data quality | 100 | 100 | 100 |
| Alerts, Cases | 100 | 96 (fixed since), 100 | 100 |
| Public home | 100 | 100 | 100 (SEO 100) |
| Sign-in | 100 | 100 | 100 (SEO is low on purpose: the page is `noindex`) |

Trends at 85 is on the line. Scores move by a few points between runs on this machine, so re-measure on the live server.

## 5. The work, phase by phase

| Phase | Item | Status |
|---|---|---|
| 1 | Stakeholders, 12 user stories, indicator definitions, request-lifecycle diagram with exposure points (`docs/REQUIREMENTS.md`) | Done |
| 2 | Public page with sitemap, robots.txt, metadata, structured data, private paths excluded | Done |
| 2 | Audit of an existing public dashboard in three browsers (network waterfall, console, Lighthouse) | To do |
| 2 | Plain-language explanation of crawl, index, rank; reflection on quality of health information in search results | To do |
| 3 | Semantic HTML, CSS, Chart.js; Git history with readable messages | Done |
| 3 | Comparison of VS Code, Dreamweaver and a CMS or static generator, with a note on FrontPage | To do |
| 5 | Wireframes and design rules (`docs/DESIGN.md`) | Done |
| 5 | Clickable higher-fidelity prototype in Figma | To do (the running system can be shown instead if agreed) |
| 5 | Test with at least five users: tasks, time, confusion, chart reading, changes made (script ready in `docs/DESIGN.md`) | To do |
| 6 | Deployment to a cloud platform (VM or container) | To do |
| 6 | Grid, cluster and cloud comparison with IaaS, PaaS, SaaS, health data law, recommendation at ten times usage | To do |
| 6 | Social media: shareable summary card is built; written strategy (audience, metrics, privacy, moderation, misinformation) | Partly |
| 6 | Emerging technology critique with six or more credible sources | To do |

## 6. Hand-in

| Item | Status |
|---|---|
| Live dashboard at a working URL | To do |
| Git repository with README and history | Done |
| Report of 4,000 to 5,000 words (outline in section 7 of the brief) | To do |
| 15-minute demo | To do |
| Reflection of 800 to 1,000 words | To do |

## What to do next, in order

1. Deploy to a small VM with a real domain, HTTPS, HSTS, email provider and DNS records (`docs/DEPLOYMENT.md`), then keep the SSL Labs, header, Lighthouse and email-header results as evidence for report section 6.
2. Run the five-person usability test and record the table in `docs/DESIGN.md`.
3. Write the analysis sections that need no build: search engines, editors and FrontPage, grid and cloud, social media strategy, emerging technology.
4. Audit one existing public dashboard in Chrome, Firefox and Edge.
5. Assemble the report, demo and reflection.
