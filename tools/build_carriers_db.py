#!/usr/bin/env python3
"""
build_carriers_db.py  -  Trim the FMCSA master database into a lean, web-safe carriers.db
                         for truckingdirectoryusa.com.

WHAT IT DOES
  * Reads your FMCSA SQLite master (e.g. "National all information.db", table
    Company_Census_File_YYYYMMDD) - read-only, it never changes the source.
  * Auto-detects the column names (MCMIS census names like CRGO_GENFREIGHT /
    COMPANY_OFFICER_1 / CELL_PHONE, or the public census names like GENFREIGHT /
    NBR_POWER_UNIT / TELEPHONE). Run with --inspect to see the mapping it found.
  * Keeps ONLY the public-record fields the website shows. Emails, cell phones,
    officer names, fax and DUNS numbers are NEVER copied - only yes/no flags
    ("has_email", "has_cell", "has_officer") so the pages can advertise that the
    premium contact data exists in your CarrierDataUSA lists.
  * Builds URL slugs, a sequential id for sitemap chunking, an index tier
    (1 = index now, 2 = index later, 3 = noindex), and pre-computed stats per
    state, city and cargo type so hub pages render instantly on shared hosting.

USAGE (Windows)
  python build_carriers_db.py --source "C:\\Users\\Pat\\Desktop\\EXCEL DATA FOLDER FROM FMCAS\\National all information.db" --out carriers.db

OPTIONS
  --table NAME          source table (default: auto-detect the biggest table with a DOT column)
  --inspect             print the detected table + column mapping and exit (no output written)
  --where "SQL"         extra SQL filter on the source rows, e.g. --where "STATUS_CODE='A'"
  --active-only         keep only rows whose status column says Active (if a status column exists)
  --include-inactive    keep inactive rows too (they get index tier 3 = noindex). Default: kept.
  --no-us-only          keep rows outside the 50 states + DC (default: US only)
  --snapshot YYYY-MM-DD data snapshot date shown on every page (default: date in the table name, else today)
  --limit N             only process N rows (for testing)

Then upload carriers.db to the server (see README.md).
"""
import argparse
import datetime as dt
import os
import re
import sqlite3
import sys
import time

# ----------------------------------------------------------------------------
# Column candidates. Keys are the logical fields the website uses; values are
# possible source column names (matched case-insensitively, ignoring _ and spaces).
# ----------------------------------------------------------------------------
FIELD_CANDIDATES = {
    "dot":                 ["DOT_NUMBER", "USDOT", "USDOT_NUMBER", "DOT", "DOT_NUM", "DOTNUMBER"],
    "legal_name":          ["LEGAL_NAME", "CARRIER_NAME", "COMPANY_NAME", "NAME"],
    "dba_name":            ["DBA_NAME", "DBA"],
    "phy_street":          ["PHY_STREET", "PHYSICAL_STREET", "PHY_ADDRESS", "PHYSICAL_ADDRESS", "STREET"],
    "phy_city":            ["PHY_CITY", "PHYSICAL_CITY", "CITY"],
    "phy_state":           ["PHY_STATE", "PHYSICAL_STATE", "STATE"],
    "phy_zip":             ["PHY_ZIP", "PHYSICAL_ZIP", "PHY_ZIPCODE", "ZIP", "ZIP_CODE"],
    "phy_country":         ["PHY_COUNTRY", "PHY_NATN", "PHYSICAL_COUNTRY", "COUNTRY", "NATN"],
    "phone":               ["PHONE", "TELEPHONE", "PHONE_NUMBER", "TEL", "BUSINESS_PHONE"],
    "carrier_operation":   ["CARRIER_OPERATION", "OPERATION"],
    "crr_inter":           ["CRRINTER", "CRR_INTER", "INTERSTATE"],
    "crr_intra":           ["CRRINTRA", "CRR_INTRA", "INTRASTATE"],
    "crr_hm_intra":        ["CRRHMINTRA", "CRR_HM_INTRA"],
    "hm_flag":             ["HM_IND", "HM_FLAG", "HAZMAT", "HAZMAT_FLAG"],
    "pc_flag":             ["PC_FLAG", "PASSENGER_CARRIER"],
    "power_units":         ["POWER_UNITS", "NBR_POWER_UNIT", "TOTAL_POWER_UNITS", "POWERUNITS", "PU"],
    "truck_units":         ["TRUCK_UNITS", "TRUCKUNITS"],
    "fleetsize":           ["FLEETSIZE", "FLEET_SIZE"],
    "drivers":             ["TOTAL_DRIVERS", "DRIVER_TOTAL", "DRIVERS", "TOTALDRIVERS"],
    "cdl_drivers":         ["TOTAL_CDL", "CDL_DRIVERS", "TOTALCDL"],
    "mcs150_date":         ["MCS150_DATE", "MCS150DATE"],
    "mcs150_mileage":      ["MCS150_MILEAGE", "MCS150MILEAGE"],
    "mcs150_mileage_year": ["MCS150_MILEAGE_YEAR", "MCS150MILEAGEYEAR"],
    "add_date":            ["ADD_DATE", "ADDDATE", "REGISTRATION_DATE"],
    "status":              ["STATUS_CODE", "STATUS", "CARRIER_STATUS", "ACT_STAT", "ACTIVE_STATUS"],
    "oos_date":            ["OOS_DATE", "OUT_OF_SERVICE_DATE", "OOSDATE"],
    "mc_number":           ["DOCKET1", "MC_NUMBER", "DOCKET_NUMBER", "MC_MX_FF_NUMBER", "DOCKET", "MCNUMBER"],
    "mc_prefix":           ["ICC_DOCKET_1_PREFIX", "DOCKET1_PREFIX", "MC_PREFIX"],
    "safety_rating":       ["SAFETY_RATING", "SAFETYRATING", "RATING"],
    "safety_rating_date":  ["SAFETY_RATING_DATE", "RATING_DATE"],
    "crash_rate":          ["RECORDABLE_CRASH_RATE", "CRASH_RATE"],
    "business_org":        ["BUSINESS_ORG_DESC", "BUSINESS_ORG", "ORG_TYPE", "ENTITY_TYPE"],
    "interstate_beyond":   ["INTERSTATE_BEYOND_100_MILES"],
    "interstate_within":   ["INTERSTATE_WITHIN_100_MILES"],
    "intrastate_beyond":   ["INTRASTATE_BEYOND_100_MILES"],
    "intrastate_within":   ["INTRASTATE_WITHIN_100_MILES"],
    # Premium fields: read ONLY to set yes/no flags. Values are never written out.
    "email":               ["EMAIL_ADDRESS", "EMAIL", "E_MAIL"],
    "cell_phone":          ["CELL_PHONE", "CELLPHONE", "MOBILE", "MOBILE_PHONE"],
    "officer1":            ["COMPANY_OFFICER_1", "OFFICER_1", "COMPANY_OFFICER1", "OFFICER1"],
    "officer2":            ["COMPANY_OFFICER_2", "OFFICER_2", "COMPANY_OFFICER2", "OFFICER2"],
}

