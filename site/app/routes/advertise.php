<?php
/** Advertiser pitch + featured sponsor form: /advertise */
declare(strict_types=1);
require __DIR__ . '/../lib/vendor_form.php';
[$fv, $ferrors] = vendor_form_handle('featured');

$total = (int)meta('total', '0');
try {
    $top = q('SELECT state, state_name, n, n_fleet_1, n_fleet_21_100, n_fleet_100 FROM stats_state ORDER BY n DESC LIMIT 8');
} catch (CarriersDbMissing | PDOException $e) {
    $top = []; // the sponsor form still works without (or with a damaged) carriers.db
}
$support = (string)cfg('contact_email');
page_start(['title' => 'Advertise to ' . nf($total) . ' Trucking Companies | ' . cfg('site_name'), 'description' => 'Sponsored placement on carrier profiles, state pages and category pages for the states you serve.', 'canonical' => '/advertise', 'cache' => false]);
?>
<div class="hero"><div class="container"><h1>Put your company in front of <span><?= nf($total) ?> carriers</span></h1><p>Every FMCSA-registered trucking company has a profile here, and the people who visit those pages are carriers, shippers, brokers and agents checking a DOT number. Featured sponsors rotate through the sponsored slots on the carrier profiles and state pages for the states they serve, and sit at the top of their category pages.</p></div></div>
<div class="container section"><div class="grid2">
<div><div class="card"><h2>Where Featured sponsors appear</h2><ul style="margin-left:18px;color:#ccc;line-height:1.9"><li>Rotation in the "Services for carriers in {state}" block on carrier profiles in your states</li><li>Rotation in the sidebar of the state, city and cargo pages in your states</li><li>Top of your category page for each state you serve (e.g. Factoring Companies in Texas)</li></ul><p class="small" style="margin-top:8px">Standard listings appear on the category pages for the states you serve. Rotating slots are shared with the other Featured sponsors serving the same state.</p></div>
<?php if ($top): ?>
<div class="card"><h2>Carriers by state (largest markets)</h2><table class="table"><thead><tr><th>State</th><th>Carriers</th><th>Owner-operators</th><th>Fleets 21+</th></tr></thead><tbody>
<?php foreach ($top as $s): ?><tr><td><?= e($s['state_name']) ?></td><td><?= nf($s['n']) ?></td><td><?= nf($s['n_fleet_1']) ?></td><td><?= nf((int)$s['n_fleet_21_100'] + (int)$s['n_fleet_100']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<div class="card"><h2>Become a sponsor</h2><?php vendor_form_render($fv, $ferrors, '/advertise'); ?></div></div>
<aside><div class="cta-box"><h3>Want the carriers themselves?</h3><p>Sponsorship reaches carriers who visit. For outbound campaigns, buy the list: every carrier in your states with phone, email and officer names.</p><a class="btn btn-gold" href="/carrier-data/">Carrier data packages &rarr;</a></div>
<div class="card"><h3>Questions</h3><p class="small">Email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> for multi-state or category-exclusive placements.</p></div></aside>
</div></div>
<?php page_end();
