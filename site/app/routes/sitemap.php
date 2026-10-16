<?php
/**
 * Sitemaps:
 *   /sitemap-carriers.xml                 sitemap index
 *   /sitemap-carriers-hubs.xml            /carriers/, state hubs, state city indexes, state x cargo pages
 *   /sitemap-carriers-cities-{state}.xml  every city page in one state
 *   /sitemap-carriers-{n}.xml             carrier profiles, chunk n (50,000 per file)
 */
declare(strict_types=1);

$chunk = max(1000, (int)cfg('sitemap_chunk', 50000));
$maxTier = max(1, min(2, (int)cfg('sitemap_max_tier', 1)));
$snapshot = meta('snapshot_date') ?: date('Y-m-d');
$maxSeq = (int)meta('max_seq', '0');
$chunks = (int)ceil($maxSeq / $chunk);

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
if (!ob_start('ob_gzhandler')) {
    ob_start();
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

$part = (string)($_GET['part'] ?? '');
if ($r === 'sitemap_index') {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<sitemap><loc>' . e(url('/sitemap-carriers-hubs.xml')) . '</loc><lastmod>' . e($snapshot) . '</lastmod></sitemap>' . "\n";
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
$u = function (string $path, string $prio = '0.5', string $freq = 'monthly') use ($snapshot): void {
    echo '<url><loc>' . e(url($path)) . '</loc><lastmod>' . e($snapshot) . '</lastmod><changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>' . "\n";
};

if ($part === 'hubs') {
    $u('/carriers/', '0.9', 'weekly');
    $u('/carrier-data/', '0.7', 'monthly');
    foreach (q('SELECT state, state_slug FROM stats_state ORDER BY state_slug') as $s) {
        $u(state_path($s['state']), '0.8', 'weekly');
        $u(state_path($s['state']) . 'cities/', '0.5', 'monthly');
    }
    foreach (q('SELECT sc.state, ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.n > 0 ORDER BY sc.state, ct.slug') as $c) {
        $u(cargo_path($c['state'], $c['slug']), '0.6', 'monthly');
    }
    foreach (array_keys($GLOBALS['CATEGORIES']) as $cat) {
        if (cfg('takeover_vendor_pages')) {
            $u('/' . $cat . '/', '0.6', 'weekly');
            foreach (US_STATES as $abbr => [$n, $slug]) {
                $u(vendor_state_path($cat, $abbr), '0.5', 'weekly');
            }
        }
    }
} elseif (str_starts_with($part, 'cities-')) {
    $abbr = state_by_slug(substr($part, 7));
    if (!$abbr) {
        http_response_code(404);
    } else {
        foreach (q('SELECT city_slug FROM stats_city WHERE state = ? ORDER BY city_slug', [$abbr]) as $c) {
            $u(city_path($abbr, $c['city_slug']), '0.6', 'monthly');
        }
    }
} elseif (ctype_digit($part)) {
    $n = (int)$part;
    if ($n >= $chunks) {
        http_response_code(404);
    } else {
        $from = $n * $chunk + 1;
        $to = ($n + 1) * $chunk;
        $st = db()->prepare('SELECT dot, slug, index_tier FROM carriers WHERE seq BETWEEN ? AND ? AND index_tier <= ? ORDER BY seq');
        $st->execute([$from, $to, $maxTier]);
        while ($c = $st->fetch()) {
            echo '<url><loc>' . e(url('/carrier/' . (int)$c['dot'] . '/' . $c['slug'])) . '</loc><lastmod>' . e($snapshot) . '</lastmod><priority>' . ((int)$c['index_tier'] === 1 ? '0.5' : '0.3') . '</priority></url>' . "\n";
        }
    }
} else {
    http_response_code(404);
}
echo '</urlset>';
ob_end_flush();
