<?php
/** Vendor category x state: /{category}/{state}.html  (replaces the old static placeholder pages) */
declare(strict_types=1);

$cats = $GLOBALS['CATEGORIES'];
$cat = preg_replace('/[^a-z_]/', '', (string)($_GET['cat'] ?? ''));
$abbr = state_by_slug((string)($_GET['state'] ?? ''));
if (!isset($cats[$cat]) || !$abbr) {
    not_found();
}
$info = $cats[$cat];
$stName = state_name($abbr);
$st = q1('SELECT * FROM stats_state WHERE state = ?', [$abbr]);
$vendors = vendors_for($cat, $abbr, 100);
$cities = q('SELECT * FROM stats_city WHERE state = ? AND n_active > 0 ORDER BY n_active DESC LIMIT 12', [$abbr]);
$crumbs = [['Home', '/'], [$info['name'], '/' . $cat . '/'], [$stName, null]];
$n = $st ? (int)$st['n'] : 0;
page_start([
    'title' => $info['name'] . ' in ' . $stName . ' | ' . cfg('site_name'),
    'description' => $info['name'] . ' serving ' . $stName . ' trucking companies. ' . nf($n) . ' FMCSA-registered carriers are based in ' . $stName . '. ' . ($vendors ? count($vendors) . ' listed providers.' : 'Request quotes or get your company listed.'),
    'canonical' => vendor_state_path($cat, $abbr), 'jsonld' => [breadcrumbs_ld($crumbs)],
    // A category x state page with no ACTIVE vendor is a placeholder: crawlable (200) but not indexed,
    // and the sitemap leaves it out. It flips to index,follow the day a vendor goes live.
    'robots' => $vendors ? 'index,follow' : 'noindex,follow',
]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1><?= e($info['name']) ?> in <span><?= e($stName) ?></span></h1>
<p><?= e($info['intro']) ?> <?= nf($n) ?> FMCSA-registered motor carriers are based in <?= e($stName) ?><?= $st ? ' - ' . nf($st['n_fleet_1']) . ' owner-operators and ' . nf((int)$st['n_fleet_21_100'] + (int)$st['n_fleet_100']) . ' fleets with more than 20 trucks' : '' ?>.</p></div></div>
<div class="container section"><div class="grid2">
<div>
<?php if ($vendors): ?>
<div class="card"><h2><?= count($vendors) ?> <?= e(strtolower($info['name'])) ?> serving <?= e($stName) ?></h2><div class="listings-grid">
<?php foreach ($vendors as $v): ?><div class="listing-card"><span class="card-name"><?= e($v['name']) ?></span><?= $v['plan'] === 'featured' ? '<span class="badge badge-warn" style="align-self:flex-start">Featured</span>' : '' ?><div class="card-location"><?= e(trim(($v['city'] ? $v['city'] . ', ' : '') . $v['state'])) ?><?= $v['states_served'] === 'ALL' ? ' &middot; nationwide' : '' ?></div><div class="small"><?= e((string)$v['blurb']) ?></div><div class="card-actions"><?= $v['phone'] ? '<a class="card-phone" href="tel:' . e(preg_replace('/\D/', '', $v['phone'])) . '">&#128222; ' . e($v['phone']) . '</a>' : '' ?><?= $v['website'] ? '<a class="card-web" rel="nofollow sponsored" href="' . e($v['website']) . '">Website</a>' : '' ?></div></div><?php endforeach; ?>
</div></div>
<?php else: ?>
<div class="card"><h2>No <?= e(strtolower($info['name'])) ?> listed in <?= e($stName) ?> yet</h2><p>We only list real, verified providers. Be the first <?= e($info['singular']) ?> on this page and on every <?= e($stName) ?> carrier profile.</p><p style="margin-top:12px"><a class="btn" href="/get-listed?cat=<?= e($cat) ?>&state=<?= e($abbr) ?>">Get listed in <?= e($stName) ?> &rarr;</a></p></div>
<?php endif; ?>
<?php if ($info['quote']): ?>
<div class="cta-box"><h3>Carriers: get <?= e(strtolower($info['name'])) ?> quotes</h3><p>Tell us about your operation and matched providers serving <?= e($stName) ?> will contact you. Free.</p><a class="btn" rel="nofollow" href="/quote/<?= e($info['quote']) ?>?state=<?= e($abbr) ?>">Request quotes &rarr;</a></div>
<?php endif; ?>
<div class="card"><h2>Trucking companies in <?= e($stName) ?></h2><p class="small" style="margin-bottom:10px">The carriers these providers serve. Largest cities:</p><div class="chips"><?php foreach ($cities as $c): ?><a href="<?= e(city_path($abbr, $c['city_slug'])) ?>"><?= e(title_case($c['city'])) ?><small><?= nf($c['n_active']) ?></small></a><?php endforeach; ?><a href="<?= e(state_path($abbr)) ?>">All <?= e($stName) ?> carriers &rarr;</a></div></div>
<div class="card"><h2><?= e($info['name']) ?> in other states</h2><div class="chips"><?php foreach (US_STATES as $a => [$nm, $s]): if ($a !== $abbr): ?><a href="<?= e(vendor_state_path($cat, $a)) ?>"><?= e($nm) ?></a><?php endif; endforeach; ?></div></div>
</div>
<aside>
<div class="cta-box"><h3>Reach every carrier in <?= e($stName) ?></h3><p><?= nf($n) ?> carriers with phone, email and officer names, filtered by fleet size and cargo. The list <?= e(strtolower($info['name'])) ?> use for outbound.</p><a class="btn btn-gold" rel="nofollow" href="<?= e(cdusa_state_url($abbr)) ?>">Get the <?= e($stName) ?> list &rarr;</a></div>
<div class="card"><h3>Other services in <?= e($stName) ?></h3><div class="chips"><?php foreach ($cats as $k => $c): if ($k !== $cat): ?><a href="<?= e(vendor_state_path($k, $abbr)) ?>"><?= e($c['name']) ?></a><?php endif; endforeach; ?></div></div>
</aside>
</div></div>
<?php page_end();
