<?php
/**
 * Sitemaps:
 *   /sitemap-carriers.xml                 sitemap index
 *   /sitemap-carriers-hubs.xml            /carriers/, state hubs, state city indexes, state x cargo pages,
 *                                         vendor category pages and the category x state pages that have an active vendor
 *   /sitemap-carriers-cities-{state}.xml  city pages in one state with at least min_city_carriers active carriers
 *   /sitemap-carriers-{n}.xml             carrier profiles, chunk n (50,000 per file), tiers <= sitemap_max_tier
 * Unknown parts are a plain 404 (no-store), never a cached empty sitemap.
 */
declare(strict_types=1);

$chunk = max(1000, (int)cfg('sitemap_chunk', 50000));
$maxTier = max(1, min(2, (int)cfg('sitemap_max_tier', 1)));
$minCity = max(1, (int)cfg('min_city_carriers', 3));
$snapshot = meta('snapshot_date') ?: date('Y-m-d');
$maxSeq = (int)meta('max_seq', '0');
$chunks = (int)ceil($maxSeq / $chunk);
$part = strtolower((string)($_GET['part'] ?? ''));

// Decide what is being asked for BEFORE any header goes out.
$kind = null;
$abbr = null;
$n = 0;
if ($r === 'sitemap_index') {
    $kind = 'index';
} elseif ($part === 'hubs') {
    $kind = 'hubs';
} elseif (str_starts_with($part, 'cities-')) {
    $abbr = state_by_slug(substr($part, 7));
    if ($abbr && (int)qv('SELECT COUNT(*) FROM stats_state WHERE state = ?', [$abbr]) > 0) {
        $kind = 'cities';
    }
} elseif (ctype_digit($part) && (string)(int)$part === $part) {
    $n = (int)$part;
    if ($n < $chunks) {
        $kind = 'chunk';
    }
}
if ($kind === null) {
    header('Cache-Control: no-store');
    not_found('No such sitemap.');
}

// Hub and vendor pages change when a vendor listing goes live, so their lastmod follows the latest
// change in site.db when that is newer than the data snapshot. Carrier and city URLs keep the snapshot date.
$hubMod = $snapshot;
try {
    $v = (string)sitedb()->query("SELECT MAX(COALESCE(updated_at, created_at)) FROM vendors WHERE " . LIVE_LISTING_SQL)->fetchColumn();
    $d = substr($v, 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d > $hubMod && $d <= date('Y-m-d')) {
        $hubMod = $d;
    }
} catch (Throwable $e) {
    // site.db unavailable: fall back to the snapshot date
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
if (!ob_start('ob_gzhandler')) {
    ob_start();
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

if ($kind === 'index') {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<sitemap><loc>' . e(url('/sitemap-carriers-hubs.xml')) . '</loc><lastmod>' . e($hubMod) . '</lastmod></sitemap>' . "\n";
    foreach (q('SELECT state_slug FROM stats_state ORDER BY state_slug') as $s) {
        echo '<sitemap><loc>' . e(url('/sitemap-carriers-cities-' . $s['state_slug'] . '.xml')) . '</loc><lastmod>' . e($snapshot) . '</lastmod></sitemap>' . "\n";
    }
    for ($i = 0; $i < $chunks; $i++) {
        echo '<sitemap><loc>' . e(url('/sitemap-carriers-' . $i . '.xml')) . '</loc><lastmod>' . e($snapshot) . '</lastmod></sitemap>' . "\n";
    }
    echo '</sitemapindex>';
    ob_end_flush();
    exit;
}

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$u = function (string $path, string $prio = '0.5', ?string $mod = null) use ($snapshot): void {
    echo '<url><loc>' . e(url($path)) . '</loc><lastmod>' . e($mod ?? $snapshot) . '</lastmod><priority>' . $prio . '</priority></url>' . "\n";
};

if ($kind === 'hubs') {
    $u('/carriers/', '0.9', $hubMod);
    $u('/carrier-data/', '0.7', $hubMod);
    foreach (q('SELECT state, state_slug FROM stats_state ORDER BY state_slug') as $s) {
        $u(state_path($s['state']), '0.8', $hubMod);
        $u(state_path($s['state']) . 'cities/', '0.5', $hubMod);
    }
    foreach (q('SELECT sc.state, ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.n_active > 0 ORDER BY sc.state, ct.slug') as $c) {
        $u(cargo_path($c['state'], $c['slug']), '0.6', $hubMod);
    }
    if (cfg('takeover_vendor_pages')) {
        $withVendors = active_vendor_states();
        foreach (array_keys($GLOBALS['CATEGORIES']) as $cat) {
            $u('/' . $cat . '/', '0.6', $hubMod);
            // Only category x state pages with at least one ACTIVE vendor; the rest are noindex placeholders.
            $states = array_keys($withVendors[$cat] ?? []);
            sort($states);
            foreach ($states as $st) {
                $u(vendor_state_path($cat, $st), '0.5', $hubMod);
            }
        }
    }
} elseif ($kind === 'cities') {
    foreach (q('SELECT city_slug FROM stats_city WHERE state = ? AND n_active >= ? ORDER BY city_slug', [$abbr, $minCity]) as $c) {
        $u(city_path($abbr, $c['city_slug']), '0.6');
    }
} else { // chunk
    $from = $n * $chunk + 1;
    $to = ($n + 1) * $chunk;
    $st = db()->prepare('SELECT dot, slug, index_tier FROM carriers WHERE seq BETWEEN ? AND ? AND index_tier <= ? ORDER BY seq');
    $st->execute([$from, $to, $maxTier]);
    while ($c = $st->fetch()) {
        echo '<url><loc>' . e(url('/carrier/' . (int)$c['dot'] . '/' . $c['slug'])) . '</loc><lastmod>' . e($snapshot) . '</lastmod><priority>' . ((int)$c['index_tier'] === 1 ? '0.5' : '0.3') . '</priority></url>' . "\n";
    }
}
echo '</urlset>';
ob_end_flush();
