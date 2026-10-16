#!/usr/bin/env python3
"""
make_sample_db.py - DEVELOPMENT ONLY. Creates a small fake FMCSA-style SQLite file in the
shape of the real Company Census File (MCMIS column names and value formats) so the website
and build_carriers_db.py can be tested without the real data:

  * one free-text CLASSDEF column ("Auth. For Hire, Private(Property)"), no per-class flags
  * INTERSTATE_*/INTRASTATE_* hold driver COUNTS (mostly 0), not X flags
  * DOCKET1PREFIX MC / FF / MX / blank, STATUS_CODE A or I, HM_Ind Y/N, SAFETY_RATING S/C/U/N or blank
  * PHONE as a 10-digit string, some stored as REAL-looking text ('7700151212.0'), some blank
  * PHY_COUNTRY US with a few CA / MX rows, a few duplicate DOT numbers, DD-MON-YY dates
  * EMAIL_ADDRESS, CELL_PHONE, COMPANY_OFFICER_1/2 with realistic fill rates
  * all 30 CRGO_* cargo columns as 'X' or blank

Every record is obviously synthetic ("SAMPLE CARRIER 00042 LLC"; phone exchanges "01x" are not
assignable in the North American numbering plan). Never upload the output to the live site.

Usage: python make_sample_db.py --out sample_master.db --rows 6000
       add --typed to store the numeric columns as REAL, like an Excel/pandas import does
"""
import argparse
import random
import sqlite3

STATES = ["GA", "TX", "FL", "CA", "IL", "OH", "NY", "PA", "NC", "TN", "WY", "AL"]
CITIES = {
    "GA": ["MARIETTA", "ATLANTA", "SAVANNAH", "MACON", "AUGUSTA", "MC DONOUGH"],
    "TX": ["HOUSTON", "DALLAS", "LAREDO", "EL PASO", "SAN ANTONIO"],
    "FL": ["MIAMI", "ORLANDO", "TAMPA", "JACKSONVILLE"],
    "CA": ["FONTANA", "LOS ANGELES", "FRESNO", "STOCKTON"],
    "IL": ["CHICAGO", "JOLIET", "ROCKFORD"],
    "OH": ["COLUMBUS", "CLEVELAND", "TOLEDO"],
    "NY": ["BUFFALO", "BROOKLYN", "ALBANY"],
    "PA": ["PITTSBURGH", "HARRISBURG", "ALLENTOWN"],
    "NC": ["CHARLOTTE", "RALEIGH", "GREENSBORO"],
    "TN": ["NASHVILLE", "MEMPHIS", "KNOXVILLE"],
    "WY": ["CHEYENNE", "CASPER", "LARAMIE"],
    "AL": ["BIRMINGHAM", "MOBILE", "HUNTSVILLE"],
}
# Foreign rows: Mexican state codes that collide with US abbreviations (CO = Coahuila) and a Canadian province
FOREIGN = [("MX", "CO", "SALTILLO"), ("MX", "NL", "MONTERREY"), ("MX", "CO", "TORREON"), ("CA", "ON", "TORONTO")]
CARGO_COLS = ["CRGO_GENFREIGHT", "CRGO_HOUSEHOLD", "CRGO_METALSHEET", "CRGO_MOTOVEH", "CRGO_DRIVETOW", "CRGO_LOGPOLE",
              "CRGO_BLDGMAT", "CRGO_MOBILEHOME", "CRGO_MACHLRG", "CRGO_PRODUCE", "CRGO_LIQGAS", "CRGO_INTERMODAL",
              "CRGO_PASSENGERS", "CRGO_OILFIELD", "CRGO_LIVESTOCK", "CRGO_GRAINFEED", "CRGO_COALCOKE", "CRGO_MEAT",
              "CRGO_GARBAGE", "CRGO_USMAIL", "CRGO_CHEM", "CRGO_DRYBULK", "CRGO_COLDFOOD", "CRGO_BEVERAGES",
              "CRGO_PAPERPROD", "CRGO_UTILITY", "CRGO_FARMSUPP", "CRGO_CONSTRUCT", "CRGO_WATERWELL", "CRGO_CARGOOTHR"]
