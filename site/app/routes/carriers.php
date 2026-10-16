<?php
/** Carriers hub: /carriers/ */
declare(strict_types=1);

$states = q('SELECT * FROM stats_state ORDER BY state_name');
$total = (int)meta('total', '0');
$snapshot = meta('snapshot_date');
$crumbs = [['Home', '/'], ['Carriers', null]];
$sumActive = array_sum(array_map(fn($s) => (int)$s['n_active'], $states));
$sumPU = array_sum(array_map(fn($s) => (int)$s['power_units'], $states));
$sumDrivers = array_sum(array_map(fn($s) => (int)$s['drivers'], $states));

page_start([
    'title' => seo_title('Trucking Companies by State', plural($total, 'FMCSA Motor Carrier')),
    'description' => 'Look up any of ' . nf($total) . ' FMCSA-registered trucking companies by USDOT number, name, state, city or cargo type. Fleet size, drivers, authority, safety rating and phone for every motor carrier in the United States.',
    'canonical' => '/carriers/',
    'jsonld' => [breadcrumbs_ld($crumbs), [
        '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => cfg('site_name'), 'url' => url('/'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => url('/carriers/search?q={search_term_string}'), 'query-input' => 'required name=search_term_string'],
    ]],
]);
?>
<div class="hero"><div class="container">
<?= breadcrumbs_html($crumbs) ?>
<h1>Every <span>Trucking Company</span> in the United States</h1>
<p><?= nf($total) ?> FMCSA-registered motor carriers with USDOT number, MC number, address, phone, fleet size, drivers, cargo types and safety rating<?= $snapshot ? ' (FMCSA snapshot ' . e(fmt_date($snapshot)) . ')' : '' ?>. Search by company name or USDOT number, or browse by state, city and cargo.</p>
<form class="search-box" action="/carriers/search" method="get"><input type="search" name="q" placeholder="Company name or USDOT number" aria-label="Search carriers"><button class="btn" type="submit">Search</button></form>
<?= stats_strip([[nf($total), 'carrier records'], [nf($sumActive), 'active USDOT'], [nf($sumPU), 'power units'], [nf($sumDrivers), 'drivers']]) ?>
</div></div>

<div class="container section">
<div class="card"><h2>Carriers by state</h2>
<table class="table"><thead><tr><th>State</th><th>Carriers</th><th>Active</th><th>Interstate</th><th>Hazmat</th><th>Owner-operators</th><th>Fleets 21+</th></tr></thead><tbody>
<?php foreach ($states as $s): ?>
<tr><td><a href="<?= e(state_path($s['state'])) ?>"><?= e($s['state_name']) ?></a></td><td><?= nf($s['n']) ?></td><td><?= nf($s['n_active']) ?></td><td><?= nf($s['n_interstate']) ?></td><td><?= nf($s['n_hazmat']) ?></td><td><?= nf($s['n_fleet_1']) ?></td><td><?= nf((int)$s['n_fleet_21_100'] + (int)$s['n_fleet_100']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<div class="grid2">
<div class="card prose"><h2>About this directory</h2>
<p>Every motor carrier that registers with the Federal Motor Carrier Safety Administration (FMCSA) receives a USDOT number and files an MCS-150 form describing its fleet, drivers, operation type and the cargo it hauls. This directory publishes those public records for all 50 states so shippers, brokers, insurers and carriers themselves can check a company quickly.</p>
<p>Carriers can <a href="/get-listed">claim their profile</a> to add a website link and verified contact details. Service providers can <a href="/advertise">reach carriers by state and category</a>. Need the complete dataset with email addresses, cell phones and company officer names? See our <a href="/carrier-data/">carrier data packages</a>.</p>
</div>
<div><div class="cta-box"><h3>The whole country as one spreadsheet</h3><p><?= nf($total) ?> carriers with every public field plus emails, cell phones and decision-maker names, filterable by state, fleet size and cargo.</p><a class="btn btn-gold" href="/carrier-data/">See data packages &rarr;</a></div></div>
</div>
</div>
<?php page_end();