# Operation classification flags -> code stored on the website
OPCLASS_CANDIDATES = {
    "AUTH_FOR_HIRE":    ["AUTHORIZED_FOR_HIRE", "CLASSDEF_AUTH_FOR_HIRE", "CLASS_AUTH_FOR_HIRE", "AUTH_FOR_HIRE",
                         "CLASSDEF_AUTHORIZED_FOR_HIRE", "AUTHFORHIRE"],
    "EXEMPT_FOR_HIRE":  ["EXEMPT_FOR_HIRE", "CLASSDEF_EXEMPT_FOR_HIRE", "CLASS_EXEMPT_FOR_HIRE", "EXEMPTFORHIRE"],
    "PRIVATE_PROPERTY": ["PRIVATE_PROPERTY", "CLASSDEF_PRIVATE_PROPERTY", "CLASS_PRIVATE_PROPERTY", "PRIVATEPROPERTY"],
    "PRIVATE_PASS_BUS": ["PRIVATE_PASSENGER_BUSINESS", "CLASSDEF_PRIV_PASS_BUSINESS", "CLASS_PRIVATE_PASSENGER_BUSINESS",
                         "PRIV_PASS_BUSINESS"],
    "PRIVATE_PASS_NONBUS": ["PRIVATE_PASSENGER_NONBUSINESS", "CLASSDEF_PRIV_PASS_NONBUSINESS",
                            "CLASS_PRIVATE_PASSENGER_NONBUSINESS", "PRIV_PASS_NONBUSINESS"],
    "MIGRANT":          ["MIGRANT", "CLASSDEF_MIGRANT", "CLASS_MIGRANT"],
    "US_MAIL":          ["US_MAIL", "CLASSDEF_US_MAIL", "CLASS_US_MAIL", "USMAIL_CLASS"],
    "FEDERAL_GOVT":     ["FEDERAL_GOVERNMENT", "CLASSDEF_FEDERAL_GOVT", "CLASS_FEDERAL_GOVERNMENT", "FED_GOVT"],
    "STATE_GOVT":       ["STATE_GOVERNMENT", "CLASSDEF_STATE_GOVT", "CLASS_STATE_GOVERNMENT"],
    "LOCAL_GOVT":       ["LOCAL_GOVERNMENT", "CLASSDEF_LOCAL_GOVT", "CLASS_LOCAL_GOVERNMENT"],
    "INDIAN_TRIBE":     ["INDIAN_TRIBE", "CLASSDEF_INDIAN_TRIBE", "CLASS_INDIAN_TRIBE", "INDIAN_NATION"],
    "OTHER":            ["OP_OTHER", "CLASSDEF_OTHER", "CLASS_OTHER", "OTHER_CLASS"],
}
OPCLASS_LABELS = {
    "AUTH_FOR_HIRE": "Authorized For-Hire", "EXEMPT_FOR_HIRE": "Exempt For-Hire",
    "PRIVATE_PROPERTY": "Private (Property)", "PRIVATE_PASS_BUS": "Private Passenger (Business)",
    "PRIVATE_PASS_NONBUS": "Private Passenger (Non-Business)", "MIGRANT": "Migrant",
    "US_MAIL": "U.S. Mail", "FEDERAL_GOVT": "Federal Government", "STATE_GOVT": "State Government",
    "LOCAL_GOVT": "Local Government", "INDIAN_TRIBE": "Indian Tribe", "OTHER": "Other",
}