BASE_COLS = ["DOT_NUMBER", "LEGAL_NAME", "DBA_NAME", "PHY_STREET", "PHY_CITY", "PHY_STATE", "PHY_ZIP", "PHY_COUNTRY",
             "PHONE", "CELL_PHONE", "FAX", "EMAIL_ADDRESS", "COMPANY_OFFICER_1", "COMPANY_OFFICER_2",
             "CARRIER_OPERATION", "HM_Ind", "STATUS_CODE", "POWER_UNITS", "TRUCK_UNITS", "BUS_UNITS",
             "FLEETSIZE", "TOTAL_DRIVERS", "TOTAL_CDL", "MCS150_DATE", "MCS150_MILEAGE", "MCS150_MILEAGE_YEAR",
             "ADD_DATE", "DOCKET1PREFIX", "DOCKET1", "SAFETY_RATING", "SAFETY_RATING_DATE",
             "RECORDABLE_CRASH_RATE", "BUSINESS_ORG_DESC", "DUN_BRADSTREET_NO", "CLASSDEF",
             "INTERSTATE_BEYOND_100_MILES", "INTERSTATE_WITHIN_100_MILES", "INTRASTATE_BEYOND_100_MILES",
             "INTRASTATE_WITHIN_100_MILES"]
# Columns an Excel/pandas import types as REAL when they hold digits with blanks (--typed)
NUMERIC_COLS = {"DOT_NUMBER", "PHY_ZIP", "PHONE", "CELL_PHONE", "POWER_UNITS", "TRUCK_UNITS", "BUS_UNITS", "FLEETSIZE",
                "TOTAL_DRIVERS", "TOTAL_CDL", "MCS150_MILEAGE", "MCS150_MILEAGE_YEAR", "DOCKET1", "RECORDABLE_CRASH_RATE",
                "INTERSTATE_BEYOND_100_MILES", "INTERSTATE_WITHIN_100_MILES", "INTRASTATE_BEYOND_100_MILES",
                "INTRASTATE_WITHIN_100_MILES"}
SUFFIX = ["LLC", "INC", "TRUCKING", "TRANSPORT", "LOGISTICS LLC", "EXPRESS INC", "HAULING", "FREIGHT LLC"]
MONTHS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"]

# CLASSDEF vocabulary exactly as the census writes it; weights roughly follow the real distribution
CLASSDEF_CHOICES = [
    ("Auth. For Hire", 55),
    ("Auth. For Hire, Private(Property)", 12),
    ("Private(Property)", 12),
    ("Exempt For Hire", 4),
    ("Auth. For Hire, Exempt For Hire", 3),
    ("Priv. Pass. (Business)", 3),
    ("Priv. Pass.(Non-business)", 3),
    ("Private(Passenger-Non-Business)", 1),
    ("Private(Passenger-Business)", 1),
    ("Auth. For Hire, Private(Property), Exempt For Hire", 1),
    ("Local Gov't", 1),
    ("State Gov't", 1),
    ("Fed. Gov't", 1),
    ("U.S. Mail", 1),
    ("Migrant", 1),
    ("Indian Nation", 1),
    ("Other", 1),
    ("Auth. For Hire, U.S. Mail", 1),
    ("Private(Property), Local Gov't, Other", 1),
]
PASSENGER_CLASSES = ("Priv. Pass.", "Private(Passenger")


def weighted(choices):
    return random.choices([c for c, _w in choices], weights=[w for _c, w in choices], k=1)[0]


def fake_phone():
    """10-digit string; exchange 01x can never be a real number."""
    return f"{random.randint(201, 989)}01{random.randint(0, 9)}{random.randint(1000, 9999)}"


def census_date(y0, y1):
    """DD-MON-YY, the format the census export uses."""
    return f"{random.randint(1, 28):02d}-{random.choice(MONTHS)}-{random.randint(y0, y1) % 100:02d}"


