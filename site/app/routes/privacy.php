<?php
/** Privacy policy: /privacy (prose from config 'privacy_text', else the defaults below). */
declare(strict_types=1);

$operator = (string)cfg('operator_name', 'Barrgo LLC');
$address = (string)cfg('operator_address', '');
$support = (string)cfg('contact_email', '');
$site = (string)cfg('site_name');
$ga = (string)cfg('ga_id') !== '';
$paragraphs = config_paragraphs('privacy_text', [
    "This policy explains what $site (truckingdirectoryusa.com), operated by $operator" . ($address !== '' ? ", $address" : '') . ", collects and how it is used. Contact: $support.",
    "Carrier records. The carrier profiles on this site come from public registration records published by the Federal Motor Carrier Safety Administration (FMCSA): company name, USDOT and docket numbers, physical address, office phone, fleet and driver counts, cargo types, operating authority and safety data. $site is not affiliated with FMCSA or the US Department of Transportation. Profiles show the FMCSA snapshot date; we refresh them when FMCSA publishes new data. Profile details come from each carrier's FMCSA registration; a carrier changes them by filing an updated MCS-150 with FMCSA, and the profile follows at our next refresh. Carriers who want their contact details removed from lists or their profile removed can use the request form at /opt-out or email $support; we process removals within 10 business days and before the next data refresh, and keep removed USDOT numbers on a suppression list so they do not reappear.",
    'What you give us. When you claim a profile, buy a vendor listing, request quotes or send an opt-out request we store what you type (name, role, company, email, phone, website, notes), the time, your IP address and browser identifier (kept as evidence of who submitted the form and to prevent abuse), and for paid orders the Stripe checkout session, payment reference, amount and the email address you used at checkout. We never see or store card numbers; payment is handled by Stripe under its own privacy policy.',
    'How it is used. Claim and vendor details are used to review, activate and publish the listing you bought; only the parts you chose to publish appear on the site (for example your email address only if you ticked the box). Quote requests are shared only with the providers you asked to hear from, up to three per request, so they can contact you by phone, email or text; you can withdraw that consent at any time by emailing us. Opt-out requests are used to make the change you asked for and to confirm it with you. Order records are kept for accounting and to respond to payment disputes.',
    "What we do not do. We do not sell the personal details you submit on this website, and they are never added to any data product. Carrier contact lists sold by CarrierDataUSA are compiled separately from public FMCSA registration records, not from this website's forms. Exported form data is used only to fulfil orders and provide support.",
    'Cookies and analytics. The public pages set no cookies of their own. ' . ($ga ? 'We use Google Analytics 4 to measure traffic; Google sets cookies and processes your IP address and browsing data under its own terms. ' : 'Google Analytics is not currently enabled on these pages; if we enable it this policy will say so. ') . 'The administration area uses a session cookie that only the site operator receives.',
    'Retention and your rights. We keep leads and opt-out requests only as long as we need them to handle the request and to keep removals in effect, and delete them on request; order records are kept for as long as tax and payment-dispute rules require. You can ask what we hold about you, ask us to correct it, or ask us to delete it (except records we must keep) by emailing ' . $support . '. California and other state residents may exercise their privacy rights the same way; we do not sell or share your personal information as those laws define it.',
    "Children. This site is for businesses and is not directed at children under 16. Changes to this policy are posted at this address. Questions: $support.",
]);
$crumbs = [['Carriers', '/carriers/'], ['Privacy Policy', null]];
page_start(['title' => 'Privacy Policy | ' . $site, 'robots' => 'noindex,follow', 'cache' => false, 'canonical' => '/privacy', 'description' => 'What Trucking Directory USA collects, how it is used, and how carriers can correct or remove their details.']);
?>
<div class="hero"><div class="container"><?= breadcrumbs_html($crumbs) ?><h1>Privacy <span>Policy</span></h1><p class="sub">Last updated <?= e(date('F Y')) ?></p></div></div>
<div class="container section"><div class="card prose" style="max-width:860px">
<?php foreach ($paragraphs as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
<p class="small">See also our <a href="/terms" style="text-decoration:underline">Terms of Service</a>. Carriers: <a href="/opt-out" style="text-decoration:underline">remove or correct your details</a>.</p>
</div></div>
<?php page_end();
