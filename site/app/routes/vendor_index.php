<?php
/** Vendor category index: /{category}/  (replaces the old static index.html) */
declare(strict_types=1);

$cats = $GLOBALS['CATEGORIES'];
$cat = preg_replace('/[^a-z_]/', '', (string)($_GET['cat'] ?? ''));
if (!isset($cats[$cat])) {
    not_found();
}
$info = $cats[$cat];
$total = (int)meta('total', '0');
$vendors = vendors_for($cat, null, 100);
$states = q('SELECT state, state_name, n_active AS n FROM stats_state ORDER BY state_name');
$crumbs = [['Home', '/'], [$info['name'], null]];
page_start([
    'title' => $info['name'] . ' for Trucking Companies - All 50 States | ' . cfg('site_name'),
    'description' => $info['intro'] . ' Browse ' . strtolower($info['name']) . ' by state, or request quotes.',
    'canonical' => '/' . $cat . '/', 'jsonld' => [breadcrumbs_ld($crumbs)],
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1><span><?= e($info['name']) ?></span> for trucking companies</h1><p><?= e($info['intro']) ?> Pick your state below<?= $info['quote'] ? ', or <a href="/quote/' . e($info['quote']) . '" rel="nofollow">request quotes</a> and let providers come to you' : '' ?>.</p></div></div>
<div class="container section">
<?php if ($vendors): ?>
<div class="card"><h2>Nationwide <?= e(strtolower($info['name'])) ?></h2><div class="listings-grid">
<?php foreach ($vendors as $v): ?><div class="listing-card<?= $v['plan'] === 'featured' ? ' paid' : '' ?>"><span class="card-name"><?= e($v['name']) ?></span><?= $v['plan'] === 'featured' ? '<span class="badge badge-warn" style="align-self:flex-start">Featured</span>' : '' ?><div class="card-location"><?= e(trim(($v['city'] ? $v['city'] . ', ' : '') . $v['state'])) ?><?= $v['states_served'] === 'ALL' ? ' &middot; serves all 50 states' : '' ?></div><div class="small"><?= e((string)$v['blurb']) ?></div><div class="card-actions"><?= $v['phone'] ? '<a class="card-phone" href="tel:' . e(preg_replace('/\D/', '', $v['phone'])) . '">&#128222; ' . e($v['phone']) . '</a>' : '' ?><?= $v['website'] ? '<a class="card-web" rel="nofollow sponsored" href="' . e($v['website']) . '">Website</a>' : '' ?></div></div><?php endforeach; ?>
</div></div>
<?php endif; ?>
<div class="card"><h2><?= e($info['name']) ?> by state</h2><div class="chips"><?php foreach ($states as $s): ?><a href="<?= e(vendor_state_path($cat, $s['state'])) ?>"><?= e($s['state_name']) ?><small><?= nf($s['n']) ?> carriers</small></a><?php endforeach; ?></div></div>
<div class="grid2"><div class="cta-box"><h3>Are you a <?= e($info['singular']) ?>?</h3><p>Be listed on this page and on the <?= e($info['name']) ?> page for every state you serve, next to <?= nf($total) ?> carrier profiles.</p><a class="btn" href="/get-listed?cat=<?= e($cat) ?>">Get listed &rarr;</a> <a class="btn btn-outline" href="/advertise">Sponsor placements</a></div>
<div class="cta-box"><h3>Sell to these carriers directly</h3><p>Every carrier on this site, with emails, cell phones and officer names, filtered to your target states and fleet sizes.</p><a class="btn btn-gold" href="/carrier-data/">Carrier data packages &rarr;</a></div></div>
</div>
<?php page_end();
