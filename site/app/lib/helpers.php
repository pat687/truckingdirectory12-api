<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    global $CONFIG;
    return array_key_exists($key, $CONFIG) ? $CONFIG[$key] : $default;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nf($n): string
{
    return number_format((int)$n);
}

function slugify(string $text, int $maxlen = 80): string
{
    $text = strtolower($text);
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > $maxlen) {
        $cut = substr($text, 0, $maxlen);
        $pos = strrpos($cut, '-');
        $text = $pos ? substr($cut, 0, $pos) : $cut;
    }
    return $text === '' ? 'carrier' : $text;
}

/** "MC DONOUGH" -> "Mc Donough", "ST. LOUIS" -> "St. Louis" */
function title_case(?string $s): string
{
    if ($s === null || $s === '') {
        return '';
    }
    $t = ucwords(strtolower($s), " -'.");
    // keep common all-caps tokens
    return preg_replace_callback('/\b(Llc|Inc|Ltd|Dba|Usa|Us|Llp|Pllc|Co|Corp)\b\.?/', fn($m) => strtoupper($m[0]), $t) ?? $t;
}

/** Company names from FMCSA are upper case; show them in title case but keep LLC/INC. */
function company_name(?string $s): string
{
    return title_case($s);
}

function fmt_phone(?string $digits): string
{
    if (!$digits || strlen($digits) !== 10) {
        return '';
    }
    return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
}

/** Docket prefix for a carrier row: MC, MX or FF (stored by the trimmer in mc_prefix; NULL/unknown = MC). */
function docket_prefix(array $c): string
{
    $p = strtoupper(trim((string)($c['mc_prefix'] ?? '')));
    return in_array($p, ['MC', 'MX', 'FF'], true) ? $p : 'MC';
}

/** "MC-123456" / "MX-123456" / "FF-123456", or '' when the carrier has no docket number. */
function docket_label(array $c, string $sep = '-'): string
{
    if (empty($c['mc_number'])) {
        return '';
    }
    return docket_prefix($c) . $sep . $c['mc_number'];
}

/** "1 Carrier" / "12 Carriers" (singular/plural with a formatted count). */
function plural(int $n, string $singular, ?string $plural = null): string
{
    return nf($n) . ' ' . ($n === 1 ? $singular : ($plural ?? $singular . 's'));
}

/**
 * Page title kept under 60 characters where possible: "$core | $detail$pageSuffix | Site name".
 * The detail segment (e.g. a count) is dropped first; the site name is dropped only when
 * $brandOptional (carrier profiles, whose names already carry the identity).
 */
function seo_title(string $core, string $detail = '', string $pageSuffix = '', bool $brandOptional = false): string
{
    $brand = (string)cfg('site_name');
    $t = $core . ($detail !== '' ? ' | ' . $detail : '') . $pageSuffix . ' | ' . $brand;
    if (strlen($t) > 60 && $detail !== '') {
        $t = $core . $pageSuffix . ' | ' . $brand;
    }
    if (strlen($t) > 60 && $brandOptional) {
        $t = $core . ($detail !== '' ? ' | ' . $detail : '') . $pageSuffix;
    }
    return $t;
}

function fmt_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime($iso);
    return $t ? date('M j, Y', $t) : $iso;
}

function fleet_label(?int $pu): string
{
    if ($pu === null || $pu <= 0) {
        return 'fleet size not reported';
    }
    if ($pu === 1) {
        return 'owner-operator (1 power unit)';
    }
    if ($pu <= 5) {
        return 'small fleet (' . $pu . ' power units)';
    }
    if ($pu <= 20) {
        return 'mid-size fleet (' . $pu . ' power units)';
    }
    if ($pu <= 100) {
        return 'large fleet (' . $pu . ' power units)';
    }
    return 'major fleet (' . nf($pu) . ' power units)';
}

function fleet_bucket(?int $pu): string
{
    if ($pu === null || $pu <= 0) {
        return 'Not reported';
    }
    if ($pu === 1) {
        return 'Owner-operator';
    }
    if ($pu <= 5) {
        return '2-5 trucks';
    }
    if ($pu <= 20) {
        return '6-20 trucks';
    }
    if ($pu <= 100) {
        return '21-100 trucks';
    }
    return '100+ trucks';
}

