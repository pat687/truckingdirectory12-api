<?php
declare(strict_types=1);

const QUOTE_TO_CATEGORY = [
    'factoring' => 'factoring_companies', 'insurance' => 'insurance_brokers', 'dispatch' => 'dispatch_services',
    'equipment' => 'equipment_brokers', 'fuel-cards' => 'fuel_cards', 'eld' => 'trucking_software',
    'financing' => 'finance_lenders', 'compliance' => 'compliance_services', 'repair' => 'truck_repair_services',
];

function site_css(): string
{
    return <<<'CSS'
:root{--primary:#1a1a2e;--primary-light:#16213e;--accent:#e94560;--gold:#f0a500;--green:#2ecc71;--radius:8px;}
*{margin:0;padding:0;box-sizing:border-box;}
html,body{overflow-x:hidden;}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#0f0f1a;color:#ddd;line-height:1.6;}
a{color:inherit;text-decoration:none;}
.nav{background:var(--primary);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.4);}
.nav-logo{font-size:1.2rem;font-weight:800;color:#fff;white-space:nowrap;}
.nav-logo span{color:var(--accent);}
.nav-links{display:flex;align-items:center;gap:2px;flex-wrap:nowrap;}
.nav-links a,.nav-links button{color:#ccc;font-size:0.8rem;padding:6px 9px;border-radius:4px;background:none;border:none;cursor:pointer;white-space:nowrap;transition:color .2s;font-family:inherit;}
.nav-links a:hover,.nav-links button:hover{color:#fff;}
.nav-links .btn-listed{background:var(--accent);color:#fff!important;padding:7px 14px;border-radius:20px;font-weight:600;}
.dropdown{position:relative;}
.dropdown-menu{display:none;position:absolute;top:100%;left:0;background:var(--primary);border:1px solid rgba(255,255,255,0.1);border-radius:var(--radius);min-width:200px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,0.5);}
.dropdown-menu a{display:block;}
.dropdown:hover .dropdown-menu,.dropdown-menu:hover{display:block;}
.hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;}
.hamburger span{display:block;width:24px;height:2px;background:#fff;margin:5px 0;}
.mobile-menu{display:none;background:var(--primary-light);padding:16px;border-top:1px solid rgba(255,255,255,0.08);}
.mobile-menu a{display:block;color:#ccc;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.9rem;}
.mobile-menu.open{display:block;}
@media(max-width:960px){.nav-links{display:none;}.hamburger{display:block;}}
.hero{background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;padding:44px 24px 32px;}
.hero h1{font-size:2rem;font-weight:800;margin-bottom:10px;line-height:1.2;}
.hero h1 span{color:var(--accent);}
.hero p{color:#bbb;max-width:820px;}
.hero .sub{color:#aaa;font-size:0.95rem;margin-top:6px;}
.container{max-width:1200px;margin:0 auto;padding:0 24px;}
.section{padding:32px 0;}
.crumbs{font-size:0.8rem;color:#888;margin-bottom:14px;}
.crumbs a:hover{color:#fff;}
.badge{display:inline-block;border-radius:4px;padding:2px 9px;font-size:0.75rem;font-weight:600;margin-right:6px;background:rgba(255,255,255,0.08);color:#ccc;}
.badge-ok{background:rgba(46,204,113,0.18);color:var(--green);}
.badge-warn{background:rgba(240,165,0,0.18);color:var(--gold);}
.badge-bad{background:rgba(233,69,96,0.2);color:var(--accent);}
.badge-blue{background:rgba(80,140,255,0.2);color:#8ab4ff;}
.grid2{display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;}
@media(max-width:900px){.grid2{grid-template-columns:1fr;}}
.card{background:var(--primary-light);border-radius:var(--radius);padding:22px;border:1px solid rgba(255,255,255,0.06);margin-bottom:20px;}
.card h2,.card h3{color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:12px;}
.facts{width:100%;border-collapse:collapse;font-size:0.9rem;}
.facts th,.facts td{text-align:left;padding:9px 10px;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:top;}
.facts th{color:#999;font-weight:600;width:38%;}
.facts td{color:#eee;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0;}
.stat{background:rgba(255,255,255,0.05);border-radius:var(--radius);padding:14px;text-align:center;}
.stat b{display:block;color:#fff;font-size:1.4rem;font-weight:800;}
.stat span{color:#999;font-size:0.78rem;}
.chips{display:flex;flex-wrap:wrap;gap:6px;}
.chips a,.chips span{background:rgba(255,255,255,0.06);color:#ccc;border-radius:4px;padding:5px 12px;font-size:0.82rem;}
.chips a:hover{background:var(--accent);color:#fff;}
.chips small{color:#888;margin-left:4px;}
.listings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
.listing-card{background:var(--primary-light);border-radius:var(--radius);padding:18px;display:flex;flex-direction:column;gap:8px;border:1px solid rgba(255,255,255,0.06);transition:transform .2s;}
.listing-card:hover{transform:translateY(-3px);}
.card-name{font-weight:700;color:#fff;font-size:1rem;line-height:1.3;}
.card-name:hover{color:var(--accent);}
.card-location{color:#888;font-size:0.82rem;}
.card-meta{display:flex;flex-wrap:wrap;gap:6px;font-size:0.78rem;color:#aaa;}
.card-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;}
.card-phone,.card-web{border-radius:6px;padding:7px 12px;font-size:0.82rem;}
.card-phone{background:rgba(255,255,255,0.06);color:#ccc;}
.card-web{background:rgba(233,69,96,0.15);color:var(--accent);}
.btn{display:inline-block;background:var(--accent);color:#fff;padding:11px 22px;border-radius:6px;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;font-family:inherit;}
.btn:hover{background:#c73652;}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.25);color:#eee;}
.btn-outline:hover{background:rgba(255,255,255,0.08);}
.btn-gold{background:var(--gold);color:#1a1a2e;}
.btn-gold:hover{background:#d49400;}
.cta-box{background:linear-gradient(135deg,#16213e,#1a1a2e);border:1px solid rgba(233,69,96,0.35);border-radius:var(--radius);padding:22px;margin-bottom:20px;}
.cta-box h3{color:#fff;font-size:1.05rem;margin-bottom:8px;}
.cta-box p{color:#aaa;font-size:0.9rem;margin-bottom:14px;}
.sponsor{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}
.sponsor .slot{background:rgba(255,255,255,0.04);border:1px dashed rgba(255,255,255,0.15);border-radius:var(--radius);padding:14px;font-size:0.85rem;}
.sponsor .slot b{display:block;color:#fff;margin-bottom:4px;}
.sponsor .slot p{color:#999;margin-bottom:10px;}
.sponsor .slot.paid{border-style:solid;border-color:rgba(240,165,0,0.5);}
.sponsor .tag{font-size:0.68rem;color:#888;text-transform:uppercase;letter-spacing:.05em;}
.prose p{margin-bottom:12px;color:#ccc;}
.prose h2{color:#fff;font-size:1.15rem;margin:18px 0 8px;}
.prose ul{margin:0 0 12px 20px;color:#ccc;}
.small{font-size:0.8rem;color:#888;}
form.f label{display:block;color:#bbb;font-size:0.85rem;margin:10px 0 4px;}
form.f input,form.f select,form.f textarea{width:100%;padding:10px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.15);background:#0f0f1a;color:#eee;font-family:inherit;font-size:0.95rem;}
form.f textarea{min-height:90px;}
form.f .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:700px){form.f .row{grid-template-columns:1fr;}}
form.f .hp{position:absolute;left:-9999px;}
.plan{border:1px solid rgba(255,255,255,0.12);border-radius:var(--radius);padding:14px;margin-bottom:10px;display:flex;gap:12px;align-items:flex-start;}
.plan input{width:auto;margin-top:4px;}
.plan b{color:#fff;}
.plan .price{color:var(--gold);font-weight:800;margin-left:6px;}
.pager{display:flex;gap:8px;justify-content:center;margin:24px 0;flex-wrap:wrap;}
.pager a,.pager span{padding:7px 12px;border-radius:6px;background:rgba(255,255,255,0.06);font-size:0.85rem;}
.pager a:hover{background:var(--accent);color:#fff;}
.pager .cur{background:var(--accent);color:#fff;}
.table{width:100%;border-collapse:collapse;font-size:0.88rem;}
.table th,.table td{padding:9px 10px;border-bottom:1px solid rgba(255,255,255,0.06);text-align:left;}
.table th{color:#999;font-weight:600;}
.table td a{color:#fff;}
.table td a:hover{color:var(--accent);}
.notice{background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.4);color:#cfe;border-radius:var(--radius);padding:14px;margin-bottom:16px;}
.error{background:rgba(233,69,96,0.12);border:1px solid rgba(233,69,96,0.4);color:#fdd;border-radius:var(--radius);padding:14px;margin-bottom:16px;}
.search-box{display:flex;gap:8px;max-width:640px;margin:14px 0;}
.search-box input{flex:1;padding:12px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.15);background:#0f0f1a;color:#eee;font-size:1rem;font-family:inherit;}
.site-footer{background:var(--primary);color:#999;padding:48px 24px 24px;margin-top:48px;}
.footer-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:32px;}
@media(max-width:900px){.footer-inner{grid-template-columns:1fr 1fr;}}
.footer-col h4{color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:16px;}
.footer-col p{font-size:0.85rem;line-height:1.7;}
.footer-col a{display:block;color:#999;font-size:0.85rem;margin-bottom:8px;}
.footer-col a:hover{color:#fff;}
.footer-bottom{max-width:1200px;margin:32px auto 0;padding-top:24px;border-top:1px solid rgba(255,255,255,0.08);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.8rem;}
CSS;
}

function nav_html(): string
{
    $cats = $GLOBALS['CATEGORIES'];
    $main = ['equipment_dealers', 'freight_brokers', 'dispatch_services', 'insurance_brokers', 'finance_lenders'];
    $links = '';
    foreach ($main as $k) {
        $links .= '<a href="/' . $k . '/">' . e($cats[$k]['name']) . '</a>';
    }
    $more = '';
    foreach ($cats as $k => $c) {
        if (!in_array($k, $main, true)) {
            $more .= '<a href="/' . $k . '/">' . e($c['name']) . '</a>';
        }
    }
    $mobile = '<a href="/carriers/">Trucking Companies (Carriers)</a><a href="/carrier-data/">Carrier Data Lists</a>';
    foreach ($cats as $k => $c) {
        $mobile .= '<a href="/' . $k . '/">' . e($c['name']) . '</a>';
    }
    $mobile .= '<a href="/carriers/search">Search</a><a href="/blog/">Blog</a><a href="/get-listed">Get Listed</a>';
    return '<nav class="nav"><a class="nav-logo" href="/">Trucking<span>Directory</span>USA</a>'
        . '<div class="nav-links"><a href="/carriers/"><b>Carriers</b></a>' . $links
        . '<div class="dropdown"><button type="button">More &#9662;</button><div class="dropdown-menu">' . $more . '</div></div>'
        . '<a href="/carrier-data/">Carrier Data</a><a href="/carriers/search">&#128269; Search</a><a href="/blog/">Blog</a>'
        . '<a class="btn-listed" href="/get-listed">Get Listed</a></div>'
        . '<button class="hamburger" aria-label="Menu" onclick="document.getElementById(\'mm\').classList.toggle(\'open\')"><span></span><span></span><span></span></button></nav>'
        . '<div class="mobile-menu" id="mm">' . $mobile . '</div>';
}

function footer_html(): string
{
    $cats = $GLOBALS['CATEGORIES'];
    $c1 = '';
    foreach ($cats as $k => $c) {
        $c1 .= '<a href="/' . $k . '/">' . e($c['name']) . '</a>';
    }
    $top = ['TX', 'CA', 'FL', 'IL', 'OH', 'NY', 'GA', 'PA', 'NC', 'TN'];
    $c2 = '';
    foreach ($top as $s) {
        $c2 .= '<a href="' . state_path($s) . '">' . e(state_name($s)) . ' trucking companies</a>';
    }
    $total = meta('total');
    $snap = meta('snapshot_date');
    return '<footer class="site-footer"><div class="footer-inner">'
        . '<div class="footer-col"><h4>Trucking Directory USA</h4><p>Every FMCSA-registered motor carrier in the United States, plus the brokers, dealers and service providers that keep them moving. Carrier records are public FMCSA data' . ($snap ? ' (snapshot ' . e(fmt_date($snap)) . ')' : '') . '.</p>'
        . '<p><a href="/carrier-data/">Need the full list with emails and officer names? Get carrier data &rarr;</a></p></div>'
        . '<div class="footer-col"><h4>All Categories</h4>' . $c1 . '</div>'
        . '<div class="footer-col"><h4>Carriers by State</h4><a href="/carriers/">All 50 states</a>' . $c2 . '</div>'
        . '<div class="footer-col"><h4>Company</h4><a href="/about.html">About Us</a><a href="/contact.html">Contact</a><a href="/blog/">Blog</a><a href="/get-listed">Get Listed</a><a href="/advertise">Advertise</a><a href="/privacy.html">Privacy Policy</a><a href="/terms.html">Terms of Service</a><a href="/carriers/search">Search Directory</a></div>'
        . '</div><div class="footer-bottom"><span>&copy; ' . date('Y') . ' Trucking Directory USA. All rights reserved.</span>'
        . '<span>Serving all 50 states' . ($total ? ' &middot; ' . nf($total) . ' carrier records' : '') . '</span></div></footer>';
}

/**
 * $o keys: title, description, canonical (path), robots ('index,follow' default), jsonld (array of arrays),
 *          cache (bool, default true), h1 / hero (optional rendered by caller)
 */
function page_start(array $o): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        $cache = (int)cfg('cache_seconds', 0);
        if (($o['cache'] ?? true) && $cache > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Cache-Control: public, max-age=' . $cache);
        } else {
            header('Cache-Control: no-store');
        }
    }
    $title = $o['title'] ?? cfg('site_name');
    $desc = $o['description'] ?? '';
    $canon = isset($o['canonical']) ? url($o['canonical']) : null;
    $robots = $o['robots'] ?? 'index,follow';
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n";
    echo '<title>' . e($title) . "</title>\n";
    if ($desc !== '') {
        echo '<meta name="description" content="' . e($desc) . "\">\n";
    }
    echo '<meta name="robots" content="' . e($robots) . "\">\n";
    if ($canon) {
        echo '<link rel="canonical" href="' . e($canon) . "\">\n";
        echo '<meta property="og:url" content="' . e($canon) . "\">\n";
    }
    echo '<meta property="og:title" content="' . e($title) . "\">\n";
    echo '<meta property="og:site_name" content="' . e((string)cfg('site_name')) . "\">\n";
    echo '<link rel="icon" href="/favicon.svg" type="image/svg+xml">' . "\n";
    foreach ($o['jsonld'] ?? [] as $ld) {
        echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
    echo '<style>' . site_css() . "</style>\n";
    $ga = (string)cfg('ga_id');
    if ($ga !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ga) . '"></script>'
            . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . e($ga) . '");</script>' . "\n";
    }
    echo "</head>\n<body>\n" . nav_html() . "\n";
}

function page_end(): void
{
    echo "\n" . footer_html() . "\n</body>\n</html>";
}

/** $items: [[name, path|null], ...]; echoes the crumb trail and returns BreadcrumbList JSON-LD. */
function breadcrumbs_ld(array $items): array
{
    $list = [];
    $i = 1;
    foreach ($items as [$name, $path]) {
        $el = ['@type' => 'ListItem', 'position' => $i++, 'name' => $name];
        if ($path) {
            $el['item'] = url($path);
        }
        $list[] = $el;
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function breadcrumbs_html(array $items): string
{
    $parts = [];
    foreach ($items as [$name, $path]) {
        $parts[] = $path ? '<a href="' . e($path) . '">' . e($name) . '</a>' : '<span>' . e($name) . '</span>';
    }
    return '<div class="crumbs">' . implode(' &rsaquo; ', $parts) . '</div>';
}

function carrier_card(array $c, bool $showCity = true): string
{
    $name = company_name($c['legal_name']);
    $h = '<div class="listing-card"><a class="card-name" href="' . e(carrier_path($c)) . '">' . e($name) . '</a>';
    if (!empty($c['dba_name'])) {
        $h .= '<div class="small">DBA ' . e(company_name($c['dba_name'])) . '</div>';
    }
    if ($showCity) {
        $h .= '<div class="card-location">&#128205; ' . e(title_case($c['phy_city'])) . ', ' . e($c['phy_state']) . '</div>';
    }
    $h .= '<div class="card-meta"><span>USDOT ' . (int)$c['dot'] . '</span>';
    if (!empty($c['mc_number'])) {
        $h .= '<span>MC ' . e($c['mc_number']) . '</span>';
    }
    if (!empty($c['power_units'])) {
        $h .= '<span>' . nf($c['power_units']) . ' power unit' . ((int)$c['power_units'] === 1 ? '' : 's') . '</span>';
    }
    if (!empty($c['drivers'])) {
        $h .= '<span>' . nf($c['drivers']) . ' driver' . ((int)$c['drivers'] === 1 ? '' : 's') . '</span>';
    }
    $h .= '</div>';
    $cargo = cargo_list($c['cargo'] ?? null);
    if ($cargo) {
        $names = array_map(fn($t) => $t['name'], array_slice($cargo, 0, 3));
        $h .= '<div class="small">' . e(implode(' · ', $names)) . (count($cargo) > 3 ? ' +' . (count($cargo) - 3) : '') . '</div>';
    }
    $h .= '<div class="card-actions">';
    if (!empty($c['phone'])) {
        $h .= '<a class="card-phone" href="tel:+1' . e($c['phone']) . '">&#128222; ' . e(fmt_phone($c['phone'])) . '</a>';
    }
    $h .= '<a class="card-web" href="' . e(carrier_path($c)) . '">View profile &rarr;</a></div></div>';
    return $h;
}

/** Vendor/sponsor block: up to $max service categories relevant to carriers in $state. */
function sponsor_block(string $state, ?int $dot = null, array $types = ['factoring', 'insurance', 'dispatch'], int $max = 3): string
{
    $sponsors = $GLOBALS['SPONSORS'];
    $h = '<div class="card"><h3>Services for carriers in ' . e(state_name($state)) . '</h3><div class="sponsor">';
    $n = 0;
    foreach ($types as $type) {
        if ($n >= $max || !isset($sponsors[$type])) {
            continue;
        }
        $cfgS = $sponsors[$type];
        $cat = QUOTE_TO_CATEGORY[$type] ?? null;
        $paid = $cat ? vendors_for($cat, $state, 1) : [];
        $house = array_values(array_filter($cfgS['items'], fn($it) => $it['states'] === 'ALL' || in_array($state, (array)$it['states'], true)));
        $h .= '<div class="slot' . ($paid ? ' paid' : '') . '"><span class="tag">' . e($cfgS['label']) . ($paid ? ' · sponsored' : '') . '</span>';
        if ($paid) {
            $v = $paid[0];
            $h .= '<b>' . e($v['name']) . '</b><p>' . e((string)$v['blurb']) . '</p>';
            if (!empty($v['website'])) {
                $h .= '<a class="btn btn-outline" rel="nofollow sponsored" href="' . e($v['website']) . '">Visit website</a>';
            } elseif (!empty($v['phone'])) {
                $h .= '<a class="btn btn-outline" href="tel:' . e(preg_replace('/\D/', '', $v['phone'])) . '">Call ' . e($v['phone']) . '</a>';
            }
        } elseif ($house) {
            $v = $house[0];
            $h .= '<b>' . e($v['name']) . '</b><p>' . e($v['blurb']) . '</p><a class="btn btn-outline" rel="sponsored" href="' . e($v['url']) . '">Learn more</a>';
        } else {
            $h .= '<b>' . e($cfgS['blurb']) . '</b><p>Compare offers from providers serving ' . e(state_name($state)) . ' carriers.</p>'
                . '<a class="btn btn-outline" href="/quote/' . e($type) . '?state=' . e($state) . ($dot ? '&dot=' . $dot : '') . '">Get quotes</a>';
        }
        $h .= '</div>';
        $n++;
    }
    $h .= '</div><p class="small" style="margin-top:10px">Provider? <a href="/advertise">Put your company in front of ' . e(state_name($state)) . ' carriers &rarr;</a></p></div>';
    return $h;
}

/** CarrierDataUSA funnel box for a state (uses stats_state row). */
function data_cta(string $state, ?array $st = null): string
{
    $st = $st ?? q1('SELECT * FROM stats_state WHERE state = ?', [$state]);
    $n = $st ? (int)$st['n'] : 0;
    $parts = [];
    if ($st && (int)$st['n_email'] > 0) {
        $parts[] = nf($st['n_email']) . ' with email';
    }
    if ($st && (int)$st['n_cell'] > 0) {
        $parts[] = nf($st['n_cell']) . ' with cell phone';
    }
    if ($st && (int)$st['n_officer'] > 0) {
        $parts[] = nf($st['n_officer']) . ' with officer names';
    }
    $h = '<div class="cta-box"><h3>Download every ' . e(state_name($state)) . ' carrier as a spreadsheet</h3>'
        . '<p>' . nf($n) . ' FMCSA-registered carriers in ' . e(state_name($state)) . ($parts ? ' &mdash; ' . e(implode(', ', $parts)) : '')
        . '. Filter by fleet size, cargo type, hazmat and authority. Instant CSV download from CarrierDataUSA, our data company.</p>'
        . '<a class="btn btn-gold" rel="nofollow" href="' . e(cdusa_state_url($state)) . '">Get the ' . e(state_name($state)) . ' list &rarr;</a> '
        . '<a class="btn btn-outline" href="/carrier-data/">See all data packages</a></div>';
    return $h;
}

function claim_box(array $c, ?array $claim): string
{
    if ($claim) {
        return '<div class="cta-box" style="border-color:rgba(46,204,113,0.5)"><h3>&#10004; Verified listing</h3>'
            . '<p>This profile was claimed and verified by ' . e(company_name($c['legal_name'])) . '.</p></div>';
    }
    return '<div class="cta-box"><h3>Is this your company?</h3>'
        . '<p>Claim this page to add your website, logo and email, get a Verified badge, correct your details and remove competitor offers from your profile. From $' . (int)cfg('prices')['claim_verified'] . '/year.</p>'
        . '<a class="btn" href="/claim/' . (int)$c['dot'] . '">Claim this listing &rarr;</a></div>';
}

function pager_html(string $base, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $h = '<div class="pager">';
    if ($page > 1) {
        $h .= '<a href="' . e(page_url($base, $page - 1)) . '" rel="prev">&laquo; Prev</a>';
    }
    $start = max(1, $page - 3);
    $end = min($pages, $page + 3);
    if ($start > 1) {
        $h .= '<a href="' . e(page_url($base, 1)) . '">1</a>' . ($start > 2 ? '<span>&hellip;</span>' : '');
    }
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_url($base, $i)) . '">' . $i . '</a>';
    }
    if ($end < $pages) {
        $h .= ($end < $pages - 1 ? '<span>&hellip;</span>' : '') . '<a href="' . e(page_url($base, $pages)) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        $h .= '<a href="' . e(page_url($base, $page + 1)) . '" rel="next">Next &raquo;</a>';
    }
    return $h . '</div>';
}

function stats_strip(array $items): string
{
    $h = '<div class="stats">';
    foreach ($items as [$value, $label]) {
        $h .= '<div class="stat"><b>' . e((string)$value) . '</b><span>' . e($label) . '</span></div>';
    }
    return $h . '</div>';
}

function not_found(string $msg = 'Page not found'): void
{
    http_response_code(404);
    page_start(['title' => 'Not found | ' . cfg('site_name'), 'robots' => 'noindex', 'cache' => false]);
    echo '<div class="container section"><h1 style="color:#fff">404</h1><p>' . e($msg) . '</p><p><a href="/carriers/">Browse carriers by state</a> or <a href="/carriers/search">search</a>.</p></div>';
    page_end();
    exit;
}

function form_hidden(): string
{
    return '<input type="hidden" name="_t" value="' . e(csrf_token()) . '"><div class="hp"><label>Leave this field empty<input type="text" name="website_url_hp" tabindex="-1" autocomplete="off"></label></div>';
}
