# Trucking Directory USA — Carrier Engine

Turns your FMCSA master database into one profile page per USDOT number on
truckingdirectoryusa.com (Hostinger Business plan), plus state / city / cargo hub
pages, sitemaps, a claim-your-listing flow, vendor listings, lead capture and a
CarrierDataUSA funnel on every page. Five PHP files serve 1.4M URLs, so the
600K-inode limit on the plan is never an issue.

```
tools/build_carriers_db.py   -> run on your PC: master .db  ->  lean carriers.db (public fields only)
tools/suppress.txt           -> removal requests: USDOT numbers left out of carriers.db at build time
tools/make_sample_db.py      -> dev only: fake data for local testing
tools/dev_router.php         -> local preview with PHP's built-in server (no Apache needed)
tools/smoke.sh               -> starts the local server, requests the key routes, prints status codes
site/.htaccess               -> clean URLs, canonical 301s + hardening (upload to public_html/)
site/app/                    -> the engine (upload to public_html/app/)
site/app/config.php          -> everything you need to edit is here (override in config.local.php)
```

## 1. Build carriers.db on your PC

Requires Python 3.9 or newer (no extra packages). The scripts are kept free of 3.10+ syntax
on purpose, so the stock Python on an older Windows PC works.

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

Options: `--table NAME` to pick a table (by default the script takes the table whose name
carries the newest `YYYYMMDD`, e.g. `Company_Census_File_20260330`, and falls back to the
table with the most rows only when no table name has a date; it prints which rule it used),
`--where "STATUS_CODE='A'"` or `--active-only` to drop inactive carriers, `--include-inactive`
to keep them (this is the default: inactive carriers are stored with index tier 3 = noindex and
are reachable only by direct URL and search), `--suppress PATH` to leave out carriers that asked
to be removed (see below), `--snapshot 2026-03-30` to label the data date.
Expect roughly 1 GB for 1.4M carriers and a few minutes of run time.

**Removal requests (`--suppress`).** `tools/suppress.txt` holds one USDOT number per line
(`#` comments allowed). When the file exists it is used automatically; `--suppress PATH` points
at a different file. Listed carriers are left out of `carriers.db` entirely, the summary line
prints how many were suppressed, and `meta.suppressed` records the count. To honour a removal:
add the USDOT number to `tools/suppress.txt`, rebuild, upload (section 6).

**What the trimmer understands about the real census file.** `CLASSDEF` is one text column
with comma-separated phrases (`Auth. For Hire, Private(Property)`); each phrase is matched
exactly, and unknown phrases are printed once to stderr instead of being guessed.
`INTERSTATE_*/INTRASTATE_*_100_MILES` are driver counts; an operating-range flag is set only
when the count is above 0. `DOCKET1PREFIX` is stored in the `mc_prefix` column
(`MC`, `MX` or `FF`; blank with a docket number means `MC`), so freight forwarders show as
`FF-123456` instead of `MC-123456`. Rows whose `PHY_COUNTRY` is not US are dropped along with
rows outside the 50 states + DC (the summary line counts both). `SAFETY_RATING` accepts the
letters S/C/U/N and the words Satisfactory / Conditional / Unsatisfactory / Unrated / Not Rated.
Masters whose numeric columns came through Excel as REAL (`7705551212.0`, `30060.0`) are
normalized (ZIPs are zero-padded back to 5 digits); `--inspect` prints the storage class of the
key columns so you can see whether that applies to your file.

What is NOT copied: EMAIL_ADDRESS, CELL_PHONE, COMPANY_OFFICER_1/2, FAX, DUNS — only
yes/no flags, so the pages can say "email and officer name on file" and send people to
CarrierDataUSA. The web server never holds the data you sell.

**carriers.db schema (version 3).** The engine needs a database built by the current script:
`carriers.mc_prefix`, `stats_city.n_active`, `stats_cargo.n_active` and `stats_state.n_active`
are read on every page. An older carriers.db (schema version 1 or 2) must be rebuilt before uploading
the new `site/app/`. Other build details: `idx_carriers_slug` is no longer created, the build
runs `ANALYZE` before `VACUUM` (with `temp_store = FILE`, so a 1 GB file does not need 1 GB of
RAM), `pc_flag` is derived from `CRGO_PASSENGERS` or `BUS_UNITS > 0` when the master has no
`PC_FLAG` column, and `oos_date` stays empty unless the master has an out-of-service column.

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
   exists, keep a copy and merge its custom rules (redirects, ErrorDocument, caching) into the new file
   rather than pasting the new rules over it: the new file must stay in one piece, guards first.