function operation_label(?string $code): string
{
    return match ($code) {
        'A' => 'Interstate',
        'B' => 'Intrastate (Hazmat)',
        'C' => 'Intrastate (Non-Hazmat)',
        default => 'Not reported',
    };
}

function safety_label(?string $code): string
{
    return match ($code) {
        'S' => 'Satisfactory',
        'C' => 'Conditional',
        'U' => 'Unsatisfactory',
        'N' => 'Not Rated',
        default => 'Not Rated',
    };
}

const OPCLASS_LABELS = [
    'AUTH_FOR_HIRE' => 'Authorized For-Hire', 'EXEMPT_FOR_HIRE' => 'Exempt For-Hire',
    'PRIVATE_PROPERTY' => 'Private (Property)', 'PRIVATE_PASS_BUS' => 'Private Passenger (Business)',
    'PRIVATE_PASS_NONBUS' => 'Private Passenger (Non-Business)', 'MIGRANT' => 'Migrant',
    'US_MAIL' => 'U.S. Mail', 'FEDERAL_GOVT' => 'Federal Government', 'STATE_GOVT' => 'State Government',
    'LOCAL_GOVT' => 'Local Government', 'INDIAN_TRIBE' => 'Indian Tribe', 'OTHER' => 'Other',
];

function opclass_labels(?string $codes): array
{
    if (!$codes) {
        return [];
    }
    $out = [];
    foreach (explode(',', $codes) as $c) {
        $out[] = OPCLASS_LABELS[$c] ?? ucwords(strtolower(str_replace('_', ' ', $c)));
    }
    return $out;
}

/** cargo code => ['id'=>..,'name'=>..,'slug'=>..] (cached per request) */
function cargo_types(): array
{
    static $types = null;
    if ($types === null) {
        $types = [];
        foreach (db()->query('SELECT id, code, name, slug FROM cargo_types ORDER BY id') as $r) {
            $types[$r['code']] = $r;
        }
    }
    return $types;
}

function cargo_by_slug(string $slug): ?array
{
    foreach (cargo_types() as $t) {
        if ($t['slug'] === $slug) {
            return $t;
        }
    }
    return null;
}

function cargo_by_id(int $id): ?array
{
    foreach (cargo_types() as $t) {
        if ((int)$t['id'] === $id) {
            return $t;
        }
    }
    return null;
}

/** Cargo codes "GENFREIGHT,BLDGMAT" => list of type rows */
function cargo_list(?string $codes): array
{
    if (!$codes) {
        return [];
    }
    $types = cargo_types();
    $out = [];
    foreach (explode(',', $codes) as $c) {
        if (isset($types[$c])) {
            $out[] = $types[$c];
        }
    }
    return $out;
}

function range_labels(?string $flags): array
{
    $map = ['INTER_BEYOND' => 'Interstate, beyond 100 miles', 'INTER_WITHIN' => 'Interstate, within 100 miles',
            'INTRA_BEYOND' => 'Intrastate, beyond 100 miles', 'INTRA_WITHIN' => 'Intrastate, within 100 miles'];
    $out = [];
    foreach ($flags ? explode(',', $flags) : [] as $f) {
        if (isset($map[$f])) {
            $out[] = $map[$f];
        }
    }
    return $out;
}

// ---------------------------------------------------------------- URLs
function url(string $path): string
{
    return rtrim((string)cfg('site_url'), '/') . $path;
}

function carrier_path(array $c): string
{
    return '/carrier/' . (int)$c['dot'] . '/' . $c['slug'];
}

function state_path(string $abbr): string
{
    return '/carriers/' . state_slug($abbr) . '/';
}

function city_path(string $abbr, string $citySlug): string
{
    return '/carriers/' . state_slug($abbr) . '/' . $citySlug . '/';
}

function cargo_path(string $abbr, string $cargoSlug): string
{
    return '/carriers/' . state_slug($abbr) . '/cargo/' . $cargoSlug . '/';
}

