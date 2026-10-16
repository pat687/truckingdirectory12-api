#!/usr/bin/env bash
# Smoke test for the carrier engine (used locally and by CI later).
# Starts PHP's built-in server on 127.0.0.1:8090 with tools/dev_router.php, requests the key routes,
# prints "<got> <expected> <path>" for every request and exits 1 when any status code (or a
# required response header / body marker) does not match. The server is killed on exit.
#
#   tools/smoke.sh [port]        default port 8090
#
# Needs: php (pdo_sqlite), curl, and a built site/app/data/carriers.db
# (python3 -I tools/make_sample_db.py --out sample_master.db &&
#  python3 -I tools/build_carriers_db.py --source sample_master.db --out site/app/data/carriers.db).
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${1:-8090}"
BASE="http://127.0.0.1:$PORT"
DB="$ROOT/site/app/data/carriers.db"
TMP="$(mktemp -d)"
trap 'if [ -n "${PID:-}" ]; then kill "$PID" 2>/dev/null; wait "$PID" 2>/dev/null; fi; rm -rf "$TMP"' EXIT

if [ ! -f "$DB" ]; then
  echo "missing $DB - build it first (see the header of this script)" >&2
  exit 2
fi

# Sample rows from the database: a tier-1 carrier, a tier-2 carrier, a cargo slug, an FF docket (if any).
read -r DOT SLUG STATE CITY CARGO T2DOT T2SLUG FFDOT FFSLUG <<<"$(php -r '
$db = new PDO("sqlite:" . $argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$c = $db->query("SELECT c.dot, c.slug, s.state_slug, c.city_slug, c.phy_state FROM carriers c JOIN stats_state s ON s.state = c.phy_state WHERE c.index_tier = 1 AND c.city_slug IS NOT NULL ORDER BY c.dot LIMIT 1")->fetch(PDO::FETCH_NUM) ?: ["0","-","-","-","-"];
$cg = $db->query("SELECT ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.state = " . $db->quote($c[4]) . " AND sc.n_active > 0 ORDER BY sc.n_active DESC LIMIT 1")->fetchColumn() ?: "-";
$t2 = $db->query("SELECT dot, slug FROM carriers WHERE index_tier = 2 ORDER BY dot LIMIT 1")->fetch(PDO::FETCH_NUM) ?: ["0","-"];
$ff = $db->query("SELECT dot, slug FROM carriers WHERE mc_prefix = \"FF\" AND mc_number IS NOT NULL ORDER BY dot LIMIT 1")->fetch(PDO::FETCH_NUM) ?: ["0","-"];
echo implode(" ", [$c[0], $c[1], $c[2], $c[3], $cg, $t2[0], $t2[1], $ff[0], $ff[1]]);
' "$DB")"

cd "$ROOT/site" || exit 2
php -S "127.0.0.1:$PORT" ../tools/dev_router.php >"$TMP/server.log" 2>&1 &
PID=$!
for _ in $(seq 1 50); do
  curl -s -o /dev/null "$BASE/terms" 2>/dev/null && break
  sleep 0.1
done

FAIL=0
# check <expected status> <path> [header-or-body regex that must match] [curl options...]
check() {
  local want="$1" path="$2" pat="${3:-}"
  shift 3 2>/dev/null || shift $#
  local got
  got=$(curl -s -o "$TMP/body" -D "$TMP/hdr" -w '%{http_code}' "$@" "$BASE$path")
  local ok=1
  [ "$got" = "$want" ] || ok=0
  if [ -n "$pat" ] && ! cat "$TMP/hdr" "$TMP/body" | grep -Eqi -- "$pat"; then ok=0; fi
  if [ $ok = 1 ]; then
    printf '%s %s %s\n' "$got" "$want" "$path"
  else
    printf '%s %s %s   <-- FAIL%s\n' "$got" "$want" "$path" "${pat:+ (expected /$pat/)}"
    FAIL=1
  fi
}

echo "== hubs and listings"
check 200 /carriers/
check 301 /carriers/georgia 'Location: .*/carriers/georgia/'
check 200 /carriers/georgia/
check 200 /carriers/georgia/cities/
check 200 "/carriers/$STATE/$CITY/"
check 301 "/carriers/$STATE/$CITY" 'Location: .*/carriers/'
check 301 "/carriers/$STATE/$CITY/?page=99999" 'Location: .*/carriers/'
check 200 "/carriers/$STATE/cargo/$CARGO/"
check 301 "/Carriers/Georgia/" 'Location: .*/carriers/georgia/'
check 404 /carriers/nowhere-land/
echo "== carrier profiles"
check 200 "/carrier/$DOT/$SLUG" 'USDOT'
check 301 "/carrier/$DOT/$SLUG/" "Location: .*/carrier/$DOT/$SLUG"
check 301 "/carrier/$DOT/wrong-slug" "Location: .*/carrier/$DOT/$SLUG"
check 301 "/carrier/0$DOT/$SLUG" "Location: .*/carrier/$DOT/$SLUG"
check 404 /carrier/1
if [ "$T2DOT" != "0" ]; then check 200 "/carrier/$T2DOT/$T2SLUG" 'name="robots" content="noindex,follow"'; fi
if [ "$FFDOT" != "0" ]; then check 200 "/carrier/$FFDOT/$FFSLUG" 'FF-[0-9]+'; fi
echo "== search"
check 200 '/carriers/search?q=%25' 'at least two letters'
check 200 '/carriers/search?q=_' 'at least two letters'
check 200 '/carriers/search?q=sample'
check 200 '/carriers/search?q=zzzzzzzz&state=GA' 'No carriers matched'
echo "== sitemaps"
check 200 /sitemap-carriers.xml '<sitemapindex'
check 200 /sitemap-carriers-hubs.xml '<urlset'
check 200 "/sitemap-carriers-cities-$STATE.xml" '<urlset'
check 200 /sitemap-carriers-0.xml '<urlset'
check 404 /sitemap-carriers-nope.xml 'Cache-Control: no-store'
check 404 /sitemap-carriers-99999.xml 'Cache-Control: no-store'
echo "== vendor pages"
check 200 /factoring_companies/
check 200 /factoring_companies/georgia.html 'name="robots" content="(no)?index,follow"'
check 301 /factoring_companies 'Location: .*/factoring_companies/'
check 301 /factoring_companies.html 'Location: .*/factoring_companies/'
check 301 /factoring_companies/index.html 'Location: .*/factoring_companies/'
check 301 /shippers/index.php 'Location: .*/shippers/'
echo "== forms, legal, admin, webhook"
check 200 /get-listed
check 200 /advertise
check 200 '/quote/factoring?state=GA'
check 200 "/claim/$DOT" 'name="terms"'
check 200 /carrier-data/
check 301 /carrier-data 'Location: .*/carrier-data/'
check 200 /terms
check 200 /privacy
check 200 /opt-out
check 401 /admin
check 400 /stripe-webhook '' -X POST -d '{}'
echo "== hardening"
check 404 /app/index.php
check 404 '/app/index.php?r=admin'
check 404 /app/config.php
check 404 /app/data/carriers.db
check 403 /carriers.db
check 403 /app.key
check 404 '/carriers/?r=admin'
check 404 '/carriers/georgia/?state=texas'
check 403 /.htaccess
check 404 /nope-not-a-page

if grep -Eq 'PHP (Fatal|Warning|Notice|Deprecated|Parse)' "$TMP/server.log"; then
  echo "== PHP errors in the server log:"
  grep -E 'PHP (Fatal|Warning|Notice|Deprecated|Parse)' "$TMP/server.log"
  FAIL=1
fi
if [ $FAIL = 0 ]; then echo "SMOKE OK"; else echo "SMOKE FAILED"; fi
exit $FAIL