5. **Permissions** — `public_html/app/storage/` must be writable by PHP (755 is enough on
   Hostinger; it creates `site.db` there on first use).
6. **Secrets** — the engine writes `public_html/app/storage/form.key` (random, 0600) on first use
   to sign the public form tokens. Keep that folder writable; or set `form_secret` in
   `config.local.php` instead. The webhook, admin and legal pages work before `carriers.db`
   is uploaded; carrier pages, /carrier-data/ and the 16 vendor category pages show a 503 "temporarily
   unavailable" page (noindex, never cached) until it is, so upload `carriers.db` before the app.
7. **.htaccess also hardens the whole site**: it forces HTTPS and the apex host (www → apex),
   blocks `/app/`, `*.log|bak|sql|py|md|txt|key` and `error_log` files (robots.txt, ads.txt, app-ads.txt,
   llms.txt, security.txt and humans.txt stay allowed) and any `public_html-upload*.zip` left behind,
   adds HSTS and a Content-Security-Policy. The CSP allows inline scripts/styles, Google Tag Manager /
   Analytics and Google Fonts. If a static page (index.html, blog) loads a script, font or style from
   another host, add that host to the `Content-Security-Policy` line or the browser blocks it. Check the
   browser console after uploading.
8. Open https://truckingdirectoryusa.com/carriers/ — you should see the state table with
   live counts. If you see the "temporarily unavailable" page, check step 2's path.

## 3. Configure (site/app/config.php)

Keep all of these in `app/config.local.php` (same array shape, gitignored) so updates never
overwrite your settings. Note: list-shaped keys such as `db_paths` are replaced by index, so give
both entries when you override that one.

