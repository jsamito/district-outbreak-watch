#!/usr/bin/env python3
"""Fetch dated snapshots of the external reference and web-attention data.

Writes to database/data/external/. The application never calls these APIs at
runtime; it imports the snapshots with `php artisan surveillance:import`.

  - WHO GHO OData API: WUENIC immunization coverage for Sierra Leone (national, yearly)
  - Wikimedia Pageviews REST API: daily page views of disease articles, summed per ISO week

Usage: python3 -I etl/fetch_external.py [OUTPUT_DIR]
"""
import csv
import datetime
import json
import os
import sys
import time
import urllib.parse
import urllib.request

UA = "DistrictOutbreakWatch/0.1 (academic coursework project; local snapshot script)"
GHO = "https://ghoapi.azureedge.net/api"
WIKI = "https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/en.wikipedia/all-access/user"
INDICATORS = {
    "WHS8_110": "MCV1",
    "WHS4_100": "DTP3",
    "WHS4_543": "BCG",
}
ARTICLES = {"Measles": "measles", "Cholera": "cholera", "Malaria": "malaria", "Plague": "plague", "Yellow_fever": "yellow_fever"}


def get_json(url, tries=4):
    for i in range(tries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
            with urllib.request.urlopen(req, timeout=60) as r:
                return json.load(r)
        except Exception as e:  # noqa: BLE001
            if i == tries - 1:
                raise
            time.sleep(2 * (i + 1))


def main():
    out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "..", "database", "data", "external")
    os.makedirs(out, exist_ok=True)
    fetched = datetime.date.today().isoformat()

    rows = []
    for code, name in INDICATORS.items():
        q = urllib.parse.urlencode({"$filter": "SpatialDim eq 'SLE'"})
        data = get_json(f"{GHO}/{code}?{q}")
        for r in data.get("value", []):
            if r.get("NumericValue") is not None:
                rows.append((name, code, int(r["TimeDim"]), r["NumericValue"]))
    rows.sort(key=lambda r: (r[0], r[2]))
    with open(os.path.join(out, "who_immunization_sle.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["antigen", "gho_code", "year", "coverage_percent"])
        w.writerows(rows)

    end = datetime.date.today() - datetime.timedelta(days=1)
    start = end - datetime.timedelta(days=7 * 112)
    weekly = {}
    for art, key in ARTICLES.items():
        url = f"{WIKI}/{art}/daily/{start:%Y%m%d}/{end:%Y%m%d}"
        data = get_json(url)
        for item in data.get("items", []):
            d = datetime.datetime.strptime(item["timestamp"][:8], "%Y%m%d").date()
            monday = d - datetime.timedelta(days=d.weekday())
            weekly[(key, monday)] = weekly.get((key, monday), 0) + item["views"]
        time.sleep(0.5)
    with open(os.path.join(out, "wikipedia_pageviews_weekly.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["topic", "week_start", "views"])
        for (key, monday), v in sorted(weekly.items()):
            w.writerow([key, monday.isoformat(), v])

    with open(os.path.join(out, "fetch_summary.json"), "w", encoding="utf-8") as f:
        json.dump({
            "fetched_on": fetched,
            "who_gho": {"endpoint": GHO, "indicators": INDICATORS, "rows": len(rows)},
            "wikipedia": {"endpoint": WIKI, "articles": list(ARTICLES), "from": start.isoformat(), "to": end.isoformat(), "weekly_rows": len(weekly)},
            "notes": "Page views are global for the English edition, not specific to Sierra Leone.",
        }, f, indent=2)
    print("WHO rows:", len(rows), "| pageview weekly rows:", len(weekly))


if __name__ == "__main__":
    main()