# Cargo flags: code -> (candidate column names, display name, url slug)
CARGO = [
    ("GENFREIGHT",  ["CRGO_GENFREIGHT", "GENFREIGHT"],            "General Freight",                 "general-freight"),
    ("HOUSEHOLD",   ["CRGO_HOUSEHOLD", "HOUSEHOLD"],              "Household Goods",                 "household-goods"),
    ("METALSHEET",  ["CRGO_METALSHEET", "METALSHEET"],            "Metal: Sheets, Coils, Rolls",     "metal-sheets-coils"),
    ("MOTOVEH",     ["CRGO_MOTOVEH", "MOTOVEH"],                  "Motor Vehicles",                  "motor-vehicles"),
    ("DRIVETOW",    ["CRGO_DRIVETOW", "DRIVETOW"],                "Drive Away / Tow Away",           "drive-away-tow-away"),
    ("LOGPOLE",     ["CRGO_LOGPOLE", "LOGPOLE"],                  "Logs, Poles, Beams, Lumber",      "logs-poles-lumber"),
    ("BLDGMAT",     ["CRGO_BLDGMAT", "BLDGMAT"],                  "Building Materials",              "building-materials"),
    ("MOBILEHOME",  ["CRGO_MOBILEHOME", "MOBILEHOME"],            "Mobile Homes",                    "mobile-homes"),
    ("MACHLRG",     ["CRGO_MACHLRG", "MACHLRG"],                  "Machinery, Large Objects",        "machinery-large-objects"),
    ("PRODUCE",     ["CRGO_PRODUCE", "PRODUCE"],                  "Fresh Produce",                   "fresh-produce"),
    ("LIQGAS",      ["CRGO_LIQGAS", "LIQGAS"],                    "Liquids / Gases (Tanker)",        "liquids-gases-tanker"),
    ("INTERMODAL",  ["CRGO_INTERMODAL", "INTERMODAL"],            "Intermodal Containers",           "intermodal-containers"),
    ("PASSENGERS",  ["CRGO_PASSENGERS", "PASSENGERS"],            "Passengers",                      "passengers"),
    ("OILFIELD",    ["CRGO_OILFIELD", "OILFIELD"],                "Oilfield Equipment",              "oilfield-equipment"),
    ("LIVESTOCK",   ["CRGO_LIVESTOCK", "LIVESTOCK"],              "Livestock",                       "livestock"),
    ("GRAINFEED",   ["CRGO_GRAINFEED", "GRAINFEED"],              "Grain, Feed, Hay",                "grain-feed-hay"),
    ("COALCOKE",    ["CRGO_COALCOKE", "COALCOKE"],                "Coal / Coke",                     "coal-coke"),
    ("MEAT",        ["CRGO_MEAT", "MEAT"],                        "Meat",                            "meat"),
    ("GARBAGE",     ["CRGO_GARBAGE", "GARBAGE"],                  "Garbage, Refuse, Trash",          "garbage-refuse"),
    ("USMAIL",      ["CRGO_USMAIL", "USMAIL"],                    "U.S. Mail",                       "us-mail"),
    ("CHEM",        ["CRGO_CHEM", "CHEM"],                        "Chemicals",                       "chemicals"),
    ("DRYBULK",     ["CRGO_DRYBULK", "DRYBULK"],                  "Commodities Dry Bulk",            "dry-bulk"),
    ("COLDFOOD",    ["CRGO_COLDFOOD", "COLDFOOD", "CRGO_REFRIGERATED"], "Refrigerated Food",         "refrigerated-food"),
    ("BEVERAGES",   ["CRGO_BEVERAGES", "BEVERAGES"],              "Beverages",                       "beverages"),
    ("PAPERPROD",   ["CRGO_PAPERPROD", "PAPERPROD"],              "Paper Products",                  "paper-products"),
    ("UTILITY",     ["CRGO_UTILITY", "UTILITY"],                  "Utilities",                       "utilities"),
    ("FARMSUPP",    ["CRGO_FARMSUPP", "FARMSUPP"],                "Agricultural / Farm Supplies",    "farm-supplies"),
    ("CONSTRUCT",   ["CRGO_CONSTRUCT", "CONSTRUCT"],              "Construction",                    "construction"),
    ("WATERWELL",   ["CRGO_WATERWELL", "WATERWELL"],              "Water Well",                      "water-well"),
    ("CARGOOTHR",   ["CRGO_CARGOOTHR", "CARGOOTHR", "CRGO_OTHER"], "Other Cargo",                    "other-cargo"),
]

