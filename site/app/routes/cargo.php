<?php
/** State x cargo listing: /carriers/{state}/cargo/{cargo}/ */
declare(strict_types=1);

$abbr = state_by_slug((string)($_GET['state'] ?? ''));
$type = $abbr ? cargo_by_slug(preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['cargo'] ?? '')))) : null;
$sc = ($abbr && $type) ? q1('SELECT n, n_active FROM stats_cargo WHERE state = ? AND cargo_id = ?', [$abbr, (int)$type['id']]) : null;
// n_active counts listable carriers (carrier_cargo.listed = 1): the same rows the page shows, so every
// page is full and the last page is exactly where the pager says it is.
$nActive = $sc ? (int)$sc['n_active'] : 0;
$total = $nActive;
if (!$abbr || !$type || $total === 0) {
    not_found('No carriers hauling that cargo type in that state.');
}
$stName = state_name($abbr);
$per = (int)cfg('per_page', 50);
$base = cargo_path($abbr, $type['slug']);
[$offset, $pages, $page] = paginate($total, current_page(), $per, $base);
$cols = 'c.dot, c.legal_name, c.slug, c.phy_city, c.phy_state, c.power_units, c.drivers, c.cargo, c.phone, c.mc_number, c.mc_prefix, c.dba_name';
// OFFSET runs on the covering index (cargo_id, state, listed, power_units DESC, dot) over listable rows
// only; just the page's rows then touch the wide carriers table.
$rows = q("SELECT $cols FROM (SELECT dot FROM carrier_cargo WHERE cargo_id = ? AND state = ? AND listed = 1 ORDER BY power_units DESC, dot LIMIT ? OFFSET ?) x JOIN carriers c ON c.dot = x.dot ORDER BY c.power_units DESC, c.legal_name", [(int)$type['id'], $abbr, $per, $offset]);

// Page 1 pins carriers with an ACTIVE featured claim that haul this cargo in this state.
$pinned = [];
if ($page === 1) {
    $fd = array_slice(featured_claim_dots(), 0, 500);
    if ($fd) {
        $in = implode(',', array_fill(0, count($fd), '?'));
        $pinned = q("SELECT $cols FROM carriers c WHERE c.dot IN ($in) AND c.phy_state = ? AND c.index_tier <= 2 AND (',' || COALESCE(c.cargo, '') || ',') LIKE ? ORDER BY c.power_units DESC, c.legal_name", array_merge($fd, [$abbr, '%,' . $type['code'] . ',%']));
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

$others = q('SELECT sc.n_active, ct.name, ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.state = ? AND sc.cargo_id <> ? AND sc.n_active > 0 ORDER BY sc.n_active DESC', [$abbr, (int)$type['id']]);
$crumbs = [['Carriers', '/carriers/'], [$stName, state_path($abbr)], [$type['name'], null]];
$pageSuffix = $page > 1 ? ' (page ' . $page . ')' : '';
$lcType = strtolower($type['name']);

page_start([
    'title' => seo_title($type['name'] . ' Carriers in ' . $stName, plural($nActive, 'Trucking Company', 'Trucking Companies'), $pageSuffix),
    'description' => plural($nActive, 'active FMCSA-registered trucking company', 'active FMCSA-registered trucking companies') . ' in ' . $stName . ' report hauling ' . $lcType . '. USDOT, MC, phone, fleet size and drivers for each carrier.' . $pageSuffix,
    'canonical' => page_url($base, $page),
    'robots' => $nActive > 0 ? 'index,follow' : 'noindex,follow',
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1><span><?= e($type['name']) ?></span> Carriers in <?= e($stName) ?></h1>
<p><?= e(plural($nActive, 'active motor carrier')) ?> based in <?= e($stName) ?> told FMCSA they haul <?= e($lcType) ?><?= $total > $nActive ? ' (' . nf($total) . ' registered, including inactive USDOT numbers)' : '' ?>. Sorted by fleet size.</p></div></div>
<div class="container section"><div class="grid2">
<div>
  <?php if ($rows): ?>
  <div class="listings-grid"><?php foreach ($rows as $r) echo carrier_card($r); ?></div>
  <?php else: ?>
  <div class="card"><p>No active carrier on this page; the carriers at these positions have inactive USDOT numbers. Use the pager below or <a href="<?= e(state_path($abbr)) ?>">browse <?= e($stName) ?> by city</a>.</p></div>
  <?php endif; ?>
  <?= pager_html($base, $page, $pages) ?>
</div>
<aside>
  <?= data_cta($abbr) ?>
  <?= sponsor_block($abbr) ?>
  <div class="card"><h3>Other cargo types in <?= e($stName) ?></h3><div class="chips"><?php foreach ($others as $o): ?><a href="<?= e(cargo_path($abbr, $o['slug'])) ?>"><?= e($o['name']) ?><small><?= nf($o['n_active']) ?></small></a><?php endforeach; ?></div></div>
</aside>
</div></div>
<?php page_end();
