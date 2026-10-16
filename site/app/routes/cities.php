<?php
/** All cities in a state: /carriers/{state}/cities/ */
declare(strict_types=1);

$abbr = state_by_slug((string)($_GET['state'] ?? ''));
if (!$abbr) {
    not_found();
}
$name = state_name($abbr);
$cities = q('SELECT * FROM stats_city WHERE state = ? ORDER BY city', [$abbr]);
if (!$cities) {
    not_found('No carriers found for that state.');
}
$crumbs = [['Carriers', '/carriers/'], [$name, state_path($abbr)], ['All cities', null]];
page_start([
    'title' => 'All Cities in ' . $name . ' with Trucking Companies | ' . cfg('site_name'),
    'description' => 'Trucking companies in every ' . $name . ' city and town: ' . nf(count($cities)) . ' locations with FMCSA-registered motor carriers.',
    'canonical' => state_path($abbr) . 'cities/',
    'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?><h1>Trucking companies in <span><?= e($name) ?></span> by city</h1><p><?= nf(count($cities)) ?> cities and towns in <?= e($name) ?> have FMCSA-registered carriers.</p></div></div>
<div class="container section"><div class="card"><div class="chips">
<?php foreach ($cities as $c): ?><a href="<?= e(city_path($abbr, $c['city_slug'])) ?>"><?= e(title_case($c['city'])) ?><small><?= nf($c['n']) ?></small></a><?php endforeach; ?>
</div></div></div>
<?php page_end();
