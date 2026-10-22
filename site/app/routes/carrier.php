<?php
/** Carrier profile: /carrier/{dot}/{slug} */
declare(strict_types=1);

$dotStr = (string)($_GET['dot'] ?? '');
$dot = (int)$dotStr;
$c = $dot > 0 ? q1('SELECT * FROM carriers WHERE dot = ?', [$dot]) : null;
if (!$c) {
    not_found('No carrier with USDOT ' . $dot . ' in our records.');
}
// One canonical URL per carrier: the USDOT digits without leading zeros plus the current name slug.
if ($dotStr !== (string)$dot || (string)($_GET['slug'] ?? '') !== $c['slug']) {
    header('Location: ' . url(carrier_path($c)), true, 301);
    exit;
}

$claim = carrier_claim($dot);
$name = company_name($c['legal_name']);
$dba = $c['dba_name'] ? company_name($c['dba_name']) : '';
$city = title_case($c['phy_city']);
$st = (string)$c['phy_state'];
$stName = state_name($st);
$hasCity = $city !== '' && !empty($c['city_slug']);   // some census rows have no city
$where = $hasCity ? $city . ', ' . $stName : $stName;
$whereShort = $hasCity ? $city . ', ' . $st : $stName;
$docket = docket_label($c);        // "MC-123456", "MX-123456", "FF-123456" or ''
$docketKind = docket_prefix($c);   // MC / MX / FF
$pu = $c['power_units'] !== null ? (int)$c['power_units'] : null;
$drv = $c['drivers'] !== null ? (int)$c['drivers'] : null;
$cargo = cargo_list($c['cargo']);
$ops = opclass_labels($c['op_class']);
$ranges = range_labels($c['range_flags']);
$status = $c['status'];
$inactive = $status === 'INACTIVE' || !empty($c['oos_date']);
$snapshot = meta('snapshot_date');
$stats = q1('SELECT * FROM stats_state WHERE state = ?', [$st]);
// Index gate: tier 3 (inactive) is never indexed; tier 2 only once the owner raises sitemap_max_tier to 2,
// so the robots rule and the sitemaps always agree.
$tier = (int)$c['index_tier'];
$maxTier = max(1, min(2, (int)cfg('sitemap_max_tier', 1)));
$indexable = $tier < 3 && $tier <= $maxTier;

// ---- generated description (unique per record, built only from the data on file)
$cargoNames = array_map(fn($t) => $t['name'], $cargo);
$cargoText = '';
if ($cargoNames) {
    $cargoText = count($cargoNames) > 1
        ? implode(', ', array_slice($cargoNames, 0, -1)) . ' and ' . end($cargoNames)
        : $cargoNames[0];
}
$opText = $ops ? strtolower(implode(' / ', $ops)) : '';
$s1 = $name . ' is a' . (preg_match('/^[aeiou]/i', $opText) ? 'n ' : ' ') . ($opText ? $opText . ' ' : '')
    . 'motor carrier based in ' . $where
    . ' (USDOT ' . $dot . ($docket ? ', ' . $docket : '') . ').';
if ($pu !== null && $pu > 0) {
    $drvText = $drv !== null && $drv > 0 ? ' and ' . nf($drv) . ' driver' . ($drv === 1 ? '' : 's') : '';
    $s2 = $pu === 1
        ? ' It is an owner-operator with 1 power unit' . $drvText . '.'
        : ' It is a ' . ($pu <= 5 ? 'small' : ($pu <= 20 ? 'mid-size' : ($pu <= 100 ? 'large' : 'major'))) . ' fleet with ' . nf($pu) . ' power units' . $drvText . '.';
} else {
    $s2 = ' Fleet size was not reported on its most recent MCS-150 filing.';
}
$s3 = $c['carrier_operation'] ? ' It is registered for ' . strtolower(operation_label($c['carrier_operation'])) . ' operations' . ($c['hm_flag'] ? ' and carries hazardous materials' : '') . '.' : '';
$s4 = $cargoText ? ' Cargo it reports hauling: ' . $cargoText . '.' : '';
$s5 = $c['mcs150_date'] ? ' Its most recent MCS-150 (biennial update) was filed on ' . fmt_date($c['mcs150_date']) . '.' : '';
$s6 = $status ? ' USDOT status: ' . ucfirst(strtolower($status)) . ($snapshot ? ' as of the FMCSA snapshot dated ' . fmt_date($snapshot) : '') . '.' : '';
$description = $s1 . $s2 . $s3 . $s4 . $s5 . $s6;

