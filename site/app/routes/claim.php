<?php
/** Claim a carrier profile: /claim/{dot}  (GET form, POST -> save -> Stripe) */
declare(strict_types=1);

$dot = (int)($_GET['dot'] ?? 0);
$c = $dot > 0 ? q1('SELECT * FROM carriers WHERE dot = ?', [$dot]) : null;
if (!$c) {
    not_found('No carrier with USDOT ' . $dot . ' in our records.');
}
$name = company_name($c['legal_name']);
$existing = carrier_claim($dot);
$prices = cfg('prices');
$stripe = cfg('stripe');
$errors = [];
$v = ['contact_name' => '', 'contact_role' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => '', 'plan' => 'verified', 'public_email' => '0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = post($k, $k === 'notes' ? 1000 : 200);
    }
    if (!form_ok()) {
        $errors[] = 'The form could not be verified. Please reload the page and try again.';
    }
    if ($v['contact_name'] === '') {
        $errors[] = 'Your name is required.';
    }
    if (!valid_email($v['email'])) {
        $errors[] = 'A valid email address is required.';
    }
    if ($v['website'] !== '' && !preg_match('~^https?://~i', $v['website'])) {
        $v['website'] = 'https://' . $v['website'];
    }
    if ($v['website'] !== '' && filter_var($v['website'], FILTER_VALIDATE_URL) === false) {
        $errors[] = 'The website address does not look valid.';
    }
    if (!in_array($v['plan'], ['verified', 'featured'], true)) {
        $v['plan'] = 'verified';
    }
    if (empty($_POST['authorized'])) {
        $errors[] = 'Please confirm you are authorized to represent this company.';
    }
    if (!$errors) {
        $db = sitedb();
        $st = $db->prepare('INSERT INTO claims (dot, plan, status, contact_name, contact_role, email, phone, website, public_email, notes, ip, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,datetime(\'now\'))');
        $st->execute([$dot, $v['plan'], 'pending', $v['contact_name'], $v['contact_role'], $v['email'], $v['phone'], $v['website'], $v['public_email'] === '1' ? 1 : 0, $v['notes'], client_ip()]);
        $id = (int)$db->lastInsertId();
        send_notification('New carrier claim #' . $id . ' - ' . $name,
            "Claim #$id ($v[plan]) for USDOT $dot - $name\nContact: $v[contact_name] ($v[contact_role])\nEmail: $v[email]\nPhone: $v[phone]\nWebsite: $v[website]\nNotes: $v[notes]\n\nStatus: pending payment. Admin: " . url('/admin'));
        $link = $v['plan'] === 'featured' ? $stripe['claim_featured'] : $stripe['claim_verified'];
        $sep = str_contains($link, '?') ? '&' : '?';
        header('Location: ' . $link . $sep . 'client_reference_id=claim-' . $id . '&prefilled_email=' . rawurlencode($v['email']), true, 303);
        exit;
    }
}

$crumbs = [['Carriers', '/carriers/'], [$name, carrier_path($c)], ['Claim listing', null]];
page_start(['title' => 'Claim ' . $name . ' (USDOT ' . $dot . ') | ' . cfg('site_name'), 'robots' => 'noindex,follow', 'cache' => false, 'canonical' => '/claim/' . $dot]);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1>Claim <span><?= e($name) ?></span></h1><p class="sub">USDOT <?= $dot ?> &middot; <?= e(title_case($c['phy_city'])) ?>, <?= e($c['phy_state']) ?></p></div></div>
<div class="container section"><div class="grid2">
<div class="card">
<?php if ($existing): ?><div class="notice">This profile is already claimed. If that's a mistake or you need to update it, email <a href="mailto:<?= e((string)cfg('notify_email')) ?>"><?= e((string)cfg('notify_email')) ?></a>.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
<form class="f" method="post" action="/claim/<?= $dot ?>">
<?= form_hidden() ?>
<h2 style="color:#fff;font-size:1.05rem;margin-bottom:6px">Choose a plan</h2>
<label class="plan"><input type="radio" name="plan" value="verified" <?= $v['plan'] === 'verified' ? 'checked' : '' ?>><span><b>Verified listing</b><span class="price">$<?= (int)$prices['claim_verified'] ?>/year</span><br><span class="small">Verified badge, your website link and email button, corrected details, no competitor offers on your page.</span></span></label>
<label class="plan"><input type="radio" name="plan" value="featured" <?= $v['plan'] === 'featured' ? 'checked' : '' ?>><span><b>Featured listing</b><span class="price">$<?= (int)$prices['claim_featured'] ?>/year</span><br><span class="small">Everything in Verified, plus top placement in your city and cargo lists and your logo on the profile.</span></span></label>
<div class="row"><div><label>Your name *</label><input name="contact_name" required value="<?= e($v['contact_name']) ?>"></div><div><label>Your role</label><input name="contact_role" placeholder="Owner, Safety Manager, Dispatcher..." value="<?= e($v['contact_role']) ?>"></div></div>
<div class="row"><div><label>Email *</label><input type="email" name="email" required value="<?= e($v['email']) ?>"></div><div><label>Phone</label><input name="phone" value="<?= e($v['phone']) ?>"></div></div>
<label>Company website</label><input name="website" placeholder="https://" value="<?= e($v['website']) ?>">
<label>Corrections or notes</label><textarea name="notes" placeholder="Anything on the profile that is wrong or out of date"><?= e($v['notes']) ?></textarea>
<label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="public_email" value="1" style="width:auto;margin-top:5px" <?= $v['public_email'] === '1' ? 'checked' : '' ?>> <span>Show an "Email" button on my public profile (uses the address above)</span></label>
<label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="authorized" value="1" style="width:auto;margin-top:5px" required> <span>I am authorized to represent <?= e($name) ?> (USDOT <?= $dot ?>). *</span></label>
<p style="margin-top:16px"><button class="btn" type="submit">Continue to secure payment &rarr;</button></p>
<p class="small">Payment is processed by Stripe. Your listing is activated after payment and a quick review (usually same day).</p>
</form></div>
<aside><div class="card"><h3>What you get</h3><ul style="margin-left:18px;color:#ccc;font-size:0.9rem;line-height:1.9"><li>Verified badge on your profile</li><li>Website link and email button</li><li>Corrected address, phone and fleet details</li><li>No competitor offers on your page</li><li>Featured: top of city and cargo lists</li></ul></div>
<div class="card"><h3>Why claim?</h3><p class="small">Shippers, brokers and insurance agents look carriers up by USDOT number every day. A claimed profile with your website and contact info turns those lookups into calls.</p></div></aside>
</div></div>
<?php page_end();
