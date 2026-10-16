<?php
/** Carrier opt-out / correction request: /opt-out (stored as a lead of type 'suppression'). */
declare(strict_types=1);

$support = (string)cfg('contact_email');
$reasons = [
    'remove_contact' => 'Remove my contact details from the carrier lists',
    'correct'        => 'Tell me how to correct the details shown on my profile',
    'remove_profile' => 'Remove my profile from this website',
];
$v = ['dot' => '', 'contact_name' => '', 'email' => '', 'reason' => 'remove_contact', 'notes' => ''];
$errors = [];
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    form_rate_limit_or_429('attempt');
    foreach ($v as $k => $_) {
        $v[$k] = post($k, $k === 'notes' ? 1000 : 200, $k === 'notes');
    }
    $v['dot'] = preg_replace('/\D/', '', $v['dot']) ?? '';
    if (!form_ok()) {
        $errors[] = 'The form could not be verified. Please reload the page and try again.';
    }
    if ($v['dot'] === '' || strlen($v['dot']) > 8 || (int)$v['dot'] <= 0) {
        $errors[] = 'Enter the USDOT number of the carrier (digits only).';
    }
    if ($v['contact_name'] === '') {
        $errors[] = 'Your name is required.';
    }
    if (!valid_email($v['email'])) {
        $errors[] = 'A valid email address is required so we can confirm the change.';
    }
    if (!isset($reasons[$v['reason']])) {
        $errors[] = 'Choose what you would like us to do.';
    }
    if (!$errors) {
        form_rate_limit_or_429('submit');
        $dot = (int)$v['dot'];
        $company = '';
        try {
            $c = q1('SELECT legal_name FROM carriers WHERE dot = ?', [$dot]);
            $company = $c ? company_name($c['legal_name']) : '';
        } catch (CarriersDbMissing $e) {
            $company = '';
        }
        $db = sitedb();
        $notes = $reasons[$v['reason']] . ($v['notes'] !== '' ? "\n" . $v['notes'] : '');
        $st = $db->prepare('INSERT INTO leads (type, dot, company, contact_name, email, notes, source_url, status, ip, user_agent, created_at) VALUES (\'suppression\', ?, ?, ?, ?, ?, ?, \'new\', ?, ?, datetime(\'now\'))');
        $st->execute([$dot, $company, $v['contact_name'], $v['email'], $notes, $v['reason'], client_ip(), user_agent()]);
        $id = (int)$db->lastInsertId();
        send_notification('Opt-out request #' . $id . ' - USDOT ' . $dot . ' (' . $v['reason'] . ')',
            "USDOT: $dot" . ($company !== '' ? " - $company" : '') . "\nRequest: " . $reasons[$v['reason']] . "\nFrom: $v[contact_name] <$v[email]>\nNotes: $v[notes]\n\n"
            . "To process: add $dot to tools/suppress.txt, rebuild carriers.db and upload it; remove the carrier from the CarrierDataUSA lists. Then reply to the requester. Admin: " . url('/admin?tab=leads'));
        $done = true;
    }
}
$crumbs = [['Carriers', '/carriers/'], ['Remove or correct your details', null]];
page_start(['title' => 'Remove or correct your carrier details | ' . cfg('site_name'), 'robots' => 'noindex,follow', 'cache' => false, 'canonical' => '/opt-out']);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?>
<h1>Remove or correct <span>your carrier details</span></h1>
<p class="sub">Carrier profiles are built from public FMCSA registration records. If you represent a carrier and want your contact details removed from the lists or your profile removed from this website, tell us here. Profile details come from the carrier's FMCSA registration. To change them, file an updated MCS-150 with FMCSA; the profile updates with our next data refresh.</p></div></div>
<div class="container section"><div class="grid2"><div class="card">
<?php if ($done): ?>
  <div class="notice"><?php if ($v['reason'] === 'correct'): ?>Request received. Profile details come from the carrier's FMCSA registration. To change them, file an updated MCS-150 with FMCSA; the profile updates with our next data refresh. We will reply by email at <?= e($v['email']) ?> if anything else is needed.<?php else: ?>Request received; we process removals within 10 business days and before the next data refresh. We will confirm by email at <?= e($v['email']) ?>.<?php endif; ?></div>
  <p><a class="btn" href="/carriers/">Back to the directory</a></p>
<?php else: ?>
  <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
  <form class="f" method="post" action="/opt-out">
  <?= form_hidden() ?>
  <div class="row"><div><label>USDOT number *</label><input name="dot" inputmode="numeric" pattern="[0-9]*" required value="<?= e($v['dot']) ?>"></div><div><label>Your name *</label><input name="contact_name" required value="<?= e($v['contact_name']) ?>"></div></div>
  <label>Email *</label><input type="email" name="email" required value="<?= e($v['email']) ?>">
  <label>What should we do? *</label>
  <?php foreach ($reasons as $k => $text): ?>
  <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="radio" name="reason" value="<?= e($k) ?>" style="width:auto;margin-top:5px" <?= $v['reason'] === $k ? 'checked' : '' ?>> <span><?= e($text) ?></span></label>
  <?php endforeach; ?>
  <label>Details (what is wrong, or anything we should know)</label><textarea name="notes" maxlength="1000"><?= e($v['notes']) ?></textarea>
  <p style="margin-top:14px"><button class="btn" type="submit">Send request</button></p>
  <p class="small">We use these details only to process your request. See our <a href="/privacy" style="text-decoration:underline">Privacy Policy</a>.</p>
  </form>
<?php endif; ?>
</div>
<aside><div class="card"><h3>How this works</h3><p class="small">Removing a profile here does not change your FMCSA registration, which stays public at <a href="https://safer.fmcsa.dot.gov/" rel="nofollow noopener" target="_blank">FMCSA SAFER</a>. Removals are applied in the next data build and your USDOT number is kept on a suppression list so the profile does not come back after a refresh.</p></div>
<div class="card"><h3>Prefer email?</h3><p class="small">Write to <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> with your USDOT number.</p></div></aside>
</div></div>
<?php page_end();
