<?php
/** State x cargo listing: /carriers/{state}/cargo/{cargo}/ */
declare(strict_types=1);

$abbr = state_by_slug((string)($_GET['state'] ?? ''));
$type = $abbr ? cargo_by_slug(preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['cargo'] ?? '')))) : null;
$total = ($abbr && $type) ? (int)qv('SELECT n FROM stats_cargo WHERE state = ? AND cargo_id = ?', [$abbr, (int)$type['id']]) : 0;
if (!$abbr || !$type || $total === 0) {
    not_found('No carriers hauling that cargo type in that state.');
}
$stName = state_name($abbr);
$per = (int)cfg('per_page', 50);
[$offset, $pages, $page] = paginate($total, current_page(), $per);
$rows = q('SELECT c.dot, c.legal_name, c.slug, c.phy_city, c.phy_state, c.power_units, c.drivers, c.cargo, c.phone, c.mc_number, c.dba_name FROM carrier_cargo cc JOIN carriers c ON c.dot = cc.dot WHERE cc.cargo_id = ? AND cc.state = ? ORDER BY cc.power_units DESC, cc.dot LIMIT ? OFFSET ?', [(int)$type['id'], $abbr, $per, $offset]);
$others = q('SELECT sc.n, ct.name, ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.state = ? AND sc.cargo_id <> ? ORDER BY sc.n DESC', [$abbr, (int)$type['id']]);
$base = cargo_path($abbr, $type['slug']);
$crumbs = [['Carriers', '/carriers/'], [$stName, state_path($abbr)], [$type['name'], null]];
$pageSuffix = $page > 1 ? ' (page ' . $page . ')' : '';

page_start([
    'title' => $type['name'] . ' Carriers in ' . $stName . $pageSuffix . ' | ' . nf($total) . ' Trucking Companies | ' . cfg('site_name'),
    'description' => nf($total) . ' FMCSA-registered trucking companies in ' . $stName . ' report hauling ' . strtolower($type['name']) . '. USDOT, MC, phone, fleet size and drivers for each carrier.' . $pageSuffix,
    'canonical' => page_url($base, $page),
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1><span><?= e($type['name']) ?></span> Carriers in <?= e($stName) ?></h1>
<p><?= nf($total) ?> motor carriers based in <?= e($stName) ?> told FMCSA they haul <?= e(strtolower($type['name'])) ?>. Sorted by fleet size.</p></div></div>
<div class="container section"><div class="grid2">
<div>
  <div class="listings-grid"><?php foreach ($rows as $r) echo carrier_card($r); ?></div>
  <?= pager_html($base, $page, $pages) ?>
</div>
<aside>
  <?= data_cta($abbr) ?>
  <?= sponsor_block($abbr) ?>
  <div class="card"><h3>Other cargo types in <?= e($stName) ?></h3><div class="chips"><?php foreach ($others as $o): ?><a href="<?= e(cargo_path($abbr, $o['slug'])) ?>"><?= e($o['name']) ?><small><?= nf($o['n']) ?></small></a><?php endforeach; ?></div></div>
</aside>
</div></div>
<?php page_end();
