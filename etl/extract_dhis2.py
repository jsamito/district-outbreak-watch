#!/usr/bin/env python3
"""Extract aggregate-only data from the DHIS2 Sierra Leone demo database dump.

Streams the gzipped plain-SQL dump (no database server needed) and writes small
CSV/GeoJSON files to database/data/dhis2/. Only org structure, populations,
aggregated immunization doses and IDSR reporting behaviour are kept. No
person-level data exists in this extract.

Usage: python3 -I etl/extract_dhis2.py DUMP.sql.gz [OUTPUT_DIR]
"""
import collections
import csv
import datetime
import gzip
import hashlib
import json
import os
import re
import struct
import sys

IMMUNIZATION = {
    "BCG doses given": "BCG",
    "OPV3 doses given": "OPV3",
    "Penta3 doses given": "PENTA3",
    "Measles doses given": "MEASLES",
    "Yellow Fever doses given": "YELLOW_FEVER",
    "Fully Immunized child": "FIC",
}
POPULATION = {
    "Total Population": "total",
    "Total population < 1 year": "under1",
    "Total population < 5 years": "under5",
    "Population of women of child bearing age (WRA)": "wra",
    "Expected pregnancies": "pregnancies",
}


def unescape(v):
    if v == "\\N":
        return None
    if "\\" in v:
        v = v.replace("\\t", "\t").replace("\\n", "\n").replace("\\r", "\r").replace("\\\\", "\\")
    return v


def copy_rows(path, wanted):
    wanted = set(wanted)
    with gzip.open(path, "rt", encoding="utf-8", errors="replace") as f:
        cur = cols = None
        for line in f:
            if cur is None:
                if line.startswith("COPY "):
                    m = re.match(r"COPY (?:public\.)?(\S+) \((.*?)\) FROM stdin;", line)
                    if m and m.group(1).strip('"') in wanted:
                        cur = m.group(1).strip('"')
                        cols = [c.strip().strip('"') for c in m.group(2).split(",")]
            elif line.startswith("\\."):
                cur = None
            else:
                yield cur, dict(zip(cols, (unescape(p) for p in line.rstrip("\n").split("\t"))))


def load_table(path, name):
    return [r for t, r in copy_rows(path, [name])]


# ---- geometry (PostGIS hex EWKB) -------------------------------------------------
def parse_ewkb(hexstr):
    b = bytes.fromhex(hexstr)
    pos = 0

    def read_geom():
        nonlocal pos
        endian = "<" if b[pos] == 1 else ">"
        pos += 1
        (t,) = struct.unpack_from(endian + "I", b, pos)
        pos += 4
        if t & 0x20000000:
            pos += 4  # SRID
        t &= 0xFF
        if t == 1:
            x, y = struct.unpack_from(endian + "dd", b, pos)
            pos += 16
            return ("Point", [x, y])
        if t == 3:
            return ("Polygon", read_polygon(endian))
        if t == 6:
            (n,) = struct.unpack_from(endian + "I", b, pos)
            pos += 4
            polys = []
            for _ in range(n):
                g = read_geom()
                polys.append(g[1])
            return ("MultiPolygon", polys)
        raise ValueError(f"unsupported geometry type {t}")

    def read_polygon(endian):
        nonlocal pos
        (nr,) = struct.unpack_from(endian + "I", b, pos)
        pos += 4
        rings = []
        for _ in range(nr):
            (np_,) = struct.unpack_from(endian + "I", b, pos)
            pos += 4
            pts = []
            for _ in range(np_):
                x, y = struct.unpack_from(endian + "dd", b, pos)
                pos += 16
                pts.append([x, y])
            rings.append(pts)
        return rings

    t, coords = read_geom()
    return {"type": t, "coordinates": coords}


def rdp(points, eps):
    if len(points) < 3:
        return points
    (x1, y1), (x2, y2) = points[0], points[-1]
    dx, dy = x2 - x1, y2 - y1
    norm = (dx * dx + dy * dy) ** 0.5
    best, idx = 0.0, 0
    for i in range(1, len(points) - 1):
        x0, y0 = points[i]
        d = abs(dy * x0 - dx * y0 + x2 * y1 - y2 * x1) / norm if norm else ((x0 - x1) ** 2 + (y0 - y1) ** 2) ** 0.5
        if d > best:
            best, idx = d, i
    if best > eps:
        return rdp(points[: idx + 1], eps)[:-1] + rdp(points[idx:], eps)
    return [points[0], points[-1]]


