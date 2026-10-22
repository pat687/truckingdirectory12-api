<?php
/**
 * Local preview without Apache: emulates site/.htaccess for PHP's built-in server - the route
 * rewrites, the canonical 301s (trailing slashes, old vendor URLs), the query-string guards and
 * the hardening denies (/app/ internals, databases, keys, config files). Keep it in step with
 * .htaccess; tools/smoke.sh runs the key routes through it.
 *   cd site && php -S 127.0.0.1:8080 ../tools/dev_router.php
 * Then open http://127.0.0.1:8080/carriers/
 */
declare(strict_types=1);

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    // Malformed request line (e.g. "//[" ): answer 400 instead of letting ltrim() throw a TypeError.
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo "400 Bad Request\n";
    return true;
}
$raw = ltrim($path, '/');          // original spelling (Apache keeps it in $1; app/index.php lower-cases)
$lc = strtolower($raw);            // for the case-insensitive ([NC]) comparisons below
$query = (string)($_SERVER['QUERY_STRING'] ?? '');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isGet = in_array($method, ['GET', 'HEAD'], true);
$cats = 'equipment_dealers|freight_brokers|dispatch_services|insurance_brokers|finance_lenders|equipment_brokers|auto_transport_brokers|third_party_logistics|load_boards|customs_brokers|factoring_companies|fuel_cards|compliance_services|truck_repair_services|trucking_software|shippers';

$deny = function (int $code): bool {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $code . ($code === 404 ? " Not Found\n" : " Forbidden\n");
    return true;
};
$redirect = function (string $to) use ($query): bool {
    header('Location: ' . $to . ($query !== '' ? '?' . $query : ''), true, 301);
    return true;
};

// --- Hardening (mirrors the guard, RedirectMatch and FilesMatch blocks) -------------------------
if (preg_match('#^app(/|$)#', $lc)) {                       // the front controller is reached only via rewrites
    return $deny(404);
}
if (preg_match('#(^|/)(\.ht|error_log$|php_errorlog$)#', $lc)) {
    return $deny(403);
}
if (!preg_match('#(^|/)(robots|ads|app-ads|llms|security|humans)\.txt$#', $lc) && preg_match('#\.(db|db-wal|db-shm|sqlite|log|bak|sql|py|md|txt|key)$#', $lc)
    || preg_match('#(^|/)public_html-upload\.zip$#', $lc)) {
    return $deny(403);
}
if (preg_match('#(^|/)(config|config\.local|categories|sponsors)\.php$#', $lc)) {
    return $deny(404);
}
// Query strings must not smuggle route parameters past the rewrites.
if (preg_match('/(^|&)r=/i', $query)) {
    return $deny(404);
}
if (!preg_match('#^(quote/|carriers/search|get-listed|advertise)#', $lc) && preg_match('/(^|&)(state|city|dot|slug|cargo|cat|part|type)=/i', $query)) {
    return $deny(404);
}

// --- Canonical URL variants (301, GET/HEAD only) --------------------------------------------
if ($isGet) {
    if ($lc !== 'carriers/search' && preg_match('#^(carriers|carriers/[a-z-]+|carriers/[a-z-]+/cities|carriers/[a-z-]+/cargo/[a-z0-9-]+|carriers/[a-z-]+/[a-z0-9-]+|carrier-data|' . $cats . ')$#i', $raw)) {
        return $redirect('/' . $raw . '/');                   // hub pages end with a slash
    }
    if (preg_match('#^(carrier/[0-9]+(/[a-z0-9-]*)?)/$#i', $raw, $m)) {
        return $redirect('/' . $m[1]);                        // profiles never end with a slash
    }
    if (preg_match('#^(' . $cats . ')(?:\.html|/index\.(?:html|php))$#i', $raw, $m)) {
        return $redirect('/' . $m[1] . '/');                  // old static vendor URLs
    }
}

// --- Routes: [regex, params, keep query string (QSA)] -------------------------------------------
$rules = [
    ['#^carriers/$#i', ['r' => 'carriers'], false],
    ['#^carriers/search/?$#i', ['r' => 'search'], true],
    ['#^carriers/([a-z-]+)/$#i', ['r' => 'state', 'state' => 1], false],
    ['#^carriers/([a-z-]+)/cities/$#i', ['r' => 'cities', 'state' => 1], false],
    ['#^carriers/([a-z-]+)/cargo/([a-z0-9-]+)/$#i', ['r' => 'cargo', 'state' => 1, 'cargo' => 2], true],
    ['#^carriers/([a-z-]+)/([a-z0-9-]+)/$#i', ['r' => 'city', 'state' => 1, 'city' => 2], true],
    ['#^carrier/([0-9]+)(?:/([a-z0-9-]*))?$#i', ['r' => 'carrier', 'dot' => 1, 'slug' => 2], false],
    ['#^claim/thanks/?$#i', ['r' => 'claim_thanks'], true],
    ['#^claim/([0-9]+)/?$#i', ['r' => 'claim', 'dot' => 1], false],
    ['#^quote/([a-z-]+)/?$#i', ['r' => 'quote', 'type' => 1], true],
    ['#^get-listed/?$#i', ['r' => 'get_listed'], true],
    ['#^advertise/?$#i', ['r' => 'advertise'], true],
    ['#^carrier-data/$#i', ['r' => 'packages'], false],
    ['#^opt-out/?$#i', ['r' => 'opt_out'], false],
    ['#^terms/?$#i', ['r' => 'terms'], false],
    ['#^privacy/?$#i', ['r' => 'privacy'], false],
    ['#^admin/?$#i', ['r' => 'admin'], true],
    ['#^stripe-webhook/?$#', ['r' => 'webhook'], false],
    ['#^sitemap-carriers\.xml$#i', ['r' => 'sitemap_index'], false],
    ['#^sitemap-carriers-([a-z0-9-]+)\.xml$#i', ['r' => 'sitemap', 'part' => 1], false],
    ['#^(' . $cats . ')/$#i', ['r' => 'vendor_index', 'cat' => 1], false],
    ['#^(' . $cats . ')/([a-z-]+)\.html$#i', ['r' => 'vendor_state', 'cat' => 1, 'state' => 2], false],
];
foreach ($rules as [$re, $map, $qsa]) {
    if (preg_match($re, $raw, $m)) {
        if (!$qsa) {
            $_GET = [];   // without QSA Apache replaces the query string with the rule's own parameters
        }
        foreach ($map as $k => $v) {
            $_GET[$k] = is_int($v) ? ($m[$v] ?? '') : $v;
        }
        $_SERVER['SCRIPT_NAME'] = '/app/index.php';
        require __DIR__ . '/../site/app/index.php';
        return true;
    }
}
if ($lc === '' || $lc === 'index.html') {
    header('Location: /carriers/', true, 302);
    return true;
}
return false; // let the built-in server serve static files
