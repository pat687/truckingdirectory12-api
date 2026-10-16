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
    'admin'         => 'admin.php',         // /admin
    'webhook'       => 'webhook.php',       // /stripe-webhook
];

$r = (string)($_GET['r'] ?? 'carriers');
if (!isset($routes[$r])) {
    not_found();
}
require __DIR__ . '/routes/' . $routes[$r];
