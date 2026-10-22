<?php
/**
 * Front controller. All /carrier*, /claim, /quote, /sitemap-carriers*, /admin, vendor category
 * pages etc. are rewritten here by .htaccess with ?r=<route>.
 */
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

$routes = [
    'carriers'      => 'carriers.php',      // /carriers/
    'state'         => 'state.php',         // /carriers/georgia/
    'cities'        => 'cities.php',        // /carriers/georgia/cities/
    'city'          => 'city.php',          // /carriers/georgia/marietta/
    'cargo'         => 'cargo.php',         // /carriers/georgia/cargo/livestock/
    'carrier'       => 'carrier.php',       // /carrier/1234567/abc-trucking-llc
    'search'        => 'search.php',        // /carriers/search?q=
    'sitemap_index' => 'sitemap.php',       // /sitemap-carriers.xml
    'sitemap'       => 'sitemap.php',       // /sitemap-carriers-<part>.xml
    'claim'         => 'claim.php',         // /claim/1234567
    'claim_thanks'  => 'claim_thanks.php',  // /claim/thanks
    'quote'         => 'quote.php',         // /quote/factoring
    'get_listed'    => 'get_listed.php',    // /get-listed
    'advertise'     => 'advertise.php',     // /advertise
    'packages'      => 'packages.php',      // /carrier-data/
    'vendor_index'  => 'vendor_index.php',  // /factoring_companies/
    'vendor_state'  => 'vendor_state.php',  // /factoring_companies/georgia.html
    'opt_out'       => 'opt_out.php',       // /opt-out
    'terms'         => 'terms.php',         // /terms
    'privacy'       => 'privacy.php',       // /privacy
    'admin'         => 'admin.php',         // /admin
    'webhook'       => 'webhook.php',       // /stripe-webhook
];

$r = (string)($_GET['r'] ?? 'carriers');
if (!isset($routes[$r])) {
    not_found();
}

// Canonical case: every public URL is lower-case. .htaccess matches case-insensitively ([NC]) so
// /Carriers/Georgia/ reaches us; send it to /carriers/georgia/ once (GET/HEAD only, query kept).
$reqPath = request_path();
if ($reqPath !== strtolower($reqPath) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    // Keep the CLIENT's query string. $_SERVER['QUERY_STRING'] holds the rewritten one (r=state&state=...)
    // under Apache/LiteSpeed; REQUEST_URI is the original request.
    $qs = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?? '');
    header('Location: ' . url(strtolower($reqPath) . ($qs !== '' ? '?' . $qs : '')), true, 301);
    exit;
}
// Route parameters that come from the path are lower-case too (belt and braces for [NC] matches).
foreach (['state', 'city', 'cargo', 'slug', 'cat', 'part'] as $k) {
    if (isset($_GET[$k]) && is_string($_GET[$k])) {
        $_GET[$k] = strtolower($_GET[$k]);
    }
}
try {
    require __DIR__ . '/routes/' . $routes[$r];
} catch (CarriersDbMissing $e) {
    // Only routes that actually touch carriers.db end up here; admin, webhook, forms and legal pages keep working.
    service_unavailable($e->getMessage());
}
