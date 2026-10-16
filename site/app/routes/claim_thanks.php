<?php
/**
 * After-payment page: /claim/thanks?session_id=... (set as the Stripe Payment Link redirect).
 * "Payment received" is shown only after the Checkout Session was fetched from Stripe and is paid;
 * without stripe.secret_key (or on any failure) the page stays neutral and points at the email receipt.
 */
declare(strict_types=1);

$support = (string)cfg('contact_email');
$refund = (string)cfg('refund_policy');
$sid = (string)($_GET['session_id'] ?? '');
$key = (string)(cfg('stripe')['secret_key'] ?? '');
$verified = null;
if ($key !== '' && preg_match('/^cs_(?:test_|live_)?[A-Za-z0-9]{8,120}$/', $sid) && function_exists('curl_init')) {
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sid));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (is_string($res) && $code === 200) {
        $j = json_decode($res, true);
        if (is_array($j) && (($j['payment_status'] ?? '') === 'paid')) {
            $verified = [
                'ref' => preg_replace('/[^A-Za-z0-9_-]/', '', (string)($j['client_reference_id'] ?? '')),
                'amount' => (int)($j['amount_total'] ?? 0),
                'currency' => strtoupper(preg_replace('/[^a-z]/', '', (string)($j['currency'] ?? 'usd'))),
            ];
        }
    } else {
        error_log('TDUSA claim_thanks: Stripe lookup failed (HTTP ' . $code . ') ' . $err);
    }
}

page_start(['title' => 'Thank you | ' . cfg('site_name'), 'robots' => 'noindex', 'cache' => false]);
?>
<div class="container section"><div class="card" style="max-width:720px;margin:0 auto">
<?php if ($verified): ?>
<h1 style="color:#fff;font-size:1.5rem;margin-bottom:10px">Payment received &mdash; thank you</h1>
<p>Stripe confirmed your payment<?= $verified['amount'] > 0 ? ' of <b>$' . e(number_format($verified['amount'] / 100, 2)) . ' ' . e($verified['currency']) . '</b>' : '' ?><?= $verified['ref'] !== '' ? ' for order reference <b>' . e($verified['ref']) . '</b>' : '' ?>.</p>
<p style="margin-top:10px">Your listing is now under review and is activated after a quick check (usually the same business day). A confirmation email with these details is sent to the address you used at checkout.</p>
<?php else: ?>
<h1 style="color:#fff;font-size:1.5rem;margin-bottom:10px">Thank you</h1>
<p>If your payment completed you will receive a confirmation email; your listing is activated after a quick review (usually the same business day).</p>
<?php endif; ?>
<p style="margin-top:12px" class="small"><?= e($refund) ?></p>
<p style="margin-top:12px">Questions? Email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a><?= $verified && $verified['ref'] !== '' ? ' and quote reference ' . e($verified['ref']) : '' ?>.</p>
<p style="margin-top:18px"><a class="btn" href="/carriers/">Back to the directory</a></p>
</div></div>
<?php page_end();
