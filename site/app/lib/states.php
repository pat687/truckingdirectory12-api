<?php
declare(strict_types=1);

/** 50 states + DC: abbreviation => [name, slug] */
const US_STATES = [
    'AL' => ['Alabama', 'alabama'], 'AK' => ['Alaska', 'alaska'], 'AZ' => ['Arizona', 'arizona'],
    'AR' => ['Arkansas', 'arkansas'], 'CA' => ['California', 'california'], 'CO' => ['Colorado', 'colorado'],
    'CT' => ['Connecticut', 'connecticut'], 'DE' => ['Delaware', 'delaware'],
    'DC' => ['District of Columbia', 'district-of-columbia'], 'FL' => ['Florida', 'florida'],
    'GA' => ['Georgia', 'georgia'], 'HI' => ['Hawaii', 'hawaii'], 'ID' => ['Idaho', 'idaho'],
    'IL' => ['Illinois', 'illinois'], 'IN' => ['Indiana', 'indiana'], 'IA' => ['Iowa', 'iowa'],
    'KS' => ['Kansas', 'kansas'], 'KY' => ['Kentucky', 'kentucky'], 'LA' => ['Louisiana', 'louisiana'],
    'ME' => ['Maine', 'maine'], 'MD' => ['Maryland', 'maryland'], 'MA' => ['Massachusetts', 'massachusetts'],
    'MI' => ['Michigan', 'michigan'], 'MN' => ['Minnesota', 'minnesota'], 'MS' => ['Mississippi', 'mississippi'],
    'MO' => ['Missouri', 'missouri'], 'MT' => ['Montana', 'montana'], 'NE' => ['Nebraska', 'nebraska'],
    'NV' => ['Nevada', 'nevada'], 'NH' => ['New Hampshire', 'new-hampshire'], 'NJ' => ['New Jersey', 'new-jersey'],
    'NM' => ['New Mexico', 'new-mexico'], 'NY' => ['New York', 'new-york'], 'NC' => ['North Carolina', 'north-carolina'],
    'ND' => ['North Dakota', 'north-dakota'], 'OH' => ['Ohio', 'ohio'], 'OK' => ['Oklahoma', 'oklahoma'],
    'OR' => ['Oregon', 'oregon'], 'PA' => ['Pennsylvania', 'pennsylvania'], 'RI' => ['Rhode Island', 'rhode-island'],
    'SC' => ['South Carolina', 'south-carolina'], 'SD' => ['South Dakota', 'south-dakota'],
    'TN' => ['Tennessee', 'tennessee'], 'TX' => ['Texas', 'texas'], 'UT' => ['Utah', 'utah'],
    'VT' => ['Vermont', 'vermont'], 'VA' => ['Virginia', 'virginia'], 'WA' => ['Washington', 'washington'],
    'WV' => ['West Virginia', 'west-virginia'], 'WI' => ['Wisconsin', 'wisconsin'], 'WY' => ['Wyoming', 'wyoming'],
];

function state_name(?string $abbr): string
{
    return US_STATES[strtoupper((string)$abbr)][0] ?? (string)$abbr;
}

function state_slug(?string $abbr): string
{
    return US_STATES[strtoupper((string)$abbr)][1] ?? strtolower((string)$abbr);
}

/** Returns the abbreviation for a URL slug (e.g. "new-york" => "NY") or null. */
function state_by_slug(string $slug): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (US_STATES as $abbr => [$name, $s]) {
            $map[$s] = $abbr;
        }
    }
    return $map[strtolower($slug)] ?? null;
}