| Setting | What to put |
| --- | --- |
| `ga_id` | A **new** GA4 property for truckingdirectoryusa.com (the live site currently has `G-PLACEHOLDER`) |
| `notify_email` | Private address that receives new claims, listings, leads, opt-out requests and payment events. Never shown on the site. |
| `contact_email` | Public support address shown in the footer, on forms, receipts and /terms, /privacy. Create `support@truckingdirectoryusa.com` (or an alias) in hPanel → Emails. |
| `from_email` | Sender for all mail; a mailbox on this domain (create `noreply@truckingdirectoryusa.com` in hPanel → Emails) so Hostinger's mail() passes SPF. Reply-To is `contact_email`. |
| `operator_name` / `operator_address` | Legal operator ("Barrgo LLC") and postal address. Shown in the footer, on /terms and /privacy and in buyer receipts. **Replace the `[street address, Marietta, GA ZIP]` placeholder before launch** with the street address only (e.g. `123 Main St, Marietta, GA 30060`): the operator name is printed in front of it. |
| `refund_policy` | One-sentence refund policy shown on the paid forms, /claim/thanks, /terms and in the buyer receipt. The shipped text ends in `[confirm]`: confirm or edit it. |
| `terms_text` / `privacy_text` | Optional. Plain text (paragraphs separated by a blank line) that replaces the built-in /terms or /privacy prose. Leave `''` to use the defaults, which are built from the settings above. |
| `form_secret` | Leave `''` to auto-create `app/storage/form.key`; or paste the output of `php -r 'echo bin2hex(random_bytes(32));'`. Never reuse the admin password. |
| `admin_user` / `admin_pass_hash` | Preferred login: `admin_pass_hash` = output of `php -r 'echo password_hash("your long password", PASSWORD_DEFAULT);'`. A plain `admin_pass` still works when the hash is `''`, but use the hash. /admin stays locked (401, message is generic, reason in the PHP error log) while the default password is in place. After 10 failed logins from one IP in 15 minutes /admin answers 429 for 15 minutes. |
| `behind_cloudflare` | `false` unless every request provably arrives through Cloudflare; only then is the `CF-Connecting-IP` header trusted for rate limiting and the stored IP evidence. |
| `stripe.claim_verified` … `stripe.vendor_featured` | **Create four NEW Payment Links for this flow** (carrier Verified $49, carrier Featured $149, vendor Listed $49, vendor Featured $149) and **deactivate the old bare $49 link in Stripe**, so nobody can pay without an intake form. Give each link a **one-time** price, not a recurring (yearly) one: a listing runs one year from activation and is renewed by a new order (see /terms). A recurring link would keep billing the buyer every period while the listing still expires one year after activation, and its sessions carry no payment intent, so refunds and disputes could not be matched. The webhook parks a correctly priced payment through such a link in `review` (the buyer still gets the order receipt), and every notification about a recurring-link payment (unmatched, wrong amount, already-paid order) tells you to cancel the subscription. A plan whose link is `''` or still `REPLACE_ME` refuses orders with "Online payment for this plan is not available yet" and emails `notify_email`. In each link set After payment → "Don't show confirmation page" → redirect to `https://truckingdirectoryusa.com/claim/thanks?session_id={CHECKOUT_SESSION_ID}`. |
| `stripe.webhook_secret` | Stripe → Developers → Webhooks → Add endpoint `https://truckingdirectoryusa.com/stripe-webhook`. Subscribe to **checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, charge.refunded, charge.dispute.created**. Paste the signing secret. This is what flips a claim to **paid** automatically; without it, mark payments paid by hand in /admin. |
| `stripe.secret_key` | A **restricted** API key with read access to Checkout Sessions. /claim/thanks uses it to confirm `payment_status = paid` before saying "Payment received". Leave `''` and the page stays neutral ("if your payment completed you will receive a confirmation email"). |
| `prices.*` | Whole-dollar price per plan; the webhook compares Stripe's `amount_total` against these and parks mismatches in status `review`. Keep them equal to the Payment Link prices. |
| `cdusa.*` | CarrierDataUSA links: home, per-state pattern or explicit per-state URLs, and the package table shown on /carrier-data/ |
| `sitemap_max_tier` | Start at 1: tier-1 carriers (active, phone on file, 1+ power unit) are indexable and in the sitemaps; tier-2 carrier pages (active but missing phone or fleet size) carry `noindex,follow` until you raise this to 2, so robots rules and sitemaps always agree. Tier 3 (inactive) is always noindex. |
| `min_city_carriers` | Default 3. City pages with fewer **active** carriers are `noindex,follow` and left out of the city sitemaps (they still render and link their carriers). |
| `takeover_vendor_pages` | `true` = the engine serves the 16 category pages with real listings; the old placeholder pages are gone the moment .htaccess is uploaded |
| `per_page` / `cache_seconds` | Cards per page on city and cargo lists (50); browser/CDN cache time for public GET/HEAD pages (3600 s, 0 disables) |

## 4. Point Google at it

1. robots.txt — add `Sitemap: https://truckingdirectoryusa.com/sitemap-carriers.xml`, and keep the
   crawl budget on real pages with
   `Disallow: /quote/`, `Disallow: /claim/`, `Disallow: /carriers/search`, `Disallow: /admin`,
   `Disallow: /stripe-webhook` (these pages are noindex and every link to them carries `rel="nofollow"`).
2. Google Search Console → Sitemaps → submit `sitemap-carriers.xml` (it is an index that
   lists the hub sitemap, one city sitemap per state, and 50,000-URL carrier chunks). The hub
   sitemap contains only category × state vendor pages that have an active vendor and only cargo
   pages with active carriers; city sitemaps contain only cities at or above `min_city_carriers`;
   carrier chunks contain tiers up to `sitemap_max_tier`. `lastmod` is the data snapshot date,
   except the hub sitemap, which moves to the date of the latest vendor listing change.
3. Add a **Carriers** link to the nav of your static pages (index.html, about, blog) so
   the hub is one click from the homepage — the engine's own pages already have it. The
   homepage hero stats can point at `/carriers/` too.

## 5. URLs the engine serves

