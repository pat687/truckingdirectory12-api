<?php
/** City listing: /carriers/{state}/{city}/ (paginated with ?page=N) */
declare(strict_types=1);

$abbr = state_by_slug((string)($_GET['state'] ?? ''));
$citySlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['city'] ?? '')));
$cs = ($abbr && $citySlug) ? q1('SELECT * FROM stats_city WHERE state = ? AND city_slug = ?', [$abbr, $citySlug]) : null;
if (!$cs) {
    not_found('No carriers found in that city.');
}
$city = title_case($cs['city']);
$stName = state_name($abbr);
$nAll = (int)$cs['n'];
$nActive = (int)$cs['n_active'];
$minCity = max(0, (int)cfg('min_city_carriers', 3));
$per = (int)cfg('per_page', 50);
$base = city_path($abbr, $citySlug);
// Pagination runs over the ACTIVE carriers only (?page past the end 301s to the last page).
[$offset, $pages, $page] = paginate($nActive, current_page(), $per, $base);
$cols = 'dot, legal_name, slug, phy_city, phy_state, power_units, drivers, cargo, phone, mc_number, mc_prefix, dba_name';
// Only active carriers (index tier 1-2) are listed; inactive ones stay reachable by direct URL and search.
$rows = q("SELECT $cols FROM carriers WHERE phy_state = ? AND city_slug = ? AND index_tier <= 2 ORDER BY power_units DESC, legal_name LIMIT ? OFFSET ?", [$abbr, $citySlug, $per, $offset]);

// Page 1 pins carriers with an ACTIVE featured claim that are based in this city (Featured plan promise).
$pinned = [];
if ($page === 1) {
    $fd = array_slice(featured_claim_dots(), 0, 500);
    if ($fd) {
        $in = implode(',', array_fill(0, count($fd), '?'));
        $pinned = q("SELECT $cols FROM carriers WHERE dot IN ($in) AND phy_state = ? AND city_slug = ? AND index_tier <= 2 ORDER BY power_units DESC, legal_name", array_merge($fd, [$abbr, $citySlug]));
    }
}
if ($pinned) {
    $pinnedDots = array_map(fn($p) => (int)$p['dot'], $pinned);
    $rows = array_values(array_filter($rows, fn($r) => !in_array((int)$r['dot'], $pinnedDots, true)));
    foreach ($pinned as &$p) {
        $p['featured'] = true;
    }
    unset($p);
    $rows = array_merge($pinned, $rows);
}

$crumbs = [['Carriers', '/carriers/'], [$stName, state_path($abbr)], [$city, null]];
$pageSuffix = $page > 1 ? ' (page ' . $page . ')' : '';

page_start([
    'title' => seo_title('Trucking Companies in ' . $city . ', ' . $abbr, plural($nActive, 'Carrier'), $pageSuffix),
    'description' => plural($nActive, 'active FMCSA-registered trucking company', 'active FMCSA-registered trucking companies') . ' based in ' . $city . ', ' . $stName . ', with USDOT and MC numbers, phone, fleet size, drivers and cargo types.' . $pageSuffix,
    'canonical' => page_url($base, $page),
    // Thin cities (fewer active carriers than min_city_carriers) stay crawlable but are not indexed.
    'robots' => $nActive >= $minCity && $nActive > 0 ? 'index,follow' : 'noindex,follow',
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1>Trucking Companies in <span><?= e($city) ?>, <?= e($stName) ?></span></h1>
<p><?= e(plural($nActive, 'active motor carrier')) ?> list a physical address in <?= e($city) ?>, <?= e($abbr) ?><?= $nAll > $nActive ? ' (' . nf($nAll) . ' registered, including inactive USDOT numbers)' : '' ?>. Sorted by fleet size.</p></div></div>
<div class="container section"><div class="grid2">
<div>
  <?php if ($rows): ?>
  <div class="listings-grid"><?php foreach ($rows as $r) echo carrier_card($r, false); ?></div>
  <?php else: ?>
  <div class="card"><p>No carrier based in <?= e($city) ?>, <?= e($abbr) ?> has an active USDOT number in the current FMCSA snapshot. Inactive registrations can still be found by <a href="/carriers/search" rel="nofollow">USDOT number or name</a>.</p></div>
  <?php endif; ?>
  <?= pager_html($base, $page, $pages) ?>
</div>
<aside>
  <?= data_cta($abbr) ?>
  <?= sponsor_block($abbr) ?>
  <div class="card"><h3>Browse</h3><div class="chips"><a href="<?= e(state_path($abbr)) ?>">All <?= e($stName) ?> carriers</a><a href="<?= e(state_path($abbr)) ?>cities/">Other <?= e($stName) ?> cities</a></div></div>
</aside>
</div></div>
<?php page_end();
