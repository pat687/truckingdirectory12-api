<?php
/** Shared vendor listing form used by /get-listed and /advertise. */
declare(strict_types=1);

/** Call BEFORE page_start(): validates a POST, saves it and redirects to Stripe. Returns [$values, $errors]. */
function vendor_form_handle(string $defaultPlan): array
{
    $cats = $GLOBALS['CATEGORIES'];
    $stripe = cfg('stripe');
    $support = (string)cfg('contact_email');
    $v = ['category' => preg_replace('/[^a-z_]/', '', (string)($_GET['cat'] ?? '')), 'plan' => $defaultPlan, 'name' => '', 'contact_name' => '',
          'email' => '', 'phone' => '', 'website' => '', 'city' => '', 'state' => strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['state'] ?? ''))),
          'states_served' => '', 'blurb' => ''];
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        form_rate_limit_or_429('attempt');
        foreach ($v as $k => $_) {
            $v[$k] = post($k, $k === 'blurb' ? 400 : 200, $k === 'blurb');
        }
        $served = [];
        foreach ((array)($_POST['served'] ?? []) as $s) {
            $s = strtoupper((string)$s);
            if ($s === 'ALL' || isset(US_STATES[$s])) {
                $served[] = $s;
            }
        }
        $v['states_served'] = in_array('ALL', $served, true) ? 'ALL' : implode(',', $served);
        if (!form_ok()) {
            $errors[] = 'The form could not be verified. Please reload the page and try again.';
        }
        if (!isset($cats[$v['category']])) {
            $errors[] = 'Choose a category.';
        }
        if ($v['name'] === '' || $v['contact_name'] === '') {
            $errors[] = 'Company name and contact name are required.';
        }
        if (!valid_email($v['email'])) {
            $errors[] = 'A valid email address is required.';
        }
        if ($v['website'] !== '' && !preg_match('~^https?://~i', $v['website'])) {
            $v['website'] = 'https://' . $v['website'];
        }
        if ($v['website'] !== '' && !valid_website($v['website'])) {
            $errors[] = 'The website address does not look valid (use the form https://www.example.com/).';
        }
        if ($v['states_served'] === '') {
            $errors[] = 'Pick at least one state you serve (or Nationwide).';
        }
        if (!in_array($v['plan'], ['listed', 'featured'], true)) {
            $v['plan'] = 'listed';
        }
        if (empty($_POST['terms'])) {
            $errors[] = 'Please agree to the Terms and the refund policy.';
        }
        $v['state'] = isset(US_STATES[$v['state']]) ? $v['state'] : '';
        // Never save a row that cannot be paid for: the Payment Link must be configured for the chosen plan.
        $link = (string)($v['plan'] === 'featured' ? ($stripe['vendor_featured'] ?? '') : ($stripe['vendor_listed'] ?? ''));
        if (!stripe_link_ok($link)) {
            $valid = !$errors;
            $errors[] = 'Online payment for this plan is not available yet. Email ' . $support . ' and we will set it up.';
            if ($valid && rate_ok('notify_nolink', 'vendor_' . $v['plan'], 1, 3600)) send_notification('Stripe Payment Link missing for vendor ' . $v['plan'],
                "A provider tried to buy the '$v[plan]' vendor plan but stripe.vendor_$v[plan] in app/config.php is empty or still REPLACE_ME.\nCompany: $v[name] ($v[category])\nContact: $v[contact_name] <$v[email]>\nNothing was saved. Create the Payment Link and put its URL in config.local.php.");
        }
        if (!$errors) {
            form_rate_limit_or_429('submit');
            $db = sitedb();
            $st = $db->prepare('INSERT INTO vendors (category, plan, status, name, contact_name, email, phone, website, city, state, states_served, blurb, ip, user_agent, terms_accepted_at, created_at) VALUES (?,?,\'pending\',?,?,?,?,?,?,?,?,?,?,?,datetime(\'now\'),datetime(\'now\'))');
            $st->execute([$v['category'], $v['plan'], $v['name'], $v['contact_name'], $v['email'], $v['phone'], $v['website'], $v['city'], $v['state'], $v['states_served'], $v['blurb'], client_ip(), user_agent()]);
            $id = (int)$db->lastInsertId();
            send_notification('New vendor listing #' . $id . ' - ' . $v['name'] . ' (' . $v['plan'] . ')',
                "Category: $v[category]\nCompany: $v[name]\nContact: $v[contact_name]\nEmail: $v[email]\nPhone: $v[phone]\nWebsite: $v[website]\nServes: $v[states_served]\nBlurb: $v[blurb]\n\nStatus: pending payment. Admin: " . url('/admin'));
            $sep = str_contains($link, '?') ? '&' : '?';
            header('Location: ' . $link . $sep . 'client_reference_id=vendor-' . $id . '&prefilled_email=' . rawurlencode($v['email']), true, 303);
            exit;
        }
    }
    return [$v, $errors];
}