$metaDesc = $name . ' - USDOT ' . $dot . ($docket ? ', ' . $docket : '') . ' - ' . $whereShort . '. '
    . ($pu ? nf($pu) . ' power unit' . ($pu === 1 ? '' : 's') . ($drv !== null && $drv > 0 ? ', ' . nf($drv) . ' driver' . ($drv === 1 ? '' : 's') : '') . '. ' : '')
    . ($cargoNames ? 'Hauls ' . implode(', ', array_slice($cargoNames, 0, 3)) . '. ' : '')
    . 'Phone, address, authority, safety rating and fleet details.';
// Site name is appended only while the title stays within 60 characters.
$title = seo_title($name . ' | USDOT ' . $dot . ' | ' . $whereShort . ' Trucking Company', '', '', true);

$crumbs = [['Carriers', '/carriers/'], [$stName, state_path($st)]];
if ($hasCity) {
    $crumbs[] = [$city, city_path($st, (string)$c['city_slug'])];
}
$crumbs[] = [$name, null];

$address = ['@type' => 'PostalAddress'];
foreach (['streetAddress' => title_case($c['phy_street']), 'addressLocality' => $city, 'addressRegion' => $st, 'postalCode' => trim((string)$c['phy_zip'])] as $k => $val) {
    if ($val !== '') {   // never emit empty strings in structured data
        $address[$k] = $val;
    }
}
$address['addressCountry'] = 'US';
$org = [
    '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $name,
    'identifier' => [['@type' => 'PropertyValue', 'propertyID' => 'USDOT', 'value' => (string)$dot]],
    'address' => $address,
    'url' => url(carrier_path($c)), 'sameAs' => [safer_url($dot)],
];
if ($dba) {
    $org['alternateName'] = $dba;
}
if ($c['mc_number']) {
    $org['identifier'][] = ['@type' => 'PropertyValue', 'propertyID' => $docketKind, 'value' => (string)$c['mc_number']];
}
if ($c['phone']) {
    $org['telephone'] = '+1' . $c['phone'];
}
if ($claim && !empty($claim['website'])) {
    $org['url'] = $claim['website'];
    $org['sameAs'][] = url(carrier_path($c));
}