US_STATES = {
    "AL": "Alabama", "AK": "Alaska", "AZ": "Arizona", "AR": "Arkansas", "CA": "California", "CO": "Colorado",
    "CT": "Connecticut", "DE": "Delaware", "DC": "District of Columbia", "FL": "Florida", "GA": "Georgia",
    "HI": "Hawaii", "ID": "Idaho", "IL": "Illinois", "IN": "Indiana", "IA": "Iowa", "KS": "Kansas",
    "KY": "Kentucky", "LA": "Louisiana", "ME": "Maine", "MD": "Maryland", "MA": "Massachusetts",
    "MI": "Michigan", "MN": "Minnesota", "MS": "Mississippi", "MO": "Missouri", "MT": "Montana",
    "NE": "Nebraska", "NV": "Nevada", "NH": "New Hampshire", "NJ": "New Jersey", "NM": "New Mexico",
    "NY": "New York", "NC": "North Carolina", "ND": "North Dakota", "OH": "Ohio", "OK": "Oklahoma",
    "OR": "Oregon", "PA": "Pennsylvania", "RI": "Rhode Island", "SC": "South Carolina", "SD": "South Dakota",
    "TN": "Tennessee", "TX": "Texas", "UT": "Utah", "VT": "Vermont", "VA": "Virginia", "WA": "Washington",
    "WV": "West Virginia", "WI": "Wisconsin", "WY": "Wyoming",
}

TRUTHY = {"X", "Y", "YES", "1", "TRUE", "T"}


def norm(name: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (name or "").upper())


def slugify(text: str, maxlen: int = 80) -> str:
    text = (text or "").lower()
    text = re.sub(r"&", " and ", text)
    text = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    if len(text) > maxlen:
        text = text[:maxlen].rsplit("-", 1)[0]
    return text or "carrier"


def to_int(v):
    if v is None:
        return None
    if isinstance(v, (int,)):
        return v
    try:
        f = float(str(v).strip().replace(",", ""))
        if f != f:  # NaN
            return None
        return int(f)
    except (ValueError, TypeError):
        return None


def to_float(v):
    if v is None:
        return None
    try:
        f = float(str(v).strip().replace(",", ""))
        return None if f != f else f
    except (ValueError, TypeError):
        return None


def truthy(v) -> int:
    if v is None:
        return 0
    s = str(v).strip().upper()
    return 1 if s in TRUTHY else 0


DATE_FORMATS = ["%Y-%m-%d", "%m/%d/%Y", "%Y%m%d", "%d-%b-%y", "%d-%b-%Y", "%Y-%m-%d %H:%M:%S",
                "%m/%d/%Y %H:%M:%S", "%Y/%m/%d", "%m-%d-%Y", "%d/%m/%Y"]


