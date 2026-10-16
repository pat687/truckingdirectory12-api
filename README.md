# Trucking Directory USA — Carrier Engine

Turns your FMCSA master database into one profile page per USDOT number on
truckingdirectoryusa.com (Hostinger Business plan), plus state / city / cargo hub
pages, sitemaps, a claim-your-listing flow, vendor listings, lead capture and a
CarrierDataUSA funnel on every page. Five PHP files serve 1.4M URLs, so the
600K-inode limit on the plan is never an issue.

```
tools/build_carriers_db.py   -> run on your PC: master .db  ->  lean carriers.db (public fields only)
tools/make_sample_db.py      -> dev only: fake data for local testing
tools/dev_router.php         -> local preview with PHP's built-in server (no Apache needed)
site/.htaccess               -> clean URLs + hardening (upload to public_html/)
site/app/                    -> the engine (upload to public_html/app/)
site/app/config.php          -> everything you need to edit is here
```

## 1. Build carriers.db on your PC

Requires Python 3.9+ (no extra packages).

```
python build_carriers_db.py --source "C:\Users\Pat\Desktop\EXCEL DATA FOLDER FROM FMCAS\National all information.db" --inspect
```

`--inspect` prints the table it picked and the column mapping (DOT_NUMBER, LEGAL_NAME,
CRGO_* cargo flags, STATUS_CODE, SAFETY_RATING, …) without writing anything. If a field
shows `(not found)` that you know exists under another name, add that name to
`FIELD_CANDIDATES` at the top of the script. Then build:

```
python build_carriers_db.py --source "...\National all information.db" --out carriers.db
```

Options: `--table NAME` to pick a table, `--where "STATUS_CODE='A'"` or `--active-only`
to keep active carriers only, `--snapshot 2026-03-30` to label the data date.
Expect roughly 1 GB for 1.4M carriers and a few minutes of run time.

What is NOT copied: EMAIL_ADDRESS, CELL_PHONE, COMPANY_OFFICER_1/2, FAX, DUNS — only
yes/no flags, so the pages can say "email and officer name on file" and send people to
CarrierDataUSA. The web server never holds the data you sell.

## 2. Upload to Hostinger

1. **PHP extensions** — hPanel → Advanced → PHP Configuration → PHP extensions: make sure
   `pdo_sqlite` and `sqlite3` are ticked (PHP 8.1+). Save.
2. **Database file** — hPanel → Files → File Manager (or FTP/SFTP: hPanel → Files → FTP
   Accounts). Create a folder `data` *next to* `public_html` (i.e.
   `/domains/truckingdirectoryusa.com/data/`) and upload `carriers.db` there. For a 1 GB
   file use FTP (FileZilla) rather than the browser uploader. If you can't create the
   folder, put it in `public_html/app/data/` instead (that folder is web-blocked).
3. **App files** — upload the whole `site/app/` folder to `public_html/app/`.
4. **.htaccess** — upload `site/.htaccess` to `public_html/.htaccess`. If one already
   exists, paste these rules at the top of it.
5. **Permissions** — `public_html/app/storage/` must be writable by PHP (755 is enough on
   Hostinger; it creates `site.db` there on first use).
6. Open https://truckingdirectoryusa.com/carriers/ — you should see the state table with
   live counts. If you see "carriers.db not found", check step 2's path.

## 3. Configure (site/app/config.php)

| Setting | What to put |
| --- | --- |
| `ga_id` | A **new** GA4 property for truckingdirectoryusa.com (the live site currently has `G-PLACEHOLDER`) |
| `notify_email` / `from_email` | Where claim/lead emails go; `from_email` should be a mailbox on this domain (create `noreply@truckingdirectoryusa.com` in hPanel → Emails) so Hostinger's mail() passes SPF |
| `stripe.*` | Four Payment Links: carrier Verified ($49/yr) and Featured ($149/yr), vendor Listed ($49/yr) and Featured ($149/yr). In each link's **After payment** setting choose "Don't show confirmation page" → redirect to `https://truckingdirectoryusa.com/claim/thanks?session_id={CHECKOUT_SESSION_ID}` |
| `stripe.webhook_secret` | Stripe → Developers → Webhooks → Add endpoint `https://truckingdirectoryusa.com/stripe-webhook`, event `checkout.session.completed`; paste the signing secret. This is what flips a claim to **paid** automatically. Without it, mark payments paid by hand in /admin |
| `admin_user` / `admin_pass` | Change both. /admin refuses to open until the default password is changed |
| `cdusa.*` | CarrierDataUSA links: home, per-state pattern or explicit per-state URLs, and the package table shown on /carrier-data/ |
| `sitemap_max_tier` | Start at 1 (active carriers with a phone and 1+ power unit). Raise to 2 after the first batch is indexed |
| `takeover_vendor_pages` | `true` = the engine serves the 16 category pages with real listings; the old placeholder pages are gone the moment .htaccess is uploaded |

Keep local overrides in `app/config.local.php` (same array shape) so updates never
overwrite your settings.

## 4. Point Google at it

1. robots.txt — add a line: `Sitemap: https://truckingdirectoryusa.com/sitemap-carriers.xml`
2. Google Search Console → Sitemaps → submit `sitemap-carriers.xml` (it is an index that
   lists the hub sitemap, one city sitemap per state, and 50,000-URL carrier chunks).
3. Add a **Carriers** link to the nav of your static pages (index.html, about, blog) so
   the hub is one click from the homepage — the engine's own pages already have it. The
   homepage hero stats can point at `/carriers/` too.

## 5. URLs the engine serves

```
/carriers/                              all states with counts + search
/carriers/georgia/                      state hub: cities, cargo types, fleet sizes, largest fleets
/carriers/georgia/cities/               every city in the state
/carriers/georgia/marietta/             carriers in a city (50 per page, ?page=2)
/carriers/georgia/cargo/livestock/      carriers in a state hauling a cargo type
/carrier/1234567/abc-trucking-llc       carrier profile (any wrong slug 301s to the right one)
/carriers/search?q=abc                  name prefix or USDOT search (noindex)
/claim/1234567                          claim form -> Stripe -> /claim/thanks
/quote/factoring?state=GA&dot=1234567   carrier lead form (factoring, insurance, dispatch, fuel-cards, eld, financing, compliance, equipment, repair)
/get-listed  /advertise                 vendor listing + sponsor forms -> Stripe
/carrier-data/                          CarrierDataUSA packages page
/factoring_companies/georgia.html       vendor category x state (same URLs as before, real listings now)
/sitemap-carriers.xml                   sitemap index
/admin                                  claims, vendor listings, leads; CSV export
/stripe-webhook                         Stripe endpoint
```

## 6. Refreshing the data

Each time FMCSA releases a new census: rebuild `carriers.db` with the script, upload it
over the old file (upload as `carriers.new.db` then rename, so the site never sees a
half-written file). Claims, vendors and leads live in `app/storage/site.db` and are not
touched by a data refresh.

## 7. Local preview (optional)

```
python tools/make_sample_db.py --out sample_master.db
python tools/build_carriers_db.py --source sample_master.db --out site/app/data/carriers.db
cd site && php -S 127.0.0.1:8080 ../tools/dev_router.php
```
Open http://127.0.0.1:8080/carriers/. Needs PHP 8.1+ with pdo_sqlite installed locally.