def range_count():
    return "0" if random.random() < 0.7 else str(random.randint(1, 50))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default="sample_master.db")
    ap.add_argument("--rows", type=int, default=6000)
    ap.add_argument("--typed", action="store_true",
                    help="store numeric columns as REAL (how an Excel/pandas import types digits-with-blanks columns)")
    args = ap.parse_args()
    random.seed(7)
    con = sqlite3.connect(args.out)
    cols = BASE_COLS + CARGO_COLS
    table = "Company_Census_File_20260330"
    con.execute(f"DROP TABLE IF EXISTS {table}")
    con.execute(f"CREATE TABLE {table} (" + ", ".join(
        f'"{c}" {"REAL" if (args.typed and c in NUMERIC_COLS) else "TEXT"}' for c in cols) + ")")

    def cell(c, v):
        if args.typed and c in NUMERIC_COLS:
            return None if v in ("", None) else float(v)
        return "" if v is None else str(v)

    rows = []
    dots = []
    n_dupes = n_foreign = 0
    for i in range(1, args.rows + 1):
        if dots and random.random() < 0.002:
            dot = random.choice(dots)          # duplicate DOT: the trimmer must keep only the first
            n_dupes += 1
        else:
            dot = str(1000000 + i * 7)
            dots.append(dot)
        if random.random() < 0.006:
            country, st, city = random.choice(FOREIGN)
            n_foreign += 1
        else:
            country = "US"
            st = random.choice(STATES)
            city = random.choice(CITIES[st])
        pu = random.choice([1, 1, 1, 2, 3, 5, 8, 12, 25, 60, 150, 0, None])
        drivers = (pu or 0) + random.choice([0, 0, 1, 2])
        status = "I" if random.random() < 0.15 else "A"
        name = f"SAMPLE CARRIER {i:05d} {random.choice(SUFFIX)}"
        classdef = weighted(CLASSDEF_CHOICES)
        passenger = any(p in classdef for p in PASSENGER_CLASSES)
        docket = str(random.randint(100000, 1599999)) if random.random() < 0.55 else ""
        prefix = weighted([("MC", 90), ("FF", 5), ("MX", 3), ("", 2)]) if docket else ""
        r_phone = random.random()
        if r_phone < 0.02:
            phone = ""
        elif r_phone < 0.07:
            phone = fake_phone() + ".0"       # REAL-looking text, as a typed master round-trips it
        else:
            phone = fake_phone()
        r = {
            "DOT_NUMBER": dot,
            "LEGAL_NAME": name,
            "DBA_NAME": f"SAMPLE DBA {i:05d}" if random.random() < 0.3 else "",
            "PHY_STREET": f"{random.randint(100, 9999)} SAMPLE RD",
            "PHY_CITY": city, "PHY_STATE": st, "PHY_ZIP": f"{random.randint(10000, 99999)}", "PHY_COUNTRY": country,
            "PHONE": phone,
            "CELL_PHONE": fake_phone() if random.random() < 0.45 else "",
            "FAX": "",
            "EMAIL_ADDRESS": f"sample{i}@example.com" if random.random() < 0.6 else "",
            "COMPANY_OFFICER_1": f"SAMPLE OFFICER {i}" if random.random() < 0.7 else "",
            "COMPANY_OFFICER_2": f"SAMPLE OFFICER {i}B" if random.random() < 0.15 else "",
            "CARRIER_OPERATION": random.choice(["A", "A", "A", "C", "B"]),
            "HM_Ind": "Y" if random.random() < 0.15 else "N",
            "STATUS_CODE": status,
            "POWER_UNITS": "" if pu is None else str(pu), "TRUCK_UNITS": str(random.randint(0, 3)),
            "BUS_UNITS": str(random.randint(1, 5)) if passenger else "0",
            "FLEETSIZE": "" if pu is None else str(pu), "TOTAL_DRIVERS": str(drivers), "TOTAL_CDL": str(drivers),
            "MCS150_DATE": census_date(2019, 2026),
            "MCS150_MILEAGE": str(random.randint(5000, 2500000)), "MCS150_MILEAGE_YEAR": str(random.randint(2022, 2025)),
            "ADD_DATE": census_date(1975, 2026),
            "DOCKET1PREFIX": prefix, "DOCKET1": docket,
            "SAFETY_RATING": weighted([("", 80), ("S", 12), ("C", 4), ("U", 2), ("N", 2)]),
            "SAFETY_RATING_DATE": census_date(2015, 2025) if random.random() < 0.2 else "",
            "RECORDABLE_CRASH_RATE": f"{random.random() * 2:.2f}",
            "BUSINESS_ORG_DESC": random.choice(["LLC", "CORPORATION", "INDIVIDUAL", "PARTNERSHIP"]),
            "DUN_BRADSTREET_NO": "",
            "CLASSDEF": classdef,
            "INTERSTATE_BEYOND_100_MILES": range_count(), "INTERSTATE_WITHIN_100_MILES": range_count(),
            "INTRASTATE_BEYOND_100_MILES": range_count(), "INTRASTATE_WITHIN_100_MILES": range_count(),
        }
        for c in CARGO_COLS:
            r[c] = ""
        for c in random.sample(CARGO_COLS, random.choice([1, 1, 2, 3, 4])):
            r[c] = "X"
        if passenger and random.random() < 0.6:
            r["CRGO_PASSENGERS"] = "X"
        rows.append(tuple(cell(c, r[c]) for c in cols))
    con.executemany(f"INSERT INTO {table} VALUES (" + ",".join("?" * len(cols)) + ")", rows)
    con.commit()
    con.close()
    print(f"wrote {args.rows} fake rows to {args.out} (table {table}, {len(cols)} columns, "
          f"{n_dupes} duplicate DOTs, {n_foreign} non-US rows{', REAL-typed numerics' if args.typed else ''})")


if __name__ == "__main__":
    main()
