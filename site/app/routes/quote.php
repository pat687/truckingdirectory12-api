<?php
/** Carrier lead form: /quote/{type}?state=GA&dot=123  (factoring, insurance, dispatch, ...) */
declare(strict_types=1);

$sponsors = $GLOBALS['SPONSORS'];
$type = preg_replace('/[^a-z-]/', '', strtolower((string)($_GET['type'] ?? '')));
if (!isset($sponsors[$type])) {
    not_found('Unknown service type.');
}
$label = $sponsors[$type]['label'];
$dot = (int)($_GET['dot'] ?? 0);
$c = $dot > 0 ? q1('SELECT dot, legal_name, phy_state, phy_city, power_units FROM carriers WHERE dot = ?', [$dot]) : null;
$v = ['company' => $c ? company_name($c['legal_name']) : '', 'contact_name' => '', 'email' => '', 'phone' => '',
      'state' => $c ? $c['phy_state'] : strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['state'] ?? ''))),
      'trucks' => $c && $c['power_units'] ? (string)$c['power_units'] : '', 'notes' => ''];
$errors = [];
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = post($k, $k === 'notes' ? 1000 : 200);
    }
    $v['state'] = isset(US_STATES[$v['state']]) ? $v['state'] : '';
    if (!form_ok()) {
        $errors[] = 'The form could not be verified. Please reload the page and try again.';
    }
    if ($v['contact_name'] === '' || $v['company'] === '') {
        $errors[] = 'Company and contact name are required.';
    }
    if (!valid_email($v['email']) && preg_replace('/\D/', '', $v['phone']) === '') {
        $errors[] = 'Enter an email or a phone number so providers can reach you.';
    }
    if (!$errors) {
        $db = sitedb();
        $st = $db->prepare('INSERT INTO leads (type, dot, company, contact_name, email, phone, state, trucks, notes, source_url, status, ip, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,\'new\',?,datetime(\'now\'))');
        $st->execute([$type, $dot ?: null, $v['company'], $v['contact_name'], $v['email'], $v['phone'], $v['state'], (int)$v['trucks'] ?: null, $v['notes'], (string)($_SERVER['HTTP_REFERER'] ?? ''), client_ip()]);
        $id = (int)$db->lastInsertId();
        send_notification('New ' . $label . ' lead #' . $id . ' (' . $v['state'] . ')', "Type: $type\nCompany: $v[company]" . ($dot ? " (USDOT $dot)" : '') . "\nContact: $v[contact_name]\nEmail: $v[email]\nPhone: $v[phone]\nState: $v[state]\nTrucks: $v[trucks]\nNotes: $v[notes]\n\nAdmin: " . url('/admin'));
        $done = true;
    }
}
page_start(['title' => 'Get ' . $label . ' quotes | ' . cfg('site_name'), 'robots' => 'noindex,follow', 'cache' => false]);
?>
<div class="hero"><div class="container"><h1>Get <span><?= e(strtolower($label)) ?></span> quotes</h1><p class="sub"><?= e($sponsors[$type]['blurb']) ?> Tell us about your operation and matched providers will contact you. Free, no obligation.</p></div></div>
<div class="container section"><div class="grid2"><div class="card">
<?php if ($done): ?>
  <div class="notice">Thanks, <?= e($v['contact_name']) ?>. Your request is in. Providers serving <?= e($v['state'] ? state_name($v['state']) : 'your area') ?> will reach out shortly.</div>
  <p><a class="btn" href="<?= $c ? e('/carrier/' . $dot . '/' . slugify($c['legal_name'])) : '/carriers/' ?>">Back</a></p>
<?php else: ?>
  <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
  <form class="f" method="post" action="/quote/<?= e($type) ?><?= $dot ? '?dot=' . $dot : '' ?>">
  <?= form_hidden() ?>
  <div class="row"><div><label>Company *</label><input name="company" required value="<?= e($v['company']) ?>"></div><div><label>Your name *</label><input name="contact_name" required value="<?= e($v['contact_name']) ?>"></div></div>
  <div class="row"><div><label>Email</label><input type="email" name="email" value="<?= e($v['email']) ?>"></div><div><label>Phone</label><input name="phone" value="<?= e($v['phone']) ?>"></div></div>
  <div class="row"><div><label>State</label><select name="state"><option value="">Select</option><?php foreach (US_STATES as $a => [$n, $s]): ?><option value="<?= $a ?>"<?= $a === $v['state'] ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div><div><label>Number of trucks</label><input type="number" min="0" name="trucks" value="<?= e($v['trucks']) ?>"></div></div>
  <label>What do you need?</label><textarea name="notes"><?= e($v['notes']) ?></textarea>
  <p style="margin-top:14px"><button class="btn" type="submit">Request quotes</button></p>
  <p class="small">By submitting you agree to be contacted by up to three providers about <?= e(strtolower($label)) ?>.</p>
  </form>
<?php endif; ?>
</div>
<aside><div class="card"><h3>Are you a provider?</h3><p class="small">Carriers in all 50 states request quotes here. <a href="/advertise">Get these leads and a sponsored slot &rarr;</a></p></div></aside>
</div></div>
<?php page_end();
