<?php
/**
 * Stripe webhook: /stripe-webhook
 * In Stripe: Developers -> Webhooks -> Add endpoint -> URL https://truckingdirectoryusa.com/stripe-webhook
 * Events: checkout.session.completed, checkout.session.async_payment_succeeded,
 *         checkout.session.async_payment_failed, charge.refunded, charge.dispute.created.
 * Copy the signing secret into config (stripe.webhook_secret).
 * Payment Links pass through client_reference_id ("claim-123" / "vendor-45") so we know what was bought.
 * A confirmed payment sets the row to 'paid' (awaiting review); only the admin makes it 'active'.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
$secret = (string)(cfg('stripe')['webhook_secret'] ?? '');
if ($secret === '') {
    http_response_code(400);
    echo 'webhook secret not configured';
    exit;
}
$payload = file_get_contents('php://input') ?: '';
$sig = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
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
if (!is_array($event)) {
    http_response_code(400);
    echo 'bad payload';
    exit;
}
$type = (string)($event['type'] ?? '');
$obj = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

function wh_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

/** "claim-12" -> ['table' => 'claims', 'kind' => 'claim', 'id' => 12]; null when the reference is not ours. */
function wh_parse_ref(string $ref): ?array
{
    if (preg_match('/^claim-(\d{1,12})$/', $ref, $m)) {
        return ['table' => 'claims', 'kind' => 'claim', 'id' => (int)$m[1]];
    }
    if (preg_match('/^vendor-(\d{1,12})$/', $ref, $m)) {
        return ['table' => 'vendors', 'kind' => 'vendor', 'id' => (int)$m[1]];
    }
    return null;
}

function wh_price_key(string $table, string $plan): string
{
    if ($table === 'claims') {
        return $plan === 'featured' ? 'claim_featured' : 'claim_verified';
    }
    return $plan === 'featured' ? 'vendor_featured' : 'vendor_listed';
}

function wh_plan_label(string $table, string $plan): string
{
    if ($table === 'claims') {
        return $plan === 'featured' ? 'Featured carrier listing (1 year)' : 'Verified carrier listing (1 year)';
    }
    return $plan === 'featured' ? 'Featured vendor sponsorship (1 year)' : 'Standard vendor listing (1 year)';
}

/** Log a payment we cannot match, once per Stripe session (the session id is kept in leads.source_url). Returns true when newly logged. */
function wh_unmatched(PDO $db, string $session, string $ref, int $amount, string $email, string $why): bool
{
    $st = $db->prepare("SELECT id FROM leads WHERE type = 'stripe-unmatched' AND source_url = ?");
    $st->execute([$session]);
    if ($st->fetch()) {
        return false;
    }
    $db->prepare("INSERT INTO leads (type, email, notes, source_url, status, ip, created_at) VALUES ('stripe-unmatched', ?, ?, ?, 'new', '', datetime('now'))")
        ->execute([$email, 'Stripe session ' . $session . ' amount ' . wh_money($amount) . ($ref !== '' ? " reference '$ref'" : '') . ' - ' . $why, $session]);
    send_notification('Unmatched Stripe payment ' . wh_money($amount),
        "Session $session from $email for " . wh_money($amount) . ($ref !== '' ? " carried reference '$ref'" : ' had no client_reference_id') . " ($why).\nMatch it manually in " . url('/admin') . " or refund it in Stripe.");
    return true;
}

