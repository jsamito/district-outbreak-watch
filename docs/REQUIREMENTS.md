# Requirements (Phase 1)

## 1. Stakeholders

**Simulated stakeholder interview.** The people below are personas built from how district and national surveillance units are described in the public IDSR guidance. The answers record the needs the system is designed for; replace them with notes from a real conversation when one is available.

| Persona | Decisions they make | What they wish they could see |
|---|---|---|
| **District Health Officer** | Which facilities to visit this week; whether to declare a local alert; where to send stock and vaccinators | A single view of my district's weekly counts by disease, an early warning before the numbers are obvious, which facilities have not reported, cases waiting for investigation |
| **National surveillance manager** | Which districts need support; when to escalate an event to national level; which alert thresholds to use | All districts side by side, trends and projections, vaccination coverage against target, who is acknowledging alerts and how fast |
| **Facility clinician** | Whether a patient meets a case definition; when to notify | A fast notification form that works on a phone, confirmation that the report was received, my facility's own trend |
| **Laboratory officer** | Which samples to prioritise | A list of cases waiting for a result, and a way to enter results without seeing unrelated patient detail |
| **Epidemiologist** | Whether a signal is real; which diseases to model | Clean weekly series, uncertainty on projections, how well past projections performed, no direct identifiers |

## 2. User stories

| # | As a… | I want to… | So that… |
|---|---|---|---|
| US1 | national manager | see weekly cases of each disease for all districts on one overview | I can spot rises and decide where to send support |
| US2 | district officer | see only my district's data and alerts | I work with what I am responsible for and nothing I should not see |
| US3 | district officer | receive an email when a disease count in my district rises abnormally | I can investigate before the outbreak spreads |
| US4 | national manager | set alert rules, severity, cooldown and escalation time | alerts stay meaningful and nobody is flooded |
| US5 | clinician | notify a suspected case from my phone in under two minutes | reporting is not a burden and cases are not missed |
| US6 | district officer | review the case list, change classification and outcome, and see the case timeline | investigations are tracked to closure |
| US7 | lab officer | enter laboratory results for cases from my laboratory | confirmed cases are counted promptly |
| US8 | epidemiologist | view 1–4 week projections with uncertainty and how accurate past projections were | I can judge how far to trust them |
| US9 | national manager | compare district vaccination coverage with the target and the WHO national estimate | I can target immunization activities |
| US10 | national manager or district officer | see which facilities reported on time each week | I know whether the numbers can be trusted |
| US11 | any logged-in user | filter by date range, district and facility and export aggregated data as CSV | I can prepare reports without exposing individual cases |
| US12 | system administrator | manage users and their district scope and review the audit log | access stays correct and accountable |

Acceptance for every story: the user sees only data inside their role and scope, on a laptop, tablet or phone, and every chart has a text alternative.

## 3. Indicator definitions

All counts are per ISO week (Monday start) unless stated. "Reported" means reported by a sentinel facility inside the district.

| ID | Indicator | Precise definition | Notes |
|---|---|---|---|
| I-01 | Cases | Suspected + probable + confirmed cases per disease, org unit and ISO week by report date. Discarded cases are excluded | Confirmed shown separately; origin recorded (derived from cases, or synthetic aggregate) |
| I-02 | Incidence | I-01 / district population × 100,000 | Population is the COD-PS district value for the year (flat growth outside 2022); the denominator is always shown |
| I-03 | Week-on-week change | (cases_t − cases_t−1) / cases_t−1 | "n/a" when the previous week is 0; flagged when cases_t < 5 |
| I-04 | Case fatality | Deaths among confirmed cases / confirmed cases, by period | Shown only when the denominator is at least 5 |
| I-05 | Time to notification | Median days from symptom onset to notification date | Case-based diseases only |
| I-06 | Lab confirmation rate | Cases with a positive lab result / suspected + probable cases tested or not | Case-based diseases only |
| I-07 | Vaccination coverage | Doses given in the period / (under-1 population × months in period / 12) × 100, per antigen and district | Above 100% flagged as a data-quality issue; compared with the target (default 90%, configurable) and the WHO national estimate |
| I-08 | Reporting completeness | Weekly reports received from sentinel facilities / reports expected; timeliness = received by Wednesday after the week ends | By facility, district and nation |
| I-09 | Outbreak signal | cases_t > baseline mean + 3·√(baseline mean), baseline = the 8 weeks ending 2 weeks before t, and cases_t ≥ 5 | Parameters live in the alert rule |
| I-10 | Immediate-notifiable trigger | One suspected or confirmed case of a flagged disease (Ebola virus disease) | Alert regardless of the count floor |
| I-11 | u-chart | u_i = c_i/n_i, centre ū = Σc/Σn, limits ū ± 3√(ū/n_i) over a 52-week baseline | Display only |
| I-12 | Projection | 1–4 week forecast with an interval | Method and backtest score shown |
| I-13 | Attention index | Weekly page views of the disease article / mean of the previous 8 weeks | Global English page views |

Diseases: malaria (B50–B54), measles (B05), cholera (A00), typhoid fever (A01.0), Ebola virus disease (A98.4, immediate-notifiable).

## 4. What happens when someone opens the dashboard

```mermaid
sequenceDiagram
  participant B as Browser
  participant D as DNS resolver
  participant S as Web server (Apache + Laravel)
  participant DB as Database
  B->>D: 1. Lookup outbreak-watch domain
  Note over B,D: EXPOSED if unprotected: which site is visited (plain DNS). Mitigation: DNS over HTTPS/TLS, minimal DNS records
  D-->>B: IP address
  B->>S: 2. TCP connect, TLS handshake (certificate, keys)
  Note over B,S: EXPOSED if unprotected: credentials, session cookie and all counts readable and alterable on the network. Mitigation: TLS 1.2+, HTTPS only, HSTS so downgrade is refused
  B->>S: 3. HTTP request with session cookie
  Note over B,S: EXPOSED: cookie theft if not Secure/HttpOnly/SameSite; URLs in logs and referrers
  S->>S: 4. Middleware: HTTPS check, authentication, role and district scope
  S->>DB: 5. Scoped query
  Note over S,DB: EXPOSED: data leakage if the scope is missing (another district's data), SQL injection if queries are not parameterised
  DB-->>S: Aggregated rows
  S-->>B: 6. HTML shell and JSON (Cache-Control: no-store)
  Note over B,S: EXPOSED: browser cache and history on shared devices; third-party fonts or tiles would reveal the user's IP to other parties
  B->>B: 7. Parse HTML, load CSS and JS, Chart.js draws charts
  Note over B: EXPOSED: screenshots, shoulder surfing, data left on screen on a shared device. Mitigation: session timeout, logout clears state
  B-->>S: 8. WebSocket connection for live updates
  Note over B,S: EXPOSED: live case counts leak if channels are public. Mitigation: per-user authorised private channels over wss
```
