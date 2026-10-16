#!/usr/bin/env python3
"""
make_sample_db.py - DEVELOPMENT ONLY. Creates a small fake FMCSA-style SQLite file
(MCMIS census column names) so the website can be tested without the real data.
Every record is obviously synthetic ("SAMPLE CARRIER 00042 LLC", 555 phone numbers).
Never upload the output of this script to the live site.

Usage: python make_sample_db.py --out sample_master.db --rows 6000
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
CARGO_COLS = ["CRGO_GENFREIGHT", "CRGO_HOUSEHOLD", "CRGO_METALSHEET", "CRGO_MOTOVEH", "CRGO_DRIVETOW", "CRGO_LOGPOLE",
              "CRGO_BLDGMAT", "CRGO_MOBILEHOME", "CRGO_MACHLRG", "CRGO_PRODUCE", "CRGO_LIQGAS", "CRGO_INTERMODAL",
              "CRGO_PASSENGERS", "CRGO_OILFIELD", "CRGO_LIVESTOCK", "CRGO_GRAINFEED", "CRGO_COALCOKE", "CRGO_MEAT",
              "CRGO_GARBAGE", "CRGO_USMAIL", "CRGO_CHEM", "CRGO_DRYBULK", "CRGO_COLDFOOD", "CRGO_BEVERAGES",
              "CRGO_PAPERPROD", "CRGO_UTILITY", "CRGO_FARMSUPP", "CRGO_CONSTRUCT", "CRGO_WATERWELL", "CRGO_CARGOOTHR"]
CLASS_COLS = ["CLASSDEF_AUTH_FOR_HIRE", "CLASSDEF_EXEMPT_FOR_HIRE", "CLASSDEF_PRIVATE_PROPERTY",
              "CLASSDEF_PRIV_PASS_BUSINESS", "CLASSDEF_PRIV_PASS_NONBUSINESS", "CLASSDEF_MIGRANT", "CLASSDEF_US_MAIL",
              "CLASSDEF_FEDERAL_GOVT", "CLASSDEF_STATE_GOVT", "CLASSDEF_LOCAL_GOVT", "CLASSDEF_INDIAN_TRIBE", "CLASSDEF_OTHER"]
BASE_COLS = ["DOT_NUMBER", "LEGAL_NAME", "DBA_NAME", "PHY_STREET", "PHY_CITY", "PHY_STATE", "PHY_ZIP", "PHY_NATN",
             "PHONE", "CELL_PHONE", "FAX", "EMAIL_ADDRESS", "COMPANY_OFFICER_1", "COMPANY_OFFICER_2",
             "CARRIER_OPERATION", "HM_IND", "PC_FLAG", "STATUS_CODE", "OOS_DATE", "POWER_UNITS", "TRUCK_UNITS",
             "FLEETSIZE", "TOTAL_DRIVERS", "TOTAL_CDL", "MCS150_DATE", "MCS150_MILEAGE", "MCS150_MILEAGE_YEAR",
             "ADD_DATE", "DOCKET1", "ICC_DOCKET_1_PREFIX", "SAFETY_RATING", "SAFETY_RATING_DATE",
             "RECORDABLE_CRASH_RATE", "BUSINESS_ORG_DESC", "DUN_BRADSTREET_NO",
             "INTERSTATE_BEYOND_100_MILES", "INTERSTATE_WITHIN_100_MILES", "INTRASTATE_BEYOND_100_MILES",
             "INTRASTATE_WITHIN_100_MILES"]
SUFFIX = ["LLC", "INC", "TRUCKING", "TRANSPORT", "LOGISTICS LLC", "EXPRESS INC", "HAULING", "FREIGHT LLC"]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default="sample_master.db")
    ap.add_argument("--rows", type=int, default=6000)
    args = ap.parse_args()
    random.seed(7)
    con = sqlite3.connect(args.out)
    cols = BASE_COLS + CLASS_COLS + CARGO_COLS
    con.execute("DROP TABLE IF EXISTS Company_Census_File_20260330")
    con.execute("CREATE TABLE Company_Census_File_20260330 (" + ", ".join(f'"{c}" TEXT' for c in cols) + ")")
    rows = []
    for i in range(1, args.rows + 1):
        st = random.choice(STATES)
        city = random.choice(CITIES[st])
        pu = random.choice([1, 1, 1, 2, 3, 5, 8, 12, 25, 60, 150, 0, None])
        drivers = (pu or 0) + random.choice([0, 0, 1, 2])
        status = random.choice(["A"] * 8 + ["I"] * 2)
        name = f"SAMPLE CARRIER {i:05d} {random.choice(SUFFIX)}"
        r = {
            "DOT_NUMBER": str(1000000 + i * 7),
            "LEGAL_NAME": name,
            "DBA_NAME": f"SAMPLE DBA {i:05d}" if random.random() < 0.3 else "",
            "PHY_STREET": f"{random.randint(100, 9999)} SAMPLE RD",
            "PHY_CITY": city, "PHY_STATE": st, "PHY_ZIP": f"{random.randint(10000, 99999)}", "PHY_NATN": "US",
            # exchange "01x" is not assignable in the North American numbering plan, so these can never be real numbers
            "PHONE": f"({random.randint(201, 989)}) 01{random.randint(0, 9)}-{random.randint(1000, 9999)}" if random.random() < 0.85 else "",
            "CELL_PHONE": f"({random.randint(201, 989)}) 555-{random.randint(1000, 9999)}" if random.random() < 0.5 else "",
            "FAX": "", "EMAIL_ADDRESS": f"sample{i}@example.com" if random.random() < 0.6 else "",
            "COMPANY_OFFICER_1": f"SAMPLE OFFICER {i}" if random.random() < 0.7 else "", "COMPANY_OFFICER_2": "",
            "CARRIER_OPERATION": random.choice(["A", "A", "A", "C", "B"]),
            "HM_IND": random.choice(["N", "N", "N", "Y"]), "PC_FLAG": "N",
            "STATUS_CODE": status, "OOS_DATE": "" if status == "A" else random.choice(["", "2025-03-01"]),
            "POWER_UNITS": "" if pu is None else str(pu), "TRUCK_UNITS": str(random.randint(0, 3)),
            "FLEETSIZE": "" if pu is None else str(pu), "TOTAL_DRIVERS": str(drivers), "TOTAL_CDL": str(drivers),
            "MCS150_DATE": f"{random.randint(2019, 2026)}-{random.randint(1, 12):02d}-{random.randint(1, 28):02d}",
            "MCS150_MILEAGE": str(random.randint(5000, 2500000)), "MCS150_MILEAGE_YEAR": str(random.randint(2022, 2025)),
            "ADD_DATE": f"{random.randint(1990, 2026)}-{random.randint(1, 12):02d}-{random.randint(1, 28):02d}",
            "DOCKET1": str(random.randint(100000, 1599999)) if random.random() < 0.55 else "",
            "ICC_DOCKET_1_PREFIX": "MC",
            "SAFETY_RATING": random.choice(["", "", "", "S", "C", "U", "N"]),
            "SAFETY_RATING_DATE": "2024-05-10", "RECORDABLE_CRASH_RATE": f"{random.random() * 2:.2f}",
            "BUSINESS_ORG_DESC": random.choice(["LLC", "CORPORATION", "INDIVIDUAL", "PARTNERSHIP"]),
            "DUN_BRADSTREET_NO": "",
            "INTERSTATE_BEYOND_100_MILES": random.choice(["X", ""]), "INTERSTATE_WITHIN_100_MILES": random.choice(["X", ""]),
            "INTRASTATE_BEYOND_100_MILES": "", "INTRASTATE_WITHIN_100_MILES": random.choice(["X", ""]),
        }
        for c in CLASS_COLS:
            r[c] = ""
        r[random.choice(["CLASSDEF_AUTH_FOR_HIRE", "CLASSDEF_AUTH_FOR_HIRE", "CLASSDEF_PRIVATE_PROPERTY", "CLASSDEF_EXEMPT_FOR_HIRE"])] = "X"
        for c in CARGO_COLS:
            r[c] = ""
        for c in random.sample(CARGO_COLS, random.choice([1, 1, 2, 3, 4])):
            r[c] = "X"
        rows.append(tuple(r[c] for c in cols))
    con.executemany("INSERT INTO Company_Census_File_20260330 VALUES (" + ",".join("?" * len(cols)) + ")", rows)
    con.commit()
    con.close()
    print(f"wrote {args.rows} fake rows to {args.out}")


if __name__ == "__main__":
    main()
