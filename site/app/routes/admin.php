<?php
/** Admin panel: /admin  (HTTP Basic Auth, credentials in config.php) */
declare(strict_types=1);

$user = (string)cfg('admin_user');
$pass = (string)cfg('admin_pass');
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW'] ?? '';
if ($u === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with($_SERVER['HTTP_AUTHORIZATION'], 'Basic ')) {
    [$u, $p] = array_pad(explode(':', (string)base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)), 2), 2, '');
}
if ($pass === 'change-me-now' || !hash_equals($user, $u) || !hash_equals($pass, $p)) {
    header('WWW-Authenticate: Basic realm="Trucking Directory USA admin"');
    http_response_code(401);
    header('Cache-Control: no-store');
    echo $pass === 'change-me-now' ? 'Set admin_user / admin_pass in app/config.php first.' : 'Authentication required.';
    exit;
}
header('X-Robots-Tag: noindex');
$db = sitedb();
$tab = in_array($_GET['tab'] ?? '', ['claims', 'vendors', 'leads'], true) ? $_GET['tab'] : 'claims';

// ---- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), (string)($_POST['_t'] ?? ''))) {
    $table = in_array($_POST['table'] ?? '', ['claims', 'vendors', 'leads'], true) ? $_POST['table'] : null;
    $id = (int)($_POST['id'] ?? 0);
    $status = preg_replace('/[^a-z]/', '', (string)($_POST['status'] ?? ''));
    $allowed = ['claims' => ['pending', 'paid', 'active', 'rejected'], 'vendors' => ['pending', 'paid', 'active', 'rejected'], 'leads' => ['new', 'sold', 'contacted', 'closed']];
    if ($table && $id && in_array($status, $allowed[$table], true)) {
        $extra = $table === 'leads' ? '' : ', updated_at = datetime(\'now\')' . ($status === 'paid' ? ', paid_at = COALESCE(paid_at, datetime(\'now\'))' : '');
        $db->prepare("UPDATE $table SET status = ?$extra WHERE id = ?")->execute([$status, $id]);
    }
    header('Location: /admin?tab=' . ($table ?? $tab), true, 303);
    exit;
}

