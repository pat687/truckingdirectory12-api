<?php
/** Admin panel: /admin  (HTTP Basic Auth, credentials in config.php or config.local.php) */
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// ---- authentication
$user = (string)cfg('admin_user');
$pass = (string)cfg('admin_pass');
$hash = (string)cfg('admin_pass_hash', '');
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW'] ?? '';
if ($u === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with($_SERVER['HTTP_AUTHORIZATION'], 'Basic ')) {
    [$u, $p] = array_pad(explode(':', (string)base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)), 2), 2, '');
}
$u = (string)$u;
$p = (string)$p;
$ip = client_ip();
$configured = $hash !== '' || ($pass !== '' && $pass !== 'change-me-now');
if (!$configured) {
    error_log('TDUSA admin: set admin_pass_hash (preferred) or a non-default admin_pass in app/config.local.php');
}

$challenge = function (): void {
    header('WWW-Authenticate: Basic realm="Trucking Directory USA admin"');
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Authentication required.';
    exit;
};
$tooMany = function (): void {
    http_response_code(429);
    header('Retry-After: 900');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Too many failed logins from your address. Try again in 15 minutes.';
    exit;
};

// Brute force: after 10 failed logins per IP in 15 minutes, refuse even to check credentials.
if (rate_hits('admin_fail', $ip, 900) >= 10) {
    $tooMany();
}
if ($u === '' && $p === '') {
    $challenge(); // first visit: the browser prompt, not counted as a failure
}
// Compare digests so hash_equals() does not leak the length of the real values.
$userOk = hash_equals(hash('sha256', $user), hash('sha256', $u));
$passOk = $hash !== '' ? password_verify($p, $hash) : ($configured && hash_equals(hash('sha256', $pass), hash('sha256', $p)));
if (!$configured || !$userOk || !$passOk) {
    error_log('TDUSA admin: failed login from ' . $ip);
    $over = !rate_ok('admin_fail', $ip, 10, 900);
    sleep(2);
    if ($over) {
        $tooMany();
    }
    $challenge();
}

// ---- per-login session token for the status forms (never the public form token)
ini_set('session.use_strict_mode', '1');
session_name('tdusa_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'domain' => '', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['admin_t']) || !is_string($_SESSION['admin_t'])) {
    $_SESSION['admin_t'] = bin2hex(random_bytes(32));
}
$adminToken = (string)$_SESSION['admin_t'];

$db = sitedb();
$tab = in_array($_GET['tab'] ?? '', ['claims', 'vendors', 'leads'], true) ? $_GET['tab'] : 'claims';
$statuses = ['claims' => ['pending', 'paid', 'review', 'active', 'rejected', 'disputed'], 'vendors' => ['pending', 'paid', 'review', 'active', 'rejected', 'disputed'], 'leads' => ['new', 'sold', 'contacted', 'closed']];

// ---- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteHost = strtolower((string)parse_url((string)cfg('site_url'), PHP_URL_HOST));
    $src = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($src === '' || $src === 'null') {
        $src = (string)($_SERVER['HTTP_REFERER'] ?? '');
    }
    $srcHost = strtolower((string)parse_url($src, PHP_URL_HOST));
    $sfs = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    $sameOrigin = $srcHost !== '' && $srcHost === $siteHost && ($sfs === '' || $sfs === 'same-origin');
    $tokenOk = hash_equals($adminToken, (string)($_POST['_t'] ?? ''));
    if (!$sameOrigin || !$tokenOk) {
        error_log('TDUSA admin: rejected POST from ' . $ip . ' (origin "' . $srcHost . '", token ' . ($tokenOk ? 'ok' : 'bad') . ')');
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Request rejected (origin or token mismatch). Open /admin again and retry.';
        exit;
    }
    $table = in_array($_POST['table'] ?? '', ['claims', 'vendors', 'leads'], true) ? $_POST['table'] : null;
    $id = (int)($_POST['id'] ?? 0);
    $status = preg_replace('/[^a-z]/', '', (string)($_POST['status'] ?? ''));
    if ($table && $id && in_array($status, $statuses[$table], true)) {
        $extra = $table === 'leads' ? '' : ', updated_at = datetime(\'now\')' . ($status === 'paid' ? ', paid_at = COALESCE(paid_at, datetime(\'now\'))' : '')
            . ($status === 'active' ? ', activated_at = COALESCE(activated_at, datetime(\'now\'))' : '');
        $db->prepare("UPDATE $table SET status = ?$extra WHERE id = ?")->execute([$status, $id]);
    }
    header('Location: /admin?tab=' . ($table ?? $tab), true, 303);
    exit;
}

