<?php
/** State hub: /carriers/{state}/ */
declare(strict_types=1);

$abbr = state_by_slug((string)($_GET['state'] ?? ''));
$st = $abbr ? q1('SELECT * FROM stats_state WHERE state = ?', [$abbr]) : null;
if (!$abbr || !$st) {
    not_found('No carriers found for that state.');
}
$name = state_name($abbr);
$snapshot = meta('snapshot_date');
// Counts shown on the hub are ACTIVE carriers; inactive registrations are only reachable by direct URL and search.
$nActive = (int)$st['n_active'];
$cities = q('SELECT * FROM stats_city WHERE state = ? AND n_active > 0 ORDER BY n_active DESC, n DESC LIMIT 60', [$abbr]);
$nCities = (int)qv('SELECT COUNT(*) FROM stats_city WHERE state = ? AND n_active > 0', [$abbr]);
$cargo = q('SELECT sc.n_active, ct.name, ct.slug FROM stats_cargo sc JOIN cargo_types ct ON ct.id = sc.cargo_id WHERE sc.state = ? AND sc.n_active > 0 ORDER BY sc.n_active DESC', [$abbr]);
$largest = q('SELECT dot, legal_name, slug, phy_city, phy_state, power_units, drivers, cargo, phone, mc_number, mc_prefix, dba_name FROM carriers WHERE phy_state = ? AND power_units > 0 AND index_tier <= 2 ORDER BY power_units DESC, legal_name LIMIT 12', [$abbr]);
$crumbs = [['Carriers', '/carriers/'], [$name, null]];
$cats = $GLOBALS['CATEGORIES'];

page_start([
    'title' => seo_title('Trucking Companies in ' . $name, plural($nActive, 'Active FMCSA Carrier')),
    'description' => plural($nActive, 'active FMCSA-registered trucking company', 'active FMCSA-registered trucking companies') . ' in ' . $name . ' (' . nf($st['n']) . ' registered): ' . nf($st['n_interstate']) . ' interstate, ' . nf($st['n_hazmat']) . ' hazmat. Browse by city and cargo type, with USDOT, MC, phone, fleet size and safety rating.',
    'canonical' => state_path($abbr),
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container">
<?= breadcrumbs_html($crumbs) ?>
<h1>Trucking Companies in <span><?= e($name) ?></span></h1>
<p><?= e(plural($nActive, 'motor carrier')) ?> with an active USDOT number list a physical address in <?= e($name) ?> (<?= nf($st['n']) ?> registered<?= $snapshot ? ', FMCSA snapshot ' . e(fmt_date($snapshot)) : '' ?>). Together they report <?= nf($st['power_units']) ?> power units and <?= nf($st['drivers']) ?> drivers. Browse by city or cargo type, or search by name or USDOT number.</p>
<form class="search-box" action="/carriers/search" method="get"><input type="search" name="q" placeholder="Company name or USDOT number" aria-label="Search carriers"><input type="hidden" name="state" value="<?= e($abbr) ?>"><button class="btn" type="submit">Search</button></form>
<?= stats_strip([[nf($st['n']), 'carriers'], [nf($st['n_active']), 'active USDOT'], [nf($st['n_interstate']), 'interstate'], [nf($st['n_hazmat']), 'hazmat'], [nf($st['n_phone']), 'with phone']]) ?>
</div></div>

<div class="container section"><div class="grid2">
<div>
  <div class="card"><h2>Cities with the most carriers</h2>
  <div class="chips"><?php foreach ($cities as $c): ?><a href="<?= e(city_path($abbr, $c['city_slug'])) ?>"><?= e(title_case($c['city'])) ?><small><?= nf($c['n_active']) ?></small></a><?php endforeach; ?></div>
  <?php if ($nCities > count($cities)): ?><p style="margin-top:12px"><a href="<?= e(state_path($abbr)) ?>cities/">All <?= nf($nCities) ?> cities in <?= e($name) ?> &rarr;</a></p><?php endif; ?>
  </div>

  <div class="card"><h2>Carriers by cargo type</h2>
  <div class="chips"><?php foreach ($cargo as $c): ?><a href="<?= e(cargo_path($abbr, $c['slug'])) ?>"><?= e($c['name']) ?><small><?= nf($c['n_active']) ?></small></a><?php endforeach; ?></div></div>

  <div class="card"><h2>Fleet sizes in <?= e($name) ?></h2>
  <table class="table"><thead><tr><th>Fleet size</th><th>Carriers</th><th>Share</th></tr></thead><tbody>
  <?php $rows = [['Owner-operator (1 power unit)', $st['n_fleet_1']], ['2-5 power units', $st['n_fleet_2_5']], ['6-20 power units', $st['n_fleet_6_20']], ['21-100 power units', $st['n_fleet_21_100']], ['Over 100 power units', $st['n_fleet_100']]];
  $reported = array_sum(array_map(fn($r) => (int)$r[1], $rows));
  foreach ($rows as [$label, $n]): ?>
  <tr><td><?= e($label) ?></td><td><?= nf($n) ?></td><td><?= $reported ? round((int)$n / $reported * 100) : 0 ?>%</td></tr>
  <?php endforeach; ?>
  </tbody></table><p class="small" style="margin-top:8px"><?= nf((int)$st['n'] - $reported) ?> carriers did not report fleet size.</p></div>

  <?php if ($largest): ?>
  <div class="card"><h2>Largest fleets based in <?= e($name) ?></h2>
  <div class="listings-grid"><?php foreach ($largest as $c) echo carrier_card($c); ?></div></div>
  <?php endif; ?>

  <div class="card"><h2>Services for <?= e($name) ?> carriers</h2>
  <div class="chips"><?php foreach ($cats as $k => $cat): ?><a href="<?= e(vendor_state_path($k, $abbr)) ?>"><?= e($cat['name']) ?> in <?= e($name) ?></a><?php endforeach; ?></div></div>
</div>
<aside>
  <?= data_cta($abbr, $st) ?>
  <?= sponsor_block($abbr, null, ['factoring', 'insurance', 'dispatch', 'fuel-cards'], 4) ?>
  <div class="card"><h3>Other states</h3><div class="chips"><?php foreach (US_STATES as $a => [$n, $s]): if ($a !== $abbr): ?><a href="<?= e(state_path($a)) ?>"><?= e($n) ?></a><?php endif; endforeach; ?></div></div>
</aside>
</div></div>
<?php page_end();
