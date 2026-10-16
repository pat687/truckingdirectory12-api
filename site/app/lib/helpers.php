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

function current_page(): int
{
    $p = (int)($_GET['page'] ?? 1);
    return $p < 1 ? 1 : min($p, 5000);
}

/** Returns [offset, pages] */
function paginate(int $total, int $page, int $per): array
{
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    return [($page - 1) * $per, $pages, $page];
}

function page_url(string $base, int $page): string
{
    return $page <= 1 ? $base : $base . '?page=' . $page;
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

function post(string $k, int $max = 500): string
{
    $v = $_POST[$k] ?? '';
    $v = is_string($v) ? trim($v) : '';
    return mb_substr($v, 0, $max);
}

function valid_email(string $e): bool
{
    return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function send_notification(string $subject, string $body): void
{
    $to = (string)cfg('notify_email');
    if ($to === '') {
        return;
    }
    $headers = 'From: ' . cfg('site_name') . ' <' . cfg('from_email') . ">\r\nReply-To: " . cfg('from_email') . "\r\n";
    @mail($to, '[TDUSA] ' . $subject, $body, $headers);
}

function csrf_token(): string
{
    return hash_hmac('sha256', 'form', (string)cfg('admin_pass') . (string)cfg('site_url'));
}

/** Generic, low-friction spam check: honeypot must be empty and the token must match. */
function form_ok(): bool
{
    return ($_POST['website_url_hp'] ?? '') === '' && hash_equals(csrf_token(), (string)($_POST['_t'] ?? ''));
}