function vendor_state_path(string $cat, string $abbr): string
{
    return '/' . $cat . '/' . state_slug($abbr) . '.html';
}

function safer_url(int $dot): string
{
    return 'https://safer.fmcsa.dot.gov/query.asp?searchtype=ANY&query_type=queryCarrierSnapshot&query_param=USDOT&query_string=' . $dot;
}

function cdusa_state_url(string $abbr): string
{
    $c = cfg('cdusa');
    if (!empty($c['state_links'][$abbr])) {
        return $c['state_links'][$abbr];
    }
    return str_replace(['{state}', '{state_slug}'], [$abbr, state_slug($abbr)], $c['state_pattern']);
}

/** Path component of the current request (no query string); '/' when the URI is malformed. */
function request_path(): string
{
    $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return is_string($p) && $p !== '' ? $p : '/';
}

function current_page(): int
{
    $p = (int)($_GET['page'] ?? 1);
    return $p < 1 ? 1 : min($p, 5000);
}

/**
 * Returns [offset, pages, page]. When $base is given and ?page points past the last page,
 * the request is 301-redirected to the last page instead of serving a duplicate empty page.
 */
function paginate(int $total, int $page, int $per, ?string $base = null): array
{
    $pages = max(1, (int)ceil($total / max(1, $per)));
    if ($page > $pages) {
        if ($base !== null && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            header('Location: ' . url(page_url($base, $pages)), true, 301);
            exit;
        }
        $page = $pages;
    }
    return [($page - 1) * $per, $pages, $page];
}

function page_url(string $base, int $page): string
{
    return $page <= 1 ? $base : $base . '?page=' . $page;
}

/**
 * Client address for rate limiting and dispute evidence. REMOTE_ADDR unless the site is
 * provably behind Cloudflare (config 'behind_cloudflare' => true), because any client can
 * send a CF-Connecting-IP header.
 */
function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (cfg('behind_cloudflare') === true && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf = (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($cf, FILTER_VALIDATE_IP) !== false) {
            $ip = $cf;
        }
    }
    return substr($ip, 0, 45);
}

/** Browser user agent, truncated, control characters removed (stored next to the IP as evidence). */
function user_agent(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ua = preg_replace('/[\x00-\x1F\x7F]/', '', $ua) ?? '';
    return mb_substr($ua, 0, 200);
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Trimmed POST field with control characters removed. $multiline keeps line breaks
 * (notes, blurb); everything else is forced onto one line.
 */
function post(string $k, int $max = 500, bool $multiline = false): string
{
    $v = $_POST[$k] ?? '';
    $v = is_string($v) ? $v : '';
    if ($multiline) {
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $v) ?? '';
    } else {
        $v = preg_replace('/[\x00-\x1F\x7F]/', ' ', $v) ?? '';
    }
    return mb_substr(trim($v), 0, $max);
}

