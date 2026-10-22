<?php
/** Terms of service: /terms (prose from config 'terms_text', else the defaults below). */
declare(strict_types=1);

$operator = (string)cfg('operator_name', 'Barrgo LLC');
$address = (string)cfg('operator_address', '');
$support = (string)cfg('contact_email', '');
$site = (string)cfg('site_name');
$prices = (array)cfg('prices');
$paragraphs = config_paragraphs('terms_text', [
    "$site (truckingdirectoryusa.com) is operated by $operator" . ($address !== '' ? ", $address" : '') . ". You can reach us at $support. By using this website or buying a listing you agree to these terms.",
    "Carrier profiles are built from public motor carrier registration records published by the Federal Motor Carrier Safety Administration (FMCSA). $site is a privately owned directory and is not affiliated with, endorsed by or operated by FMCSA, the US Department of Transportation or any government agency. Records reflect the FMCSA snapshot shown on each page and can be out of date; confirm operating authority and insurance on FMCSA SAFER before doing business with a carrier.",
    'Paid carrier listings. A Verified listing ($' . (int)($prices['claim_verified'] ?? 0) . ' for one year, paid once) adds a Verified badge, your website link and an email button to your carrier profile, and removes competitor offers from your page. Profile details come from FMCSA registration records; to change them, file an updated MCS-150 with FMCSA and the profile updates with our next data refresh. A Featured listing ($' . (int)($prices['claim_featured'] ?? 0) . ' for one year, paid once) includes everything in Verified plus top placement on the city and cargo pages your carrier belongs to. Listings are activated after payment and a short review, usually the same business day. We may decline a claim we cannot reasonably connect to the carrier, and we may remove a listing that misrepresents who operates the carrier; in both cases we refund the payment.',
    'Paid vendor listings. A Standard listing ($' . (int)($prices['vendor_listed'] ?? 0) . ' for one year, paid once) shows your company on the category pages for every state you serve. A Featured sponsorship ($' . (int)($prices['vendor_featured'] ?? 0) . ' for one year, paid once) adds top placement on those category pages plus rotation in the sponsored slots on carrier profiles and state pages in the states you serve. Placement in rotating slots is shared with other featured sponsors in the same states. Listings are activated after payment and a short review, usually the same business day.',
    'Term and renewal. Each listing is a one-time payment, not a subscription: it runs for one year from activation and comes down automatically when the year ends, and we never charge you again on our own. To renew, place a new order for the same listing; it runs for a year from its own activation.',
    'Refund policy. ' . (string)cfg('refund_policy'),
    'Payments are processed by Stripe. Prices are in US dollars. We never see or store your card number.',
    'Your responsibilities. You confirm that you are authorized to act for the company you claim or list, that the details you give us are accurate, and that your website is your own. You may not use automated tools to copy the directory, and you may not use the lead forms to contact carriers for purposes they did not ask for.',
    "Liability. The directory is provided as is. $operator is not liable for decisions made on the basis of the records shown here or for the conduct of carriers, vendors or providers listed on the site. Our total liability to a buyer is limited to the amount paid for the listing in question.",
    "Changes and law. We may update these terms; the current version is always at this address. These terms are governed by the laws of the State of Georgia, USA. Questions: $support.",
]);
$crumbs = [['Carriers', '/carriers/'], ['Terms of Service', null]];
page_start(['title' => 'Terms of Service | ' . $site, 'robots' => 'noindex,follow', 'cache' => false, 'canonical' => '/terms', 'description' => 'Terms for using Trucking Directory USA and buying a carrier or vendor listing.']);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?><h1>Terms of <span>Service</span></h1><p class="sub">Last updated <?= e(date('F Y')) ?></p></div></div>
<div class="container section"><div class="card prose" style="max-width:860px">
<?php foreach ($paragraphs as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
<p class="small">See also our <a href="/privacy" style="text-decoration:underline">Privacy Policy</a>. Carriers can <a href="/opt-out" style="text-decoration:underline">request a correction or removal</a>.</p>
</div></div>
<?php page_end();
