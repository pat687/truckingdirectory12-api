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

    // Where notifications about new claims, listings and quote requests go.
    'notify_email'    => 'pat@barrgo.com',
    'from_email'      => 'noreply@truckingdirectoryusa.com',

    // Stripe Payment Links (create/edit in the Stripe dashboard).
    // In each link's settings set "After payment" -> redirect to:
    //   https://truckingdirectoryusa.com/claim/thanks?session_id={CHECKOUT_SESSION_ID}
    'stripe' => [
        'claim_verified'  => 'https://buy.stripe.com/REPLACE_ME',  // Verified carrier listing  ($49/yr suggested)
        'claim_featured'  => 'https://buy.stripe.com/REPLACE_ME',  // Featured carrier listing  ($149/yr suggested)
        'vendor_listed'   => 'https://buy.stripe.com/cNibIUaVF2FlbAy8go3Ru04', // existing "Get Listed" $49 link
        'vendor_featured' => 'https://buy.stripe.com/REPLACE_ME',  // existing "Advertise" $149 link
        // Webhook signing secret (Developers -> Webhooks -> Add endpoint https://truckingdirectoryusa.com/stripe-webhook)
        'webhook_secret'  => '',
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
    'admin_user'      => 'admin',
    'admin_pass'      => 'change-me-now',

    // SEO rollout: 1 = only tier-1 carriers in sitemaps (active, phone on file, 1+ power unit);
    // 2 = also tier-2 (active but missing phone or fleet size). Tier 3 (inactive) is always noindex.
    'sitemap_max_tier' => 1,
    'sitemap_chunk'    => 50000,

    // Let the engine serve the 16 vendor category pages (/factoring_companies/georgia.html etc.)
    // with real listings and lead forms instead of the old static placeholder pages.
    // Requires the matching rules in .htaccess and deleting (or renaming) the old static folders.
    'takeover_vendor_pages' => true,

    // Items per page on city / cargo / vendor listing pages.
    'per_page'        => 50,

    // Browser/CDN cache time for public pages (seconds). 0 disables.
    'cache_seconds'   => 3600,
];