function valid_email(string $e): bool
{
    return $e !== '' && strlen($e) <= 200 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/** Website URL check shared by the claim and vendor forms (after the https:// prefix was added). */
function valid_website(string $url): bool
{
    return strlen($url) <= 200 && preg_match('~^https?://[A-Za-z0-9.-]+(?::\d+)?(?:/[^\s<>"\'`]*)?$~', $url) === 1;
}

/** A Payment Link is usable only when it is set, points at Stripe and is not the shipped placeholder. */
function stripe_link_ok(?string $link): bool
{
    $link = (string)$link;
    return $link !== '' && !str_contains($link, 'REPLACE_ME') && str_starts_with($link, 'https://');
}

/**
 * Plain-text mail with safe headers. Returns false (and logs) when the address is bad or mail() failed.
 * From = from_email (a mailbox on this domain so SPF passes), Reply-To = the public support address.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if (!valid_email($to)) {
        error_log('TDUSA mail: refused invalid recipient');
        return false;
    }
    $subject = trim(str_replace(["\r", "\n", "\t"], ' ', $subject));
    $subject = preg_replace('/[\x00-\x1F\x7F]/', '', $subject) ?? '';
    $subject = mb_substr($subject, 0, 200);
    $from = trim(str_replace(["\r", "\n"], '', (string)cfg('from_email')));
    $reply = trim(str_replace(["\r", "\n"], '', (string)(cfg('contact_email') ?: $from)));
    $name = preg_replace('/[^\w .-]/', '', (string)cfg('site_name')) ?? 'Trucking Directory USA';
    $headers = 'From: ' . $name . ' <' . $from . ">\r\n"
        . 'Reply-To: ' . $reply . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        error_log('TDUSA mail: mail() returned false for subject "' . $subject . '"');
    }
    return $ok;
}

/** Owner notification (private notify_email). */
function send_notification(string $subject, string $body): bool
{
    $to = (string)cfg('notify_email');
    if ($to === '') {
        return false;
    }
    return send_mail($to, '[TDUSA] ' . $subject, $body);
}

/**
 * Secret for the public form tokens: config 'form_secret', else app/storage/form.key
 * (created with random bytes on first use). Never derived from the admin password.
 */
function form_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $cfg = trim((string)cfg('form_secret'));
    if ($cfg !== '') {
        return $secret = $cfg;
    }
    $file = dirname((string)cfg('site_db_path')) . '/form.key';
    $val = is_file($file) ? trim((string)@file_get_contents($file)) : '';
    if (strlen($val) < 32) {
        $val = bin2hex(random_bytes(32));
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (@file_put_contents($file, $val . "\n", LOCK_EX) !== false) {
            @chmod($file, 0600);
        } else {
            error_log('TDUSA: cannot write ' . $file . '; set form_secret in config.local.php');
        }
    }
    return $secret = $val;
}

/** Paragraphs for /terms and /privacy: config text (blank-line separated) when set, else the built-in defaults. */
function config_paragraphs(string $key, array $defaults): array
{
    $txt = trim((string)cfg($key, ''));
    if ($txt === '') {
        return $defaults;
    }
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    $out = [];
    foreach (preg_split('/\n\s*\n/', $txt) ?: [] as $p) {
        $p = trim(preg_replace('/\s+/', ' ', $p) ?? '');
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out ?: $defaults;
}

/** Hour-bucketed HMAC token for the public forms; $offset = -1 gives the previous hour. */
function csrf_token(int $offset = 0): string
{
    $windowHour = intdiv(time(), 3600) + $offset;
    return hash_hmac('sha256', 'form|' . $windowHour, form_secret());
}

/** Generic, low-friction spam check: honeypot must be empty and the token must be from this or the previous hour. */
function form_ok(): bool
{
    if (($_POST['website_url_hp'] ?? '') !== '') {
        return false;
    }
    $t = (string)($_POST['_t'] ?? '');
    return hash_equals(csrf_token(), $t) || hash_equals(csrf_token(-1), $t);
}

/**
 * Public-form throttle: 5 submissions per IP per hour and 20 per day. On the limit it sends
 * a plain 429 and stops, so nothing is inserted or mailed.
 */
/**
 * Two stages so a person who mistypes a field (or shares a network with a bot) is not locked out:
 *  - 'attempt' runs on every POST before validation: generous, stops floods (30/hour, 100/day per IP).
 *  - 'submit' runs only once a submission passed the token and field checks, right before the
 *    row is saved and mail is sent: strict (5/hour, 20/day per IP).
 */
function form_rate_limit_or_429(string $stage = 'submit'): void
{
    $ip = client_ip();
    if ($stage === 'attempt') {
        $okHour = rate_ok('form_try_h', $ip, 30, 3600);
        $okDay = rate_ok('form_try_d', $ip, 100, 86400);
    } else {
        $okHour = rate_ok('form_h', $ip, 5, 3600);
        $okDay = rate_ok('form_d', $ip, 20, 86400);
    }
    if ($okHour && $okDay) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(429);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    header('Retry-After: ' . ($okHour ? 86400 : 3600));
    echo "Too many submissions from your network. Please try again later or email " . (string)cfg('contact_email') . ".\n";
    exit;
}
