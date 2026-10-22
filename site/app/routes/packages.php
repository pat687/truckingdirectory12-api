<?php
/** Carrier data packages (CarrierDataUSA funnel): /carrier-data/ */
declare(strict_types=1);

$total = (int)meta('total', '0');
$snapshot = meta('snapshot_date');
$sum = q1('SELECT SUM(n) n, SUM(n_email) e, SUM(n_cell) c, SUM(n_officer) o, SUM(n_phone) p FROM stats_state');
$packages = cfg('cdusa')['packages'];
$home = cfg('cdusa')['home'];
$crumbs = [['Home', '/'], ['Carrier data', null]];
page_start([
    'title' => 'FMCSA Carrier Data Lists - ' . nf($total) . ' Trucking Companies with Emails & Phones | ' . cfg('site_name'),
    'description' => 'Download FMCSA motor carrier lists: ' . nf($total) . ' trucking companies with USDOT, MC, phone, email, cell phone, company officers, fleet size, cargo types and safety rating. By state, fleet size or niche.',
    'canonical' => '/carrier-data/', 'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1>The <span>complete FMCSA carrier list</span>, ready to download</h1>
<p>Everything on this site comes from one dataset: <?= nf($total) ?> registered motor carriers<?= $snapshot ? ', refreshed from FMCSA (snapshot ' . e(fmt_date($snapshot)) . ')' : '' ?>. The free profiles show the public fields. The lists add the fields you can't get here: email addresses, cell phones and the names of the company officers who sign the MCS-150. Sold by CarrierDataUSA, our data company.</p>
<?= stats_strip([[nf($sum['n'] ?? 0), 'carriers'], [nf($sum['p'] ?? 0), 'office phones'], [nf($sum['e'] ?? 0), 'email addresses'], [nf($sum['c'] ?? 0), 'cell phones'], [nf($sum['o'] ?? 0), 'officer names']]) ?>
</div></div>
<div class="container section">
<div class="card"><h2>Pick the list that matches your buyers</h2>
<table class="table"><thead><tr><th>Package</th><th>Built for</th><th>What's inside</th><th>Price</th><th></th></tr></thead><tbody>
<?php foreach ($packages as $p): ?>
<tr><td><b style="color:#fff"><?= e($p['name']) ?></b></td><td><?= e($p['who']) ?></td><td><?= e($p['fields']) ?></td><td style="color:var(--gold);font-weight:800;white-space:nowrap">$<?= (int)$p['price'] ?></td><td><a class="btn" rel="nofollow" href="<?= e($p['url']) ?>">Get it &rarr;</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="small" style="margin-top:10px">Instant CSV/Excel download after checkout. Need a custom cut (a radius, a cargo mix, a fleet-size band)? Email <a href="mailto:<?= e((string)cfg('contact_email')) ?>"><?= e((string)cfg('contact_email')) ?></a>.</p></div>

<div class="grid2">
<div class="card"><h2>Every field, explained</h2>
<table class="facts">
<tr><th>Company officers 1 &amp; 2</th><td>Reach the decision-maker by name, not "owner"</td></tr>
<tr><th>Cell phone</th><td>Direct mobile outreach and dialer lists</td></tr>
<tr><th>Email address</th><td>Direct email campaigns</td></tr>
<tr><th>Office phone and fax</th><td>Dispatch and office contact</td></tr>
<tr><th>Fleet size, power units, truck units</th><td>Target owner-operators or large fleets</td></tr>
<tr><th>Total drivers and CDL drivers</th><td>Driver staffing, CDL schools, compliance vendors</td></tr>
<tr><th>30 cargo-type flags</th><td>Filter to reefer, tanker, flatbed, livestock, hazmat and more</td></tr>
<tr><th>Carrier operation and authority</th><td>Interstate vs intrastate, for-hire vs private, MC number</td></tr>
<tr><th>Safety rating, crash rate, hazmat flag</th><td>Skip risky or unrated carriers</td></tr>
<tr><th>Status and MCS-150 date</th><td>Active carriers only, with the last filing date</td></tr>
<tr><th>Physical and mailing address</th><td>Direct mail, radius targeting, field sales</td></tr>
<tr><th>Business type and DUNS</th><td>LLC / corporation / individual, credit lookups</td></tr>
</table></div>
<div><div class="cta-box"><h3>Buy one state</h3><p>Pick a state below for its full carrier list.</p><div class="chips"><?php foreach (US_STATES as $a => [$n, $s]): ?><a rel="nofollow" href="<?= e(cdusa_state_url($a)) ?>"><?= e($n) ?></a><?php endforeach; ?></div></div>
<div class="card"><h3>Why buy from us</h3><p class="small">Barrgo has brokered trucks and equipment for over 13 years and built CarrierDataUSA to sell the same carrier data we use ourselves. Lists are rebuilt from the FMCSA census each release, deduplicated by USDOT number, and delivered as clean CSV files.</p><p style="margin-top:10px"><a class="btn btn-outline" rel="nofollow" href="<?= e($home) ?>">Visit CarrierDataUSA &rarr;</a></p></div></div>
</div></div>
<?php page_end();