```
/carriers/                              all states with counts + search
/carriers/georgia/                      state hub: cities, cargo types, fleet sizes, largest fleets
/carriers/georgia/cities/               every city in the state with active carriers
/carriers/georgia/marietta/             active carriers in a city (50 per page, ?page=2)
/carriers/georgia/cargo/livestock/      active carriers in a state hauling a cargo type
/carrier/1234567/abc-trucking-llc       carrier profile (wrong slug, leading zeros or trailing slash 301 to this)
/carriers/search?q=abc                  name prefix or USDOT search (noindex)
/claim/1234567                          claim form -> Stripe -> /claim/thanks
/quote/factoring?state=GA&dot=1234567   carrier lead form (factoring, insurance, dispatch, fuel-cards, eld, financing, compliance, equipment, repair)
/get-listed  /advertise                 vendor listing + sponsor forms -> Stripe
/carrier-data/                          CarrierDataUSA packages page
/factoring_companies/                   vendor category index (also reached from /factoring_companies, .html, /index.html, /index.php via 301)
/factoring_companies/georgia.html       vendor category x state (same URLs as before, real listings now; noindex until a vendor is active there)
/opt-out                                carriers: remove contact details / correct / remove profile (stored as a lead of type suppression)
/terms  /privacy                        terms of service and privacy policy (noindex, prose from config or defaults)
/sitemap-carriers.xml                   sitemap index (unknown sitemap parts answer 404, never an empty cached file)
/admin                                  claims, vendor listings, leads; CSV export
/stripe-webhook                         Stripe endpoint
```

Canonical URL rules enforced by .htaccess and the front controller (all 301): https and the apex
host; hub pages end with a slash (`/carriers/georgia` → `/carriers/georgia/`); profiles never do
(`/carrier/123/slug/` → `/carrier/123/slug`); upper-case letters in a path go to the lower-case URL;
`?page=` past the last page goes to the last page. Query strings that try to inject route parameters
(`?r=`, `?state=` on a hub page, …) answer 404.

## 6. Refreshing the data

Each time FMCSA releases a new census: rebuild `carriers.db` with the script, upload it
over the old file (upload as `carriers.new.db` then rename, so the site never sees a
half-written file). Claims, vendors and leads live in `app/storage/site.db` and are not
touched by a data refresh.

Keep `tools/suppress.txt` in sync with the removal requests you have received before each
rebuild; the suppression list is applied at build time, so a refresh without it would put a
removed carrier back on the site. After the rebuild the summary line shows
`N suppressed`; check that it is not 0 when you expect removals.

## 7. Local preview and smoke test (optional)

```
python tools/make_sample_db.py --out sample_master.db            # add --typed to mimic an Excel-typed master (REAL columns)
python tools/build_carriers_db.py --source sample_master.db --out site/app/data/carriers.db
cd site && php -S 127.0.0.1:8080 ../tools/dev_router.php
```
Open http://127.0.0.1:8080/carriers/. Needs PHP 8.1+ with pdo_sqlite installed locally.
`tools/dev_router.php` mirrors .htaccess (routes, canonical 301s, query guards and the denies for
`/app/`, databases, keys and config files), so what you see locally is what LiteSpeed serves.

`tools/smoke.sh [port]` (default 8090) starts that server, requests the key routes (hubs, a
profile and its 301 variants, search with wildcards, sitemaps including a missing part, vendor
pages and their old URLs, forms, /admin, the webhook and the hardening paths), prints
`<got> <expected> <path>` per line, fails on any mismatch or PHP error, and kills the server.
Run it after every change and before uploading.

The sample master has the real census shapes: one `CLASSDEF` text column, driver counts in
the `INTERSTATE_*/INTRASTATE_*` columns, `DOCKET1PREFIX` MC/FF/MX/blank, `PHY_COUNTRY` with a
few CA/MX rows, a few duplicate USDOT numbers, DD-MON-YY dates, 10-digit phone strings with
some stored as `7700151212.0`, and `HM_Ind` / `BUS_UNITS` / `EMAIL_ADDRESS` / `CELL_PHONE` /
`COMPANY_OFFICER_1/2` columns. Phone exchanges are `01x` (never assignable), so the numbers are
obviously fake but still pass the trimmer's phone filter. Never upload the sample output.

## 8. Payments, statuses and what shows publicly

Statuses on claims and vendor listings:

