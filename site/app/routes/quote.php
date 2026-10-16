<?php
/** Carrier lead form: /quote/{type}?state=GA&dot=123  (factoring, insurance, dispatch, ...) */
declare(strict_types=1);

const LEAD_CONSENT_VERSION = '2026-10';
const LEAD_CONSENT_TEXT = 'By submitting, I confirm I am authorized to request quotes for this company and I agree that up to three providers may contact me about this request by phone, email or text message. Message and data rates may apply. I can opt out at any time.';

$sponsors = $GLOBALS['SPONSORS'];
$type = preg_replace('/[^a-z-]/', '', strtolower((string)($_GET['type'] ?? '')));
if (!isset($sponsors[$type])) {
    not_found('Unknown service type.');
}
$label = $sponsors[$type]['label'];
$dot = (int)($_GET['dot'] ?? 0);
$c = null;
if ($dot > 0) {
    try {
        $c = q1('SELECT dot, legal_name, phy_state FROM carriers WHERE dot = ?', [$dot]);
    } catch (CarriersDbMissing $e) {
        $c = null; // the lead form still works without carriers.db
    }
}
// Prefill only the company name (and the USDOT in the action URL) from the carrier record; never its phone or other details.
$v = ['company' => $c ? company_name($c['legal_name']) : '', 'contact_name' => '', 'email' => '', 'phone' => '',
      'state' => strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['state'] ?? ''))),
      'trucks' => '', 'notes' => ''];
$errors = [];
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    form_rate_limit_or_429('attempt');
    foreach ($v as $k => $_) {
        $v[$k] = post($k, $k === 'notes' ? 1000 : 200, $k === 'notes');
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
    if (empty($_POST['consent'])) {
        $errors[] = 'Please tick the consent box so providers may contact you.';
    }
    if (!$errors) {
        form_rate_limit_or_429('submit');
        $db = sitedb();
        $source = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', (string)($_SERVER['HTTP_REFERER'] ?? '')) ?? '', 0, 500);
        $st = $db->prepare('INSERT INTO leads (type, dot, company, contact_name, email, phone, state, trucks, notes, source_url, status, ip, user_agent, consent_text_version, consent_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,\'new\',?,?,?,datetime(\'now\'),datetime(\'now\'))');
        $st->execute([$type, $dot ?: null, $v['company'], $v['contact_name'], $v['email'], $v['phone'], $v['state'], (int)$v['trucks'] ?: null, $v['notes'], $source, client_ip(), user_agent(), LEAD_CONSENT_VERSION]);
        $id = (int)$db->lastInsertId();
        send_notification('New ' . $label . ' lead #' . $id . ' (' . $v['state'] . ')', "Type: $type\nCompany: $v[company]" . ($dot ? " (USDOT $dot)" : '') . "\nContact: $v[contact_name]\nEmail: $v[email]\nPhone: $v[phone]\nState: $v[state]\nTrucks: $v[trucks]\nNotes: $v[notes]\nConsent: v" . LEAD_CONSENT_VERSION . "\n\nAdmin: " . url('/admin'));
        $done = true;
    }
}
page_start(['title' => 'Get ' . $label . ' quotes | ' . cfg('site_name'), 'robots' => 'noindex,follow', 'cache' => false]);
?>
<div class="hero"><div class="container"><h1>Get <span><?= e(strtolower($label)) ?></span> quotes</h1><p class="sub"><?= e($sponsors[$type]['blurb']) ?> Tell us about your operation and we pass your request to up to three providers serving your state. Free, no obligation.</p></div></div>
<div class="container section"><div class="grid2"><div class="card">
<?php if ($done): ?>
  <div class="notice">Thanks, <?= e($v['contact_name']) ?>. Your request is in. We review it and pass it to up to three providers serving <?= e($v['state'] ? state_name($v['state']) : 'your area') ?>, usually within one business day.</div>
  <p><a class="btn" href="<?= $c ? e('/carrier/' . $dot . '/' . slugify($c['legal_name'])) : '/carriers/' ?>">Back</a></p>
<?php else: ?>
  <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
  <form class="f" method="post" action="/quote/<?= e($type) ?><?= $dot ? '?dot=' . $dot : '' ?>">
  <?= form_hidden() ?>
  <div class="row"><div><label>Company *</label><input name="company" required value="<?= e($v['company']) ?>"></div><div><label>Your name *</label><input name="contact_name" required value="<?= e($v['contact_name']) ?>"></div></div>
  <div class="row"><div><label>Email</label><input type="email" name="email" value="<?= e($v['email']) ?>"></div><div><label>Phone</label><input name="phone" value="<?= e($v['phone']) ?>"></div></div>
  <div class="row"><div><label>State</label><select name="state"><option value="">Select</option><?php foreach (US_STATES as $a => [$n, $s]): ?><option value="<?= $a ?>"<?= $a === $v['state'] ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div><div><label>Number of trucks</label><input type="number" min="0" name="trucks" value="<?= e($v['trucks']) ?>"></div></div>
  <label>What do you need?</label><textarea name="notes" maxlength="1000"><?= e($v['notes']) ?></textarea>
  <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px"><input type="checkbox" name="consent" value="1" style="width:auto;margin-top:5px" required> <span class="small" style="color:#bbb"><?= e(LEAD_CONSENT_TEXT) ?> See our <a href="/privacy" target="_blank" style="text-decoration:underline">Privacy Policy</a>. *</span></label>
  <p style="margin-top:14px"><button class="btn" type="submit">Request quotes</button></p>
  </form>
<?php endif; ?>
</div>
<aside><div class="card"><h3>Are you a provider?</h3><p class="small">Carriers in all 50 states visit these pages. <a href="/advertise">Put your company in front of them with a sponsored slot &rarr;</a></p></div></aside>
</div></div>
<?php page_end();