// ---- CSV export. Exported contacts are for fulfilment and support only (they are never merged into data products).
if (isset($_GET['export'])) {
    $table = in_array($_GET['export'], ['claims', 'vendors', 'leads'], true) ? $_GET['export'] : 'leads';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $table . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    $rows = $db->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll();
    $guard = function ($v) {
        if (!is_string($v)) {
            return $v;
        }
        $v = str_replace(["\r", "\n"], ' ', $v);
        // Spreadsheet formula injection: neutralise leading = + - @ tab CR |
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r", '|'], true) ? "'" . $v : $v;
    };
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
            fputcsv($out, array_map($guard, $r));
        }
    }
    fclose($out);
    exit;
}

$counts = [];
foreach (['claims' => "status IN ('pending','paid','review')", 'vendors' => "status IN ('pending','paid','review')", 'leads' => "status = 'new'"] as $t => $where) {
    $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM $t WHERE $where")->fetchColumn();
}
$rows = $db->query("SELECT * FROM $tab ORDER BY id DESC LIMIT 300")->fetchAll();
$stripeCfg = (array)cfg('stripe');
$warnings = [];
foreach (['claim_verified', 'claim_featured', 'vendor_listed', 'vendor_featured'] as $k) {
    if (!stripe_link_ok((string)($stripeCfg[$k] ?? ''))) {
        $warnings[] = 'stripe.' . $k . ' is not set (forms for that plan refuse to take orders)';
    }
}
if ((string)($stripeCfg['webhook_secret'] ?? '') === '') {
    $warnings[] = 'stripe.webhook_secret is empty: payments will not be marked paid automatically';
}
if ($hash === '') {
    $warnings[] = 'admin_pass_hash is not set: use password_hash() instead of a plain admin_pass';
}
$badge = function (string $s): string {
    if ($s === 'active') {
        return 'badge-ok';
    }
    if ($s === 'rejected' || $s === 'disputed') {
        return 'badge-bad';
    }
    return 'badge-warn';
};
page_start(['title' => 'Admin | ' . cfg('site_name'), 'robots' => 'noindex,nofollow', 'cache' => false]);
?>
<div class="container section">
<h1 style="color:#fff;margin-bottom:12px">Admin</h1>
<?php foreach ($warnings as $w): ?><div class="error"><?= e($w) ?></div><?php endforeach; ?>
<div class="chips" style="margin-bottom:16px">
<a href="/admin?tab=claims" <?= $tab === 'claims' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Carrier claims <small><?= $counts['claims'] ?> open</small></a>
<a href="/admin?tab=vendors" <?= $tab === 'vendors' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Vendor listings <small><?= $counts['vendors'] ?> open</small></a>
<a href="/admin?tab=leads" <?= $tab === 'leads' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Leads <small><?= $counts['leads'] ?> new</small></a>
<a href="/admin?export=<?= e($tab) ?>">&#11015; Export <?= e($tab) ?> CSV</a>
</div>
<p class="small" style="margin-bottom:12px">Statuses: <b>pending</b> = not paid. <b>paid</b> = Stripe confirmed, awaiting your review. <b>review</b> = Stripe amount did not match the plan; check before activating. <b>active</b> = live on the site. <b>rejected</b> = declined or refunded. <b>disputed</b> = chargeback opened. Only <b>active</b> rows show publicly. Leads of type <b>suppression</b> are opt-out requests: add the USDOT to tools/suppress.txt and rebuild carriers.db.</p>
<div class="card" style="overflow-x:auto"><table class="table"><thead><tr>
<?php if ($tab === 'claims'): ?><th>#</th><th>USDOT</th><th>Plan</th><th>Status</th><th>Contact</th><th>Email</th><th>Phone</th><th>Website</th><th>Notes</th><th>Paid</th><th>Created</th><th>Set status</th>
<?php elseif ($tab === 'vendors'): ?><th>#</th><th>Company</th><th>Category</th><th>Plan</th><th>Status</th><th>Contact</th><th>Email</th><th>Phone</th><th>Website</th><th>Serves</th><th>Paid</th><th>Created</th><th>Set status</th>
<?php else: ?><th>#</th><th>Type</th><th>Company</th><th>USDOT</th><th>Contact</th><th>Email</th><th>Phone</th><th>State</th><th>Trucks</th><th>Notes</th><th>Status</th><th>Created</th><th>Set status</th><?php endif; ?>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php if ($tab === 'claims'): ?>
<td><?= (int)$r['id'] ?></td><td><a href="/carrier/<?= (int)$r['dot'] ?>" target="_blank"><?= (int)$r['dot'] ?></a></td><td><?= e($r['plan']) ?></td><td><span class="badge <?= $badge((string)$r['status']) ?>"><?= e($r['status']) ?></span></td><td><?= e($r['contact_name']) ?><br><span class="small"><?= e((string)$r['contact_role']) ?></span></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= $r['website'] ? '<a href="' . e($r['website']) . '" target="_blank" rel="noopener nofollow">link</a>' : '' ?></td><td class="small"><?= e((string)$r['notes']) ?></td><td class="small"><?= e((string)$r['paid_at']) ?><?= $r['amount_cents'] ? '<br>$' . number_format($r['amount_cents'] / 100, 2) : '' ?></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php elseif ($tab === 'vendors'): ?>
<td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?><br><span class="small"><?= e((string)$r['blurb']) ?></span></td><td><?= e($r['category']) ?></td><td><?= e($r['plan']) ?></td><td><span class="badge <?= $badge((string)$r['status']) ?>"><?= e($r['status']) ?></span></td><td><?= e((string)$r['contact_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= $r['website'] ? '<a href="' . e($r['website']) . '" target="_blank" rel="noopener nofollow">link</a>' : '' ?></td><td class="small"><?= e((string)$r['states_served']) ?></td><td class="small"><?= e((string)$r['paid_at']) ?><?= $r['amount_cents'] ? '<br>$' . number_format($r['amount_cents'] / 100, 2) : '' ?></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php else: ?>
<td><?= (int)$r['id'] ?></td><td><?= e($r['type']) ?></td><td><?= e((string)$r['company']) ?></td><td><?= $r['dot'] ? '<a href="/carrier/' . (int)$r['dot'] . '" target="_blank">' . (int)$r['dot'] . '</a>' : '' ?></td><td><?= e((string)$r['contact_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= e((string)$r['state']) ?></td><td><?= e((string)$r['trucks']) ?></td><td class="small"><?= e((string)$r['notes']) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php endif; ?>
<td><form method="post" action="/admin" style="display:flex;gap:4px"><input type="hidden" name="_t" value="<?= e($adminToken) ?>"><input type="hidden" name="table" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><select name="status" style="padding:4px;background:#0f0f1a;color:#eee;border:1px solid #444;border-radius:4px"><?php foreach ($statuses[$tab] as $s): ?><option value="<?= $s ?>"<?= $s === $r['status'] ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn" style="padding:4px 10px;font-size:0.8rem" type="submit">Save</button></form></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="13" class="small">Nothing here yet.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
<?php page_end();
