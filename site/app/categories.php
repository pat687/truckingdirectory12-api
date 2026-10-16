<?php
/**
 * The 16 vendor categories (folder names match the existing site URLs).
 * 'quote' = the lead-form type carriers can request quotes for (null = no quote form).
 */
declare(strict_types=1);

return [
    'freight_brokers'        => ['name' => 'Freight Brokers',             'singular' => 'freight broker',           'quote' => null,
        'intro' => 'Licensed freight brokers who move loads for shippers and keep carriers rolling.'],
    'equipment_dealers'      => ['name' => 'Equipment Dealers',           'singular' => 'equipment dealer',         'quote' => 'equipment',
        'intro' => 'New and used truck, trailer and heavy equipment dealers.'],
    'equipment_brokers'      => ['name' => 'Equipment Brokers',           'singular' => 'equipment broker',         'quote' => 'equipment',
        'intro' => 'Brokers who match buyers and sellers of trailers, trucks and heavy equipment.'],
    'dispatch_services'      => ['name' => 'Dispatch Services',           'singular' => 'dispatch service',         'quote' => 'dispatch',
        'intro' => 'Dispatchers who find loads, negotiate rates and handle paperwork for owner-operators and small fleets.'],
    'insurance_brokers'      => ['name' => 'Trucking Insurance',          'singular' => 'trucking insurance agency', 'quote' => 'insurance',
        'intro' => 'Commercial auto, cargo and general liability insurance for motor carriers.'],
    'finance_lenders'        => ['name' => 'Truck Financing & Lenders',   'singular' => 'truck lender',             'quote' => 'financing',
        'intro' => 'Equipment financing, leasing and working-capital lenders for carriers.'],
    'factoring_companies'    => ['name' => 'Factoring Companies',         'singular' => 'factoring company',        'quote' => 'factoring',
        'intro' => 'Freight factoring companies that pay invoices in 24 hours.'],
    'fuel_cards'             => ['name' => 'Fuel Cards & Programs',       'singular' => 'fuel card program',        'quote' => 'fuel-cards',
        'intro' => 'Fleet fuel cards and discount programs.'],
    'trucking_software'      => ['name' => 'Trucking Software & ELD',     'singular' => 'ELD or software provider', 'quote' => 'eld',
        'intro' => 'ELDs, TMS, dispatch and accounting software built for carriers.'],
    'compliance_services'    => ['name' => 'DOT Compliance & Authority',  'singular' => 'compliance service',       'quote' => 'compliance',
        'intro' => 'Authority filings, UCR, IFTA, drug and alcohol programs, audits and safety consulting.'],
    'truck_repair_services'  => ['name' => 'Truck Repair & Maintenance',  'singular' => 'truck repair shop',        'quote' => 'repair',
        'intro' => 'Heavy-duty repair shops, mobile mechanics and roadside service.'],
    'auto_transport_brokers' => ['name' => 'Auto Transport Brokers',      'singular' => 'auto transport broker',    'quote' => null,
        'intro' => 'Brokers who arrange vehicle shipping with licensed car haulers.'],
    'third_party_logistics'  => ['name' => '3PL Services',                'singular' => '3PL provider',             'quote' => null,
        'intro' => 'Third-party logistics providers: warehousing, fulfillment and managed transportation.'],
    'load_boards'            => ['name' => 'Load Boards',                 'singular' => 'load board',               'quote' => null,
        'intro' => 'Load boards where carriers find freight and brokers post loads.'],
    'customs_brokers'        => ['name' => 'Customs Brokers',             'singular' => 'customs broker',           'quote' => null,
        'intro' => 'Licensed customs brokers for cross-border freight.'],
    'shippers'               => ['name' => 'Shippers & Freight Sources',  'singular' => 'shipper',                  'quote' => null,
        'intro' => 'Manufacturers and distributors that ship freight and work directly with carriers.'],
];
