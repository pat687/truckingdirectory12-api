<?php
/**
 * Stripe webhook: /stripe-webhook
 * In Stripe: Developers -> Webhooks -> Add endpoint -> URL https://truckingdirectoryusa.com/stripe-webhook
 * Event: checkout.session.completed. Copy the signing secret into config.php (stripe.webhook_secret).
 * Payment Links pass through client_reference_id ("claim-123" / "vendor-45") so we know what was bought.
 */
declare(strict_types=1);

header('Content-Type: text/plain');
header('Cache-Control: no-store');
$secret = (string)(cfg('stripe')['webhook_secret'] ?? '');
if ($secret === '') {
    http_response_code(400);
    echo 'webhook secret not configured';
    exit;
}
$payload = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$ts = '';
$v1 = [];
foreach (explode(',', $sig) as $kv) {
    [$k, $val] = array_pad(explode('=', trim($kv), 2), 2, '');
    if ($k === 't') {
        $ts = $val;
    } elseif ($k === 'v1') {
        $v1[] = $val;
    }
}
$expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
$ok = false;
foreach ($v1 as $s) {
    if (hash_equals($expected, $s)) {
        $ok = true;
    }
}
if (!$ok || abs(time() - (int)$ts) > 600) {
    http_response_code(400);
    echo 'bad signature';
    exit;
}
$event = json_decode($payload, true);
if (($event['type'] ?? '') !== 'checkout.session.completed') {
    echo 'ignored';
    exit;
}
$s = $event['data']['object'] ?? [];
$ref = (string)($s['client_reference_id'] ?? '');
$session = (string)($s['id'] ?? '');
$amount = (int)($s['amount_total'] ?? 0);
$email = (string)($s['customer_details']['email'] ?? ($s['customer_email'] ?? ''));
$db = sitedb();
if (preg_match('/^claim-(\d+)$/', $ref, $m)) {
    $st = $db->prepare('UPDATE claims SET status = \'paid\', stripe_session = ?, amount_cents = ?, paid_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ? AND status = \'pending\'');
    $st->execute([$session, $amount, (int)$m[1]]);
    send_notification('Claim #' . $m[1] . ' PAID ($' . number_format($amount / 100, 2) . ')', "Stripe session $session\nPayer: $email\nReview and activate: " . url('/admin'));
    echo 'claim updated';
} elseif (preg_match('/^vendor-(\d+)$/', $ref, $m)) {
    $st = $db->prepare('UPDATE vendors SET status = \'paid\', stripe_session = ?, amount_cents = ?, paid_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ? AND status = \'pending\'');
    $st->execute([$session, $amount, (int)$m[1]]);
    send_notification('Vendor listing #' . $m[1] . ' PAID ($' . number_format($amount / 100, 2) . ')', "Stripe session $session\nPayer: $email\nReview and activate: " . url('/admin'));
    echo 'vendor updated';
} else {
    // Payment without a reference (e.g. someone used a bare Stripe link). Log it for manual matching.
    $st = $db->prepare('INSERT INTO leads (type, email, notes, status, ip, created_at) VALUES (\'stripe-unmatched\', ?, ?, \'new\', \'\', datetime(\'now\'))');
    $st->execute([$email, 'Stripe session ' . $session . ' amount ' . $amount]);
    send_notification('Unmatched Stripe payment', "Session $session from $email for $" . number_format($amount / 100, 2) . " had no client_reference_id. Match it manually in /admin.");
    echo 'logged';
}