def simplify_ring(ring, eps):
    s = rdp(ring, eps)
    if len(s) < 4:
        return ring
    return [[round(x, 4), round(y, 4)] for x, y in s]


def simplify_geom(g, eps):
    if g["type"] == "Polygon":
        return {"type": "Polygon", "coordinates": [simplify_ring(r, eps) for r in g["coordinates"]]}
    if g["type"] == "MultiPolygon":
        return {
            "type": "MultiPolygon",
            "coordinates": [[simplify_ring(r, eps) for r in poly] for poly in g["coordinates"]],
        }
    return g


def week_monday(d):
    return d


def main():
    dump = sys.argv[1]
    out = sys.argv[2] if len(sys.argv) > 2 else os.path.join(os.path.dirname(__file__), "..", "database", "data", "dhis2")
    os.makedirs(out, exist_ok=True)

    ou_rows = load_table(dump, "organisationunit")
    ou = {r["organisationunitid"]: r for r in ou_rows}
    uid_of = {k: v["uid"] for k, v in ou.items()}

    grp = {r["orgunitgroupid"]: r["name"] for r in load_table(dump, "orgunitgroup")}
    gs = {r["orgunitgroupsetid"]: r["name"] for r in load_table(dump, "orgunitgroupset")}
    gset_members = collections.defaultdict(set)
    for r in load_table(dump, "orgunitgroupsetmembers"):
        gset_members[gs[r["orgunitgroupsetid"]]].add(r["orgunitgroupid"])
    attr = collections.defaultdict(dict)
    for r in load_table(dump, "orgunitgroupmembers"):
        for setname, gids in gset_members.items():
            if r["orgunitgroupid"] in gids:
                attr[r["organisationunitid"]][setname] = grp[r["orgunitgroupid"]]

    # ---- org units + geometry
    features = []
    with open(os.path.join(out, "org_units.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["uid", "parent_uid", "level", "name", "code", "area", "facility_type", "ownership", "rural_urban", "lat", "lng", "opening_date"])
        for r in sorted(ou_rows, key=lambda r: (int(r["hierarchylevel"]), r["name"])):
            a = attr.get(r["organisationunitid"], {})
            lat = lng = ""
            if r.get("geometry"):
                g = parse_ewkb(r["geometry"])
                if g["type"] == "Point":
                    lng, lat = round(g["coordinates"][0], 5), round(g["coordinates"][1], 5)
                elif r["hierarchylevel"] == "2":
                    features.append({"type": "Feature", "properties": {"uid": r["uid"], "name": r["name"]}, "geometry": simplify_geom(g, 0.003)})
            w.writerow([r["uid"], uid_of.get(r["parentid"], ""), r["hierarchylevel"], r["name"], r["code"] or "",
                        a.get("Area", ""), a.get("Facility Type", ""), a.get("Facility Ownership", ""), a.get("Location Rural/Urban", ""),
                        lat, lng, r["openingdate"] or ""])
    with open(os.path.join(out, "districts.geojson"), "w", encoding="utf-8") as f:
        json.dump({"type": "FeatureCollection", "features": features}, f, separators=(",", ":"))

    # ---- lookups for data values
    de = {r["dataelementid"]: r["name"] for r in load_table(dump, "dataelement")}
    pt = {r["periodtypeid"]: r["name"] for r in load_table(dump, "periodtype")}
    period = {r["periodid"]: (pt[r["periodtypeid"]], r["startdate"]) for r in load_table(dump, "period")}
    datasets = {r["name"]: r["datasetid"] for r in load_table(dump, "dataset")}
    dse = collections.defaultdict(set)
    for r in load_table(dump, "datasetelement"):
        dse[r["datasetid"]].add(r["dataelementid"])
    idsr_elems = {i: de[i] for i in dse[datasets["IDSR Weekly"]]}
    imm_ids = {i: IMMUNIZATION[n] for i, n in de.items() if n in IMMUNIZATION}
    pop_ids = {i: POPULATION[n] for i, n in de.items() if n in POPULATION}

    population = collections.defaultdict(dict)  # (ou, year) -> {field: value}
    imm = collections.defaultdict(lambda: [0.0, set()])  # (district_ou, month, antigen) -> [doses, facility set]
    idsr_weeks = collections.defaultdict(set)  # facility -> weeks reported
    idsr_all_weeks = set()
    idsr_stats = collections.defaultdict(lambda: [0, 0, 0.0, 0.0])  # disease -> [n, nonzero, sum, max]

    def district_of(ouid):
        # organisationunit.path holds UIDs: /country/district/chiefdom/facility
        path = ou[ouid]["path"].strip("/").split("/")
        return path[1] if len(path) > 1 else None

    imm_rows = []
    for t, r in copy_rows(dump, ["datavalue"]):
        if r["deleted"] == "t":
            continue
        deid = r["dataelementid"]
        if deid in idsr_elems:
            ptype, start = period[r["periodid"]]
            if ptype != "Weekly":
                continue
            idsr_weeks[r["sourceid"]].add(start)
            idsr_all_weeks.add(start)
            try:
                v = float(r["value"])
            except (TypeError, ValueError):
                continue
            s = idsr_stats[idsr_elems[deid]]
            s[0] += 1
            s[1] += v > 0
            s[2] += v
            s[3] = max(s[3], v)
        elif deid in pop_ids:
            ptype, start = period[r["periodid"]]
            try:
                population[(r["sourceid"], start[:4])][pop_ids[deid]] = float(r["value"])
            except (TypeError, ValueError):
                pass
        elif deid in imm_ids:
            ptype, start = period[r["periodid"]]
            if ptype != "Monthly":
                continue
            try:
                v = float(r["value"])
            except (TypeError, ValueError):
                continue
            imm_rows.append((r["sourceid"], start[:7], imm_ids[deid], v))

    with open(os.path.join(out, "population.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["facility_uid", "year", "total", "under1", "under5", "wra", "pregnancies"])
        for (sid, year), vals in sorted(population.items(), key=lambda kv: (uid_of[kv[0][0]], kv[0][1])):
            w.writerow([uid_of[sid], year] + [int(vals.get(k, 0)) for k in ("total", "under1", "under5", "wra", "pregnancies")])

    # immunization aggregated to district/month/antigen, with matched denominators
    agg = collections.defaultdict(lambda: [0.0, set()])
    for sid, month, antigen, v in imm_rows:
        d = district_of(sid)
        if d is None:
            continue
        a = agg[(d, month, antigen)]
        a[0] += v
        a[1].add(sid)
    with open(os.path.join(out, "immunization_monthly.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["district_uid", "month", "antigen", "doses", "reporting_facilities", "target_under1_annual"])
        for (d, month, antigen), (doses, facs) in sorted(agg.items(), key=lambda kv: kv[0]):
            year = month[:4]
            target = sum(population.get((fid, year), {}).get("under1", 0) for fid in facs)
            w.writerow([d, month, antigen, int(doses), len(facs), int(target)])

    n_weeks = len(idsr_all_weeks)
    with open(os.path.join(out, "idsr_reporting_profile.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["facility_uid", "weeks_reported", "weeks_available"])
        for sid, weeks in sorted(idsr_weeks.items(), key=lambda kv: uid_of[kv[0]]):
            w.writerow([uid_of[sid], len(weeks), n_weeks])

    sha = hashlib.sha256()
    with open(dump, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            sha.update(chunk)
    summary = {
        "source": "DHIS2 Sierra Leone demo database",
        "file": os.path.basename(dump),
        "url": "https://databases.dhis2.org/sierra-leone/2.43.2/dhis2-db-sierra-leone.sql.gz",
        "size_bytes": os.path.getsize(dump),
        "sha256": sha.hexdigest(),
        "extracted_at": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
        "org_units": len(ou_rows),
        "districts_with_geometry": len(features),
        "population_rows": len(population),
        "immunization_rows": len(agg),
        "idsr_facilities_reporting": len(idsr_weeks),
        "idsr_weeks_with_data": n_weeks,
        "idsr_demo_value_stats": {k: {"n": v[0], "nonzero": v[1], "sum": v[2], "max": v[3]} for k, v in idsr_stats.items()},
        "note": "IDSR weekly values in the demo are uniform random integers 0-5 for every disease, "
                "present for only a sparse subset of weeks, so they cannot show real outbreak dynamics. "
                "Only the facility reporting behaviour is used from them.",
    }
    with open(os.path.join(out, "extract_summary.json"), "w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2)
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