| Status | Meaning |
| --- | --- |
| `pending` | Form submitted, not paid. |
| `paid` | Stripe confirmed the payment (webhook). **Awaiting your review.** Not public. |
| `review` | Stripe paid a different amount than the plan costs (e.g. the $49 link used for a $149 plan), or the payment came through a recurring (subscription) Payment Link. Check in Stripe before activating; for a subscription, cancel it at period end first. Not public. |
| `active` | Live on the site. **Only `active` rows show publicly** (Verified badge, website link, vendor cards, sponsor slots, featured pinning). |
| `rejected` | Declined by you, or the charge was refunded (set automatically on `charge.refunded`). Not public. |
| `disputed` | A chargeback was opened (set automatically on `charge.dispute.created`). Not public. |

Flow: buyer fills the intake form → row `pending` → Stripe Payment Link → webhook verifies the
signature, `payment_status = paid` and the amount for the plan → row `paid`, owner notified, buyer
receives "Your Trucking Directory USA order #N" with the plan, amount, company, review promise, the
refund policy and the support address → you open /admin, check the claimant against the carrier's
FMCSA phone / website domain, set `active`. Replays of the same Stripe session are ignored; a payment
for an id that does not exist is logged once as a `stripe-unmatched` lead (its session id is stored in
the lead's `source_url` column).

What the plans deliver (keep the copy and the code in sync):

* Verified carrier listing: Verified badge, website link, email button (if the claimant ticked the box),
  corrected details, and **no competitor offers on the profile** (any active claim hides the sponsor block).
* Featured carrier listing: everything in Verified plus top placement on page 1 of the carrier's city page
  and of every cargo × state page it belongs to (pinned first, with a "Featured" badge).
* Standard vendor listing: the category pages for every state served.
* Featured vendor sponsorship: top of those category pages plus rotation in the sponsored slots on
  carrier profiles and state/city/cargo pages in the states served. Up to 3 (4 on state pages) active
  featured vendors rotate per page; the static `sponsors.php` entries show only when no featured vendor
  serves that state. No logo upload exists; do not promise one.

## 9. Opt-out requests and the suppression list

Carriers ask for removal or correction at `/opt-out` (linked from every profile and the footer). Each
request is stored as a lead of type `suppression` (USDOT, name, email, reason) and emailed to
`notify_email`. To process a removal:

1. Add the USDOT number (one per line, `#` comments allowed) to `tools/suppress.txt`.
2. Rebuild `carriers.db` (the trimmer reads `tools/suppress.txt` by default, or pass `--suppress PATH`)
   and upload it as in section 6.
3. Remove the carrier from the CarrierDataUSA lists and reply to the requester.

The public promise is "within 10 business days and before the next data refresh".

## 10. Security notes

* The public form token is an hourly HMAC (`form|<hour>`, signed with `form_secret`), accepted for the
  current and previous hour, plus a honeypot. Forms are limited to 5 submissions per IP per hour and
  20 per day (HTTP 429 beyond that; nothing is stored or mailed).
* /admin uses its own per-login session token (cookie `tdusa_admin`, HttpOnly, SameSite=Strict, Secure on
  HTTPS) and rejects POSTs whose Origin/Referer host is not `site_url` or whose `Sec-Fetch-Site` is not
  `same-origin`. Because of the Origin check, `site_url` must match the host you log in at.
* Claims, vendors and leads store the IP and user agent (truncated) as dispute evidence; lead rows also
  store `consent_text_version` (`2026-10`) and `consent_at`; paid rows store `terms_accepted_at`.
* The admin CSV export is for fulfilment and support only; its contacts are never merged into data
  products (and /privacy says so).
* `carriers.db` is opened read-only at the driver level. When it is missing, routes that need it return a
  503 page (noindex, no-store); /admin, /stripe-webhook, /get-listed, /advertise, /quote, /opt-out,
  /terms and /privacy keep working.
* Search escapes `%` and `_`, needs at least two letters or digits, and runs the "contains" fallback only
  for queries of six or more characters (limit 20), so no request can force a scan of the carriers table.

## 11. What changed in the review pass

Behaviour an operator must know after this update:

* **Only `active` rows show publicly.** A paid claim or vendor listing is invisible until you set it to
  `active` in /admin. New statuses `review` (amount mismatch, or a payment through a recurring link) and
  `disputed` (chargeback) exist; both are private.
* **Listings expire after one year.** Setting a row to `active` stamps `activated_at`; every public query
  ignores rows activated more than 365 days ago (falling back to `paid_at`, then `created_at`). A renewal is
  a new order, which creates a new row with its own activation date. /terms says the same.
* **New config keys**: `contact_email`, `operator_name`, `operator_address`, `refund_policy`,
  `terms_text`, `privacy_text`, `form_secret`, `behind_cloudflare`, `admin_pass_hash`, `stripe.secret_key`,
  `min_city_carriers`. Fill in the two placeholders (`operator_address`, the `[confirm]` in `refund_policy`)
  and switch to `admin_pass_hash` before launch.
* **Stripe**: subscribe the webhook to `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
  `checkout.session.async_payment_failed`, `charge.refunded` and `charge.dispute.created`; create new Payment
  Links per plan with **one-time** prices and deactivate the old bare $49 link. A plan whose link is still
  `REPLACE_ME` refuses orders (`vendor_listed` no longer defaults to the old link). A correctly priced payment through a
  recurring (subscription) link is parked in `review`; every notification about a recurring-link payment says to
  cancel the subscription. A payment for an order that is no longer `pending` (paid twice, or set by hand) now
  emails you instead of only writing to the error log.
  Buyers now receive a confirmation email; /claim/thanks says "Payment received" only after verifying the
  session with `stripe.secret_key`.
* **Suppression list**: `tools/suppress.txt` is applied at every rebuild; /opt-out requests arrive as
  `suppression` leads.
* **Tier gating**: tier-2 carrier pages are `noindex,follow` until `sitemap_max_tier` is raised to 2;
  tier-3 (inactive) carriers are always noindex and are no longer listed on city, cargo or state pages
  (reachable by direct URL, search and the 301 from a wrong slug). Counts in titles and copy are
  **active** carriers; the total registered is shown next to them.
* **noindex rules**: city pages below `min_city_carriers` active carriers; category × state vendor pages
  with no active vendor; /claim, /quote, /carriers/search, /opt-out, /terms, /privacy; error pages. These
  pages stay crawlable (200) and still link onward. Sitemaps list only indexable pages, drop `changefreq`,
  and answer 404 (no-store) for unknown parts.
* **Canonical URLs**: 301s for missing/extra trailing slashes, old vendor URLs (`/shippers/index.php`),
  upper-case paths, leading zeros or a wrong slug in a profile URL and `?page=` past the end. Route rules
  keep the query string only where a page reads it; injected route parameters answer 404.
* **Docket numbers** render with their real prefix (`MC-`, `MX-`, `FF-`) from the new `mc_prefix` column;
  carriers.db schema version 3 is required (rebuild).
* **Cargo pages page over listable carriers only.** `carrier_cargo` gained a `listed` flag (1 when the
  carrier's index tier is 1 or 2) and the covering index includes it; `stats_cargo.n_active` counts those
  rows, so every page is full and the pager's last page is exact.
* **Profiles**: the repeated FAQ block is gone; every profile carries the FMCSA source sentence with
  claim and opt-out links; profiles without a city no longer show broken city links or empty breadcrumbs.
* **Forms and mail**: hourly HMAC form tokens; two-stage per-IP limits (30/hour and 100/day attempts of any
  kind, plus 5/hour and 20/day accepted submissions, so a mistyped form or a bot on the same network does
  not lock a real person out; HTTP 429); the missing-Payment-Link notice reaches you at most once an hour per
  plan and only for otherwise valid orders; consent text and
  version stored with leads, terms acceptance stored with paid rows, all mail through `send_mail()` with
  `contact_email` as Reply-To.
* **Claim copy**: Verified and Featured no longer promise "corrected details". Profile data comes from the
  carrier's FMCSA registration; the claim, opt-out and privacy pages tell carriers to file an updated
  MCS-150 with FMCSA, and the profile follows at the next data refresh.
* **Hardening**: forced HTTPS and apex host, HSTS, CSP, `/app/` blocked (the guards test `THE_REQUEST`, so
  Apache's second pass after the internal rewrite is not caught by them; canonical 301 targets are absolute
  https URLs), logs/dumps/keys denied, output
  buffering so a failing page is a clean 500 (no-store) instead of a cached fragment, HEAD requests cached
  like GET, `tools/smoke.sh` for a quick regression run.