page_start([
    'title' => $title, 'description' => $metaDesc, 'canonical' => carrier_path($c),
    'robots' => $indexable ? 'index,follow' : 'noindex,follow',
    'jsonld' => [$org, breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container">
<?= breadcrumbs_html($crumbs) ?>
<h1><?= e($name) ?><?= $claim ? ' <span class="badge badge-ok" style="vertical-align:middle;font-size:0.8rem">&#10004; Verified</span>' : '' ?></h1>
<?php if ($dba): ?><p class="sub">Doing business as <b><?= e($dba) ?></b></p><?php endif; ?>
<p class="sub">USDOT <?= $dot ?><?= $docket ? ' &middot; ' . e($docket) : '' ?> &middot; <?= e($where) ?> &middot; <?= e(operation_label($c['carrier_operation'])) ?></p>
<p style="margin-top:12px">
<?php if ($status): ?><span class="badge <?= $inactive ? 'badge-bad' : 'badge-ok' ?>"><?= e(ucfirst(strtolower($status))) ?></span><?php endif; ?>
<?php if (!empty($c['oos_date'])): ?><span class="badge badge-bad">Out of service <?= e(fmt_date($c['oos_date'])) ?></span><?php endif; ?>
<?php if ($c['hm_flag']): ?><span class="badge badge-warn">Hazmat</span><?php endif; ?>
<?php if ($c['pc_flag']): ?><span class="badge badge-blue">Passenger carrier</span><?php endif; ?>
<?php foreach ($ops as $o): ?><span class="badge"><?= e($o) ?></span><?php endforeach; ?>
<?php if ($c['safety_rating'] && $c['safety_rating'] !== 'N'): ?><span class="badge <?= $c['safety_rating'] === 'S' ? 'badge-ok' : ($c['safety_rating'] === 'C' ? 'badge-warn' : 'badge-bad') ?>">Safety rating: <?= e(safety_label($c['safety_rating'])) ?></span><?php endif; ?>
</p>
</div></div>

<div class="container section"><div class="grid2">
<div>
  <div class="card prose"><p><?= e($description) ?></p>
  <?php
  // The claimant's website and email buttons are what a Verified listing pays for, so they render
  // whether or not FMCSA has an office phone on file for the carrier.
  $hasSite = $claim && !empty($claim['website']);
  $hasMail = $claim && (int)$claim['public_email'] === 1 && !empty($claim['email']);
  if ($c['phone'] || $hasSite || $hasMail): ?><p>
  <?php if ($c['phone']): ?><a class="btn" href="tel:+1<?= e($c['phone']) ?>">&#128222; Call <?= e(fmt_phone($c['phone'])) ?></a><?php endif; ?>
  <?php if ($hasSite): ?> <a class="btn btn-outline" rel="nofollow" href="<?= e($claim['website']) ?>">Visit website</a><?php endif; ?>
  <?php if ($hasMail): ?> <a class="btn btn-outline" href="mailto:<?= e($claim['email']) ?>">Email</a><?php endif; ?></p><?php endif; ?>
  </div>

  <div class="card"><h2>Company details</h2>
  <table class="facts">
    <tr><th>Legal name</th><td><?= e($name) ?></td></tr>
    <?php if ($dba): ?><tr><th>DBA name</th><td><?= e($dba) ?></td></tr><?php endif; ?>
    <tr><th>USDOT number</th><td><?= $dot ?></td></tr>
    <?php if ($docket): ?><tr><th><?= e($docketKind) ?> number</th><td><?= e($docket) ?></td></tr><?php endif; ?>
    <tr><th>Physical address</th><td><?= e(title_case($c['phy_street'])) ?><br><?= $hasCity ? e($city) . ', ' : '' ?><?= e($st) ?> <?= e($c['phy_zip']) ?></td></tr>
    <tr><th>Phone</th><td><?= $c['phone'] ? '<a href="tel:+1' . e($c['phone']) . '">' . e(fmt_phone($c['phone'])) . '</a>' : 'Not on file' ?></td></tr>
    <?php if ($status): ?><tr><th>USDOT status</th><td><?= e(ucfirst(strtolower($status))) ?><?= $snapshot ? ' <span class="small">(as of ' . e(fmt_date($snapshot)) . ')</span>' : '' ?></td></tr><?php endif; ?>
    <tr><th>Carrier operation</th><td><?= e(operation_label($c['carrier_operation'])) ?></td></tr>
    <?php if ($ops): ?><tr><th>Operation classification</th><td><?= e(implode(', ', $ops)) ?></td></tr><?php endif; ?>
    <?php if ($ranges): ?><tr><th>Operating range</th><td><?= e(implode('; ', $ranges)) ?></td></tr><?php endif; ?>
    <tr><th>Hazmat</th><td><?= $c['hm_flag'] ? 'Yes' : 'No' ?></td></tr>
    <tr><th>Power units</th><td><?= $pu !== null && $pu > 0 ? nf($pu) : 'Not reported' ?></td></tr>
    <?php if (!empty($c['truck_units'])): ?><tr><th>Straight trucks</th><td><?= nf($c['truck_units']) ?></td></tr><?php endif; ?>
    <tr><th>Drivers</th><td><?= $drv !== null && $drv > 0 ? nf($drv) : 'Not reported' ?><?= !empty($c['cdl_drivers']) ? ' (' . nf($c['cdl_drivers']) . ' CDL)' : '' ?></td></tr>
    <tr><th>Fleet size</th><td><?= e(ucfirst(fleet_label($pu))) ?></td></tr>
    <?php if ($cargo): ?><tr><th>Cargo carried</th><td><?php foreach ($cargo as $i => $t): ?><?= $i ? ', ' : '' ?><a href="<?= e(cargo_path($st, $t['slug'])) ?>"><?= e($t['name']) ?></a><?php endforeach; ?></td></tr><?php endif; ?>
    <?php if ($c['safety_rating']): ?><tr><th>Safety rating</th><td><?= e(safety_label($c['safety_rating'])) ?><?= $c['safety_rating_date'] ? ' <span class="small">(' . e(fmt_date($c['safety_rating_date'])) . ')</span>' : '' ?></td></tr><?php endif; ?>
    <?php if ($c['crash_rate'] !== null && (float)$c['crash_rate'] > 0): ?><tr><th>Recordable crash rate</th><td><?= e(number_format((float)$c['crash_rate'], 2)) ?></td></tr><?php endif; ?>
    <?php if ($c['mcs150_date']): ?><tr><th>MCS-150 last updated</th><td><?= e(fmt_date($c['mcs150_date'])) ?></td></tr><?php endif; ?>
    <?php if (!empty($c['mcs150_mileage'])): ?><tr><th>MCS-150 mileage</th><td><?= nf($c['mcs150_mileage']) ?><?= $c['mcs150_mileage_year'] ? ' (' . (int)$c['mcs150_mileage_year'] . ')' : '' ?></td></tr><?php endif; ?>
    <?php if ($c['add_date']): ?><tr><th>USDOT registered</th><td><?= e(fmt_date($c['add_date'])) ?></td></tr><?php endif; ?>
    <?php if ($c['business_org']): ?><tr><th>Business type</th><td><?= e(title_case($c['business_org'])) ?></td></tr><?php endif; ?>
    <?php if ($c['has_email'] || $c['has_cell'] || $c['has_officer']): ?>
    <tr><th>Also on file</th><td>
      <?php $onfile = []; if ($c['has_officer']) $onfile[] = 'company officer name'; if ($c['has_email']) $onfile[] = 'email address'; if ($c['has_cell']) $onfile[] = 'cell phone'; ?>
      <?= e(ucfirst(implode(', ', $onfile))) ?> &mdash; <a href="<?= e(cdusa_state_url($st)) ?>" rel="nofollow">included in the <?= e($stName) ?> carrier list</a></td></tr>
    <?php endif; ?>
  </table>
  <p class="small" style="margin-top:12px">Source: FMCSA motor carrier census<?= $snapshot ? ', snapshot ' . e(fmt_date($snapshot)) : '' ?>. Operating status and insurance can change daily; confirm on <a href="<?= e(safer_url($dot)) ?>" rel="nofollow noopener" target="_blank">FMCSA SAFER</a> before tendering freight.</p>
  <p class="small" style="margin-top:6px">Data from public FMCSA registration records<?= $snapshot ? ' as of ' . e(fmt_date($snapshot)) : '' ?>. Not affiliated with FMCSA or USDOT. Carriers can <a href="/claim/<?= $dot ?>" rel="nofollow">claim</a> or <a href="/opt-out" rel="nofollow">request removal</a> of this profile.</p>
  </div>

  <?php
  // Nearby active carriers in the same city (internal links); skipped when the record has no city.
  $nearby = $hasCity ? q('SELECT dot, legal_name, slug, phy_city, phy_state, power_units, drivers, cargo, phone, mc_number, mc_prefix, dba_name FROM carriers WHERE phy_state = ? AND city_slug = ? AND dot <> ? AND index_tier <= 2 ORDER BY power_units DESC, legal_name LIMIT 6', [$st, $c['city_slug'], $dot]) : [];
  if ($nearby): ?>
  <div class="card"><h2>Other trucking companies in <?= e($city) ?>, <?= e($st) ?></h2>
  <div class="listings-grid"><?php foreach ($nearby as $n) echo carrier_card($n, false); ?></div>
  <p style="margin-top:12px"><a href="<?= e(city_path($st, (string)$c['city_slug'])) ?>">All carriers in <?= e($city) ?> &rarr;</a></p></div>
  <?php endif; ?>

  <?php if ($cargo): $first = $cargo[0];
  $similar = q('SELECT c.dot, c.legal_name, c.slug, c.phy_city, c.phy_state, c.power_units, c.drivers, c.cargo, c.phone, c.mc_number, c.mc_prefix, c.dba_name FROM carrier_cargo cc JOIN carriers c ON c.dot = cc.dot WHERE cc.cargo_id = ? AND cc.state = ? AND cc.dot <> ? AND c.index_tier <= 2 ORDER BY cc.power_units DESC LIMIT 6', [(int)$first['id'], $st, $dot]);
  if ($similar): ?>
  <div class="card"><h2><?= e($first['name']) ?> carriers in <?= e($stName) ?></h2>
  <div class="listings-grid"><?php foreach ($similar as $n) echo carrier_card($n); ?></div>
  <p style="margin-top:12px"><a href="<?= e(cargo_path($st, $first['slug'])) ?>">All <?= e($first['name']) ?> carriers in <?= e($stName) ?> &rarr;</a></p></div>
  <?php endif; endif; ?>
</div>

<aside>
  <?= claim_box($c, $claim) ?>
  <?php if (!$claim) echo sponsor_block($st, $dot); // any ACTIVE claim removes competitor offers (promised in the Verified plan) ?>
  <?= data_cta($st, $stats) ?>
  <div class="card"><h3>Browse</h3><div class="chips">
    <a href="<?= e(state_path($st)) ?>"><?= e($stName) ?> carriers</a>
    <?php if ($hasCity): ?><a href="<?= e(city_path($st, (string)$c['city_slug'])) ?>"><?= e($city) ?> carriers</a><?php endif; ?>
    <?php foreach ($cargo as $t): ?><a href="<?= e(cargo_path($st, $t['slug'])) ?>"><?= e($t['name']) ?></a><?php endforeach; ?>
  </div></div>
</aside>
</div></div>
<?php page_end();