// ---- CSV export (feeds CarrierDataUSA with fresh, verified contact names and emails)
if (isset($_GET['export'])) {
    $table = in_array($_GET['export'], ['claims', 'vendors', 'leads'], true) ? $_GET['export'] : 'leads';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $table . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    $rows = $db->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll();
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
            fputcsv($out, array_map(fn($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'" . $v : $v, $r));
        }
    }
    fclose($out);
    exit;
}

$counts = [];
foreach (['claims' => "status IN ('pending','paid')", 'vendors' => "status IN ('pending','paid')", 'leads' => "status = 'new'"] as $t => $where) {
    $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM $t WHERE $where")->fetchColumn();
}
$rows = $db->query("SELECT * FROM $tab ORDER BY id DESC LIMIT 300")->fetchAll();
page_start(['title' => 'Admin | ' . cfg('site_name'), 'robots' => 'noindex,nofollow', 'cache' => false]);
$statuses = ['claims' => ['pending', 'paid', 'active', 'rejected'], 'vendors' => ['pending', 'paid', 'active', 'rejected'], 'leads' => ['new', 'sold', 'contacted', 'closed']];
?>
<div class="container section">
<h1 style="color:#fff;margin-bottom:12px">Admin</h1>
<div class="chips" style="margin-bottom:16px">
<a href="/admin?tab=claims" <?= $tab === 'claims' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Carrier claims <small><?= $counts['claims'] ?> open</small></a>
<a href="/admin?tab=vendors" <?= $tab === 'vendors' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Vendor listings <small><?= $counts['vendors'] ?> open</small></a>
<a href="/admin?tab=leads" <?= $tab === 'leads' ? 'style="background:var(--accent);color:#fff"' : '' ?>>Leads <small><?= $counts['leads'] ?> new</small></a>
<a href="/admin?export=<?= e($tab) ?>">&#11015; Export <?= e($tab) ?> CSV</a>
</div>
<p class="small" style="margin-bottom:12px">Statuses: <b>pending</b> = form submitted, not paid. <b>paid</b> = Stripe confirmed (webhook) or marked by you. <b>active</b> = live on the site. Carrier claims and vendor listings show publicly once <b>paid</b> or <b>active</b>.</p>
<div class="card" style="overflow-x:auto"><table class="table"><thead><tr>
<?php if ($tab === 'claims'): ?><th>#</th><th>USDOT</th><th>Plan</th><th>Status</th><th>Contact</th><th>Email</th><th>Phone</th><th>Website</th><th>Notes</th><th>Paid</th><th>Created</th><th>Set status</th>
<?php elseif ($tab === 'vendors'): ?><th>#</th><th>Company</th><th>Category</th><th>Plan</th><th>Status</th><th>Contact</th><th>Email</th><th>Phone</th><th>Website</th><th>Serves</th><th>Paid</th><th>Created</th><th>Set status</th>
<?php else: ?><th>#</th><th>Type</th><th>Company</th><th>USDOT</th><th>Contact</th><th>Email</th><th>Phone</th><th>State</th><th>Trucks</th><th>Notes</th><th>Status</th><th>Created</th><th>Set status</th><?php endif; ?>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php if ($tab === 'claims'): ?>
<td><?= (int)$r['id'] ?></td><td><a href="/carrier/<?= (int)$r['dot'] ?>" target="_blank"><?= (int)$r['dot'] ?></a></td><td><?= e($r['plan']) ?></td><td><span class="badge <?= $r['status'] === 'active' || $r['status'] === 'paid' ? 'badge-ok' : ($r['status'] === 'rejected' ? 'badge-bad' : 'badge-warn') ?>"><?= e($r['status']) ?></span></td><td><?= e($r['contact_name']) ?><br><span class="small"><?= e((string)$r['contact_role']) ?></span></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= $r['website'] ? '<a href="' . e($r['website']) . '" target="_blank" rel="noopener">link</a>' : '' ?></td><td class="small"><?= e((string)$r['notes']) ?></td><td class="small"><?= e((string)$r['paid_at']) ?><?= $r['amount_cents'] ? '<br>$' . number_format($r['amount_cents'] / 100, 2) : '' ?></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php elseif ($tab === 'vendors'): ?>
<td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?><br><span class="small"><?= e((string)$r['blurb']) ?></span></td><td><?= e($r['category']) ?></td><td><?= e($r['plan']) ?></td><td><span class="badge <?= $r['status'] === 'active' || $r['status'] === 'paid' ? 'badge-ok' : ($r['status'] === 'rejected' ? 'badge-bad' : 'badge-warn') ?>"><?= e($r['status']) ?></span></td><td><?= e((string)$r['contact_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= $r['website'] ? '<a href="' . e($r['website']) . '" target="_blank" rel="noopener">link</a>' : '' ?></td><td class="small"><?= e((string)$r['states_served']) ?></td><td class="small"><?= e((string)$r['paid_at']) ?></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php else: ?>
<td><?= (int)$r['id'] ?></td><td><?= e($r['type']) ?></td><td><?= e((string)$r['company']) ?></td><td><?= $r['dot'] ? '<a href="/carrier/' . (int)$r['dot'] . '" target="_blank">' . (int)$r['dot'] . '</a>' : '' ?></td><td><?= e((string)$r['contact_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= e((string)$r['state']) ?></td><td><?= e((string)$r['trucks']) ?></td><td class="small"><?= e((string)$r['notes']) ?></td><td><span class="badge"><?= e($r['status']) ?></span></td><td class="small"><?= e((string)$r['created_at']) ?></td>
<?php endif; ?>
<td><form method="post" action="/admin" style="display:flex;gap:4px"><input type="hidden" name="_t" value="<?= e(csrf_token()) ?>"><input type="hidden" name="table" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><select name="status" style="padding:4px;background:#0f0f1a;color:#eee;border:1px solid #444;border-radius:4px"><?php foreach ($statuses[$tab] as $s): ?><option value="<?= $s ?>"<?= $s === $r['status'] ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn" style="padding:4px 10px;font-size:0.8rem" type="submit">Save</button></form></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="13" class="small">Nothing here yet.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
<?php page_end();
