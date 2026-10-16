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
$per = (int)cfg('per_page', 50);
[$offset, $pages, $page] = paginate((int)$cs['n'], current_page(), $per);
$rows = q('SELECT dot, legal_name, slug, phy_city, phy_state, power_units, drivers, cargo, phone, mc_number, dba_name FROM carriers WHERE phy_state = ? AND city_slug = ? ORDER BY power_units DESC, legal_name LIMIT ? OFFSET ?', [$abbr, $citySlug, $per, $offset]);
$base = city_path($abbr, $citySlug);
$crumbs = [['Carriers', '/carriers/'], [$stName, state_path($abbr)], [$city, null]];
$pageSuffix = $page > 1 ? ' (page ' . $page . ')' : '';

page_start([
    'title' => 'Trucking Companies in ' . $city . ', ' . $abbr . $pageSuffix . ' | ' . nf($cs['n']) . ' Carriers | ' . cfg('site_name'),
    'description' => nf($cs['n']) . ' FMCSA-registered trucking companies based in ' . $city . ', ' . $stName . ', with USDOT and MC numbers, phone, fleet size, drivers and cargo types.' . $pageSuffix,
    'canonical' => page_url($base, $page),
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1>Trucking Companies in <span><?= e($city) ?>, <?= e($stName) ?></span></h1>
<p><?= nf($cs['n']) ?> motor carriers list a physical address in <?= e($city) ?>, <?= e($abbr) ?> (<?= nf($cs['n_active']) ?> with an active USDOT). Sorted by fleet size.</p></div></div>
<div class="container section"><div class="grid2">
<div>
  <div class="listings-grid"><?php foreach ($rows as $r) echo carrier_card($r, false); ?></div>
  <?= pager_html($base, $page, $pages) ?>
</div>
<aside>
  <?= data_cta($abbr) ?>
  <?= sponsor_block($abbr) ?>
  <div class="card"><h3>Browse</h3><div class="chips"><a href="<?= e(state_path($abbr)) ?>">All <?= e($stName) ?> carriers</a><a href="<?= e(state_path($abbr)) ?>cities/">Other <?= e($stName) ?> cities</a></div></div>
</aside>
</div></div>
<?php page_end();
