<?php
/**
 * Trucking Directory USA - carrier engine configuration.
 *
 * Edit the values below (or copy this file to config.local.php and override only
 * what you need; config.local.php is loaded after this file if it exists).
 */
declare(strict_types=1);

return [
    // Public site URL, no trailing slash.
    'site_url'        => 'https://truckingdirectoryusa.com',
    'site_name'       => 'Trucking Directory USA',

    // Where the read-only carrier database (built by tools/build_carriers_db.py) lives.
    // Best: one level ABOVE public_html so it can never be downloaded:
    //   /home/uXXXXXXX/domains/truckingdirectoryusa.com/data/carriers.db
    // The first path that exists is used.
    'db_paths'        => [
        __DIR__ . '/../../data/carriers.db',   // ../data next to public_html (recommended)
        __DIR__ . '/data/carriers.db',         // public_html/app/data (protected by .htaccess)
    ],

    // Small writable database for claims, vendor listings and leads (created automatically).
    'site_db_path'    => __DIR__ . '/storage/site.db',

    // Google Analytics 4 measurement ID for THIS domain (not the CarrierDataUSA one). '' disables.
    'ga_id'           => '',

    // Where notifications about new claims, listings and quote requests go (private, never shown on the site).
    'notify_email'    => 'pat@barrgo.com',
    'from_email'      => 'noreply@truckingdirectoryusa.com',
    // Public support address: shown in the footer, on forms, receipts and the terms/privacy pages.
    // Create this mailbox (or an alias to your inbox) in hPanel -> Emails.
    'contact_email'   => 'support@truckingdirectoryusa.com',

    // Legal identity shown in the footer, on /terms and /privacy and in buyer receipts.
    'operator_name'    => 'Barrgo LLC',
    'operator_address' => '[Barrgo LLC, street address, Marietta, GA]',
    'refund_policy'    => 'Because listings are activated after payment, we do not refund completed orders, but if we cannot activate your listing as described within 3 business days, email us within 7 days and we will fix it or refund you. [confirm]',
    // Optional: replace the built-in /terms and /privacy prose. Plain text, paragraphs separated by a blank line.
    'terms_text'       => '',
    'privacy_text'     => '',

    // Secret used to sign the public form tokens. Leave '' and the engine creates
    // app/storage/form.key (random, chmod 0600) on first use. Never reuse the admin password.
    'form_secret'     => '',

    // Set true ONLY when every request reaches the server through Cloudflare; then the
    // CF-Connecting-IP header is trusted for rate limiting and the stored IP evidence.
    'behind_cloudflare' => false,

    // Stripe Payment Links (create/edit in the Stripe dashboard). Create NEW links for this
    // flow and deactivate the old bare $49 link, so nobody can pay without an intake form.
    // Each link must use a ONE-TIME price, not a recurring one: a listing runs one year and is
    // renewed by a new order. A payment through a subscription link is parked in 'review'.
    // In each link's settings set "After payment" -> redirect to:
    //   https://truckingdirectoryusa.com/claim/thanks?session_id={CHECKOUT_SESSION_ID}
    'stripe' => [
        'claim_verified'  => 'https://buy.stripe.com/REPLACE_ME',  // Verified carrier listing  ($49 one-time, runs one year)
        'claim_featured'  => 'https://buy.stripe.com/REPLACE_ME',  // Featured carrier listing  ($149 one-time, runs one year)
        'vendor_listed'   => 'https://buy.stripe.com/REPLACE_ME',  // Vendor listing            ($49 one-time, runs one year)
        'vendor_featured' => 'https://buy.stripe.com/REPLACE_ME',  // Vendor featured sponsor   ($149 one-time, runs one year)
        // Webhook signing secret (Developers -> Webhooks -> Add endpoint https://truckingdirectoryusa.com/stripe-webhook)
        // Subscribe to: checkout.session.completed, checkout.session.async_payment_succeeded,
        // checkout.session.async_payment_failed, charge.refunded, charge.dispute.created
        'webhook_secret'  => '',
        // Restricted API key (read access to Checkout Sessions is enough). Used by /claim/thanks
        // to confirm a payment before saying "Payment received". '' = never claim payment on that page.
        'secret_key'      => '',
    ],
    'prices' => [
        'claim_verified'  => 49,
        'claim_featured'  => 149,
        'vendor_listed'   => 49,
        'vendor_featured' => 149,
    ],

    // CarrierDataUSA funnel. {state} and {state_slug} are replaced on state pages.
    'cdusa' => [
        'home'      => 'https://carrierdatausa.com/?utm_source=truckingdirectoryusa&utm_medium=directory&utm_campaign=carrier_pages',
        // Per-state product page/checkout. Leave the pattern if you have state products at predictable URLs,
        // or fill 'state_links' with specific URLs: 'GA' => 'https://carrierdatausa.com/...'
        'state_pattern' => 'https://carrierdatausa.com/?utm_source=truckingdirectoryusa&utm_medium=directory&utm_campaign=state_{state_slug}',
        'state_links'   => [],
        // Packages shown on /carrier-data/ (edit names, prices and links freely).
        'packages' => [
            ['name' => 'National Full Database',        'who' => 'Any buyer, broadest reach',          'fields' => 'All fields, every record',                                          'price' => 349, 'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Single State List',             'who' => 'Local vendors, small brokers',       'fields' => 'All fields for one state',                                          'price' => 49,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Regional Bundle (5 states)',    'who' => 'Regional brokers and vendors',       'fields' => 'All fields, buyer picks the region',                                'price' => 149, 'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Freight Broker Pack',           'who' => 'Freight brokers, load boards',       'fields' => 'For-hire carriers, MC number, fleet size, cargo types, contacts',   'price' => 129, 'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Insurance Agent Pack',          'who' => 'Trucking insurance agents',          'fields' => 'Safety rating, crash rate, hazmat flag, fleet size, contacts',      'price' => 129, 'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Owner-Operator List',           'who' => 'CDL schools, fuel cards, equipment', 'fields' => '1-3 power units, cell phone, email, DOT, state',                   'price' => 99,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Large Fleet List (20+ trucks)', 'who' => 'Equipment dealers, tire vendors',    'fields' => '20+ power units, truck units, contacts',                            'price' => 99,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Hazmat Carrier List',           'who' => 'Hazmat equipment, compliance',       'fields' => 'HM carriers, DOT, MC, email, phone, officer name',                  'price' => 79,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Reefer / Cold Chain List',      'who' => 'Cold chain equipment, logistics',    'fields' => 'Refrigerated food and produce haulers, contacts, fleet size',       'price' => 79,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Tanker Carrier List',           'who' => 'Chemical, fuel, liquid vendors',     'fields' => 'Liquids/gases and chemical haulers, contacts, safety rating',       'price' => 79,  'url' => 'https://carrierdatausa.com/'],
            ['name' => 'Decision-Maker Direct',         'who' => 'High-touch sales outreach',          'fields' => 'Officer names 1 and 2, cell phone, email, company, state',          'price' => 149, 'url' => 'https://carrierdatausa.com/'],
        ],
    ],

    // Admin panel at /admin (HTTP Basic Auth). CHANGE THESE.
    // Preferred: set admin_pass_hash to the output of
    //   php -r 'echo password_hash("your long password", PASSWORD_DEFAULT), PHP_EOL;'
    // and leave admin_pass empty. A plain admin_pass still works when admin_pass_hash is ''.
    'admin_user'      => 'admin',
    'admin_pass'      => 'change-me-now',
    'admin_pass_hash' => '',

    // SEO rollout: 1 = only tier-1 carriers are indexable and in the sitemaps (active, phone on file,
    // 1+ power unit); 2 = also tier-2 (active but missing phone or fleet size). Carrier pages above
    // this tier carry robots noindex,follow so the sitemap and the robots rule always agree.
    // Tier 3 (inactive) is always noindex and never listed on city/cargo/state pages.
    'sitemap_max_tier' => 1,
    'sitemap_chunk'    => 50000,

    // City pages with fewer ACTIVE carriers than this are noindex,follow and left out of the city
    // sitemaps (they still render and link their carriers, so nothing becomes unreachable).
    'min_city_carriers' => 3,

    // Let the engine serve the 16 vendor category pages (/factoring_companies/georgia.html etc.)
    // with real listings and lead forms instead of the old static placeholder pages.
    // Requires the matching rules in .htaccess and deleting (or renaming) the old static folders.
    'takeover_vendor_pages' => true,

    // Items per page on city / cargo / vendor listing pages.
    'per_page'        => 50,

    // Browser/CDN cache time for public pages (seconds). 0 disables.
    'cache_seconds'   => 3600,
];