def to_date(v):
    """Return ISO date string or None."""
    if v is None:
        return None
    s = str(v).strip()
    if not s or s.upper() in ("NULL", "NONE", "NAN", "NAT"):
        return None
    if len(s) >= 10 and re.match(r"^\d{4}-\d{2}-\d{2}", s):
        return s[:10]
    for fmt in DATE_FORMATS:
        try:
            return dt.datetime.strptime(s[:19] if " " in s else s, fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return None


def clean_phone(v):
    if v is None:
        return None
    digits = re.sub(r"\D", "", str(v))
    if len(digits) == 11 and digits.startswith("1"):
        digits = digits[1:]
    if len(digits) != 10 or digits.startswith("0") or digits.startswith("1"):
        return None
    if digits[3:6] == "555":  # placeholder-looking numbers are not published
        return None
    return digits


def clean_text(v, maxlen=200):
    if v is None:
        return None
    s = re.sub(r"\s+", " ", str(v)).strip()
    if not s or s.upper() in ("NULL", "NONE", "NAN"):
        return None
    return s[:maxlen]


def status_norm(v):
    if v is None:
        return None
    s = str(v).strip().upper()
    if s in ("A", "ACTIVE", "Y"):
        return "ACTIVE"
    if s in ("I", "INACTIVE", "N"):
        return "INACTIVE"
    return s[:20] or None


# ----------------------------------------------------------------------------
def detect_table(src: sqlite3.Connection, wanted: str | None):
    tables = [r[0] for r in src.execute("SELECT name FROM sqlite_master WHERE type IN ('table','view')")]
    if wanted:
        if wanted not in tables:
            sys.exit(f"Table {wanted!r} not found. Tables: {', '.join(tables)}")
        return wanted
    best, best_rows = None, -1
    for t in tables:
        cols = [norm(r[1]) for r in src.execute(f'PRAGMA table_info("{t}")')]
        if not any(c in cols for c in map(norm, FIELD_CANDIDATES["dot"])):
            continue
        try:
            rows = src.execute(f'SELECT MAX(rowid) FROM "{t}"').fetchone()[0] or 0
        except sqlite3.Error:
            rows = 0
        if rows > best_rows:
            best, best_rows = t, rows
    if not best:
        sys.exit("No table with a DOT number column found. Use --table to name it. Tables: " + ", ".join(tables))
    return best


def map_columns(colnames):
    by_norm = {norm(c): c for c in colnames}
    mapping = {}
    for field, cands in FIELD_CANDIDATES.items():
        for cand in cands:
            if norm(cand) in by_norm:
                mapping[field] = by_norm[norm(cand)]
                break
    opclass = {}
    for code, cands in OPCLASS_CANDIDATES.items():
        for cand in cands:
            if norm(cand) in by_norm:
                opclass[code] = by_norm[norm(cand)]
                break
    cargo = {}
    for code, cands, _name, _slug in CARGO:
        for cand in cands:
            if norm(cand) in by_norm:
                cargo[code] = by_norm[norm(cand)]
                break
    # A single free-text classification column (e.g. CLASSDEF = "Auth. For Hire, Private(Property)")
    classdef_text = None
    if not opclass:
        for c in colnames:
            if norm(c) in ("CLASSDEF", "CLASSDEFS", "OPERATIONCLASSIFICATION", "OPERATIONCLASS", "CLASSIFICATION"):
                classdef_text = c
                break
    return mapping, opclass, cargo, classdef_text


CLASSDEF_KEYWORDS = [
    ("AUTH_FOR_HIRE", ["AUTH"]), ("EXEMPT_FOR_HIRE", ["EXEMPT"]), ("PRIVATE_PROPERTY", ["PROPERTY"]),
    ("PRIVATE_PASS_BUS", ["PASS", "BUS"]), ("PRIVATE_PASS_NONBUS", ["NON"]), ("MIGRANT", ["MIGRANT"]),
    ("US_MAIL", ["MAIL"]), ("FEDERAL_GOVT", ["FED"]), ("STATE_GOVT", ["STATE"]), ("LOCAL_GOVT", ["LOCAL"]),
    ("INDIAN_TRIBE", ["INDIAN", "TRIBE"]), ("OTHER", ["OTHER"]),
]


def parse_classdef_text(s):
    if not s:
        return []
    up = str(s).upper()
    out = []
    for code, kws in CLASSDEF_KEYWORDS:
        if all(k in up for k in kws):
            out.append(code)
    return out


SCHEMA = """
PRAGMA page_size = 4096;
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE cargo_types (id INTEGER PRIMARY KEY, code TEXT UNIQUE, name TEXT, slug TEXT UNIQUE);
CREATE TABLE carriers (
  dot INTEGER PRIMARY KEY,
  seq INTEGER NOT NULL,
  legal_name TEXT NOT NULL,
  dba_name TEXT,
  slug TEXT NOT NULL,
  phy_street TEXT, phy_city TEXT, phy_state TEXT, phy_zip TEXT,
  city_slug TEXT,
  phone TEXT,
  carrier_operation TEXT,      -- A = Interstate, B = Intrastate Hazmat, C = Intrastate Non-Hazmat
  hm_flag INTEGER DEFAULT 0,
  pc_flag INTEGER DEFAULT 0,
  power_units INTEGER, truck_units INTEGER, fleetsize INTEGER,
  drivers INTEGER, cdl_drivers INTEGER,
  mcs150_date TEXT, mcs150_mileage INTEGER, mcs150_mileage_year INTEGER,
  add_date TEXT,
  status TEXT,                 -- ACTIVE / INACTIVE / other
  oos_date TEXT,
  mc_number TEXT,
  safety_rating TEXT,          -- S / C / U / N (raw)
  safety_rating_date TEXT,
  crash_rate REAL,
  business_org TEXT,
  op_class TEXT,               -- comma list of OPCLASS codes
  cargo TEXT,                  -- comma list of cargo codes
  range_flags TEXT,            -- e.g. "INTER_BEYOND,INTRA_WITHIN"
  has_email INTEGER DEFAULT 0, -- premium data exists in CarrierDataUSA lists (value never stored here)
  has_cell INTEGER DEFAULT 0,
  has_officer INTEGER DEFAULT 0,
  index_tier INTEGER DEFAULT 1 -- 1 index now, 2 index later, 3 noindex
);
CREATE TABLE carrier_cargo (cargo_id INTEGER, state TEXT, power_units INTEGER, dot INTEGER);
CREATE TABLE stats_state (
  state TEXT PRIMARY KEY, state_name TEXT, state_slug TEXT,
  n INTEGER, n_active INTEGER, n_phone INTEGER, n_interstate INTEGER, n_hazmat INTEGER,
  n_fleet_1 INTEGER, n_fleet_2_5 INTEGER, n_fleet_6_20 INTEGER, n_fleet_21_100 INTEGER, n_fleet_100 INTEGER,
  power_units INTEGER, drivers INTEGER, n_email INTEGER, n_cell INTEGER, n_officer INTEGER, n_indexable INTEGER
);
CREATE TABLE stats_city (state TEXT, city_slug TEXT, city TEXT, n INTEGER, n_active INTEGER, PRIMARY KEY (state, city_slug));
CREATE TABLE stats_cargo (state TEXT, cargo_id INTEGER, n INTEGER, PRIMARY KEY (state, cargo_id));
"""

INDEXES = """
CREATE INDEX idx_carriers_seq ON carriers(seq);
CREATE INDEX idx_carriers_state_city ON carriers(phy_state, city_slug, power_units DESC, legal_name);
CREATE INDEX idx_carriers_state_pu ON carriers(phy_state, power_units DESC);
CREATE INDEX idx_carriers_name ON carriers(legal_name);
CREATE INDEX idx_carriers_dba ON carriers(dba_name) WHERE dba_name IS NOT NULL;
CREATE INDEX idx_carriers_slug ON carriers(slug);
CREATE INDEX idx_cc_cargo_state ON carrier_cargo(cargo_id, state, power_units DESC, dot);
CREATE INDEX idx_stats_city_n ON stats_city(state, n DESC);
"""


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--source", required=True, help="path to the FMCSA master SQLite file")
    ap.add_argument("--out", default="carriers.db", help="output file (default carriers.db)")
    ap.add_argument("--table", default=None)
    ap.add_argument("--inspect", action="store_true")
    ap.add_argument("--where", default=None, help="extra SQL WHERE filter on the source table")
    ap.add_argument("--active-only", action="store_true")
    ap.add_argument("--no-us-only", action="store_true")
    ap.add_argument("--snapshot", default=None)
    ap.add_argument("--limit", type=int, default=None)
    args = ap.parse_args()

    if not os.path.exists(args.source):
        sys.exit(f"Source not found: {args.source}")
    src = sqlite3.connect(f"file:{os.path.abspath(args.source)}?mode=ro", uri=True)
    src.text_factory = lambda b: b.decode("utf-8", "replace")
    table = detect_table(src, args.table)
    colnames = [r[1] for r in src.execute(f'PRAGMA table_info("{table}")')]
    mapping, opclass_cols, cargo_cols, classdef_text = map_columns(colnames)

    missing_required = [f for f in ("dot", "legal_name", "phy_state") if f not in mapping]
    print(f"Source table: {table}  ({len(colnames)} columns)")
    print("Column mapping:")
    for field in FIELD_CANDIDATES:
        print(f"  {field:22s} <- {mapping.get(field, '(not found)')}")
    print(f"  operation classes      <- {len(opclass_cols)} flag columns" + (f" + text column {classdef_text}" if classdef_text else ""))
    print(f"  cargo flags            <- {len(cargo_cols)} of {len(CARGO)} cargo columns found")
    if missing_required:
        sys.exit(f"Required fields missing: {missing_required}. Add their column names to FIELD_CANDIDATES.")
    if args.inspect:
        unmapped = [c for c in colnames if c not in mapping.values() and c not in opclass_cols.values()
                    and c not in cargo_cols.values() and c != classdef_text]
        print("Unmapped source columns (left out on purpose unless listed above):")
        print("  " + ", ".join(unmapped))
        return

    # Snapshot date
    snapshot = args.snapshot
    if not snapshot:
        m = re.search(r"(20\d{2})(\d{2})(\d{2})", table)
        snapshot = f"{m.group(1)}-{m.group(2)}-{m.group(3)}" if m else dt.date.today().isoformat()

    if os.path.exists(args.out):
        os.remove(args.out)
    out = sqlite3.connect(args.out)
    out.executescript("PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF; PRAGMA temp_store = MEMORY; PRAGMA cache_size = -200000;")
    out.executescript(SCHEMA)

    cargo_ids = {}
    for i, (code, _c, name, slug) in enumerate(CARGO, start=1):
        cargo_ids[code] = i
        out.execute("INSERT INTO cargo_types (id, code, name, slug) VALUES (?,?,?,?)", (i, code, name, slug))

    # Build the SELECT
    select_cols = list(dict.fromkeys(list(mapping.values()) + list(opclass_cols.values()) + list(cargo_cols.values())
                                     + ([classdef_text] if classdef_text else [])))
    col_index = {c: i for i, c in enumerate(select_cols)}
    sql = f'SELECT {", ".join(chr(34) + c + chr(34) for c in select_cols)} FROM "{table}"'
    if args.where:
        sql += f" WHERE ({args.where})"
    if args.limit:
        sql += f" LIMIT {int(args.limit)}"

    def g(row, field):
        c = mapping.get(field)
        return row[col_index[c]] if c else None

    us_only = not args.no_us_only
    t0 = time.time()
    seen = set()
    seq = 0
    n_read = n_kept = n_skipped_dupe = n_skipped_state = n_skipped_inactive = 0
    batch, cargo_batch = [], []
    cur = src.execute(sql)
    cur.arraysize = 5000
    ins = ("INSERT INTO carriers (dot, seq, legal_name, dba_name, slug, phy_street, phy_city, phy_state, phy_zip, city_slug, "
           "phone, carrier_operation, hm_flag, pc_flag, power_units, truck_units, fleetsize, drivers, cdl_drivers, "
           "mcs150_date, mcs150_mileage, mcs150_mileage_year, add_date, status, oos_date, mc_number, safety_rating, "
           "safety_rating_date, crash_rate, business_org, op_class, cargo, range_flags, has_email, has_cell, has_officer, index_tier) "
           "VALUES (" + ",".join(["?"] * 37) + ")")
    while True:
        rows = cur.fetchmany()
        if not rows:
            break
        for row in rows:
            n_read += 1
            dot = to_int(g(row, "dot"))
            name = clean_text(g(row, "legal_name"), 160)
            if not dot or dot <= 0 or not name:
                continue
            if dot in seen:
                n_skipped_dupe += 1
                continue
            state = clean_text(g(row, "phy_state"), 2)
            state = state.upper() if state else None
            if us_only and state not in US_STATES:
                n_skipped_state += 1
                continue
            status = status_norm(g(row, "status")) if "status" in mapping else None
            if args.active_only and status is not None and status != "ACTIVE":
                n_skipped_inactive += 1
                continue
            seen.add(dot)
            seq += 1

            city = clean_text(g(row, "phy_city"), 80)
            city = city.upper() if city else None
            phone = clean_phone(g(row, "phone"))
            pu = to_int(g(row, "power_units"))
            drivers = to_int(g(row, "drivers"))
            oos_date = to_date(g(row, "oos_date"))

            op = clean_text(g(row, "carrier_operation"), 1)
            op = op.upper() if op else None
            if op not in ("A", "B", "C"):
                if truthy(g(row, "crr_inter")):
                    op = "A"
                elif truthy(g(row, "crr_hm_intra")):
                    op = "B"
                elif truthy(g(row, "crr_intra")):
                    op = "C"
                else:
                    op = None

            codes = [code for code, col in opclass_cols.items() if truthy(row[col_index[col]])]
            if classdef_text:
                codes = parse_classdef_text(row[col_index[classdef_text]])
            cargo_codes = [code for code, col in cargo_cols.items() if truthy(row[col_index[col]])]

            rng = []
            if truthy(g(row, "interstate_beyond")):
                rng.append("INTER_BEYOND")
            if truthy(g(row, "interstate_within")):
                rng.append("INTER_WITHIN")
            if truthy(g(row, "intrastate_beyond")):
                rng.append("INTRA_BEYOND")
            if truthy(g(row, "intrastate_within")):
                rng.append("INTRA_WITHIN")

            mc = clean_text(g(row, "mc_number"), 12)
            if mc:
                mc_digits = re.sub(r"\D", "", mc)
                mc = mc_digits if mc_digits and mc_digits != "0" else None

            email_v = g(row, "email")
            has_email = 1 if email_v and "@" in str(email_v) else 0
            has_cell = 1 if clean_phone(g(row, "cell_phone")) else 0
            off = clean_text(g(row, "officer1"), 10) or clean_text(g(row, "officer2"), 10)
            has_officer = 1 if off else 0

            sr = clean_text(g(row, "safety_rating"), 1)
            sr = sr.upper() if sr else None
            if sr not in ("S", "C", "U", "N"):
                sr = None

            # Index tier
            inactive = (status == "INACTIVE") or bool(oos_date)
            if inactive:
                tier = 3
            elif phone and pu and pu >= 1:
                tier = 1
            else:
                tier = 2

            batch.append((
                dot, seq, name, clean_text(g(row, "dba_name"), 160), slugify(name),
                clean_text(g(row, "phy_street"), 120), city, state, clean_text(g(row, "phy_zip"), 10),
                slugify(city or "", 60) if city else None,
                phone, op, truthy(g(row, "hm_flag")), truthy(g(row, "pc_flag")),
                pu, to_int(g(row, "truck_units")), to_int(g(row, "fleetsize")), drivers, to_int(g(row, "cdl_drivers")),
                to_date(g(row, "mcs150_date")), to_int(g(row, "mcs150_mileage")), to_int(g(row, "mcs150_mileage_year")),
                to_date(g(row, "add_date")), status, oos_date, mc, sr, to_date(g(row, "safety_rating_date")),
                to_float(g(row, "crash_rate")), clean_text(g(row, "business_org"), 60),
                ",".join(codes) or None, ",".join(cargo_codes) or None, ",".join(rng) or None,
                has_email, has_cell, has_officer, tier,
            ))
            for code in cargo_codes:
                cargo_batch.append((cargo_ids[code], state, pu or 0, dot))
            n_kept += 1
            if len(batch) >= 20000:
                out.executemany(ins, batch)
                out.executemany("INSERT INTO carrier_cargo VALUES (?,?,?,?)", cargo_batch)
                batch, cargo_batch = [], []
                print(f"  {n_read:,} read / {n_kept:,} kept  ({time.time() - t0:,.0f}s)", end="\r", flush=True)
    if batch:
        out.executemany(ins, batch)
        out.executemany("INSERT INTO carrier_cargo VALUES (?,?,?,?)", cargo_batch)
    out.commit()
    print(f"\nRows read {n_read:,}; kept {n_kept:,}; skipped: {n_skipped_dupe:,} duplicate DOT, "
          f"{n_skipped_state:,} outside US, {n_skipped_inactive:,} inactive")

    print("Building indexes...")
    out.executescript(INDEXES)
    print("Computing stats...")
    out.execute("DELETE FROM stats_state")
    out.execute("""
        INSERT INTO stats_state SELECT phy_state, '', '',
          COUNT(*), SUM(status='ACTIVE' OR status IS NULL), SUM(phone IS NOT NULL), SUM(carrier_operation='A'), SUM(hm_flag=1),
          SUM(power_units=1), SUM(power_units BETWEEN 2 AND 5), SUM(power_units BETWEEN 6 AND 20),
          SUM(power_units BETWEEN 21 AND 100), SUM(power_units>100),
          SUM(COALESCE(power_units,0)), SUM(COALESCE(drivers,0)), SUM(has_email), SUM(has_cell), SUM(has_officer), SUM(index_tier<=2)
        FROM carriers GROUP BY phy_state""")
    for abbr, name in US_STATES.items():
        out.execute("UPDATE stats_state SET state_name=?, state_slug=? WHERE state=?", (name, slugify(name), abbr))
    out.execute("""
        INSERT INTO stats_city SELECT phy_state, city_slug, MIN(phy_city), COUNT(*), SUM(status='ACTIVE' OR status IS NULL)
        FROM carriers WHERE city_slug IS NOT NULL GROUP BY phy_state, city_slug""")
    out.execute("INSERT INTO stats_cargo SELECT state, cargo_id, COUNT(*) FROM carrier_cargo GROUP BY state, cargo_id")
    total = out.execute("SELECT COUNT(*) FROM carriers").fetchone()[0]
    max_seq = out.execute("SELECT MAX(seq) FROM carriers").fetchone()[0] or 0
    for k, v in {
        "snapshot_date": snapshot, "built_at": dt.datetime.now().isoformat(timespec="seconds"),
        "source_table": table, "total": str(total), "max_seq": str(max_seq),
        "indexable": str(out.execute("SELECT COUNT(*) FROM carriers WHERE index_tier<=2").fetchone()[0]),
        "tier1": str(out.execute("SELECT COUNT(*) FROM carriers WHERE index_tier=1").fetchone()[0]),
        "schema_version": "1",
    }.items():
        out.execute("INSERT INTO meta VALUES (?,?)", (k, v))
    out.commit()
    print("Compacting...")
    out.execute("VACUUM")
    out.close()
    size_mb = os.path.getsize(args.out) / 1e6
    print(f"Done: {args.out}  {total:,} carriers, {size_mb:,.0f} MB, snapshot {snapshot}, {time.time() - t0:,.0f}s")
    print("Next: upload it to the server as data/carriers.db (see README.md).")


if __name__ == "__main__":
    main()
