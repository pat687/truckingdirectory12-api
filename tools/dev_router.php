<?php
/**
 * Local preview without Apache: emulates the .htaccess rewrite rules for PHP's built-in server.
 *   cd site && php -S 127.0.0.1:8080 ../tools/dev_router.php
 * Then open http://127.0.0.1:8080/carriers/
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = ltrim($path, '/');
$cats = 'equipment_dealers|freight_brokers|dispatch_services|insurance_brokers|finance_lenders|equipment_brokers|auto_transport_brokers|third_party_logistics|load_boards|customs_brokers|factoring_companies|fuel_cards|compliance_services|truck_repair_services|trucking_software|shippers';
$rules = [
    ['#^carriers/?$#', ['r' => 'carriers']],
    ['#^carriers/search/?$#', ['r' => 'search']],
    ['#^carriers/([a-z-]+)/?$#', ['r' => 'state', 'state' => 1]],
    ['#^carriers/([a-z-]+)/cities/?$#', ['r' => 'cities', 'state' => 1]],
    ['#^carriers/([a-z-]+)/cargo/([a-z0-9-]+)/?$#', ['r' => 'cargo', 'state' => 1, 'cargo' => 2]],
    ['#^carriers/([a-z-]+)/([a-z0-9-]+)/?$#', ['r' => 'city', 'state' => 1, 'city' => 2]],
    ['#^carrier/([0-9]+)(?:/([a-z0-9-]*))?/?$#', ['r' => 'carrier', 'dot' => 1, 'slug' => 2]],
    ['#^claim/thanks/?$#', ['r' => 'claim_thanks']],
    ['#^claim/([0-9]+)/?$#', ['r' => 'claim', 'dot' => 1]],
    ['#^quote/([a-z-]+)/?$#', ['r' => 'quote', 'type' => 1]],
    ['#^get-listed/?$#', ['r' => 'get_listed']],
    ['#^advertise/?$#', ['r' => 'advertise']],
    ['#^carrier-data/?$#', ['r' => 'packages']],
    ['#^admin/?$#', ['r' => 'admin']],
    ['#^stripe-webhook/?$#', ['r' => 'webhook']],
    ['#^sitemap-carriers\.xml$#', ['r' => 'sitemap_index']],
    ['#^sitemap-carriers-([a-z0-9-]+)\.xml$#', ['r' => 'sitemap', 'part' => 1]],
    ['#^(' . $cats . ')/?(index\.html)?$#', ['r' => 'vendor_index', 'cat' => 1]],
    ['#^(' . $cats . ')/([a-z-]+)\.html$#', ['r' => 'vendor_state', 'cat' => 1, 'state' => 2]],
];
foreach ($rules as [$re, $map]) {
    if (preg_match($re, $path, $m)) {
        foreach ($map as $k => $v) {
            $_GET[$k] = is_int($v) ? ($m[$v] ?? '') : $v;
        }
        $_SERVER['SCRIPT_NAME'] = '/app/index.php';
        require __DIR__ . '/../site/app/index.php';
        return true;
    }
}
if ($path === '' || $path === 'index.html') {
    header('Location: /carriers/');
    return true;
}
return false; // let the built-in server serve static files
