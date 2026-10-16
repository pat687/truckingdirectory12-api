<?php
/** Search: /carriers/search?q=...&state=XX  (noindex) */
declare(strict_types=1);

$qstr = trim((string)($_GET['q'] ?? ''));
$qstr = trim(preg_replace('/\s+/', ' ', mb_substr($qstr, 0, 80)) ?? '');
$stateF = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['state'] ?? '')));
$stateF = isset(US_STATES[$stateF]) ? $stateF : '';
$rows = [];
$hint = '';
if ($qstr !== '') {
    if (ctype_digit($qstr)) {
        $c = q1('SELECT dot, slug FROM carriers WHERE dot = ?', [(int)$qstr]);
        if ($c) {
            header('Location: ' . url(carrier_path($c)), true, 302);
            exit;
        }
    } elseif (mb_strlen($qstr) < 2 || !preg_match('/[A-Za-z0-9]/', $qstr)) {
        // Too short or punctuation only: never reaches the database (a bare % or _ would scan the table).
        $hint = 'Enter at least two letters or digits.';
    } else {
        // Names are stored upper-case; % and _ in the query are literal characters, not wildcards.
        $term = addcslashes(strtoupper($qstr), '%_\\');
        $cols = 'dot, legal_name, slug, phy_city, phy_state, power_units, drivers, cargo, phone, mc_number, mc_prefix, dba_name';
        $sql = "SELECT $cols FROM carriers WHERE (legal_name LIKE ? ESCAPE '\\' OR dba_name LIKE ? ESCAPE '\\')";
        $params = [$term . '%', $term . '%'];
        if ($stateF) {
            $sql .= ' AND phy_state = ?';
            $params[] = $stateF;
        }
        $rows = q($sql . ' ORDER BY power_units DESC, legal_name LIMIT 60', $params);
        if (!$rows && mb_strlen($qstr) >= 6) {   // "contains" fallback only for longer queries, small limit
            $params[0] = $params[1] = '%' . $term . '%';
            $rows = q($sql . ' ORDER BY power_units DESC, legal_name LIMIT 20', $params);
        }
    }
}
page_start(['title' => ($qstr !== '' ? 'Search: ' . $qstr . ' | ' : 'Search carriers | ') . cfg('site_name'), 'robots' => 'noindex,follow', 'cache' => false]);
?>
<div class="hero"><div class="container"><h1>Search <span>trucking companies</span></h1>
<form class="search-box" action="/carriers/search" method="get"><input type="search" name="q" value="<?= e($qstr) ?>" placeholder="Company name or USDOT number" aria-label="Search" minlength="2" required>
<select name="state" style="padding:0 10px;border-radius:6px;background:#0f0f1a;color:#eee;border:1px solid rgba(255,255,255,0.15)"><option value="">All states</option><?php foreach (US_STATES as $a => [$n, $s]): ?><option value="<?= $a ?>"<?= $a === $stateF ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
<button class="btn" type="submit">Search</button></form>
<p class="sub">Search by company name (starts with) or exact USDOT number.</p></div></div>
<div class="container section">
<?php if ($hint !== ''): ?>
  <div class="card"><p><?= e($hint) ?></p></div>
<?php elseif ($qstr !== '' && !$rows): ?>
  <div class="card"><p>No carriers matched "<?= e($qstr) ?>"<?= $stateF ? ' in ' . e(state_name($stateF)) : '' ?>. Try fewer words, the USDOT number, or <a href="/carriers/">browse by state</a>.</p></div>
<?php elseif ($rows): ?>
  <p class="small" style="margin-bottom:12px"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) === 60 ? ' (showing the first 60 - refine your search)' : '' ?></p>
  <div class="listings-grid"><?php foreach ($rows as $r) echo carrier_card($r); ?></div>
<?php endif; ?>
</div>
<?php page_end();