/** Receipt to the buyer after a confirmed payment. */
function wh_buyer_confirmation(string $table, array $row, int $id, int $amount, string $to): void
{
    if (!valid_email($to)) {
        return;
    }
    $cats = $GLOBALS['CATEGORIES'];
    $ref = ($table === 'claims' ? 'claim-' : 'vendor-') . $id;
    if ($table === 'claims') {
        $dot = (int)$row['dot'];
        $what = 'carrier profile USDOT ' . $dot;
        $where = url('/carrier/' . $dot);
        try {
            $c = q1('SELECT legal_name, slug FROM carriers WHERE dot = ?', [$dot]);
            if ($c) {
                $what = company_name($c['legal_name']) . ' (USDOT ' . $dot . ')';
                $where = url(carrier_path(['dot' => $dot, 'slug' => $c['slug']]));
            }
        } catch (Throwable $e) {
            // carriers.db unavailable: keep the generic description
        }
        $goesLive = 'Your Verified badge, website link and email button appear on ' . $where . ' once the listing is activated, and competitor offers are removed from that page.'
            . ((string)$row['plan'] === 'featured' ? ' As a Featured listing, the profile is also pinned at the top of your city and cargo pages.' : '');
    } else {
        $catName = $cats[$row['category']]['name'] ?? (string)$row['category'];
        $what = (string)$row['name'] . ' (' . $catName . ')';
        $goesLive = 'Your listing appears on the ' . $catName . ' pages for the states you selected (' . (string)$row['states_served'] . ') once it is activated.'
            . ((string)$row['plan'] === 'featured' ? ' As a Featured sponsor you are placed at the top of those pages and rotate through the sponsored slots on carrier profiles and state pages in those states.' : '');
    }
    $body = "Thank you for your order.\n\n"
        . "Order: #$id (reference $ref)\n"
        . 'Purchased: ' . wh_plan_label($table, (string)$row['plan']) . "\n"
        . "For: $what\n"
        . 'Amount paid: ' . wh_money($amount) . " USD\n\n"
        . "What happens next: your listing is under review and is activated after a quick check, usually the same business day. $goesLive\n\n"
        . 'Refund policy: ' . (string)cfg('refund_policy') . "\n\n"
        . 'Questions or changes: email ' . (string)cfg('contact_email') . " and quote the order reference above.\n\n"
        . (string)cfg('site_name') . ' is operated by ' . (string)cfg('operator_name') . ', ' . (string)cfg('operator_address') . "\n"
        . url('/terms') . ' | ' . url('/privacy') . "\n";
    send_mail($to, 'Your Trucking Directory USA order #' . $id, $body);
}

function wh_checkout(PDO $db, array $s): void
{
    $ref = (string)($s['client_reference_id'] ?? '');
    $session = (string)($s['id'] ?? '');
    $amount = (int)($s['amount_total'] ?? 0);
    $currency = strtolower((string)($s['currency'] ?? 'usd'));
    $email = (string)($s['customer_details']['email'] ?? ($s['customer_email'] ?? ''));
    $pi = is_string($s['payment_intent'] ?? null) ? (string)$s['payment_intent'] : (string)($s['payment_intent']['id'] ?? '');
    if (($s['payment_status'] ?? '') !== 'paid') {
        echo 'not paid yet';
        return;
    }
    if ($session === '') {
        http_response_code(400);
        echo 'missing session id';
        return;
    }
    $target = wh_parse_ref($ref);
    if ($target === null) {
        // Payment without a usable reference (e.g. someone used a bare Stripe link). Log it once for manual matching.
        echo wh_unmatched($db, $session, $ref, $amount, $email, 'no client_reference_id') ? 'logged' : 'already processed';
        return;
    }
    $table = $target['table'];
    $id = $target['id'];
    $st = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        wh_unmatched($db, $session, $ref, $amount, $email, 'reference does not exist');
        echo 'no pending row';
        return;
    }
    if ((string)$row['stripe_session'] === $session) {
        echo 'already processed';
        return;
    }
    if ($row['status'] !== 'pending') {
        error_log("TDUSA webhook: $ref is '{$row['status']}', not pending; session $session (" . wh_money($amount) . ') not applied');
        echo 'no pending row';
        return;
    }
    $expected = (int)(cfg('prices')[wh_price_key($table, (string)$row['plan'])] ?? 0) * 100;
    if ($expected <= 0 || $amount !== $expected || $currency !== 'usd') {
        $db->prepare("UPDATE $table SET status = 'review', stripe_session = ?, amount_cents = ?, payment_intent = ?, updated_at = datetime('now') WHERE id = ? AND status = 'pending'")
            ->execute([$session, $amount, $pi, $id]);
        send_notification(ucfirst($target['kind']) . " #$id AMOUNT MISMATCH (" . wh_money($amount) . ' paid, ' . wh_money($expected) . ' expected)',
            "Stripe session $session paid " . wh_money($amount) . ' ' . strtoupper($currency) . " but the '{$row['plan']}' plan costs " . wh_money($expected) . ".\nPayer: $email\nThe row was set to 'review' and is NOT live. Check it in " . url('/admin') . ' before activating, or refund in Stripe.');
        echo 'amount mismatch';
        return;
    }
    $st = $db->prepare("UPDATE $table SET status = 'paid', stripe_session = ?, amount_cents = ?, payment_intent = ?, paid_at = datetime('now'), updated_at = datetime('now') WHERE id = ? AND status = 'pending'");
    $st->execute([$session, $amount, $pi, $id]);
    if ($st->rowCount() !== 1) {
        echo 'no pending row';
        return;
    }
    send_notification(ucfirst($target['kind']) . " #$id PAID (" . wh_money($amount) . ')',
        "Stripe session $session\nPayment intent: $pi\nPayer: $email\nPlan: {$row['plan']}\n\nReview and activate: " . url('/admin'));
    wh_buyer_confirmation($table, $row, $id, $amount, valid_email($email) ? $email : (string)$row['email']);
    echo $target['kind'] . ' updated';
}