/** Call inside the page: renders the form with the values/errors from vendor_form_handle(). */
function vendor_form_render(array $v, array $errors, string $action): void
{
    $cats = $GLOBALS['CATEGORIES'];
    $prices = cfg('prices');
    foreach ($errors as $err) {
        echo '<div class="error">' . e($err) . '</div>';
    }
    ?>
    <form class="f" method="post" action="<?= e($action) ?>">
    <?= form_hidden() ?>
    <label class="plan"><input type="radio" name="plan" value="listed" <?= $v['plan'] === 'listed' ? 'checked' : '' ?>><span><b>Standard listing</b><span class="price">$<?= (int)$prices['vendor_listed'] ?> for one year</span><br><span class="small">Your company on the category pages for every state you serve, with phone, website and description.</span></span></label>
    <label class="plan"><input type="radio" name="plan" value="featured" <?= $v['plan'] === 'featured' ? 'checked' : '' ?>><span><b>Featured sponsor</b><span class="price">$<?= (int)$prices['vendor_featured'] ?> for one year</span><br><span class="small">Top of those category pages, plus rotation in the sponsored slots on carrier profiles and state pages in the states you serve.</span></span></label>
    <div class="row"><div><label>Category *</label><select name="category" required><option value="">Select</option><?php foreach ($cats as $k => $c): ?><option value="<?= $k ?>"<?= $k === $v['category'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Company name *</label><input name="name" required value="<?= e($v['name']) ?>"></div></div>
    <div class="row"><div><label>Contact name *</label><input name="contact_name" required value="<?= e($v['contact_name']) ?>"></div><div><label>Email *</label><input type="email" name="email" required value="<?= e($v['email']) ?>"></div></div>
    <div class="row"><div><label>Phone</label><input name="phone" value="<?= e($v['phone']) ?>"></div><div><label>Website</label><input name="website" placeholder="https://" maxlength="200" value="<?= e($v['website']) ?>"></div></div>
    <div class="row"><div><label>City</label><input name="city" value="<?= e($v['city']) ?>"></div><div><label>Home state</label><select name="state"><option value="">Select</option><?php foreach (US_STATES as $a => [$n, $s]): ?><option value="<?= $a ?>"<?= $a === $v['state'] ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div></div>
    <label>States you serve *</label>
    <div class="chips" style="margin-bottom:6px"><label style="display:inline-flex;gap:6px;align-items:center;background:rgba(240,165,0,0.15);border-radius:4px;padding:5px 10px"><input type="checkbox" name="served[]" value="ALL" style="width:auto"> Nationwide</label>
    <?php foreach (US_STATES as $a => [$n, $s]): ?><label style="display:inline-flex;gap:6px;align-items:center;background:rgba(255,255,255,0.06);border-radius:4px;padding:5px 10px;font-size:0.8rem"><input type="checkbox" name="served[]" value="<?= $a ?>" style="width:auto" <?= $a === $v['state'] ? 'checked' : '' ?>> <?= $a ?></label><?php endforeach; ?></div>
    <label>Short description (shown on your listing)</label><textarea name="blurb" maxlength="400"><?= e($v['blurb']) ?></textarea>
    <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px"><input type="checkbox" name="terms" value="1" style="width:auto;margin-top:5px" required> <span>I agree to the <a href="/terms" target="_blank" style="text-decoration:underline">Terms</a> and the refund policy, and I have read the <a href="/privacy" target="_blank" style="text-decoration:underline">Privacy Policy</a>. *</span></label>
    <p style="margin-top:14px"><button class="btn" type="submit">Continue to secure payment &rarr;</button></p>
    <p class="small">Listings go live after payment and a quick review (usually the same business day). Payment is processed by Stripe. <?= e((string)cfg('refund_policy')) ?></p>
    </form>
    <?php
}
