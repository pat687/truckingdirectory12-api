<?php
/**
 * Sponsor slots shown on carrier and state pages ("Services for carriers in Georgia").
 * Paid vendor listings from the admin panel are shown first; these are the house defaults.
 *
 * Each slot: 'name', 'url', 'blurb', 'states' => 'ALL' or ['GA','FL'].
 * Leave a category's 'items' empty to show a "Get quotes" lead form instead.
 */
declare(strict_types=1);

return [
    'factoring' => [
        'label' => 'Freight factoring',
        'blurb' => 'Get paid in 24 hours on every load.',
        'items' => [],
    ],
    'insurance' => [
        'label' => 'Trucking insurance',
        'blurb' => 'Compare commercial auto and cargo quotes.',
        'items' => [],
    ],
    'dispatch' => [
        'label' => 'Dispatch services',
        'blurb' => 'Better loads and rates for owner-operators.',
        'items' => [],
    ],
    'equipment' => [
        'label' => 'Trailers and equipment',
        'blurb' => 'Tankers, flatbeds, hoppers, pneumatics and cranes.',
        'items' => [
            // Patrick's own brokerage - edit or remove.
            ['name' => 'Barrgo LLC', 'url' => 'https://barrgo.com/?utm_source=truckingdirectoryusa&utm_medium=sponsor',
             'blurb' => 'Commercial truck and heavy equipment brokerage. Buy or sell trailers nationwide.', 'states' => 'ALL'],
        ],
    ],
    'fuel-cards' => [
        'label' => 'Fuel cards',
        'blurb' => 'Fleet fuel discounts at thousands of truck stops.',
        'items' => [],
    ],
    'eld' => [
        'label' => 'ELD and software',
        'blurb' => 'Stay compliant with an FMCSA-registered ELD.',
        'items' => [],
    ],
    'financing' => [
        'label' => 'Truck financing',
        'blurb' => 'Finance or lease your next truck or trailer.',
        'items' => [],
    ],
    'compliance' => [
        'label' => 'DOT compliance',
        'blurb' => 'Authority, UCR, IFTA, drug and alcohol programs.',
        'items' => [],
    ],
    'repair' => [
        'label' => 'Truck repair',
        'blurb' => 'Heavy-duty shops and mobile mechanics.',
        'items' => [],
    ],
];