function wh_async_failed(PDO $db, array $s): void
{
    $ref = (string)($s['client_reference_id'] ?? '');
    $session = (string)($s['id'] ?? '');
    $target = wh_parse_ref($ref);
    if ($target === null) {
        echo 'ignored';
        return;
    }
    $st = $db->prepare("UPDATE {$target['table']} SET status = 'pending', updated_at = datetime('now') WHERE id = ? AND status IN ('paid','review') AND (stripe_session = ? OR stripe_session IS NULL)");
    $st->execute([$target['id'], $session]);
    if ($st->rowCount() === 0) {
        // Normal delayed-payment flow: the row never left 'pending', so there is nothing to undo or report.
        echo 'payment failed (no change)';
        return;
    }
    send_notification(ucfirst($target['kind']) . " #{$target['id']} payment FAILED", "Stripe reported the delayed payment for session $session as failed. The row is back to 'pending' (not live). Nothing to do unless the buyer pays again.");
    echo 'payment failed';
}

/** charge.refunded -> rejected, charge.dispute.created -> disputed; the row is found by payment_intent. */
function wh_intent_status(PDO $db, array $obj, string $newStatus, string $verb): void
{
    $pi = is_string($obj['payment_intent'] ?? null) ? (string)$obj['payment_intent'] : (string)($obj['payment_intent']['id'] ?? '');
    if ($pi === '') {
        echo 'no payment_intent';
        return;
    }
    $amount = (int)($obj['amount_refunded'] ?? ($obj['amount'] ?? 0));
    foreach (['claims' => 'claim', 'vendors' => 'vendor'] as $table => $kind) {
        $st = $db->prepare("SELECT * FROM $table WHERE payment_intent = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$pi]);
        $row = $st->fetch();
        if (!$row) {
            continue;
        }
        if ($row['status'] === $newStatus) {
            echo 'already processed';
            return;
        }
        $db->prepare("UPDATE $table SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$newStatus, (int)$row['id']]);
        send_notification(ucfirst($kind) . " #{$row['id']} $verb (" . wh_money($amount) . ')',
            "Payment intent $pi was $verb in Stripe. The row is now '$newStatus' and no longer shows publicly.\nContact: {$row['email']}\nAdmin: " . url('/admin'));
        echo "$kind $verb";
        return;
    }
    echo 'no row for payment_intent';
}

$db = sitedb();
switch ($type) {
    case 'checkout.session.completed':
    case 'checkout.session.async_payment_succeeded':
        wh_checkout($db, $obj);
        break;
    case 'checkout.session.async_payment_failed':
        wh_async_failed($db, $obj);
        break;
    case 'charge.refunded':
        wh_intent_status($db, $obj, 'rejected', 'refunded');
        break;
    case 'charge.dispute.created':
        wh_intent_status($db, $obj, 'disputed', 'disputed');
        break;
    default:
        echo 'ignored';
}
