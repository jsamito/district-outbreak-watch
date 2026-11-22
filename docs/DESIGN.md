# Design: wireframes, rules and usability test

The screens were built straight into the AdminLTE shell, so the running application is the working prototype. These wireframes record the layout of each screen so that changes can be argued from a drawing rather than from code, and the usability test at the end is how the design is checked with real users.

## 1. Rules every screen follows

- One shell: top bar (menu button, open-alert bell, account menu), left navigation grouped as Monitoring, Action and Administration, page title with the area and disease underneath, filters, then content.
- Filters sit on one line on laptop screens (area, facility, disease, from, to, Apply, Reset, Views) and wrap in a predictable order on tablet and phone.
- Colour-blind-safe palette (blue, orange, green, vermillion); every series also differs by marker shape or dash, and every chart has a text caption and a "View as a data table" alternative.
- Charts start their axes at zero, name the unit on every axis, show the denominator beside every rate, and mark counts below five as small numbers.
- Text and controls meet WCAG 2.2 AA: visible focus, skip link, 44 px or larger touch targets on phones, no information carried by colour alone.
- Real-time changes are announced politely to screen readers and never move the page under the reader.

## 2. Wireframes

### Sign in
```
+----------------------+-----------------------------+
|  [heart] District    |  Welcome back!              |
|  Outbreak Watch      |  Sign in to continue        |
|  Early warning ...   |  EMAIL ADDRESS              |
|                      |  [______________________]   |
|                      |  PASSWORD                   |
|  Disease Surveillance|  [____________________ (o)] |
|  - Uganda            |  [ ] Remember me            |
|                      |  [        Sign in         ] |
+----------------------+-----------------------------+
```

### Overview
```
+ top bar ------------------------------------ bell  user v +
| nav  | Overview  area . latest week          [area v]      |
|      | [Cholera 12 ^40%] [Measles 3] [Malaria 210] [Report 91%]
|      | ( Cholera ) ( Measles ) ( Malaria ) ...  <- chips   |
|      | +---------------------------+ +------------------+ |
|      | | Weekly cases, 52 weeks    | | Districts to     | |
|      | | (line + signal diamonds)  | | watch (ranked)   | |
|      | +---------------------------+ +------------------+ |
|      | +--------------+ +----------+ +------------------+ |
|      | | Map          | | Alerts   | | Reporting        | |
+------+--------------------------------------------------+
```

### Trends, quality chart, vaccination, forecasts
```
| Title . disease . area                                    |
| [Area v][Facility v][Disease v][From][To][Apply][Reset][Views]
| +-------------------------------------------------------+ |
| |  chart (zero-based, units, caption)                   | |
| +-------------------------------------------------------+ |
| > View as a data table                                    |
| Notes on this chart:  week of 03 Aug: Campaign started    |
| [week][note text.............................][Add note]   |
| Weekly values with denominators (sortable table)          |
```

### Map and comparison
```
| filters                                                   |
| +--------------------------+ +--------------------------+ |
| | District choropleth      | | Ranked bar chart         | |
| | click = drill down       | | rate per 100,000         | |
| | legend with class ranges | | table of same values     | |
| +--------------------------+ +--------------------------+ |
```

### Case analytics
```
| Notice: counts only, audited, small numbers marked        |
| filters                                                   |
| [Cases] [Deaths + CFR] [Median days onset->notice] [Lab %]|
| +-------------------------------------------------------+ |
| | Epidemic curve (stacked by classification)            | |
| +-------------------------------------------------------+ |
| +----------------------+ +------------------------------+ |
| | Age and sex pyramid  | | Time onset -> notification   | |
| +----------------------+ +------------------------------+ |
| Case fatality table with 95% interval and unknown outcome |
| Laboratory follow-up chart + table                        |
```

### Alerts, alert rules, data quality, API clients
```
Alerts:        list by status (open / acknowledged / escalated) -> detail with chart, who was told, [Acknowledge]
Alert rules:   name, method, severity, disease, area, thresholds, cooldown, escalation, audience, on/off
Data quality:  six count tiles (one per kind of problem) -> filter -> table -> [note][Mark reviewed]
API clients:   [system name][may report for][Register] -> token shown once -> table of systems, last seen, accepted, rejected, [Disable]
```

## 3. Usability test (five people)

**Who:** five people who are not the builders: two district-level health workers or students in a health course, one clinician or nurse, one data officer, one person using a phone only. Each session is 20 minutes, on their own device where possible, using the sample data and the matching sample account.

**Tasks** (read aloud, one at a time; do not help):
1. Sign in and say which disease had the most cases last week nationally.
2. Find out whether cholera in Kasese is going up or down over the last 12 weeks.
3. Open the map and name the district with the highest measles rate.
4. Find an alert, say how serious it is and acknowledge it.
5. Find the percentage of cases that had a finished laboratory test.
6. Change your alert emails so you receive only a daily summary.
7. On a phone: open the dashboard and find the number of cholera cases.

**Record for each task:** finished without help (yes / no), time in seconds, wrong turns, and one quote. After the tasks ask the five-question System Usability Scale form (or a shorter 1 to 5 rating of ease, clarity of charts, trust in the numbers).

**Pass line:** at least 4 of 5 people finish each task unaided, and the average SUS score is 68 or higher. Anything that fails goes into a fix list ranked by how many people were stuck.

| Task | Finished (of 5) | Median seconds | Main problem seen | Fix made |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | |
| 7 | | | | |

**Status:** the test has not been run yet. Fill the table after the sessions and keep the notes with the report.
